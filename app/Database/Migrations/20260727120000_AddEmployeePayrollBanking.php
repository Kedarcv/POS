<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEmployeePayrollBanking extends Migration
{
    public function up(): void
    {
        // Add employee_id (unique employee number), department, payroll and banking fields
        $this->db->query(
            <<<'SQL'
                ALTER TABLE `ospos_employees`
                ADD COLUMN `employee_id` varchar(50) DEFAULT NULL AFTER `person_id`,
                ADD COLUMN `department` varchar(100) DEFAULT NULL AFTER `employee_id`,
                ADD COLUMN `job_title` varchar(100) DEFAULT NULL AFTER `department`,
                ADD COLUMN `employment_date` date DEFAULT NULL AFTER `job_title`,
                ADD COLUMN `employment_status` varchar(30) NOT NULL DEFAULT 'active' AFTER `employment_date`,
                ADD COLUMN `salary` decimal(15,2) DEFAULT NULL AFTER `employment_status`,
                ADD COLUMN `pay_frequency` varchar(30) DEFAULT 'monthly' AFTER `salary`,
                ADD COLUMN `bank_name` varchar(150) DEFAULT NULL AFTER `pay_frequency`,
                ADD COLUMN `bank_branch` varchar(150) DEFAULT NULL AFTER `bank_name`,
                ADD COLUMN `account_number` varchar(50) DEFAULT NULL AFTER `bank_branch`,
                ADD COLUMN `account_name` varchar(150) DEFAULT NULL AFTER `account_number`,
                ADD COLUMN `payment_method` varchar(30) DEFAULT 'bank_transfer' AFTER `account_name`
                SQL,
        );

        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS idx_employee_id ON ospos_employees (employee_id)');
    }

    public function down(): void
    {
        $this->db->query(
            <<<'SQL'
                ALTER TABLE `ospos_employees`
                DROP COLUMN `employee_id`,
                DROP COLUMN `department`,
                DROP COLUMN `job_title`,
                DROP COLUMN `employment_date`,
                DROP COLUMN `employment_status`,
                DROP COLUMN `salary`,
                DROP COLUMN `pay_frequency`,
                DROP COLUMN `bank_name`,
                DROP COLUMN `bank_branch`,
                DROP COLUMN `account_number`,
                DROP COLUMN `account_name`,
                DROP COLUMN `payment_method`
                SQL,
        );
    }
}
