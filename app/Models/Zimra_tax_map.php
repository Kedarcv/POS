<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

// Maps this till's tax categories onto the ZIMRA-assigned tax IDs/rates
// returned by GetConfig at device registration.
class Zimra_tax_map extends Model
{
    protected $table            = 'zimra_tax_map';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $allowedFields    = ['tax_category_id', 'zimra_tax_id', 'zimra_tax_percent', 'zimra_tax_name'];
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    public function getMapForCategory(int $taxCategoryId): ?object
    {
        return $this->where('tax_category_id', $taxCategoryId)->first();
    }

    public function getAllMapped(): array
    {
        $rows = $this->join('tax_categories', 'tax_categories.tax_category_id = zimra_tax_map.tax_category_id', 'left')
            ->select('zimra_tax_map.*, tax_categories.tax_category')
            ->findAll();

        return array_column($rows, null, 'tax_category_id');
    }

    public function upsert(int $taxCategoryId, int $zimraTaxId, ?float $zimraTaxPercent, ?string $zimraTaxName): bool
    {
        $existing = $this->getMapForCategory($taxCategoryId);
        $data = [
            'tax_category_id'   => $taxCategoryId,
            'zimra_tax_id'      => $zimraTaxId,
            'zimra_tax_percent' => $zimraTaxPercent,
            'zimra_tax_name'    => $zimraTaxName,
        ];

        return $existing !== null ? $this->update($existing->id, $data) : (bool) $this->insert($data, true);
    }
}
