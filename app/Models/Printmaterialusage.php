<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class Printmaterialusage extends Model
{
    protected $table            = 'print_material_usage';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'print_job_id', 'material_id', 'quantity_used',
        'location_id', 'unit_cost', 'total_cost', 'expense_id',
        'channel', 'estimated_ml', 'source',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getByJob(int $jobId): array
    {
        return $this->select('print_material_usage.*, print_materials.name as material_name, print_materials.unit as material_unit, print_materials.item_id as material_item_id')
            ->join('print_materials', 'print_materials.id = print_material_usage.material_id')
            ->where('print_job_id', $jobId)
            ->orderBy('print_material_usage.id', 'DESC')
            ->findAll();
    }
}
