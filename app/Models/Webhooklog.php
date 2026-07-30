<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Webhook Log / Event Queue — Stores inbound/outbound webhook events.
 */
class Webhooklog extends Model
{
    protected $table            = 'webhook_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'direction', 'source', 'event_type', 'payload',
        'status', 'response_body', 'http_status', 'processed_at',
    ];
    protected $useTimestamps    = false;

    /**
     * Log an inbound webhook event
     */
    public function logInbound(string $source, string $eventType, string $payload, string $status = 'pending'): int|false
    {
        return $this->insert([
            'direction'  => 'inbound',
            'source'     => $source,
            'event_type' => $eventType,
            'payload'    => $payload,
            'status'     => $status,
            'created_at' => date('Y-m-d H:i:s'),
        ], true);
    }

    /**
     * Log an outbound webhook event
     */
    public function logOutbound(string $source, string $eventType, string $payload, string $status = 'pending'): int|false
    {
        return $this->insert([
            'direction'  => 'outbound',
            'source'     => $source,
            'event_type' => $eventType,
            'payload'    => $payload,
            'status'     => $status,
            'created_at' => date('Y-m-d H:i:s'),
        ], true);
    }

    /**
     * Mark webhook as processed with response
     */
    public function markProcessed(int $id, string $status, ?string $responseBody = null, ?int $httpStatus = null): bool
    {
        $data = [
            'status'       => $status,
            'processed_at' => date('Y-m-d H:i:s'),
        ];
        if ($responseBody !== null) {
            $data['response_body'] = $responseBody;
        }
        if ($httpStatus !== null) {
            $data['http_status'] = $httpStatus;
        }
        return $this->update($id, $data);
    }

    /**
     * Get recent unprocessed webhooks (for retry queue)
     */
    public function getPending(int $limit = 50): array
    {
        return $this->where('status', 'pending')
            ->orderBy('created_at', 'ASC')
            ->limit($limit)
            ->findAll();
    }
}
