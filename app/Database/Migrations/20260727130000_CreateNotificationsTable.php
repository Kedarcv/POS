<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNotificationsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'person_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
            'title'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'message'     => ['type' => 'TEXT', 'null' => true],
            'type'        => ['type' => "ENUM('job_new','job_status','job_assigned','stock_alert','system')", 'default' => 'system'],
            'link'        => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'is_read'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('person_id');
        $this->forge->addKey(['person_id', 'is_read']);
        // Idempotent: installs created before the namespace fix recorded this
        // migration under App\Database\Migration and will re-run it once.
        $this->forge->createTable('notifications', true);
    }

    public function down()
    {
        $this->forge->dropTable('notifications');
    }
}
