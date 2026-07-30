<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RegisterMachinesModule extends Migration
{
    public function up(): void
    {
        $this->db->query(
            <<<'SQL'
                INSERT IGNORE INTO `ospos_modules` (`module_id`, `name_lang_key`, `desc_lang_key`, `sort`)
                VALUES ('machines', 'Module.machines', 'Module.machines_desc', 62);
                SQL,
        );

        $this->db->query(
            <<<'SQL'
                INSERT IGNORE INTO `ospos_permissions` (`permission_id`, `module_id`)
                VALUES ('machines', 'machines');
                SQL,
        );

        $this->db->query(
            <<<'SQL'
                INSERT IGNORE INTO `ospos_grants` (`permission_id`, `person_id`, `menu_group`)
                SELECT 'machines', person_id, 'home'
                FROM `ospos_employees`
                WHERE deleted = 0;
                SQL,
        );
    }

    public function down(): void
    {
        $this->db->query("DELETE FROM `ospos_grants` WHERE `permission_id` = 'machines';");
        $this->db->query("DELETE FROM `ospos_permissions` WHERE `permission_id` = 'machines';");
        $this->db->query("DELETE FROM `ospos_modules` WHERE `module_id` = 'machines';");
    }
}
