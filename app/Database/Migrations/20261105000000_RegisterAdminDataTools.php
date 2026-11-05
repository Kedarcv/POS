<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RegisterAdminDataTools extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "INSERT IGNORE INTO ospos_modules (module_id, name_lang_key, desc_lang_key, sort)
             VALUES ('admin_data', 'Module.admin_data', 'Module.admin_data_desc', 5)",
        );
        $this->db->query(
            "INSERT IGNORE INTO ospos_permissions (permission_id, module_id)
             VALUES ('admin_data', 'admin_data')",
        );
        $this->db->query(
            "INSERT IGNORE INTO ospos_grants (permission_id, person_id, menu_group)
             VALUES ('admin_data', 1, 'home')",
        );
    }

    public function down(): void
    {
        $this->db->query("DELETE FROM ospos_grants WHERE permission_id = 'admin_data'");
        $this->db->query("DELETE FROM ospos_permissions WHERE permission_id = 'admin_data'");
        $this->db->query("DELETE FROM ospos_modules WHERE module_id = 'admin_data'");
    }
}
