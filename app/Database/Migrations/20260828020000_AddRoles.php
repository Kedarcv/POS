<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// Role-based access: an admin picks one role for an employee instead of
// checking off modules individually. Applying a role still just writes to
// the existing ospos_grants table, so Secure_Controller/has_grant() and
// every permission check already in the app keep working unchanged.
class AddRoles extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'role_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => false],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('role_id', true);
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('roles', true);

        $this->forge->addField([
            'role_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'permission_id' => ['type' => 'VARCHAR', 'constraint' => 60],
            'menu_group'    => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'home'],
        ]);
        $this->forge->addKey(['role_id', 'permission_id'], true);
        $this->forge->createTable('role_permissions', true);

        if (!$this->db->fieldExists('role_id', 'employees')) {
            $this->forge->addColumn('employees', [
                'role_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'person_id'],
            ]);
        }

        $this->seedDefaultRoles();
    }

    private function seedDefaultRoles(): void
    {
        // module_id => menu_group, drawn from the modules already registered
        // by this point (see the various RegisterXModule migrations).
        $roles = [
            [
                'name'        => 'Administrator',
                'description' => 'Full access to every module.',
                'modules'     => null, // null = every currently-registered module
            ],
            [
                'name'        => 'Cashier',
                'description' => 'Runs the sales register: sales, customers, cashups, giftcards.',
                'modules'     => ['home', 'sales', 'customers', 'cashups', 'giftcards'],
            ],
            [
                'name'        => 'Printer Operator',
                'description' => 'Print shop floor: sees only assigned print jobs and machine status.',
                'modules'     => ['home', 'printjobs', 'machines'],
            ],
            [
                'name'        => 'Manager',
                'description' => 'Day-to-day operations oversight without system configuration.',
                'modules'     => ['home', 'sales', 'reports', 'customers', 'employees', 'items', 'suppliers', 'printjobs', 'machines', 'laybys', 'expenses', 'cashups'],
            ],
        ];

        $allModules = $this->db->table('modules')->select('module_id')->get()->getResultArray();
        $allModuleIds = array_column($allModules, 'module_id');
        $now = date('Y-m-d H:i:s');

        foreach ($roles as $role) {
            $this->db->table('roles')->insert([
                'name'        => $role['name'],
                'description' => $role['description'],
                'created_at'  => $now,
            ]);
            $roleId = $this->db->insertID();

            $moduleIds = $role['modules'] ?? $allModuleIds;
            $rows = [];
            foreach ($moduleIds as $moduleId) {
                if (!in_array($moduleId, $allModuleIds, true)) {
                    continue; // module not registered in this install; skip rather than fail
                }
                // 'both' — not 'home' — so these modules are reachable from
                // both the Home and Office navigation contexts. A grant only
                // tagged 'home' is invisible to get_allowed_office_modules(),
                // which silently empties $allowed_modules for any page whose
                // session menu_group is 'office' (Config, Employees, Reports,
                // ...) and crashes partial/header.php outright.
                $rows[] = ['role_id' => $roleId, 'permission_id' => $moduleId, 'menu_group' => 'both'];
            }
            if (!empty($rows)) {
                $this->db->table('role_permissions')->insertBatch($rows);
            }
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('role_id', 'employees')) {
            $this->forge->dropColumn('employees', 'role_id');
        }
        $this->forge->dropTable('role_permissions', true);
        $this->forge->dropTable('roles', true);
    }
}
