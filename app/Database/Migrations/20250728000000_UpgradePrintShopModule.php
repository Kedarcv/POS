<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Complete Print Shop module schema — mirrors BeamLink ERP print pipeline.
 *
 * Adds:
 * - Updates print_jobs to match ERP 9-step workflow
 * - print_materials (inventory of paper, vinyl, ink, etc.)
 * - print_material_usage (consumption per job)
 * - print_pricing (price list per type/material/size)
 */
class UpgradePrintShopModule extends Migration
{
    public function up(): void
    {
        // ── 1. Update print_jobs to match BeamLink ERP schema ──
        // Drop and recreate with correct columns
        $this->forge->dropTable('print_jobs', true);

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'job_number'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
            'customer_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'ticket_ref'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'comment' => 'External ticket reference from BeamLink ERP or WhatsApp'],
            'job_type'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'other', 'null' => false],
            'quantity'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
            'width_mm'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'height_mm'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'material'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'comment' => 'Free text or material name'],
            'material_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'comment' => 'FK to print_materials'],
            'finish'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'matte', 'null' => false],
            'colors'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'full_color', 'null' => false],
            'design_file_url' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'proof_url'       => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'proof_approved'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'null' => false],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'pending', 'null' => false],
            'assigned_to'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'unit_cost'       => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'total_cost'      => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'notes'           => ['type' => 'TEXT', 'null' => true],
            'source'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'pos', 'null' => false],
            'external_ref'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'completed_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('job_number');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('status');
        $this->forge->addKey('assigned_to');
        $this->forge->createTable('print_jobs', true);

        // ── 2. Print Materials Inventory ──
        $this->forge->dropTable('print_materials', true);

        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'sku'             => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'category'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'paper', 'null' => false],
            'unit'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'sheet', 'null' => false],
            'current_stock'   => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0, 'null' => false],
            'reorder_level'   => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 10, 'null' => false],
            'unit_cost'       => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'null' => false],
            'supplier'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'last_restock'    => ['type' => 'DATETIME', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('category');
        $this->forge->addKey('sku');
        $this->forge->createTable('print_materials', true);

        // ── 3. Print Material Usage (per job) ──
        $this->forge->dropTable('print_material_usage', true);

        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'print_job_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
            'material_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
            'quantity_used'  => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0, 'null' => false],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('print_job_id');
        $this->forge->addKey('material_id');
        $this->forge->createTable('print_material_usage', true);

        // ── 4. Print Pricing ──
        $this->forge->dropTable('print_pricing', true);

        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'              => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'job_type'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
            'material'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'size_description'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'min_quantity'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
            'price_per_unit'    => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'null' => false],
            'setup_fee'         => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'null' => false],
            'currency'          => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'USD', 'null' => false],
            'is_active'         => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'null' => false],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('job_type');
        $this->forge->createTable('print_pricing', true);

        // ── Keep existing supporting tables (print_job_files, external_payments, etc.) ──
        // print_job_files, external_payments, webhook_logs, whatsapp_*, webhook_endpoints already exist from prior migration
    }

    public function down(): void
    {
        $this->forge->dropTable('print_pricing', true);
        $this->forge->dropTable('print_material_usage', true);
        $this->forge->dropTable('print_materials', true);
        $this->forge->dropTable('print_jobs', true);
    }
}
