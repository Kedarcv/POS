<?php

namespace App\Libraries;

/**
 * EcoCash Direct Integration Library
 * Communicates with Econet EcoCash Payment Gateway.
 */
class Ecocash_lib
{
    protected string $username;
    protected string $password;
    protected string $merchantCode;
    protected string $merchantNumber;
    protected string $chargeUrl;
    protected string $lookupUrl;
    protected string $refundUrl;

    public function __construct()
    {
        $this->username       = env('ECOCASH_USERNAME', 'BEAMEDIT');
        $this->password       = env('ECOCASH_PASSWORD', 'KUct@^$cVtfK3n0h');
        $this->merchantCode   = env('ECOCASH_MERCHANT_CODE', '004947');
        $this->merchantNumber = env('ECOCASH_MERCHANT_NUMBER', '787722347');

        $this->chargeUrl = env('ECOCASH_CHARGE_URL', 'https://payonline.ecocash.co.zw/ecocashGateway/payment/v1/transactions/amount/');
        $this->lookupUrl = env('ECOCASH_LOOKUP_URL', 'https://payonline.ecocash.co.zw/ecocashGateway/payment/v1/{endUserId}/transactions/amount/{clientCorrelator}/');
        $this->refundUrl = env('ECOCASH_REFUND_URL', 'https://payonline.ecocash.co.zw/ecocashGateway/payment/v1/transactions/refund/');
    }

    /**
     * Format phone number to international MSISDN format (2637xxxxxxx)
     */
    public function formatMsisdn(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($clean, '263')) {
            return $clean;
        }
        if (str_starts_with($clean, '0')) {
            return '263' . substr($clean, 1);
        }
        if (strlen($clean) === 9) {
            return '263' . $clean;
        }
        return $clean;
    }

    /**
     * Get HTTP Basic Authorization header string
     */
    protected function getAuthHeader(): string
    {
        return 'Basic ' . base64_encode($this->username . ':' . $this->password);
    }

    /**
     * Initiate EcoCash Payment Charge Prompt (USSD push)
     */
    public function charge(string $phone, float $amount, string $referenceCode = '', string $remarks = ''): array
    {
        $msisdn = $this->formatMsisdn($phone);
        $clientCorrelator = 'POS-' . time() . '-' . rand(100, 999);
        $referenceCode = $referenceCode ?: $clientCorrelator;
        // This is what shows up on the customer's phone prompt — the real
        // sale flow (Sales::postInitiateEcocash) never passes a custom
        // remarks, so without a branded default the prompt just said
        // "Payment for POS Sale #..." with no mention of BeamLink at all.
        $remarks = $remarks ?: 'BeamLink POS - Sale #' . $referenceCode;

        $payload = [
            'clientCorrelator' => $clientCorrelator,
            'notifyUrl'        => base_url('webhook/ecocash'),
            'referenceCode'    => $referenceCode,
            // The gateway's own response schema echoes back a top-level
            // "remarks" field (always null in testing) — "tranRemarks" isn't
            // a field it recognizes at all, so our text never reached it,
            // and its prompt fell back to literally displaying the word
            // "Remarks" as a placeholder on the customer's phone.
            'remarks'          => $remarks,
            'paymentAmount'    => [
                'charginginformation' => [
                    'amount'      => number_format($amount, 2, '.', ''),
                    'currency'    => 'USD',
                    'description' => 'BeamLink POS Sale',
                ],
                'chargeMetaData' => [
                    'channel'              => 'WEB',
                    'purchaseCategoryCode' => 'Goods',
                    // Response echoes this back as "onBeHalfOf" (capital H);
                    // we were sending "onBehalfOf", so it was never applied.
                    'onBeHalfOf'           => 'BEAMEDIT',
                ],
            ],
            'merchantCode'   => $this->merchantCode,
            'merchantPin'    => '',
            'merchantNumber' => $this->merchantNumber,
            'endUserId'      => $msisdn,
        ];

        log_message('info', '[EcoCash Charge] Initiating for MSISDN: ' . $msisdn . ', Amount: ' . $amount . ', Correlator: ' . $clientCorrelator);

        $response = $this->sendRequest('POST', $this->chargeUrl, $payload);
        $response['clientCorrelator'] = $clientCorrelator;
        $response['msisdn']           = $msisdn;

        return $response;
    }

    /**
     * Lookup Transaction Status
     */
    public function lookupStatus(string $phone, string $clientCorrelator): array
    {
        $msisdn = $this->formatMsisdn($phone);
        $url = str_replace(
            ['{endUserId}', '{clientCorrelator}'],
            [$msisdn, $clientCorrelator],
            $this->lookupUrl
        );

        log_message('info', '[EcoCash Lookup] Checking status for MSISDN: ' . $msisdn . ', Correlator: ' . $clientCorrelator);

        return $this->sendRequest('GET', $url);
    }

    /**
     * Refund Transaction
     */
    public function refund(string $originalClientCorrelator, float $amount, string $remarks = ''): array
    {
        $refundCorrelator = 'REF-' . time() . '-' . rand(100, 999);
        $remarks = $remarks ?: 'Refund for transaction ' . $originalClientCorrelator;

        $payload = [
            'clientCorrelator'         => $refundCorrelator,
            'originalClientCorrelator' => $originalClientCorrelator,
            'merchantCode'             => $this->merchantCode,
            'merchantNumber'           => $this->merchantNumber,
            'paymentAmount'            => [
                'charginginformation' => [
                    'amount'      => number_format($amount, 2, '.', ''),
                    'currency'    => 'USD',
                    'description' => $remarks,
                ],
            ],
        ];

        log_message('info', '[EcoCash Refund] Refunding ' . $amount . ' for original correlator: ' . $originalClientCorrelator);

        return $this->sendRequest('POST', $this->refundUrl, $payload);
    }

    /**
     * Send HTTP Curl Request to EcoCash API
     */
    protected function sendRequest(string $method, string $url, ?array $data = null): array
    {
        $ch = curl_init();

        $headers = [
            'Authorization: ' . $this->getAuthHeader(),
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $result    = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            log_message('error', '[EcoCash API Error] Curl error: ' . $curlError);
            return [
                'success' => false,
                'status'  => 'FAILED',
                'message' => 'Network error: ' . $curlError,
            ];
        }

        $decoded = json_decode($result, true) ?: [];

        log_message('info', '[EcoCash API Response] HTTP Code: ' . $httpCode . ' Body: ' . $result);

        $status = $decoded['transactionOperationStatus'] ?? ($httpCode >= 200 && $httpCode < 300 ? 'SUCCESS' : 'FAILED');

        // The live gateway returns descriptive multi-word statuses (e.g.
        // "PENDING SUBSCRIBER VALIDATION" while it waits for the customer to
        // approve the prompt on their phone), not the bare keywords this
        // used to match exactly — every real charge was misreported as
        // failed even when the prompt was sent successfully.
        $statusUpper = strtoupper($status);
        $success = false;
        foreach (['SUCCESS', 'COMPLETED', 'PENDING', 'PAID', 'SUCCESSFUL'] as $okStatus) {
            if (str_contains($statusUpper, $okStatus)) {
                $success = true;
                break;
            }
        }

        return [
            'success'      => $success,
            'http_code'    => $httpCode,
            'status'       => $status,
            'data'         => $decoded,
            'message'      => $decoded['message'] ?? $decoded['description'] ?? ($success ? 'Request processed successfully' : 'EcoCash transaction failed'),
            'raw_response' => $result,
        ];
    }
}
