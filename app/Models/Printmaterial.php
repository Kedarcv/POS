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
        'reorder_level', 'unit_cost', 'supplier', 'item_id',
        'ml_per_unit', 'channel',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public const CATEGORIES = ['paper', 'vinyl', 'ink', 'toner', 'laminate', 'substrate', 'other'];
    public const UNITS      = ['ml', 'm2', 'sheet', 'roll', 'liter', 'kg', 'meter'];

    /** Ink / toner channels are handled per-process; everything else is media. */
    public const SUBSTRATE_CATEGORIES = ['paper', 'vinyl', 'laminate', 'substrate', 'other'];

    /**
     * Materials that can be a job's print media (vinyl, film, paper, ...).
     */
    public function getSubstrates(): array
    {
        return $this->whereIn('category', self::SUBSTRATE_CATEGORIES)
            ->orderBy('category', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

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

    /**
     * Records a stock intake (e.g. a supplier delivery) by adding to
     * current_stock, rather than requiring the whole record to be
     * re-edited just to bump a quantity. Optionally updates unit_cost and
     * supplier to reflect this delivery.
     */
    public function receiveStock(int $materialId, float $quantityReceived, ?float $unitCost = null, ?string $supplier = null): bool
    {
        $material = $this->find($materialId);
        if (! $material || $quantityReceived <= 0) {
            return false;
        }

        $update = ['current_stock' => $material->current_stock + $quantityReceived];
        if ($unitCost !== null) {
            $update['unit_cost'] = $unitCost;
        }
        if (! empty($supplier)) {
            $update['supplier'] = $supplier;
        }

        return $this->update($materialId, $update);
    }
}
