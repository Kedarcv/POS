<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Layby / Installment Payment Plan module.
 *
 * Tracks customer laybys with deposit, installment schedule,
 * interest rates, and payment history.
 */
class AddLaybyModule extends Migration
{
    public function up(): void
    {
        // ── 1. Layby Plans ──
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'layby_number'        => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
            'sale_id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'customer_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
            'employee_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'total_amount'        => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'null' => false],
            'deposit_amount'      => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'null' => false],
            'balance'             => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'null' => false],
            'interest_rate'       => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0, 'null' => false, 'comment' => 'Annual percentage rate'],
            'interest_amount'     => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'null' => false],
            'total_with_interest' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'null' => false],
            'installment_amount'  => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'null' => false, 'comment' => 'Fixed amount per installment'],
            'installment_count'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1, 'null' => false],
            'frequency'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'monthly', 'null' => false, 'comment' => 'weekly, biweekly, monthly'],
            'start_date'          => ['type' => 'DATE', 'null' => false],
            'end_date'            => ['type' => 'DATE', 'null' => true],
            'status'              => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active', 'null' => false],
            'document_signed'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'null' => false],
            'notes'               => ['type' => 'TEXT', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('layby_number');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('status');
        $this->forge->addKey('sale_id');
        $this->forge->createTable('layby_plans', true);

        // ── 2. Layby Installments ──
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'layby_id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
            'installment_number'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
            'amount_due'          => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'null' => false],
            'amount_paid'         => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0, 'null' => false],
            'due_date'            => ['type' => 'DATE', 'null' => false],
            'paid_date'           => ['type' => 'DATE', 'null' => true],
            'status'              => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending', 'null' => false],
            'payment_method'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'reference_number'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'notes'               => ['type' => 'TEXT', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('layby_id');
        $this->forge->addKey('status');
        $this->forge->addKey('due_date');
        $this->forge->createTable('layby_installments', true);

        // ── 3. Register as OSPOS Module ──
        $this->db->query(<<<'SQL'
            INSERT IGNORE INTO `ospos_modules` (`module_id`, `name_lang_key`, `desc_lang_key`, `sort`)
            VALUES ('laybys', 'Module.laybys', 'Module.laybys_desc', 65);
            SQL
        );

        $this->db->query(<<<'SQL'
            INSERT IGNORE INTO `ospos_permissions` (`permission_id`, `module_id`)
            VALUES ('laybys', 'laybys');
            SQL
        );

        $this->db->query(<<<'SQL'
            INSERT IGNORE INTO `ospos_grants` (`permission_id`, `person_id`, `menu_group`)
            SELECT 'laybys', person_id, 'home'
            FROM `ospos_employees`
            WHERE deleted = 0;
            SQL
        );
    }

    public function down(): void
    {
        $this->db->query("DELETE FROM `ospos_grants` WHERE `permission_id` = 'laybys';");
        $this->db->query("DELETE FROM `ospos_permissions` WHERE `permission_id` = 'laybys';");
        $this->db->query("DELETE FROM `ospos_modules` WHERE `module_id` = 'laybys';");
        $this->forge->dropTable('layby_installments', true);
        $this->forge->dropTable('layby_plans', true);
    }
}
