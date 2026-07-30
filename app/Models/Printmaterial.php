<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class Printmaterial extends Model
{
    protected $table            = 'print_materials';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'name', 'sku', 'category', 'unit', 'current_stock',
        'reorder_level', 'unit_cost', 'supplier',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public const CATEGORIES = ['paper', 'vinyl', 'ink', 'toner', 'laminate', 'substrate', 'other'];
    public const UNITS      = ['sheet', 'roll', 'liter', 'kg', 'meter'];

    public function getLowStock(): array
    {
        return $this->where('current_stock <= reorder_level')
            ->orderBy('current_stock', 'ASC')
            ->findAll();
    }

    public function getGroupedByCategory(): array
    {
        $materials = $this->orderBy('category', 'ASC')->orderBy('name', 'ASC')->findAll();
        $grouped   = array_fill_keys(self::CATEGORIES, []);
        foreach ($materials as $m) {
            $cat = $m->category ?? 'other';
            if (! isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $m;
        }

        return $grouped;
    }

    public function deductStock(int $materialId, float $quantity): bool
    {
        $material = $this->find($materialId);
        if (! $material) {
            return false;
        }
        $newStock = max(0, $material->current_stock - $quantity);

        return $this->update($materialId, ['current_stock' => $newStock]);
    }
}
