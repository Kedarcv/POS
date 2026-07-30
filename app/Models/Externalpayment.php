<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * External Payments Model — Mirrors Pesepay/EcoCash integration.
 */
class Externalpayment extends Model
{
    protected $table            = 'external_payments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'sale_id', 'print_job_id', 'gateway', 'reference',
        'amount', 'currency', 'status', 'gateway_payload', 'processed_at',
    ];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';

    protected $validationRules = [
        'gateway'   => 'required|in_list[pesepay,ecocash,paynow,bank,cash]',
        'reference' => 'required|max_length[100]',
        'amount'    => 'required|decimal',
        'status'    => 'in_list[pending,completed,failed,refunded,cancelled]',
    ];

    /**
     * Find a payment by its gateway reference
     */
    public function getByReference(string $reference): ?object
    {
        return $this->where('reference', $reference)->first();
    }

    /**
     * Get payments for a sale
     */
    public function getBySale(int $saleId): array
    {
        return $this->where('sale_id', $saleId)->findAll();
    }

    /**
     * Get pending payments that are waiting for webhook callback
     */
    public function getPending(int $limit = 50): array
    {
        return $this->where('status', 'pending')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Mark a payment as completed from a webhook
     */
    public function markCompleted(int $id, string $payload): bool
    {
        return $this->update($id, [
            'status'       => 'completed',
            'gateway_payload' => $payload,
            'processed_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
