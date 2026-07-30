<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class Laybyplan extends Model
{
    public const STATUSES    = ['active', 'paid', 'overdue', 'cancelled'];
    public const FREQUENCIES = ['weekly', 'biweekly', 'monthly'];

    protected $table            = 'layby_plans';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'layby_number', 'sale_id', 'customer_id', 'employee_id',
        'total_amount', 'deposit_amount', 'balance',
        'interest_rate', 'interest_amount', 'total_with_interest',
        'installment_amount', 'installment_count', 'frequency',
        'start_date', 'end_date', 'status', 'document_signed', 'notes',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function generateLaybyNumber(): string
    {
        $prefix   = 'LAYBY-' . date('Y');
        $existing = $this->where('layby_number LIKE', $prefix . '-%')
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()
            ->getRow();

        $seq = $existing
            ? (int) ltrim(substr((string) $existing->layby_number, -3), '0') + 1
            : 1;

        return $prefix . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    public function getActive(): array
    {
        return $this->where('status', 'active')
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    public function getOverdue(): array
    {
        return $this->where('status', 'active')
            ->where('end_date <', date('Y-m-d'))
            ->orderBy('end_date', 'ASC')
            ->findAll();
    }

    public function getStatusCounts(): array
    {
        $rows = $this->select('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->get()
            ->getResult();

        $counts = array_fill_keys(self::STATUSES, 0);

        foreach ($rows as $r) {
            $counts[$r->status] = (int) $r->cnt;
        }

        return $counts;
    }

    public function getSummary(): array
    {
        $active  = $this->where('status', 'active')->findAll();
        $total   = array_sum(array_column($active, 'total_with_interest'));
        $paid    = array_sum(array_column($active, 'deposit_amount'));
        $balance = array_sum(array_column($active, 'balance'));

        return [
            'total_laybys'      => count($active),
            'total_value'       => $total,
            'total_deposits'    => $paid,
            'total_outstanding' => $balance,
        ];
    }
}
