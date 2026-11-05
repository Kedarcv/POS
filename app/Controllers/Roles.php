<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Role;
use CodeIgniter\HTTP\ResponseInterface;

class Roles extends Secure_Controller
{
    private Role $role;

    public function __construct()
    {
        // Gated behind "config" — the same module the Employees permission
        // grid itself already treats as admin-only — since defining what a
        // role grants (including Administrator) is a system-configuration
        // capability, not a day-to-day staff-management one. A Manager can
        // still assign an existing role to an employee via the Employees
        // page; they just can't redefine what any role itself grants.
        parent::__construct('config');

        $this->role = model(Role::class);
        // $this->module is already populated by Secure_Controller.
    }

    public function getIndex(): string
    {
        $data['title'] = 'Roles';
        $data['roles'] = $this->role->getActive();

        $db = \Config\Database::connect();
        $counts = $db->table('employees')
            ->select('role_id, COUNT(*) as employee_count')
            ->where('deleted', 0)
            ->groupBy('role_id')
            ->get()->getResultArray();
        $data['employee_counts'] = array_column($counts, 'employee_count', 'role_id');

        return view('roles/index', $data);
    }

    public function getView(int $role_id = NEW_ENTRY): string
    {
        $role = $role_id !== NEW_ENTRY ? $this->role->find($role_id) : null;
        if ($role_id !== NEW_ENTRY && $role === null) {
            return redirect()->to('roles');
        }

        $granted = $role_id !== NEW_ENTRY ? $this->role->getPermissionIds($role_id) : [];

        $modules = [];
        foreach ($this->module->get_all_modules()->getResult() as $module) {
            $module->grant = in_array($module->module_id, $granted, true);
            $modules[] = $module;
        }

        $permissions = [];
        foreach ($this->module->get_all_subpermissions()->getResult() as $permission) {
            $permission->permission_id = str_replace(' ', '_', $permission->permission_id);
            $permission->grant = in_array($permission->permission_id, $granted, true);
            $permissions[] = $permission;
        }

        $data['title']              = $role ? 'Edit Role' : 'New Role';
        $data['role']               = $role;
        $data['role_id']            = $role_id;
        $data['all_modules']        = $modules;
        $data['all_subpermissions'] = $permissions;

        return view('roles/form', $data);
    }

    public function postSave(int $role_id = NEW_ENTRY): ResponseInterface
    {
        $name = trim((string) $this->request->getPost('name'));
        $description = trim((string) $this->request->getPost('description'));

        if ($name === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'Role name is required.']);
        }

        if ($this->role->isNameTaken($name, $role_id === NEW_ENTRY ? 0 : $role_id)) {
            return $this->response->setJSON(['success' => false, 'message' => 'A role with that name already exists.']);
        }

        $data = ['name' => $name, 'description' => $description];

        if ($role_id === NEW_ENTRY) {
            $role_id = $this->role->insert($data, true);
        } else {
            $this->role->update($role_id, $data);
        }

        // Build the permission set from whichever grant_<id> checkboxes were
        // submitted — same convention Employees::postSave() already uses.
        $permissionMenuGroups = [];
        foreach ($this->module->get_all_permissions()->getResult() as $permission) {
            $grant = $this->request->getPost('grant_' . $permission->permission_id);
            if ($grant === $permission->permission_id) {
                $menuGroup = $this->request->getPost('menu_group_' . $permission->permission_id) ?: 'home';
                $permissionMenuGroups[$permission->permission_id] = $menuGroup;
            }
        }

        $this->role->savePermissions((int) $role_id, $permissionMenuGroups);

        // Every employee currently on this role gets the updated permission
        // set immediately — that's the point of a role.
        $db = \Config\Database::connect();
        $employeeIds = array_column(
            $db->table('employees')->select('person_id')->where('role_id', $role_id)->get()->getResultArray(),
            'person_id',
        );
        foreach ($employeeIds as $employeeId) {
            $this->role->applyToEmployee((int) $role_id, (int) $employeeId);
        }

        return $this->response->setJSON(['success' => true, 'role_id' => $role_id, 'message' => 'Role saved.']);
    }

    public function postDelete(int $role_id = 0): ResponseInterface
    {
        $db = \Config\Database::connect();
        $inUse = $db->table('employees')->where('role_id', $role_id)->where('deleted', 0)->countAllResults();

        if ($inUse > 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => "This role is assigned to {$inUse} employee(s). Reassign them to another role first.",
            ]);
        }

        $this->role->update($role_id, ['deleted' => 1]);
        $db->table('role_permissions')->where('role_id', $role_id)->delete();

        return $this->response->setJSON(['success' => true]);
    }

    /**
     * GET /roles/permissions/(:num) — the permission_id list for a role, used
     * by the employee form's JS to auto-check the matching boxes when an
     * admin picks a role instead of hand-picking modules.
     */
    public function getPermissions(int $role_id): ResponseInterface
    {
        return $this->response->setJSON([
            'permission_ids' => $this->role->getPermissionIds($role_id),
        ]);
    }
}
