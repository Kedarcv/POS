<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class Laybyinstallment extends Model
{
    protected $table            = 'layby_installments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'layby_id', 'installment_number', 'amount_due', 'amount_paid',
        'due_date', 'paid_date', 'status', 'payment_method',
        'reference_number', 'notes',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getByLayby(int $laybyId): array
    {
        return $this->where('layby_id', $laybyId)
            ->orderBy('installment_number', 'ASC')
            ->findAll();
    }

    public function getUpcoming(int $days = 30): array
    {
        return $this->where('status', 'pending')
            ->where('due_date <=', date('Y-m-d', strtotime("+{$days} days")))
            ->where('due_date >=', date('Y-m-d'))
            ->orderBy('due_date', 'ASC')
            ->findAll();
    }

    public function getOverdue(): array
    {
        return $this->where('status', 'pending')
            ->where('due_date <', date('Y-m-d'))
            ->orderBy('due_date', 'ASC')
            ->findAll();
    }

    public function recordPayment(int $installmentId, float $amount, string $method, ?string $reference = null): bool
    {
        $installment = $this->find($installmentId);
        if (! $installment) {
            return false;
        }

        $newPaid = $installment->amount_paid + $amount;
        $status  = $newPaid >= $installment->amount_due ? 'paid' : 'partial';

        return $this->update($installmentId, [
            'amount_paid'      => $newPaid,
            'status'           => $status,
            'paid_date'        => date('Y-m-d'),
            'payment_method'   => $method,
            'reference_number' => $reference,
        ]);
    }
}
