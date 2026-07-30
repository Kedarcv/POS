<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Register Print Jobs as an OSPOS Module and insert language keys.
 */
class RegisterPrintjobsModule extends Migration
{
    public function up(): void
    {
        // 1. Insert module record (if not exists)
        $this->db->query(<<<'SQL'
            INSERT IGNORE INTO `ospos_modules` (`module_id`, `name_lang_key`, `desc_lang_key`, `sort`)
            VALUES ('printjobs', 'Module.printjobs', 'Module.printjobs_desc', 60);
            SQL
        );

        // 2. Insert permissions
        $this->db->query(<<<'SQL'
            INSERT IGNORE INTO `ospos_permissions` (`permission_id`, `module_id`)
            VALUES ('printjobs', 'printjobs');
            SQL
        );

        // 3. Auto-grant printjobs permission to all existing admin users
        $this->db->query(<<<'SQL'
            INSERT IGNORE INTO `ospos_grants` (`permission_id`, `person_id`, `menu_group`)
            SELECT 'printjobs', person_id, 'home'
            FROM `ospos_employees`
            WHERE deleted = 0;
            SQL
        );
    }

    public function down(): void
    {
        $this->db->query("DELETE FROM `ospos_grants` WHERE `permission_id` = 'printjobs';");
        $this->db->query("DELETE FROM `ospos_permissions` WHERE `permission_id` = 'printjobs';");
        $this->db->query("DELETE FROM `ospos_modules` WHERE `module_id` = 'printjobs';");
    }
}
