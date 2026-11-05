<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// Tax_categories, Tax_codes, and Tax_jurisdictions controllers each gate
// themselves behind their own permission_id (tax_categories/tax_codes/
// tax_jurisdictions), but none of the three were ever inserted into
// ospos_permissions — so has_module_grant() could never return true for
// them, for any user, ever. These pages have been unreachable since day
// one. Registered here as sub-permissions of the existing "taxes" module
// (matching how the Employees/Roles permission grid nests them), and
// granted to every employee who currently holds "taxes" so nobody's
// access silently narrows because of a bug fix.
class RegisterMissingTaxPermissions extends Migration
{
    private const PERMISSIONS = ['tax_categories', 'tax_codes', 'tax_jurisdictions'];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permissionId) {
            $this->db->table('permissions')->replace(['permission_id' => $permissionId, 'module_id' => 'taxes']);
        }

        $holders = $this->db->table('grants')
            ->select('person_id, menu_group')
            ->where('permission_id', 'taxes')
            ->get()->getResultArray();

        foreach ($holders as $holder) {
            foreach (self::PERMISSIONS as $permissionId) {
                $this->db->table('grants')->replace([
                    'permission_id' => $permissionId,
                    'person_id'     => $holder['person_id'],
                    'menu_group'    => '--',
                ]);
            }
        }
    }

    public function down(): void
    {
        $this->db->table('grants')->whereIn('permission_id', self::PERMISSIONS)->delete();
        $this->db->table('permissions')->whereIn('permission_id', self::PERMISSIONS)->delete();
    }
}
