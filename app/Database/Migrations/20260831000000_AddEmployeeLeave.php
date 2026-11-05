<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// Employee leave tracking — lets admins/managers record time off (annual,
// sick, unpaid, ...) so they can see at a glance who's actually available
// today before assigning jobs, without cross-checking a paper calendar.
class AddEmployeeLeave extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('employee_leave')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'person_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
                'leave_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false, 'default' => 'annual'],
                'start_date' => ['type' => 'DATE', 'null' => false],
                'end_date'   => ['type' => 'DATE', 'null' => false],
                'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false, 'default' => 'approved'],
                'reason'     => ['type' => 'TEXT', 'null' => true],
                'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('person_id');
            $this->forge->addKey(['start_date', 'end_date']);
            $this->forge->createTable('employee_leave');
        }

        $this->db->query(
            <<<'SQL'
                INSERT IGNORE INTO `ospos_modules` (`module_id`, `name_lang_key`, `desc_lang_key`, `sort`)
                VALUES ('leave', 'Module.leave', 'Module.leave_desc', 107);
                SQL,
        );

        $this->db->query(
            <<<'SQL'
                INSERT IGNORE INTO `ospos_permissions` (`permission_id`, `module_id`)
                VALUES ('leave', 'leave');
                SQL,
        );

        // Admin/manager tool, not employee self-service — grant it to whoever
        // already holds the 'employees' permission rather than everyone, so
        // it lands on exactly the same people who can already manage staff.
        $this->db->query(
            <<<'SQL'
                INSERT IGNORE INTO `ospos_grants` (`permission_id`, `person_id`, `menu_group`)
                SELECT 'leave', person_id, 'both'
                FROM `ospos_grants`
                WHERE permission_id = 'employees';
                SQL,
        );

        // Keep every role that already grants 'employees' in sync too, so
        // this doesn't quietly fall off the next time roles get re-applied.
        $this->db->query(
            <<<'SQL'
                INSERT IGNORE INTO `ospos_role_permissions` (`role_id`, `permission_id`, `menu_group`)
                SELECT role_id, 'leave', 'both'
                FROM `ospos_role_permissions`
                WHERE permission_id = 'employees';
                SQL,
        );
    }

    public function down(): void
    {
        $this->db->query("DELETE FROM `ospos_role_permissions` WHERE `permission_id` = 'leave';");
        $this->db->query("DELETE FROM `ospos_grants` WHERE `permission_id` = 'leave';");
        $this->db->query("DELETE FROM `ospos_permissions` WHERE `permission_id` = 'leave';");
        $this->db->query("DELETE FROM `ospos_modules` WHERE `module_id` = 'leave';");

        $this->forge->dropTable('employee_leave', true);
    }
}
