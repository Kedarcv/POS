<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rounds out the print job cost model: substrate/media (vinyl, film, paper),
 * overhead (labour + power + machine) and a spoilage/damage provision.
 *
 * Everything is configurable and inherits:
 *   process value  (per technology, on print_processes)
 *     falls back to
 *   shop default   (app_config: print_overhead_per_sqm, ...)
 *     falls back to
 *   0
 *
 * Ink + substrate are physical stock -> deducted and expensed on printing.
 * Overhead + spoilage are costing-only (already booked elsewhere in real life).
 */
class AddPrintEconomics extends Migration
{
    public function up(): void
    {
        // Per-process economic overrides (null => use the shop default).
        $processColumns = [];
        foreach ([
            'overhead_per_sqm'  => ['type' => 'DECIMAL', 'constraint' => '15,4', 'null' => true, 'comment' => 'labour+power+machine per m2'],
            'overhead_per_job'  => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true, 'comment' => 'fixed handling/setup cost per job'],
            'overhead_pct'      => ['type' => 'DECIMAL', 'constraint' => '6,3', 'null' => true, 'comment' => '% uplift on ink+substrate'],
            'spoilage_pct'      => ['type' => 'DECIMAL', 'constraint' => '6,3', 'null' => true, 'comment' => 'misprint/damage provision on ink+substrate'],
            'target_margin_pct' => ['type' => 'DECIMAL', 'constraint' => '6,3', 'null' => true, 'comment' => 'for the cost-plus floor price readout'],
        ] as $col => $spec) {
            if (! $this->db->fieldExists($col, 'print_processes')) {
                $processColumns[$col] = $spec;
            }
        }
        if ($processColumns !== []) {
            $this->forge->addColumn('print_processes', $processColumns);
        }

        // Cost breakdown snapshot on the job.
        $jobColumns = [];
        foreach ([
            'substrate_m2'   => ['type' => 'DECIMAL', 'constraint' => '12,4', 'null' => true],
            'substrate_cost' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'overhead_cost'  => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'spoilage_cost'  => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'est_total_cost' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
        ] as $col => $spec) {
            if (! $this->db->fieldExists($col, 'print_jobs')) {
                $jobColumns[$col] = $spec;
            }
        }
        if ($jobColumns !== []) {
            $this->forge->addColumn('print_jobs', $jobColumns);
        }

        // Shop-wide defaults, editable at Print Shop -> Settings.
        $defaults = [
            'print_overhead_per_sqm'  => '0',
            'print_overhead_per_job'  => '0',
            'print_overhead_pct'      => '0',
            'print_spoilage_pct'      => '5',
            'print_target_margin_pct' => '0',
        ];
        $builder = $this->db->table('app_config');
        foreach ($defaults as $key => $value) {
            if ($builder->where('key', $key)->countAllResults() === 0) {
                $this->db->table('app_config')->insert(['key' => $key, 'value' => $value]);
            }
        }
    }

    public function down(): void
    {
        foreach (['overhead_per_sqm', 'overhead_per_job', 'overhead_pct', 'spoilage_pct', 'target_margin_pct'] as $col) {
            if ($this->db->fieldExists($col, 'print_processes')) {
                $this->forge->dropColumn('print_processes', $col);
            }
        }
        foreach (['substrate_m2', 'substrate_cost', 'overhead_cost', 'spoilage_cost', 'est_total_cost'] as $col) {
            if ($this->db->fieldExists($col, 'print_jobs')) {
                $this->forge->dropColumn('print_jobs', $col);
            }
        }
        $this->db->table('app_config')->whereIn('key', [
            'print_overhead_per_sqm', 'print_overhead_per_job', 'print_overhead_pct',
            'print_spoilage_pct', 'print_target_margin_pct',
        ])->delete();
    }
}
