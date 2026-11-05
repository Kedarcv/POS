<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// modules.name_lang_key was seeded as the bare string 'module_<id>' (e.g.
// 'module_taxes') for nearly every core module, instead of the namespaced
// 'Module.<id>' key that lang() actually needs and that app/Language/*/
// Module.php defines entries for. lang() falls back to echoing an
// unresolvable key back verbatim, so Module::get_module_name() — used only
// by the "no access" page — has been displaying literal garbage like "the
// module named module_taxes" instead of "Taxes" for every module except the
// handful (laybys, machines, printjobs, zimra) that were seeded correctly.
class FixModuleNameLangKeys extends Migration
{
    public function up(): void
    {
        $rows = $this->db->table('modules')
            ->select('module_id, name_lang_key')
            ->like('name_lang_key', 'module_', 'after')
            ->get()->getResultArray();

        foreach ($rows as $row) {
            $this->db->table('modules')
                ->where('module_id', $row['module_id'])
                ->update(['name_lang_key' => 'Module.' . $row['module_id']]);
        }
    }

    public function down(): void
    {
        // Deliberately a no-op — reverting would restore the broken keys.
    }
}
