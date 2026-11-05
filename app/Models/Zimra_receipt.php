<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

// One row per POS sale submitted (or attempted) to ZIMRA's FDMS.
class Zimra_receipt extends Model
{
    protected $table            = 'zimra_receipts';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $allowedFields    = [
        'sale_id', 'fiscal_day_no', 'receipt_counter', 'receipt_global_no',
        'receipt_type', 'currency', 'status', 'device_signature',
        'server_signature', 'qr_url', 'verification_code',
        'request_payload', 'response_payload', 'error_message',
        'attempts', 'submitted_at',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function findBySaleId(int $saleId): ?object
    {
        return $this->where('sale_id', $saleId)->first();
    }

    public function insertPending(array $sale, ?int $fiscalDayNo, ?int $receiptCounter): int
    {
        return (int) $this->insert([
            'sale_id'         => $sale['sale_id'],
            'fiscal_day_no'   => $fiscalDayNo ?? 0,
            'receipt_counter' => $receiptCounter ?? 0,
            'receipt_type'    => $sale['receipt_type'] ?? 'FISCALINVOICE',
            'currency'        => $sale['currency'],
            'status'          => 'PENDING',
            'request_payload' => json_encode($sale),
        ], true);
    }

    public function markSubmitted(int $id, int $globalNo, string $hash, string $signature, ?string $serverSignature, string $qrUrl, string $verificationCode, array $requestPayload, array $response): bool
    {
        return $this->update($id, [
            'status'            => 'SUBMITTED',
            'receipt_global_no' => $globalNo,
            'device_signature'  => $hash . '|' . $signature,
            'server_signature'  => $serverSignature,
            'qr_url'            => $qrUrl,
            'verification_code' => $verificationCode,
            'request_payload'   => json_encode($requestPayload),
            'response_payload'  => json_encode($response['data'] ?? $response),
            'submitted_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    public function markFailed(int $id, array $requestPayload, array $response): bool
    {
        $row = $this->find($id);

        return $this->update($id, [
            'status'           => 'FAILED',
            'attempts'         => ($row->attempts ?? 0) + 1,
            'request_payload'  => json_encode($requestPayload),
            'response_payload' => json_encode($response['data'] ?? $response),
            'error_message'    => $response['message'] ?? 'Unknown error',
        ]);
    }

    public function getPending(): array
    {
        return $this->whereIn('status', ['PENDING', 'FAILED'])->orderBy('id', 'ASC')->findAll();
    }

    public function getLastHash(int $fiscalDayNo): ?string
    {
        $row = $this->where('fiscal_day_no', $fiscalDayNo)
            ->where('status', 'SUBMITTED')
            ->orderBy('receipt_counter', 'DESC')
            ->first();

        if ($row === null || empty($row->device_signature)) {
            return null;
        }

        return explode('|', $row->device_signature)[0] ?? null;
    }

    public function getRecent(int $limit = 30): array
    {
        return $this->orderBy('id', 'DESC')->findAll($limit);
    }

    /**
     * Aggregates this fiscal day's submitted receipts into the counters
     * ZIMRA expects at CloseDay: totals by tax rate and by payment/currency.
     */
    public function getCountersForDay(int $fiscalDayNo): array
    {
        $rows = $this->where('fiscal_day_no', $fiscalDayNo)->where('status', 'SUBMITTED')->findAll();

        $saleByTax = [];
        $balanceByCurrency = [];

        foreach ($rows as $row) {
            $payload = json_decode($row->request_payload ?: '{}', true);
            $currency = $payload['receiptCurrency'] ?? $row->currency;
            $balanceByCurrency[$currency] = ($balanceByCurrency[$currency] ?? 0) + (float) ($payload['receiptTotal'] ?? 0);

            foreach ($payload['receiptTaxes'] ?? [] as $tax) {
                $key = $currency . '|' . $tax['taxID'];
                if (!isset($saleByTax[$key])) {
                    $saleByTax[$key] = [
                        'taxID'      => $tax['taxID'],
                        'taxPercent' => $tax['taxPercent'],
                        'currency'   => $currency,
                        'salesAmountWithTax' => 0.0,
                        'taxAmount'  => 0.0,
                    ];
                }
                $saleByTax[$key]['salesAmountWithTax'] += (float) ($tax['salesAmountWithTax'] ?? 0);
                $saleByTax[$key]['taxAmount'] += (float) ($tax['taxAmount'] ?? 0);
            }
        }

        return [
            'saleByTax' => array_values($saleByTax),
            'balanceByMoneyType' => array_map(
                fn ($currency, $total) => ['moneyType' => 'Cash', 'currency' => $currency, 'amount' => $total],
                array_keys($balanceByCurrency),
                array_values($balanceByCurrency),
            ),
        ];
    }
}
