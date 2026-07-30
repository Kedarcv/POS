<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds:
 * - Print Jobs module (mirrors BeamLink printing department)
 * - Webhook infrastructure (incoming/outgoing)
 * - External payment tracking (Pesepay, EcoCash, Paynow)
 * - WhatsApp integration
 */
class AddPrintingAndWebhooks extends Migration
{
    public function up(): void
    {
        // ── 1. Print Jobs ──
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'job_number'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false, 'comment' => 'e.g. PRINT-2025-001'],
            'customer_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'item_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'comment' => 'Link to OSPOS items if the print job itself is a stock item'],
            'job_type'        => ['type' => 'ENUM("banner","flyer","brochure","business_card","tshirt","sticker","poster","booklet","other")', 'default' => 'other', 'null' => false],
            'description'     => ['type' => 'TEXT', 'null' => true],
            'dimensions'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'comment' => 'e.g. A3, 3x2m, custom'],
            'quantity'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1],
            'color_mode'      => ['type' => 'ENUM("full_color","black_white","spot_uv")', 'default' => 'full_color', 'null' => false],
            'paper_type'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'comment' => 'glossy, matte, vinyl, etc'],
            'delivery_method' => ['type' => 'ENUM("pickup","delivery","whatsapp_digital")', 'default' => 'pickup', 'null' => false],
            'deadline'        => ['type' => 'DATE', 'null' => true],
            'cost_price'      => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'unit_price'      => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'total'           => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'status'          => ['type' => 'ENUM("draft","quoted","pending_payment","confirmed","queued","printing","quality_check","ready","delivered","cancelled")', 'default' => 'draft', 'null' => false],
            'priority'        => ['type' => 'ENUM("low","normal","high","urgent")', 'default' => 'normal', 'null' => false],
            'assigned_to'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'comment' => 'Employee ID'],
            'sale_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'comment' => 'Linked OSPOS sale when billed'],
            'payment_status'  => ['type' => 'ENUM("unpaid","partial","paid")', 'default' => 'unpaid', 'null' => false],
            'customer_notes'  => ['type' => 'TEXT', 'null' => true],
            'internal_notes'  => ['type' => 'TEXT', 'null' => true],
            'whatsapp_notified' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'null' => false],
            'source'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'pos', 'null' => false, 'comment' => 'pos, whatsapp, web, beamlink-erp'],
            'external_ref'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'comment' => 'Reference from external system'],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'completed_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('job_number');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('status');
        $this->forge->addKey('assigned_to');
        $this->forge->addKey('sale_id');
        $this->forge->createTable('print_jobs', true);

        // ── 2. Print Job Files / Attachments ──
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'print_job_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
            'file_name'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'file_path'  => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => false],
            'file_type'  => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'file_size'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('print_job_id');
        $this->forge->createTable('print_job_files', true);

        // ── 3. External Payment Tracking ──
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'sale_id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'print_job_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'gateway'         => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false, 'comment' => 'pesepay, ecocash, paynow, bank, cash'],
            'reference'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'amount'          => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => false],
            'currency'        => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'USD', 'null' => false],
            'status'          => ['type' => 'ENUM("pending","completed","failed","refunded","cancelled")', 'default' => 'pending', 'null' => false],
            'gateway_payload' => ['type' => 'TEXT', 'null' => true],
            'processed_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('reference');
        $this->forge->addKey(['gateway', 'status']);
        $this->forge->addKey('sale_id');
        $this->forge->addKey('print_job_id');
        $this->forge->createTable('external_payments', true);

        // ── 4. Webhook Event Log (inbound/outbound) ──
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'direction'     => ['type' => 'ENUM("inbound","outbound")', 'default' => 'inbound', 'null' => false],
            'source'        => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
            'event_type'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'payload'       => ['type' => 'TEXT', 'null' => true],
            'status'        => ['type' => 'ENUM("pending","processing","completed","failed")', 'default' => 'pending', 'null' => false],
            'response_body' => ['type' => 'TEXT', 'null' => true],
            'http_status'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'processed_at'  => ['type' => 'DATETIME', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['source', 'event_type']);
        $this->forge->addKey('status');
        $this->forge->createTable('webhook_logs', true);

        // ── 5. WhatsApp Integration ──
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'phone'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
            'state'      => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'menu'],
            'context'    => ['type' => 'TEXT', 'null' => true, 'comment' => 'JSON context'],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('phone');
        $this->forge->createTable('whatsapp_conversations', true);

        $this->forge->addField([
            'message_id'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'processed_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('message_id', true);
        $this->forge->createTable('whatsapp_processed_messages', true);

        // ── 6. Webhook Configuration ──
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'source'       => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
            'event_type'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'endpoint_url' => ['type' => 'TEXT', 'null' => false],
            'secret_key'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'headers_json' => ['type' => 'TEXT', 'null' => true],
            'is_active'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'null' => false],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['source', 'event_type']);
        $this->forge->createTable('webhook_endpoints', true);

        // ── Seed default webhook configs ──
        $this->db->query(<<<'SQL'
            INSERT INTO `ospos_webhook_endpoints`
                (`name`, `source`, `event_type`, `endpoint_url`, `is_active`)
            VALUES
                ('BeamLink ERP Router', 'beamlink-erp', '*', '', 1),
                ('WhatsApp Messages', 'whatsapp', 'message', '', 1),
                ('Pesepay Payments', 'pesepay', 'payment', '', 1),
                ('EcoCash Payments', 'ecocash', 'payment', '', 1),
                ('Paynow Payments', 'paynow', 'payment', '', 1),
                ('Fusertech Clone', 'fusertech', '*', '', 1);
            SQL
        );

        // ── Register Print Jobs as an OSPOS Module ──
        $this->db->query(<<<'SQL'
            INSERT INTO `ospos_modules` (`name_lang_key`, `desc_lang_key`, `sort`, `module_id`)
            VALUES
                ('Module.printjobs', 'Module.printjobs_desc', 60, 'printjobs')
            ON DUPLICATE KEY UPDATE `name_lang_key` = VALUES(`name_lang_key`);
            SQL
        );

        // ── Add permissions for printjobs ──
        $this->db->query(<<<'SQL'
            INSERT INTO `ospos_permissions` (`permission_id`, `module_id`)
            VALUES
                ('printjobs', 'printjobs')
            ON DUPLICATE KEY UPDATE `module_id` = VALUES(`module_id`);
            SQL
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('webhook_endpoints', true);
        $this->forge->dropTable('whatsapp_processed_messages', true);
        $this->forge->dropTable('whatsapp_conversations', true);
        $this->forge->dropTable('webhook_logs', true);
        $this->forge->dropTable('external_payments', true);
        $this->forge->dropTable('print_job_files', true);
        $this->forge->dropTable('print_jobs', true);
    }
}
