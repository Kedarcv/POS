<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// AddRoles (20260828020000) seeded every default role's module grants with
// menu_group='home'. get_allowed_office_modules() only returns rows tagged
// 'office' or 'both', so every employee on a default role had zero allowed
// modules the moment their session's menu_group flipped to 'office' (Config,
// Employees, Reports, ...) — and Secure_Controller's $allowed_modules array
// was left completely unset in that case, crashing partial/header.php with
// "Undefined variable $allowed_modules" on every page after that, including
// Home once the empty-office-menu bug is triggered once. Widening every
// module-level grant (not sub-permissions, which don't drive navigation) to
// 'both' fixes existing installs; the AddRoles source is fixed too so a
// fresh install seeds it correctly from the start.
class FixRoleMenuGroups extends Migration
{
    public function up(): void
    {
        // Module-level grants only (permission_id == module_id) — sub-
        // permissions correctly keep '--', they don't drive navigation.
        $moduleIds = array_column(
            $this->db->table('modules')->select('module_id')->get()->getResultArray(),
            'module_id',
        );

        if (! empty($moduleIds)) {
            $this->db->table('role_permissions')
                ->where('menu_group', 'home')
                ->whereIn('permission_id', $moduleIds)
                ->update(['menu_group' => 'both']);
        }

        // Re-sync every employee currently on a role so the fix is visible
        // immediately, not just for future role assignments.
        $roleIds = array_column(
            $this->db->table('roles')->select('role_id')->where('deleted', 0)->get()->getResultArray(),
            'role_id',
        );

        foreach ($roleIds as $roleId) {
            $employeeIds = array_column(
                $this->db->table('employees')->select('person_id')->where('role_id', $roleId)->get()->getResultArray(),
                'person_id',
            );
            if (empty($employeeIds)) {
                continue;
            }

            $permissions = $this->db->table('role_permissions')->where('role_id', $roleId)->get()->getResultArray();

            foreach ($employeeIds as $employeeId) {
                $this->db->table('grants')->where('person_id', $employeeId)->delete();
                $rows = array_map(static fn ($p) => [
                    'permission_id' => $p['permission_id'],
                    'person_id'     => $employeeId,
                    'menu_group'    => $p['menu_group'],
                ], $permissions);
                if (! empty($rows)) {
                    $this->db->table('grants')->insertBatch($rows);
                }
            }
        }
    }

    public function down(): void
    {
        // Deliberately a no-op — reverting would re-introduce the crash.
    }
}
