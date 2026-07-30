<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class Printpricing extends Model
{
    protected $table            = 'print_pricing';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'name', 'job_type', 'material', 'size_description',
        'min_quantity', 'price_per_unit', 'setup_fee', 'currency', 'is_active',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getActive(): array
    {
        return $this->where('is_active', 1)
            ->orderBy('job_type', 'ASC')
            ->orderBy('price_per_unit', 'ASC')
            ->findAll();
    }

    public function getGroupedByType(): array
    {
        $pricing = $this->where('is_active', 1)->orderBy('job_type', 'ASC')->findAll();
        $grouped = [];
        foreach ($pricing as $p) {
            $grouped[$p->job_type][] = $p;
        }

        return $grouped;
    }
}
