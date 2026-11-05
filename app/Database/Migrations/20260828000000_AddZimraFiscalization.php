<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// Adds ZIMRA (Zimbabwe Revenue Authority) fiscalisation support: device
// registration, fiscal day tracking, and per-receipt submission records,
// per the Fiscal Device Gateway API used by FDMS.
class AddZimraFiscalization extends Migration
{
    public function up(): void
    {
        // Single-row table holding this till's fiscal device identity and
        // certificate material. Kept separate from ospos_app_config so the
        // private key path/activation key aren't mixed in with general settings.
        $this->forge->addField([
            'id'                    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'enabled'               => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'environment'           => ['type' => "ENUM('test','production')", 'default' => 'test'],
            'tin'                   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'device_serial_no'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'device_model_name'     => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'Server'],
            'device_model_version'  => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => '1.0'],
            'device_id'             => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'activation_key'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            // Certificate/private key are stored on disk (in the app's data
            // directory, outside the web root); these columns hold the paths.
            'certificate_path'      => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'private_key_path'      => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'default_currency'      => ['type' => 'VARCHAR', 'constraint' => 5, 'default' => 'USD'],
            'last_fiscal_day_no'    => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'last_receipt_global_no' => ['type' => 'BIGINT', 'constraint' => 20, 'default' => 0],
            'registered_at'         => ['type' => 'DATETIME', 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => false],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('zimra_settings', true);

        // Maps this till's tax categories onto the tax IDs/rates ZIMRA
        // assigned this device at registration (e.g. 15.5% standard -> 515).
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'tax_category_id'   => ['type' => 'INT', 'constraint' => 11, 'null' => false],
            'zimra_tax_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => false],
            'zimra_tax_percent' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
            'zimra_tax_name'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => false],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('tax_category_id');
        $this->forge->createTable('zimra_tax_map', true);

        // One row per fiscal day the device has opened. ZIMRA requires
        // strictly sequential day numbers and same-day receipt counters.
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'fiscal_day_no'  => ['type' => 'INT', 'constraint' => 11, 'null' => false],
            'fiscal_date'    => ['type' => 'DATE', 'null' => false],
            'status'         => ['type' => "ENUM('OPEN','CLOSED','CLOSE_FAILED')", 'default' => 'OPEN'],
            'last_receipt_counter' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'operation_id'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'counters_json'  => ['type' => 'LONGTEXT', 'null' => true],
            'opened_at'      => ['type' => 'DATETIME', 'null' => false],
            'closed_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('fiscal_day_no');
        $this->forge->createTable('zimra_fiscal_days', true);

        // One row per sale submitted (or attempted) to FDMS.
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'sale_id'             => ['type' => 'INT', 'constraint' => 10, 'null' => false],
            'fiscal_day_no'       => ['type' => 'INT', 'constraint' => 11, 'null' => false],
            'receipt_counter'     => ['type' => 'INT', 'constraint' => 11, 'null' => false],
            'receipt_global_no'   => ['type' => 'BIGINT', 'constraint' => 20, 'null' => true],
            'receipt_type'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'FISCALINVOICE'],
            'currency'            => ['type' => 'VARCHAR', 'constraint' => 5, 'default' => 'USD'],
            'status'              => ['type' => "ENUM('PENDING','SUBMITTED','FAILED')", 'default' => 'PENDING'],
            'device_signature'    => ['type' => 'TEXT', 'null' => true],
            'server_signature'    => ['type' => 'TEXT', 'null' => true],
            'qr_url'              => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'verification_code'   => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'request_payload'     => ['type' => 'LONGTEXT', 'null' => true],
            'response_payload'    => ['type' => 'LONGTEXT', 'null' => true],
            'error_message'       => ['type' => 'TEXT', 'null' => true],
            'attempts'            => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'submitted_at'        => ['type' => 'DATETIME', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => false],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('sale_id');
        $this->forge->addKey('status');
        $this->forge->addKey(['fiscal_day_no', 'receipt_counter']);
        $this->forge->createTable('zimra_receipts', true);

        $this->db->query(
            <<<'SQL'
                INSERT IGNORE INTO `ospos_modules` (`module_id`, `name_lang_key`, `desc_lang_key`, `sort`)
                VALUES ('zimra', 'Module.zimra', 'Module.zimra_desc', 106);
                SQL,
        );

        $this->db->query(
            <<<'SQL'
                INSERT IGNORE INTO `ospos_permissions` (`permission_id`, `module_id`)
                VALUES ('zimra', 'zimra');
                SQL,
        );

        $this->db->query(
            <<<'SQL'
                INSERT IGNORE INTO `ospos_grants` (`permission_id`, `person_id`, `menu_group`)
                SELECT 'zimra', person_id, 'home'
                FROM `ospos_employees`
                WHERE deleted = 0;
                SQL,
        );
    }

    public function down(): void
    {
        $this->db->query("DELETE FROM `ospos_grants` WHERE `permission_id` = 'zimra';");
        $this->db->query("DELETE FROM `ospos_permissions` WHERE `permission_id` = 'zimra';");
        $this->db->query("DELETE FROM `ospos_modules` WHERE `module_id` = 'zimra';");

        $this->forge->dropTable('zimra_receipts', true);
        $this->forge->dropTable('zimra_fiscal_days', true);
        $this->forge->dropTable('zimra_tax_map', true);
        $this->forge->dropTable('zimra_settings', true);
    }
}
