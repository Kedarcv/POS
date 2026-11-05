<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// AddEmployeeLeave (20260831000000) granted the 'leave' module only to
// whoever already held 'employees' (admin/manager), since it was built as a
// direct-entry tool at the time. It's now an apply/approve workflow — every
// employee needs to reach the page to apply for their own leave; only
// admin/manager (checked inline via the 'employees' grant, not a second
// module-gating sub-permission) sees everyone's requests and can approve or
// reject them.
class OpenLeaveToAllEmployees extends Migration
{
    public function up(): void
    {
        $this->db->query(
            <<<'SQL'
                INSERT IGNORE INTO `ospos_grants` (`permission_id`, `person_id`, `menu_group`)
                SELECT 'leave', person_id, 'both'
                FROM `ospos_employees`
                WHERE deleted = 0;
                SQL,
        );
    }

    public function down(): void
    {
        // Deliberately a no-op — reverting would lock rank-and-file
        // employees back out of applying for their own leave.
    }
}
