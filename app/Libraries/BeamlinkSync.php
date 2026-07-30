<?php

declare(strict_types=1);

namespace App\Libraries;

use Throwable;

/**
 * BeamLink Sync — bidirectional sync between local POS and Vercel ERP.
 *
 * New POS API: /api/pos/print-jobs, /api/pos/stock
 * Auth: X-POS-Key header
 */
class BeamlinkSync
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(getenv('BEAMLINK_API_URL') ?: '', '/');
        $this->apiKey  = getenv('BEAMLINK_API_KEY') ?: '';
    }

    // ─── Print Jobs ────────────────────────────────────────

    /**
     * Push a print job event to the Vercel ERP (POST /api/pos/print-jobs).
     * ERP expects flat job data, not nested under 'job'.
     */
    public function pushPrintJob(string $event, array $jobData): array
    {
        if ($this->baseUrl === '') {
            return ['ok' => false, 'error' => 'BEAMLINK_API_URL not configured'];
        }

        $endpoint = $this->baseUrl . '/api/pos/print-jobs';

        // Map OSPOS fields to ERP fields
        $payload = [
            'job_type'   => $jobData['job_type'] ?? 'other',
            'quantity'   => (int) ($jobData['quantity'] ?? 1),
            'notes'      => $jobData['notes'] ?? '',
            'status'     => $jobData['status'] ?? 'pending',
            'material'   => $jobData['material'] ?? null,
            'width_mm'   => $jobData['width_mm'] ?? null,
            'height_mm'  => $jobData['height_mm'] ?? null,
            'unit_cost'  => $jobData['unit_cost'] ?? null,
            'total_cost' => $jobData['total_cost'] ?? null,
        ];

        return $this->post($endpoint, $payload);
    }

    /**
     * Update a print job on the ERP (PATCH /api/pos/print-jobs).
     */
    public function updatePrintJob(array $jobData): array
    {
        if ($this->baseUrl === '') {
            return ['ok' => false, 'error' => 'BEAMLINK_API_URL not configured'];
        }

        $endpoint = $this->baseUrl . '/api/pos/print-jobs';

        return $this->patch($endpoint, $jobData);
    }

    /**
     * Pull all print jobs from the Vercel ERP (GET /api/pos/print-jobs).
     */
    public function pullPrintJobs(?string $status = null): array
    {
        if ($this->baseUrl === '') {
            return ['ok' => false, 'error' => 'BEAMLINK_API_URL not configured'];
        }

        $endpoint = $this->baseUrl . '/api/pos/print-jobs';
        if ($status) {
            $endpoint .= '?status=' . urlencode($status);
        }

        return $this->get($endpoint);
    }

    /**
     * Pull a single print job by ID (GET /api/pos/print-jobs?id=<uuid>).
     */
    public function pullPrintJob(string $id): array
    {
        if ($this->baseUrl === '') {
            return ['ok' => false, 'error' => 'BEAMLINK_API_URL not configured'];
        }

        $endpoint = $this->baseUrl . '/api/pos/print-jobs?id=' . urlencode($id);

        return $this->get($endpoint);
    }

    // ─── Stock ─────────────────────────────────────────────

    /**
     * Lookup stock by barcode (GET /api/pos/stock?barcode=<code>).
     */
    public function getStockByBarcode(string $barcode): array
    {
        if ($this->baseUrl === '') {
            return ['ok' => false, 'error' => 'BEAMLINK_API_URL not configured'];
        }

        return $this->get($this->baseUrl . '/api/pos/stock?barcode=' . urlencode($barcode));
    }

    /**
     * Search stock products (GET /api/pos/stock?search=<q>).
     */
    public function searchStock(string $query): array
    {
        if ($this->baseUrl === '') {
            return ['ok' => false, 'error' => 'BEAMLINK_API_URL not configured'];
        }

        return $this->get($this->baseUrl . '/api/pos/stock?search=' . urlencode($query));
    }

    /**
     * Get low-stock items (GET /api/pos/stock?low_stock=1).
     */
    public function getLowStock(): array
    {
        if ($this->baseUrl === '') {
            return ['ok' => false, 'error' => 'BEAMLINK_API_URL not configured'];
        }

        return $this->get($this->baseUrl . '/api/pos/stock?low_stock=1');
    }

    /**
     * Update stock quantity on ERP (PATCH /api/pos/stock).
     */
    public function updateStock(array $data): array
    {
        if ($this->baseUrl === '') {
            return ['ok' => false, 'error' => 'BEAMLINK_API_URL not configured'];
        }

        return $this->patch($this->baseUrl . '/api/pos/stock', $data);
    }

    // ─── WhatsApp ──────────────────────────────────────────

    /**
     * Send a WhatsApp message via the Vercel ERP.
     */
    public function sendWhatsApp(string $to, string $message): array
    {
        if ($this->baseUrl === '') {
            return ['ok' => false, 'error' => 'BEAMLINK_API_URL not configured'];
        }

        $endpoint = $this->baseUrl . '/api/whatsapp/send';

        return $this->post($endpoint, [
            'to'      => $to,
            'message' => $message,
        ]);
    }

    // ─── HTTP Methods ──────────────────────────────────────

    private function post(string $url, array $data): array
    {
        return $this->request('POST', $url, $data);
    }

    private function patch(string $url, array $data): array
    {
        return $this->request('PATCH', $url, $data);
    }

    private function get(string $url): array
    {
        return $this->request('GET', $url);
    }

    private function request(string $method, string $url, ?array $data = null): array
    {
        try {
            $headers = [
                'Accept: application/json',
                'X-POS-Key: ' . $this->apiKey,
            ];

            $ch = curl_init();
            $options = [
                CURLOPT_URL            => $url,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 5,
            ];

            if ($method === 'POST') {
                $headers[] = 'Content-Type: application/json';
                $options[CURLOPT_POST]       = true;
                $options[CURLOPT_POSTFIELDS]  = json_encode($data, JSON_THROW_ON_ERROR);
            } elseif ($method === 'PATCH') {
                $headers[] = 'Content-Type: application/json';
                $options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
                $options[CURLOPT_POSTFIELDS]    = json_encode($data, JSON_THROW_ON_ERROR);
            }

            curl_setopt_array($ch, $options);
            $body     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($error) {
                log_message('error', '[BeamLink Sync] cURL error: ' . $error);

                return ['ok' => false, 'error' => $error];
            }

            $decoded = json_decode($body, true);

            return [
                'ok'   => $httpCode >= 200 && $httpCode < 300,
                'code' => $httpCode,
                'data' => $decoded,
            ];
        } catch (Throwable $e) {
            log_message('error', '[BeamLink Sync] Request failed: ' . $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
