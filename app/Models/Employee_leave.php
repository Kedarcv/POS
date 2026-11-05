<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class Employee_leave extends Model
{
    public const TYPES  = ['annual', 'sick', 'unpaid', 'compassionate', 'other'];
    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $table            = 'employee_leave';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'person_id', 'leave_type', 'start_date', 'end_date', 'status', 'reason', 'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'date';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Everyone whose leave covers today (approved only) — the "who's
     * actually here" list a manager needs before assigning jobs.
     */
    public function getOnLeaveToday(): array
    {
        $today = date('Y-m-d');

        return $this->db->table('employee_leave')
            ->select('employee_leave.*, people.first_name, people.last_name')
            ->join('employees', 'employees.person_id = employee_leave.person_id')
            ->join('people', 'people.person_id = employees.person_id')
            ->where('employee_leave.status', 'approved')
            ->where('employee_leave.start_date <=', $today)
            ->where('employee_leave.end_date >=', $today)
            ->where('employees.deleted', 0)
            ->orderBy('people.first_name', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * @return int[] person_ids currently on approved leave — cheap lookup
     * for tagging "on leave" against an employee dropdown elsewhere.
     */
    public function getPersonIdsOnLeaveToday(): array
    {
        return array_column($this->getOnLeaveToday(), 'person_id');
    }

    public function getAllWithEmployeeNames(): array
    {
        return $this->db->table('employee_leave')
            ->select('employee_leave.*, people.first_name, people.last_name')
            ->join('employees', 'employees.person_id = employee_leave.person_id')
            ->join('people', 'people.person_id = employees.person_id')
            ->orderBy('employee_leave.start_date', 'DESC')
            ->get()->getResultArray();
    }

    public function overlaps(int $personId, string $startDate, string $endDate, int $excludeId = 0): bool
    {
        $builder = $this->where('person_id', $personId)
            ->where('start_date <=', $endDate)
            ->where('end_date >=', $startDate)
            ->where('status', 'approved');

        if ($excludeId > 0) {
            $builder = $builder->where('id !=', $excludeId);
        }

        return $builder->countAllResults() > 0;
    }
}
