<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// AddEmployeePayrollBanking (20260727120000) had a trailing comma in its
// ALTER TABLE, so the whole statement failed on MariaDB — but the migration
// was still recorded as run, so `spark migrate` never retries it. Employee
// creation/edit has been broken since (Employees::postSave writes to these
// columns unconditionally). This re-applies whichever columns are still
// missing, idempotently, so it's safe on both broken and already-fixed installs.
class FixEmployeePayrollBanking extends Migration
{
    private const COLUMNS = [
        'employee_id'       => "varchar(50) DEFAULT NULL AFTER `person_id`",
        'department'        => "varchar(100) DEFAULT NULL",
        'job_title'         => "varchar(100) DEFAULT NULL",
        'employment_date'   => "date DEFAULT NULL",
        'employment_status' => "varchar(30) NOT NULL DEFAULT 'active'",
        'salary'            => "decimal(15,2) DEFAULT NULL",
        'pay_frequency'     => "varchar(30) DEFAULT 'monthly'",
        'bank_name'         => "varchar(150) DEFAULT NULL",
        'bank_branch'       => "varchar(150) DEFAULT NULL",
        'account_number'    => "varchar(50) DEFAULT NULL",
        'account_name'      => "varchar(150) DEFAULT NULL",
        'payment_method'    => "varchar(30) DEFAULT 'bank_transfer'",
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $column => $definition) {
            if (!$this->db->fieldExists($column, 'employees')) {
                $this->db->query("ALTER TABLE `ospos_employees` ADD COLUMN `{$column}` {$definition}");
            }
        }

        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS idx_employee_id ON ospos_employees (employee_id)');
    }

    public function down(): void
    {
        // Intentionally a no-op: the companion migration's down() already
        // drops these columns, and running both would error on the second.
    }
}
