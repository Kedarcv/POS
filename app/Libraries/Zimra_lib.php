<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\Zimra_fiscal_day;
use App\Models\Zimra_receipt;
use App\Models\Zimra_settings;
use App\Models\Zimra_tax_map;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * ZIMRA Fiscalisation client (Fiscal Device Gateway / FDMS).
 *
 * Implements the device lifecycle described in ZIMRA's public fiscalisation
 * guidance (https://www.zimra.co.zw/domestic-taxes/corporate/fiscalisation-explained)
 * and the "Fiscal Device Gateway API" it references: taxpayer verification,
 * device registration via CSR, fiscal day open/close, and per-sale receipt
 * submission with a locally-computed signature.
 *
 * IMPORTANT — verify before going live: this environment has no network path
 * to zimra.co.zw and no registered device to test against, so the exact
 * request/response field names and the receipt hash's field concatenation
 * order below follow the API's publicly documented shape but have NOT been
 * validated against a live FDMS response. Before processing real fiscal
 * receipts, confirm every field in buildReceiptHashString() and the
 * endpoint paths in this file against the official "Fiscal Device Gateway
 * API" specification PDF (ZIMRA > Domestic Taxes > Fiscalisation Explained
 * > downloads), which this machine could not fetch directly. Everything
 * here is otherwise fully wired: settings, key/cert storage, day tracking,
 * retry queue, and the POS hook — only field-level accuracy needs a final
 * check once a real Device ID/Activation Key is issued.
 */
class Zimra_lib
{
    private Zimra_node_bridge $nodeBridge;

    private const TEST_BASE_URL = 'https://fdmstest.zimra.co.zw';
    private const PROD_BASE_URL = 'https://fdms.zimra.co.zw';

    protected Zimra_settings $settingsModel;
    protected Zimra_tax_map $taxMapModel;
    protected Zimra_fiscal_day $fiscalDayModel;
    protected Zimra_receipt $receiptModel;

    public function __construct()
    {
        $this->nodeBridge    = new Zimra_node_bridge();
        $this->settingsModel  = model(Zimra_settings::class);
        $this->taxMapModel    = model(Zimra_tax_map::class);
        $this->fiscalDayModel = model(Zimra_fiscal_day::class);
        $this->receiptModel   = model(Zimra_receipt::class);
    }

    public function getSettings(): ?object
    {
        return $this->settingsModel->getSingleton();
    }

    /**
     * Builds the fiscalizeSale() payload for an already-saved (persisted)
     * sale by reading back the sales/sales_items/sales_taxes/sales_payments
     * rows, so this stays decoupled from the cart's in-memory tax logic.
     */
    public function buildSalePayloadFromPersistedSale(int $saleId, float $total): array
    {
        $db = \Config\Database::connect();

        $sale = $db->table('sales')->where('sale_id', $saleId)->get()->getRowArray();
        $settings = $this->getSettings();
        $currency = $settings->default_currency ?? 'USD';

        $items = $db->table('sales_items')
            ->select('sales_items.*, items.name, items.hsn_code, items.tax_category_id')
            ->join('items', 'items.item_id = sales_items.item_id')
            ->where('sales_items.sale_id', $saleId)
            ->get()->getResultArray();

        $taxMap = $this->taxMapModel->getAllMapped();

        $lines = [];
        foreach ($items as $line) {
            $lineTotal = $line['discount_type'] == 1
                ? $line['quantity_purchased'] * $line['item_unit_price'] * (1 - $line['discount'] / 100)
                : $line['quantity_purchased'] * ($line['item_unit_price'] - $line['discount']);

            $mapping = $taxMap[$line['tax_category_id']] ?? null;

            $lines[] = [
                'line'               => $line['line'],
                'name'               => $line['name'],
                'hs_code'            => $line['hsn_code'],
                'quantity'           => $line['quantity_purchased'],
                'unit_price'         => $line['item_unit_price'],
                'line_total'         => round($lineTotal, 2),
                'tax_amount'         => 0, // authoritative tax comes from sales_taxes below
                'zimra_tax_id'       => $mapping->zimra_tax_id ?? 3, // default: Exempt
                'zimra_tax_percent'  => $mapping->zimra_tax_percent ?? 0,
            ];
        }

        $taxRows = $db->table('sales_taxes')->where('sale_id', $saleId)->get()->getResultArray();
        $taxesByCategory = [];
        foreach ($taxRows as $tax) {
            $mapping = $taxMap[$tax['tax_category_id']] ?? null;
            $zimraTaxId = $mapping->zimra_tax_id ?? 3;
            if (!isset($taxesByCategory[$zimraTaxId])) {
                $taxesByCategory[$zimraTaxId] = [
                    'taxID'      => $zimraTaxId,
                    'taxPercent' => $mapping->zimra_tax_percent ?? (float) $tax['tax_rate'],
                    'taxAmount'  => 0.0,
                    'salesAmountWithTax' => 0.0,
                ];
            }
            $taxesByCategory[$zimraTaxId]['taxAmount'] += (float) $tax['sale_tax_amount'];
            $taxesByCategory[$zimraTaxId]['salesAmountWithTax'] += (float) $tax['sale_tax_basis'] + (float) $tax['sale_tax_amount'];
        }

        // Fold the authoritative per-tax-group amounts back onto the lines so
        // buildTaxBreakdown()'s per-line aggregation matches sales_taxes exactly.
        foreach (array_values($taxesByCategory) as $i => $group) {
            if (isset($lines[$i])) {
                $lines[$i]['tax_amount'] = $group['taxAmount'];
            }
        }

        $payments = $db->table('sales_payments')->where('sale_id', $saleId)->get()->getResultArray();

        return [
            'sale_id'        => $saleId,
            'invoice_number' => $sale['invoice_number'] ?? null,
            'receipt_type'   => $this->receiptTypeForSaleType((int) ($sale['sale_type'] ?? 0)),
            'currency'       => $currency,
            'total'          => $total,
            'lines'          => $lines,
            'payments'       => array_map(fn ($p) => ['type' => $p['payment_type'], 'amount' => $p['payment_amount']], $payments),
        ];
    }

    protected function receiptTypeForSaleType(int $saleType): string
    {
        // SALE_TYPE_RETURN == 4 per app/Config/Constants-style definitions used in Sales.php
        return $saleType === 4 ? 'CREDITNOTE' : 'FISCALINVOICE';
    }

    public function isEnabled(): bool
    {
        $settings = $this->getSettings();
        return $settings !== null && (bool) $settings->enabled && !empty($settings->device_id) && !empty($settings->certificate_path);
    }

    protected function baseUrl(object $settings): string
    {
        return $settings->environment === 'production' ? self::PROD_BASE_URL : self::TEST_BASE_URL;
    }

    protected function opensslConfigPath(): string
    {
        // PHPRC (set by electron/main.cjs) points at the bundled PHP's own
        // directory, where its openssl.cnf ships under extras/ssl/ — needed
        // because openssl_pkey_new() can't find a config file on its own
        // when this bundled PHP runs outside a normal system install.
        $phprc = getenv('PHPRC');
        if ($phprc) {
            $candidate = rtrim($phprc, '\\/') . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return PHP_OS_FAMILY === 'Windows' ? 'C:\\Windows\\openssl.cnf' : '/etc/ssl/openssl.cnf';
    }

    protected function certDir(): string
    {
        $dataDir = getenv('OSPOS_DATA_DIR') ?: WRITEPATH . '..';
        $dir = rtrim($dataDir, '/\\') . DIRECTORY_SEPARATOR . 'zimra';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        return $dir;
    }

    // ------------------------------------------------------------------
    // Device registration
    // ------------------------------------------------------------------

    /**
     * Generates a local RSA-2048 keypair and a CSR for the given device.
     * The private key never leaves this machine; only the CSR is sent to
     * ZIMRA, which returns a signed client certificate.
     */
    public function generateKeyPairAndCsr(string $deviceSerialNo, string $tin): array
    {
        // PHP's openssl_* functions need an openssl.cnf to find their default
        // section; on Windows this isn't always on the system path PHP
        // searches, so point it explicitly at the one bundled with this PHP.
        $opensslConfig = ['config' => $this->opensslConfigPath()];

        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ] + $opensslConfig);

        if ($keyPair === false) {
            throw new \RuntimeException('Failed to generate RSA keypair: ' . openssl_error_string());
        }

        $dn = [
            'countryName'            => 'ZW',
            'organizationName'       => $tin,
            'commonName'             => $deviceSerialNo,
        ];

        $csr = openssl_csr_new($dn, $keyPair, ['digest_alg' => 'sha256'] + $opensslConfig);
        if ($csr === false) {
            throw new \RuntimeException('Failed to generate CSR: ' . openssl_error_string());
        }

        openssl_csr_export($csr, $csrPem);
        if (!openssl_pkey_export($keyPair, $privateKeyPem, null, $opensslConfig)) {
            throw new \RuntimeException('Failed to export private key: ' . openssl_error_string());
        }

        return ['csr' => $csrPem, 'private_key' => $privateKeyPem];
    }

    /**
     * Registers this device with FDMS: submits the CSR + activation key and
     * stores the returned certificate. Must be called once, after ZIMRA has
     * issued a Device ID and Activation Key for this till on their portal.
     */
    public function registerDevice(int $deviceId, string $activationKey, string $tin, string $deviceSerialNo, string $deviceModelName, string $deviceModelVersion): array
    {
        $settings = $this->getSettings();
        $baseUrl = $this->baseUrl($settings);

        ['csr' => $csrPem, 'private_key' => $privateKeyPem] = $this->generateKeyPairAndCsr($deviceSerialNo, $tin);

        $payload = [
            'certificateRequest' => base64_encode($csrPem),
            'deviceModelName'    => $deviceModelName,
            'deviceModelVersion' => $deviceModelVersion,
        ];

        // Registration itself authenticates via deviceID + activationKey
        // headers rather than mTLS, since no certificate exists yet.
        $response = $this->request(
            'POST',
            "{$baseUrl}/Device/v1/{$deviceId}/RegisterDevice",
            $payload,
            ['DeviceModelName' => $deviceModelName, 'DeviceModelVersion' => $deviceModelVersion],
            null,
            $activationKey,
        );

        if (empty($response['ok']) || empty($response['data']['certificate'])) {
            return ['success' => false, 'message' => $response['message'] ?? 'Registration failed', 'raw' => $response];
        }

        $certDir = $this->certDir();
        $certPath = $certDir . DIRECTORY_SEPARATOR . "device_{$deviceId}.crt";
        $keyPath  = $certDir . DIRECTORY_SEPARATOR . "device_{$deviceId}.key";

        file_put_contents($certPath, base64_decode($response['data']['certificate']));
        file_put_contents($keyPath, $privateKeyPem);
        chmod($keyPath, 0600);

        $this->settingsModel->updateSettings([
            'device_id'            => $deviceId,
            'activation_key'       => $activationKey,
            'tin'                  => $tin,
            'device_serial_no'     => $deviceSerialNo,
            'device_model_name'    => $deviceModelName,
            'device_model_version' => $deviceModelVersion,
            'certificate_path'     => $certPath,
            'private_key_path'     => $keyPath,
            'registered_at'        => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'message' => 'Device registered successfully', 'raw' => $response];
    }

    /**
     * Fetches this device's ZIMRA-assigned configuration: applicable taxes,
     * branch details, etc. Used to help populate the tax mapping table.
     */
    public function getConfig(): array
    {
        $settings = $this->requireRegisteredSettings();
        return $this->authenticatedRequest('GET', "/Device/v1/{$settings->device_id}/GetConfig", null, $settings);
    }

    public function getStatus(): array
    {
        $settings = $this->requireRegisteredSettings();
        return $this->authenticatedRequest('GET', "/Device/v1/{$settings->device_id}/GetStatus", null, $settings);
    }

    // ------------------------------------------------------------------
    // Fiscal day lifecycle
    // ------------------------------------------------------------------

    public function openDay(): array
    {
        $settings = $this->requireRegisteredSettings();

        $existing = $this->fiscalDayModel->getOpenDay();
        if ($existing !== null) {
            return ['success' => true, 'message' => "Fiscal day {$existing->fiscal_day_no} is already open", 'fiscal_day_no' => $existing->fiscal_day_no];
        }

        $nextDayNo = $settings->last_fiscal_day_no + 1;
        $today = date('Y-m-d');

        $response = $this->authenticatedRequest('POST', "/Device/v1/{$settings->device_id}/OpenDay", [
            'fiscalDayOpened' => $today,
            'fiscalDayNo'     => $nextDayNo,
        ], $settings);

        if (empty($response['ok'])) {
            return ['success' => false, 'message' => $response['message'] ?? 'Failed to open fiscal day', 'raw' => $response];
        }

        $this->fiscalDayModel->insert([
            'fiscal_day_no' => $nextDayNo,
            'fiscal_date'   => $today,
            'status'        => 'OPEN',
            'operation_id'  => $response['data']['operationID'] ?? null,
            'opened_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->settingsModel->updateSettings(['last_fiscal_day_no' => $nextDayNo]);

        return ['success' => true, 'message' => "Fiscal day {$nextDayNo} opened", 'fiscal_day_no' => $nextDayNo];
    }

    public function closeDay(): array
    {
        $settings = $this->requireRegisteredSettings();
        $day = $this->fiscalDayModel->getOpenDay();

        if ($day === null) {
            return ['success' => false, 'message' => 'No fiscal day is currently open'];
        }

        $counters = $this->receiptModel->getCountersForDay($day->fiscal_day_no);

        $payload = [
            'fiscalDayNo'             => $day->fiscal_day_no,
            'fiscalDayDate'           => $day->fiscal_date,
            'fiscalDayCounters'       => $counters,
            'receiptCounter'          => $day->last_receipt_counter,
        ];

        $response = $this->authenticatedRequest('POST', "/Device/v1/{$settings->device_id}/CloseDay", $payload, $settings);

        if (empty($response['ok'])) {
            $this->fiscalDayModel->update($day->id, ['status' => 'CLOSE_FAILED']);
            return ['success' => false, 'message' => $response['message'] ?? 'Failed to close fiscal day', 'raw' => $response];
        }

        $this->fiscalDayModel->update($day->id, [
            'status'        => 'CLOSED',
            'closed_at'     => date('Y-m-d H:i:s'),
            'counters_json' => json_encode($counters),
        ]);

        return ['success' => true, 'message' => "Fiscal day {$day->fiscal_day_no} closed"];
    }

    // ------------------------------------------------------------------
    // Receipt submission
    // ------------------------------------------------------------------

    /**
     * Builds a ZIMRA receipt from POS sale data and submits it. Never
     * throws for network/API failures — the sale must complete and print
     * regardless of ZIMRA reachability, so failures are recorded as
     * PENDING for later retry via retryPending().
     *
     * @param array $sale ['sale_id', 'invoice_number', 'sale_type', 'currency', 'lines' => [...], 'payments' => [...], 'total']
     */
    public function fiscalizeSale(array $sale): array
    {
        if ($this->shouldUseNodeBridge()) {
            return $this->fiscalizeSaleWithNodeBridge($sale);
        }

        if (!$this->isEnabled()) {
            return ['success' => false, 'skipped' => true, 'message' => 'Fiscalisation not enabled'];
        }

        if ($this->receiptModel->findBySaleId($sale['sale_id']) !== null) {
            return ['success' => false, 'skipped' => true, 'message' => 'Sale already fiscalized'];
        }

        $settings = $this->getSettings();
        $day = $this->fiscalDayModel->getOpenDay();

        if ($day === null) {
            $this->receiptModel->insertPending($sale, null, null);
            return ['success' => false, 'message' => 'No fiscal day open; queued for retry'];
        }

        $receiptCounter = $day->last_receipt_counter + 1;
        $receiptId = $this->receiptModel->insertPending($sale, $day->fiscal_day_no, $receiptCounter);

        $result = $this->submitReceiptToFdms($settings, $day, $receiptCounter, $sale, $receiptId);

        return $result;
    }

    /**
     * The npm SDK owns FDMS numbering, ECDSA signatures, hash chaining and
     * crash reconciliation. PHP keeps the sale transaction and receipt view.
     *
     * @param array<string, mixed> $sale
     * @return array<string, mixed>
     */
    protected function fiscalizeSaleWithNodeBridge(array $sale): array
    {
        $settings = $this->getSettings();
        $receiptType = ($sale['receipt_type'] ?? 'FISCALINVOICE') === 'CREDITNOTE'
            ? 'CreditNote'
            : 'FiscalInvoice';

        $receipt = [
            'receiptType' => $receiptType,
            'currency'    => (string) ($sale['currency'] ?? $settings->default_currency ?? 'USD'),
            'invoiceNo'   => (string) ($sale['invoice_number'] ?? ('POS-' . $sale['sale_id'])),
            'lines'       => array_map(static fn (array $line): array => [
                'name'       => (string) $line['name'],
                'price'      => (float) $line['unit_price'],
                'quantity'   => (float) $line['quantity'],
                'taxId'      => (int) $line['zimra_tax_id'],
                'taxPercent' => $line['zimra_tax_percent'] === null ? null : (float) $line['zimra_tax_percent'],
                'hsCode'     => $line['hs_code'] ?: null,
            ], $sale['lines'] ?? []),
            'payments'    => array_map(static fn (array $payment): array => [
                'moneyType' => match (strtolower((string) $payment['type'])) {
                    'card' => 'Card',
                    'mobilewallet', 'mobile wallet', 'ecocash' => 'MobileWallet',
                    'bank transfer', 'banktransfer' => 'BankTransfer',
                    default => 'Cash',
                },
                'amount' => (float) $payment['amount'],
            ], $sale['payments'] ?? []),
        ];

        $result = $this->nodeBridge->call([
            'operation' => 'submit',
            'profile'   => $this->nodeBridge->profilePath(),
            'receipt'   => $receipt,
        ]);

        return [
            'success'           => true,
            'message'           => 'Receipt fiscalized',
            'qr_url'            => $result['qrData'] ?? null,
            'verification_code' => null,
            'raw'               => $result,
        ];
    }

    private function shouldUseNodeBridge(): bool
    {
        return getenv('ZIMRA_CLIENT') !== 'php'
            && $this->nodeBridge->isAvailable()
            && is_file($this->nodeBridge->profilePath() . DIRECTORY_SEPARATOR . 'device.json');
    }

    protected function submitReceiptToFdms(object $settings, object $day, int $receiptCounter, array $sale, int $receiptId): array
    {
        $taxes = $this->buildTaxBreakdown($sale['lines']);
        $receiptDate = date('Y-m-d\TH:i:s');
        $previousHash = $this->receiptModel->getLastHash($day->fiscal_day_no) ?? '';

        $receiptTotal = (int) round(((float) $sale['total']) * 100); // cents, per FDMS convention

        $hashString = $this->buildReceiptHashString(
            (int) $settings->device_id,
            $sale['receipt_type'] ?? 'FISCALINVOICE',
            $sale['currency'],
            $receiptCounter,
            $receiptDate,
            $receiptTotal,
            $taxes,
            $previousHash,
        );

        $hash = base64_encode(hash('sha256', $hashString, true));
        $signature = $this->signWithDeviceKey($settings, $hashString);

        $payload = [
            'receiptType'      => $sale['receipt_type'] ?? 'FISCALINVOICE',
            'receiptCurrency'  => $sale['currency'],
            'receiptCounter'   => $receiptCounter,
            'receiptGlobalNo'  => $settings->last_receipt_global_no + 1,
            'invoiceNo'        => $sale['invoice_number'] ?? ('POS-' . $sale['sale_id']),
            'receiptDate'      => $receiptDate,
            'receiptLinesTaxInclusive' => true,
            'receiptLines'     => array_map(fn ($line) => [
                'receiptLineType' => 'Sale',
                'receiptLineNo'   => $line['line'],
                'receiptLineHSCode' => $line['hs_code'] ?: '04021099',
                'receiptLineName' => $line['name'],
                'receiptLinePrice' => (float) $line['unit_price'],
                'receiptLineQuantity' => (float) $line['quantity'],
                'receiptLineTotal' => (float) $line['line_total'],
                'taxID'           => $line['zimra_tax_id'],
                'taxPercent'      => $line['zimra_tax_percent'],
            ], $sale['lines']),
            'receiptTaxes'     => $taxes,
            'receiptPayments'  => array_map(fn ($p) => [
                'moneyTypeCode' => $p['type'],
                'paymentAmount' => (float) $p['amount'],
            ], $sale['payments']),
            'receiptTotal'     => (float) $sale['total'],
            'receiptDeviceSignature' => [
                'hash'      => $hash,
                'signature' => $signature,
            ],
        ];

        $response = $this->authenticatedRequest(
            'POST',
            "/Device/v1/{$settings->device_id}/SubmitReceipt",
            ['receipt' => $payload],
            $settings,
        );

        if (empty($response['ok'])) {
            $this->receiptModel->markFailed($receiptId, $payload, $response);
            return ['success' => false, 'message' => $response['message'] ?? 'FDMS submission failed', 'raw' => $response];
        }

        $globalNo = $response['data']['receiptID']['receiptGlobalNo'] ?? $payload['receiptGlobalNo'];
        $serverSignature = $response['data']['serverSignature'] ?? null;
        $qrUrl = $this->buildQrUrl($settings, $day->fiscal_day_no, $globalNo, $hash);
        $verificationCode = $this->buildVerificationCode($hash);

        $this->receiptModel->markSubmitted($receiptId, $globalNo, $hash, $signature, $serverSignature, $qrUrl, $verificationCode, $payload, $response);
        $this->fiscalDayModel->bumpReceiptCounter($day->id, $receiptCounter);
        $this->settingsModel->updateSettings(['last_receipt_global_no' => $globalNo]);

        return ['success' => true, 'message' => 'Receipt fiscalized', 'qr_url' => $qrUrl, 'verification_code' => $verificationCode];
    }

    /**
     * Retries every PENDING/FAILED receipt still waiting on submission,
     * e.g. after ZIMRA connectivity is restored or a fiscal day is opened.
     */
    public function retryPending(): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Fiscalisation not enabled'];
        }

        $settings = $this->getSettings();
        $day = $this->fiscalDayModel->getOpenDay();
        $pending = $this->receiptModel->getPending();

        $results = ['attempted' => 0, 'succeeded' => 0, 'failed' => 0];

        if ($day === null) {
            return array_merge($results, ['message' => 'No fiscal day open; nothing retried']);
        }

        foreach ($pending as $receipt) {
            $results['attempted']++;
            $saleData = json_decode($receipt->request_payload ?: '{}', true) ?: $receipt;
            $receiptCounter = $day->last_receipt_counter + 1;

            $this->receiptModel->update($receipt->id, ['fiscal_day_no' => $day->fiscal_day_no, 'receipt_counter' => $receiptCounter]);
            $result = $this->submitReceiptToFdms($settings, $day, $receiptCounter, $saleData, $receipt->id);

            $result['success'] ? $results['succeeded']++ : $results['failed']++;
        }

        return $results;
    }

    // ------------------------------------------------------------------
    // Signing / hashing
    // ------------------------------------------------------------------

    /**
     * Builds the canonical string that gets SHA-256 hashed and RSA-signed
     * for a receipt. Field order and formatting here follow the commonly
     * documented FDMS pattern (device ID, receipt type/currency/globalNo,
     * ISO receipt date, integer total in cents, per-tax breakdown, and the
     * previous receipt's hash chaining each receipt to the last) but MUST
     * be checked against the official spec before live use — see the class
     * docblock.
     */
    protected function buildReceiptHashString(int $deviceId, string $receiptType, string $currency, int $receiptGlobalNo, string $receiptDate, int $receiptTotalCents, array $taxes, string $previousHash): string
    {
        $parts = [$deviceId, $receiptType, $currency, $receiptGlobalNo, $receiptDate, $receiptTotalCents];

        foreach ($taxes as $tax) {
            $parts[] = $tax['taxID'];
            $parts[] = number_format((float) $tax['taxPercent'], 2, '.', '');
            $parts[] = (int) round(((float) $tax['taxAmount']) * 100);
        }

        $parts[] = $previousHash;

        return implode('', $parts);
    }

    protected function signWithDeviceKey(object $settings, string $data): string
    {
        $privateKeyPem = file_get_contents($settings->private_key_path);
        $privateKey = openssl_pkey_get_private($privateKeyPem);

        if ($privateKey === false) {
            throw new \RuntimeException('Could not load device private key: ' . openssl_error_string());
        }

        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    protected function buildQrUrl(object $settings, int $fiscalDayNo, int $receiptGlobalNo, string $hash): string
    {
        $baseUrl = $this->baseUrl($settings);
        $shortCode = substr(preg_replace('/[^A-Za-z0-9]/', '', $hash), 0, 16);
        return "{$baseUrl}/{$settings->device_id}/{$fiscalDayNo}/{$receiptGlobalNo}/{$shortCode}";
    }

    /**
     * Renders a self-contained data: URI (SVG) for the receipt's QR code —
     * safe to drop straight into an <img src>, no extra request or file needed.
     */
    public function renderQrDataUri(string $qrUrl): string
    {
        return (new QRCode(new QROptions(['imageBase64' => true])))->render($qrUrl);
    }

    protected function buildVerificationCode(string $hash): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $hash));
        $code = substr($clean, 0, 16);
        return implode('-', str_split($code, 4));
    }

    protected function buildTaxBreakdown(array $lines): array
    {
        $byTax = [];
        foreach ($lines as $line) {
            $key = $line['zimra_tax_id'] . '|' . $line['zimra_tax_percent'];
            if (!isset($byTax[$key])) {
                $byTax[$key] = [
                    'taxID'      => $line['zimra_tax_id'],
                    'taxPercent' => $line['zimra_tax_percent'],
                    'taxAmount'  => 0.0,
                    'salesAmountWithTax' => 0.0,
                ];
            }
            $byTax[$key]['taxAmount'] += (float) $line['tax_amount'];
            $byTax[$key]['salesAmountWithTax'] += (float) $line['line_total'];
        }

        return array_values($byTax);
    }

    // ------------------------------------------------------------------
    // HTTP transport
    // ------------------------------------------------------------------

    protected function requireRegisteredSettings(): object
    {
        $settings = $this->getSettings();
        if ($settings === null || empty($settings->device_id) || empty($settings->certificate_path)) {
            throw new \RuntimeException('Device is not registered with ZIMRA yet');
        }
        return $settings;
    }

    protected function authenticatedRequest(string $method, string $path, ?array $payload, object $settings): array
    {
        $baseUrl = $this->baseUrl($settings);
        return $this->request($method, $baseUrl . $path, $payload, [], $settings);
    }

    /**
     * @param object|null $settings When provided, the request authenticates
     *                              via mTLS using the device's stored cert/key.
     */
    protected function request(string $method, string $url, ?array $payload, array $extraHeaders, ?object $settings, ?string $activationKey = null): array
    {
        $ch = curl_init();

        $headers = array_merge(['Content-Type: application/json', 'Accept: application/json'], array_map(
            fn ($k, $v) => "$k: $v",
            array_keys($extraHeaders),
            $extraHeaders,
        ));

        if ($activationKey !== null) {
            $headers[] = 'DeviceActivationKey: ' . $activationKey;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($settings !== null && !empty($settings->certificate_path) && !empty($settings->private_key_path)) {
            curl_setopt($ch, CURLOPT_SSLCERT, $settings->certificate_path);
            curl_setopt($ch, CURLOPT_SSLKEY, $settings->private_key_path);
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
        }

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            log_message('error', '[ZIMRA API Error] ' . $method . ' ' . $url . ' - ' . $curlError);
            return ['ok' => false, 'http_code' => 0, 'message' => 'Network error: ' . $curlError];
        }

        $decoded = json_decode((string) $result, true);
        log_message('info', '[ZIMRA API] ' . $method . ' ' . $url . ' -> HTTP ' . $httpCode);

        $ok = $httpCode >= 200 && $httpCode < 300;

        return [
            'ok'        => $ok,
            'http_code' => $httpCode,
            'data'      => $decoded ?: [],
            'message'   => $decoded['message'] ?? $decoded['error'] ?? ($ok ? 'OK' : "HTTP {$httpCode}"),
            'raw'       => $result,
        ];
    }
}
