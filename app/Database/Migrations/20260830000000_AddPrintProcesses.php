<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Print process types (DTF, UV, Xerox, Eco-solvent) with per-channel ink
 * consumption rates and their own pricing basis.
 *
 * - print_processes            one row per printing technology
 * - print_process_channels     per-channel (C/M/Y/K/White/primer...) ml-per-m2
 *                              rate + the stock material it draws from
 * - print_jobs                 gains process_id, area, coverage, ink estimate
 * - print_material_usage       gains channel / estimated_ml / source so the
 *                              existing consumption ledger can hold the
 *                              per-channel estimate rows too
 * - print_materials            gains ml_per_unit + channel tags; inks are
 *                              stocked in millilitres
 */
class AddPrintProcesses extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('print_processes')) {
            $this->forge->addField([
                'id'                   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'name'                 => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => false],
                'code'                 => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => false, 'comment' => 'slug: dtf, uv, xerox, ecosolvent'],
                'pricing_basis'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false, 'default' => 'per_sqm', 'comment' => 'per_sqm | per_sheet'],
                'sell_rate'            => ['type' => 'DECIMAL', 'constraint' => '15,4', 'null' => false, 'default' => 0, 'comment' => 'price per m2 or per sheet'],
                'min_charge'           => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => false, 'default' => 0],
                'setup_fee'            => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => false, 'default' => 0],
                'waste_pct'            => ['type' => 'DECIMAL', 'constraint' => '6,3', 'null' => false, 'default' => 0, 'comment' => 'ink overhead added to every estimate, e.g. 10 = +10%'],
                'default_coverage_pct' => ['type' => 'DECIMAL', 'constraint' => '6,3', 'null' => false, 'default' => 70],
                'sheet_area_m2'        => ['type' => 'DECIMAL', 'constraint' => '10,6', 'null' => true, 'comment' => 'area of one sheet for per_sheet processes (drives the ink estimate)'],
                'currency'             => ['type' => 'VARCHAR', 'constraint' => 3, 'null' => false, 'default' => 'USD'],
                'is_active'            => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1],
                'created_at'           => ['type' => 'DATETIME', 'null' => true],
                'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('code');
            $this->forge->createTable('print_processes');
        }

        if (! $this->db->tableExists('print_process_channels')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'process_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
                'channel'     => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false, 'comment' => 'cyan, magenta, yellow, black, white, primer, varnish, ...'],
                'material_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'comment' => 'FK to print_materials (stocked in ml)'],
                'ml_per_sqm'  => ['type' => 'DECIMAL', 'constraint' => '12,4', 'null' => false, 'default' => 0, 'comment' => 'ml per m2 at 100% coverage'],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('process_id');
            $this->forge->addUniqueKey(['process_id', 'channel']);
            $this->forge->createTable('print_process_channels');
        }

        // print_jobs additions
        $jobColumns = [];
        if (! $this->db->fieldExists('process_id', 'print_jobs')) {
            $jobColumns['process_id'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'job_type'];
        }
        if (! $this->db->fieldExists('area_m2', 'print_jobs')) {
            $jobColumns['area_m2'] = ['type' => 'DECIMAL', 'constraint' => '12,4', 'null' => true, 'comment' => '(w/1000)*(h/1000)*quantity'];
        }
        if (! $this->db->fieldExists('coverage_pct', 'print_jobs')) {
            $jobColumns['coverage_pct'] = ['type' => 'DECIMAL', 'constraint' => '6,3', 'null' => true];
        }
        if (! $this->db->fieldExists('sheets', 'print_jobs')) {
            $jobColumns['sheets'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'comment' => 'sheet/click count for per_sheet processes'];
        }
        if (! $this->db->fieldExists('est_ink_ml', 'print_jobs')) {
            $jobColumns['est_ink_ml'] = ['type' => 'DECIMAL', 'constraint' => '14,4', 'null' => true];
        }
        if (! $this->db->fieldExists('est_ink_cost', 'print_jobs')) {
            $jobColumns['est_ink_cost'] = ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true];
        }
        if (! $this->db->fieldExists('ink_posted', 'print_jobs')) {
            $jobColumns['ink_posted'] = ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 0, 'comment' => 'ink consumption already deducted from stock'];
        }
        if ($jobColumns !== []) {
            $this->forge->addColumn('print_jobs', $jobColumns);
        }

        // print_material_usage additions - reuse the one consumption ledger
        $usageColumns = [];
        if (! $this->db->fieldExists('channel', 'print_material_usage')) {
            $usageColumns['channel'] = ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true];
        }
        if (! $this->db->fieldExists('estimated_ml', 'print_material_usage')) {
            $usageColumns['estimated_ml'] = ['type' => 'DECIMAL', 'constraint' => '14,4', 'null' => true];
        }
        if (! $this->db->fieldExists('source', 'print_material_usage')) {
            $usageColumns['source'] = ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false, 'default' => 'manual', 'comment' => 'manual | process_estimate'];
        }
        if ($usageColumns !== []) {
            $this->forge->addColumn('print_material_usage', $usageColumns);
        }

        // print_materials additions
        $materialColumns = [];
        if (! $this->db->fieldExists('ml_per_unit', 'print_materials')) {
            $materialColumns['ml_per_unit'] = ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true, 'comment' => 'ml in one bottle/cartridge - for pack maths and selling a whole unit'];
        }
        if (! $this->db->fieldExists('channel', 'print_materials')) {
            $materialColumns['channel'] = ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'comment' => 'ink channel this material is, e.g. cyan / white'];
        }
        if ($materialColumns !== []) {
            $this->forge->addColumn('print_materials', $materialColumns);
        }

        // Seed the four processes the shop runs, if the table is empty.
        if ($this->db->table('print_processes')->countAllResults() === 0) {
            $now = date('Y-m-d H:i:s');
            // insertBatch() requires every row to share the exact same set of
            // columns (it builds one INSERT with a fixed column list from the
            // first row) — Xerox's extra sheet_area_m2 key with no matching
            // key on the other three rows was a "Column count doesn't match
            // value count" SQL error on every fresh install/migrate.
            $this->db->table('print_processes')->insertBatch([
                ['name' => 'DTF',         'code' => 'dtf',        'pricing_basis' => 'per_sqm',   'sell_rate' => 0, 'waste_pct' => 12, 'default_coverage_pct' => 80, 'sheet_area_m2' => null,     'created_at' => $now, 'updated_at' => $now],
                ['name' => 'UV',          'code' => 'uv',         'pricing_basis' => 'per_sqm',   'sell_rate' => 0, 'waste_pct' => 10, 'default_coverage_pct' => 70, 'sheet_area_m2' => null,     'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Eco-solvent', 'code' => 'ecosolvent', 'pricing_basis' => 'per_sqm',   'sell_rate' => 0, 'waste_pct' => 8,  'default_coverage_pct' => 70, 'sheet_area_m2' => null,     'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Xerox',       'code' => 'xerox',      'pricing_basis' => 'per_sheet', 'sell_rate' => 0, 'waste_pct' => 5,  'default_coverage_pct' => 60, 'sheet_area_m2' => 0.062370, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        foreach (['process_id', 'area_m2', 'coverage_pct', 'sheets', 'est_ink_ml', 'est_ink_cost', 'ink_posted'] as $column) {
            if ($this->db->fieldExists($column, 'print_jobs')) {
                $this->forge->dropColumn('print_jobs', $column);
            }
        }
        foreach (['channel', 'estimated_ml', 'source'] as $column) {
            if ($this->db->fieldExists($column, 'print_material_usage')) {
                $this->forge->dropColumn('print_material_usage', $column);
            }
        }
        foreach (['ml_per_unit', 'channel'] as $column) {
            if ($this->db->fieldExists($column, 'print_materials')) {
                $this->forge->dropColumn('print_materials', $column);
            }
        }
        $this->forge->dropTable('print_process_channels', true);
        $this->forge->dropTable('print_processes', true);
    }
}
