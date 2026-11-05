<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ties print-shop material consumption to the main POS stock.
 *
 * A print material can now point at a real `items` row (`print_materials.item_id`).
 * When the print team logs usage of such a material, the quantity is drawn from
 * the main stock location (item_quantities + inventory movement, exactly like a
 * sale) and an internal expense is booked against the "Print Dept - Materials"
 * category. That category is flagged `is_internal` so it can be excluded from
 * P&L-style expense reports - the cost was already paid when the stock was
 * purchased; this line only reallocates it to the department.
 */
class LinkPrintMaterialsToStock extends Migration
{
    public function up(): void
    {
        // 1. Link a print material to a sellable POS item.
        if (! $this->db->fieldExists('item_id', 'print_materials')) {
            $this->forge->addColumn('print_materials', [
                'item_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'id',
                    'comment'    => 'FK to items.item_id - when set, usage draws from main POS stock',
                ],
            ]);
        }

        // 2. Record where the stock came from, what it cost, and the expense it raised.
        $usageColumns = [];
        if (! $this->db->fieldExists('location_id', 'print_material_usage')) {
            $usageColumns['location_id'] = [
                'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true,
                'comment' => 'Stock location the material was drawn from',
            ];
        }
        if (! $this->db->fieldExists('unit_cost', 'print_material_usage')) {
            $usageColumns['unit_cost'] = [
                'type' => 'DECIMAL', 'constraint' => '15,4', 'null' => true,
                'comment' => 'Snapshot of print_materials.unit_cost at time of use',
            ];
        }
        if (! $this->db->fieldExists('total_cost', 'print_material_usage')) {
            $usageColumns['total_cost'] = [
                'type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true,
                'comment' => 'unit_cost * quantity_used - amount booked as the department expense',
            ];
        }
        if (! $this->db->fieldExists('expense_id', 'print_material_usage')) {
            $usageColumns['expense_id'] = [
                'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true,
                'comment' => 'FK to expenses.expense_id raised for this usage',
            ];
        }
        if ($usageColumns !== []) {
            $this->forge->addColumn('print_material_usage', $usageColumns);
        }

        // 3. Mark expense categories that are internal transfers, not real cash-out.
        if (! $this->db->fieldExists('is_internal', 'expense_categories')) {
            $this->forge->addColumn('expense_categories', [
                'is_internal' => [
                    'type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'null' => false,
                    'after' => 'category_description',
                    'comment' => 'Report-only category (e.g. internal stock consumption) - excluded from P&L expense totals',
                ],
            ]);
        }

        // 4. Seed the "Print Dept - Materials" category.
        $builder = $this->db->table('expense_categories');
        $existing = $builder->where('category_name', 'Print Dept - Materials')->get()->getRow();
        if ($existing === null) {
            $builder->insert([
                'category_name'        => 'Print Dept - Materials',
                'category_description' => 'Print-shop consumption of stock materials (inks, media). Internal transfer - excluded from P&L.',
                'is_internal'          => 1,
                'deleted'              => 0,
            ]);
        } else {
            $builder->where('expense_category_id', $existing->expense_category_id)
                ->update(['is_internal' => 1]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('item_id', 'print_materials')) {
            $this->forge->dropColumn('print_materials', 'item_id');
        }
        foreach (['location_id', 'unit_cost', 'total_cost', 'expense_id'] as $column) {
            if ($this->db->fieldExists($column, 'print_material_usage')) {
                $this->forge->dropColumn('print_material_usage', $column);
            }
        }
        if ($this->db->fieldExists('is_internal', 'expense_categories')) {
            $this->forge->dropColumn('expense_categories', 'is_internal');
        }
        $this->db->table('expense_categories')
            ->where('category_name', 'Print Dept - Materials')
            ->delete();
    }
}
