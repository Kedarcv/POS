<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

// Tracks fiscal days this device has opened with ZIMRA. Day numbers and
// receipt counters within a day must stay strictly sequential.
class Zimra_fiscal_day extends Model
{
    protected $table            = 'zimra_fiscal_days';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $allowedFields    = [
        'fiscal_day_no', 'fiscal_date', 'status', 'last_receipt_counter',
        'operation_id', 'counters_json', 'opened_at', 'closed_at',
    ];
    protected $useTimestamps = false;

    public function getOpenDay(): ?object
    {
        return $this->where('status', 'OPEN')->orderBy('fiscal_day_no', 'DESC')->first();
    }

    public function bumpReceiptCounter(int $dayId, int $counter): bool
    {
        return $this->update($dayId, ['last_receipt_counter' => $counter]);
    }

    public function getRecent(int $limit = 20): array
    {
        return $this->orderBy('fiscal_day_no', 'DESC')->findAll($limit);
    }
}
