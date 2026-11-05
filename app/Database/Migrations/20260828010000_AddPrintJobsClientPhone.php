<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// Printjob model and two controllers (Printjobs::save, Webhook::handleBeamLinkPrintJobCreated)
// read/write a `client_phone` field that was never added to the table — every
// print job creation through the form or the BeamLink webhook fails with
// "Unknown column 'client_phone'" until this runs.
class AddPrintJobsClientPhone extends Migration
{
    public function up(): void
    {
        if (!$this->db->fieldExists('client_phone', 'print_jobs')) {
            $this->forge->addColumn('print_jobs', [
                'client_phone' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'customer_id'],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('client_phone', 'print_jobs')) {
            $this->forge->dropColumn('print_jobs', 'client_phone');
        }
    }
}
