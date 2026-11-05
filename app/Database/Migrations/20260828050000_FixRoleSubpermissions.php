<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// Employee::has_module_grant() has a quirk: if a module has any registered
// sub-permissions (items_stock, sales_delete, reports_sales, etc.) in
// ospos_permissions, holding *only* the bare module grant isn't enough —
// at least one of its sub-permissions must also be granted, or access is
// denied outright. The default roles seeded by AddRoles (20260828020000)
// only ever granted bare module_ids, so Administrator/Cashier/Manager were
// all silently locked out of "items", "sales", and "reports" — Manager
// couldn't even see its own advertised sales/items/reports access. Backfills
// the missing sub-permissions and re-syncs anyone already on those roles.
class FixRoleSubpermissions extends Migration
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        // Administrator: everything, sub-permissions included.
        $adminRole = $this->db->table('roles')->where('name', 'Administrator')->get()->getRow();
        if ($adminRole) {
            $allPermissionIds = array_column(
                $this->db->table('permissions')->select('permission_id')->get()->getResultArray(),
                'permission_id',
            );
            $this->addMissingPermissions((int) $adminRole->role_id, $allPermissionIds, $now);
        }

        // Cashier: "sales_stock" is an unused stub (grep confirms nothing
        // checks it) — granting it only satisfies the sub-permission
        // requirement, it doesn't add any real capability. Deliberately not
        // granting sales_delete/sales_change_price here; those are
        // Manager-level.
        $cashierRole = $this->db->table('roles')->where('name', 'Cashier')->get()->getRow();
        if ($cashierRole) {
            $this->addMissingPermissions((int) $cashierRole->role_id, ['sales_stock'], $now);
        }

        // Manager: unblock sales/items (inert stock stubs), grant every
        // report type (a manager should see all reports), and grant the two
        // real sales sub-permissions (void/delete a sale, override a price)
        // since those are conventionally manager-level, not cashier-level.
        $managerRole = $this->db->table('roles')->where('name', 'Manager')->get()->getRow();
        if ($managerRole) {
            $reportPermissions = array_column(
                $this->db->table('permissions')
                    ->select('permission_id')
                    ->like('permission_id', 'reports_', 'after')
                    ->get()->getResultArray(),
                'permission_id',
            );
            $managerExtra = array_merge(
                ['sales_stock', 'items_stock', 'sales_delete', 'sales_change_price'],
                $reportPermissions,
            );
            $this->addMissingPermissions((int) $managerRole->role_id, $managerExtra, $now);
        }

        // Re-sync every employee currently on one of these roles so the fix
        // takes effect immediately, not just for future role assignments.
        $roleIds = array_filter([$adminRole->role_id ?? null, $cashierRole->role_id ?? null, $managerRole->role_id ?? null]);
        foreach ($roleIds as $roleId) {
            $this->resyncEmployeesOnRole((int) $roleId);
        }
    }

    private function addMissingPermissions(int $roleId, array $permissionIds, string $now): void
    {
        $existing = array_column(
            $this->db->table('role_permissions')->select('permission_id')->where('role_id', $roleId)->get()->getResultArray(),
            'permission_id',
        );

        $rows = [];
        foreach (array_unique($permissionIds) as $permissionId) {
            if (!in_array($permissionId, $existing, true)) {
                $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionId, 'menu_group' => '--'];
            }
        }

        if (!empty($rows)) {
            $this->db->table('role_permissions')->insertBatch($rows);
        }
    }

    private function resyncEmployeesOnRole(int $roleId): void
    {
        $employeeIds = array_column(
            $this->db->table('employees')->select('person_id')->where('role_id', $roleId)->get()->getResultArray(),
            'person_id',
        );
        if (empty($employeeIds)) {
            return;
        }

        $permissions = $this->db->table('role_permissions')->where('role_id', $roleId)->get()->getResultArray();

        foreach ($employeeIds as $employeeId) {
            $this->db->table('grants')->where('person_id', $employeeId)->delete();
            $rows = array_map(static fn ($p) => [
                'permission_id' => $p['permission_id'],
                'person_id'     => $employeeId,
                'menu_group'    => $p['menu_group'],
            ], $permissions);
            if (!empty($rows)) {
                $this->db->table('grants')->insertBatch($rows);
            }
        }
    }

    public function down(): void
    {
        // Deliberately a no-op — this only ever adds permissions that should
        // have been there from the start; reverting would re-introduce the bug.
    }
}
