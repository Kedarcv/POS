<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class Role extends Model
{
    protected $table            = 'roles';
    protected $primaryKey       = 'role_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $allowedFields    = ['name', 'description', 'deleted'];
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    public function getActive(): array
    {
        return $this->where('deleted', 0)->orderBy('name', 'ASC')->findAll();
    }

    /** @return string[] permission_id list this role grants */
    public function getPermissionIds(int $roleId): array
    {
        $rows = $this->db->table('role_permissions')->where('role_id', $roleId)->get()->getResultArray();

        return array_column($rows, 'permission_id');
    }

    /** @return array<string, string> permission_id => menu_group */
    public function getPermissionsWithMenuGroup(int $roleId): array
    {
        $rows = $this->db->table('role_permissions')->where('role_id', $roleId)->get()->getResultArray();

        return array_column($rows, 'menu_group', 'permission_id');
    }

    public function savePermissions(int $roleId, array $permissionMenuGroups): void
    {
        $permissionMenuGroups = $this->withRequiredSubpermissions($permissionMenuGroups);

        $this->db->transStart();

        $this->db->table('role_permissions')->where('role_id', $roleId)->delete();

        if (!empty($permissionMenuGroups)) {
            $rows = [];
            foreach ($permissionMenuGroups as $permissionId => $menuGroup) {
                $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionId, 'menu_group' => $menuGroup ?: 'home'];
            }
            $this->db->table('role_permissions')->insertBatch($rows);
        }

        $this->db->transComplete();
    }

    /**
     * Employee::has_module_grant() denies access to a module entirely if it
     * has any registered sub-permissions (items_stock, sales_delete,
     * reports_sales, ...) and NONE of them are granted — holding only the
     * bare module isn't enough. A role built in the UI by checking just the
     * top-level "Items" or "Sales" box would silently lock that module out
     * completely. This adds every sub-permission of any granted module that
     * doesn't already have at least one sub-permission selected, so a role
     * that looks like it grants a module actually does.
     *
     * @param array<string, string> $permissionMenuGroups permission_id => menu_group
     * @return array<string, string>
     */
    private function withRequiredSubpermissions(array $permissionMenuGroups): array
    {
        $granted = array_keys($permissionMenuGroups);

        $allSubpermissions = $this->db->table('permissions')
            ->select('permission_id, module_id')
            ->where('module_id !=', 'permission_id', false)
            ->get()->getResultArray();

        $subsByModule = [];
        foreach ($allSubpermissions as $row) {
            $subsByModule[$row['module_id']][] = $row['permission_id'];
        }

        foreach ($subsByModule as $moduleId => $subPermissionIds) {
            if (!in_array($moduleId, $granted, true)) {
                continue; // module itself isn't granted; nothing to unblock
            }
            $hasAnySub = (bool) array_intersect($subPermissionIds, $granted);
            if (!$hasAnySub) {
                foreach ($subPermissionIds as $subId) {
                    $permissionMenuGroups[$subId] = '--';
                }
            }
        }

        return $permissionMenuGroups;
    }

    /**
     * Replaces an employee's grants with exactly this role's permission set
     * and records the role on the employee record. Mirrors the grants-sync
     * block in Employee::save_employee() so every existing permission check
     * (Secure_Controller, has_grant, has_module_grant) keeps working as-is.
     */
    public function applyToEmployee(int $roleId, int $employeeId): bool
    {
        $this->db->transStart();

        $this->db->table('grants')->where('person_id', $employeeId)->delete();

        $permissions = $this->getPermissionsWithMenuGroup($roleId);
        if (!empty($permissions)) {
            $rows = [];
            foreach ($permissions as $permissionId => $menuGroup) {
                $rows[] = ['permission_id' => $permissionId, 'person_id' => $employeeId, 'menu_group' => $menuGroup];
            }
            $this->db->table('grants')->insertBatch($rows);
        }

        $this->db->table('employees')->where('person_id', $employeeId)->update(['role_id' => $roleId]);

        $this->db->transComplete();

        return $this->db->transStatus();
    }

    public function isNameTaken(string $name, int $excludeRoleId = 0): bool
    {
        $builder = $this->where('name', $name)->where('deleted', 0);
        if ($excludeRoleId > 0) {
            $builder = $builder->where('role_id !=', $excludeRoleId);
        }

        return $builder->countAllResults() > 0;
    }
}
