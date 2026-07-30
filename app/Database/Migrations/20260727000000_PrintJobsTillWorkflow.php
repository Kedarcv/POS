<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

class PrintJobsTillWorkflow extends Migration
{
    public function up()
    {
        $forge = Database::forge();

        // Add new columns to print_jobs for till workflow
        $fields = [
            'machine' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'colors',
            ],
            'deadline' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'completed_at',
            ],
            'served_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'assigned_to',
            ],
            'whatsapp_images' => [
                'type'  => 'TEXT',
                'null'  => true,
                'after' => 'notes',
            ],
        ];

        $forge->addColumn('print_jobs', $fields);

        // Add index on deadline
        $forge->addKey('print_jobs', 'deadline');
    }

    public function down()
    {
        $forge = Database::forge();
        $forge->dropColumn('print_jobs', ['machine', 'deadline', 'served_by', 'whatsapp_images']);
    }
}
