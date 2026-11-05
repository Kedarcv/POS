<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Employee_leave;
use CodeIgniter\HTTP\ResponseInterface;

class Leave extends Secure_Controller
{
    private Employee_leave $leave;

    public function __construct()
    {
        parent::__construct('leave');

        $this->leave = model(Employee_leave::class);
    }

    /**
     * Whether the current employee can see everyone's leave and
     * approve/reject requests, vs. just applying for and viewing their own.
     * Checked inline against the existing 'employees' grant rather than a
     * second module-gating sub-permission — a bare 'leave' grant with no
     * sub-permissions registered keeps the module reachable by every
     * employee (see Employee::has_module_grant()'s sub-permission rule).
     */
    private function canManage(): bool
    {
        return $this->employee->has_grant('employees', $this->loggedInPersonId());
    }

    /**
     * get_logged_in_employee_info()->person_id comes back as a numeric
     * string (session/DB value), not an int — fine everywhere else in this
     * codebase since those callers aren't strict_types, but this file is,
     * so passing it straight into an (?int $person_id) parameter throws.
     */
    private function loggedInPersonId(): int
    {
        return (int) $this->employee->get_logged_in_employee_info()->person_id;
    }

    public function getIndex(): string
    {
        $personId  = $this->loggedInPersonId();
        $canManage = $this->canManage();

        $data['title']      = 'Employee Leave';
        $data['can_manage'] = $canManage;
        $data['types']      = Employee_leave::TYPES;
        $data['my_person_id'] = $personId;

        if ($canManage) {
            $data['on_leave_today'] = $this->leave->getOnLeaveToday();
            $data['records']        = $this->leave->getAllWithEmployeeNames();
            $data['employees']      = $this->employee->get_all()->getResult();
        } else {
            $data['records'] = array_filter(
                $this->leave->getAllWithEmployeeNames(),
                static fn ($row) => (int) $row['person_id'] === (int) $personId,
            );
        }

        return view('leave/index', $data);
    }

    /**
     * POST /leave/apply — self-service: an employee requesting their own
     * leave. Always lands as 'pending' regardless of who's asking, even a
     * manager using this endpoint directly.
     */
    public function postApply(): ResponseInterface
    {
        $personId  = $this->loggedInPersonId();
        $startDate = $this->request->getPost('start_date');
        $endDate   = $this->request->getPost('end_date');
        $leaveType = $this->request->getPost('leave_type') ?: 'annual';
        $reason    = $this->request->getPost('reason');

        [$ok, $error] = $this->validateDates($startDate, $endDate);
        if (! $ok) {
            return $this->response->setJSON(['success' => false, 'message' => $error]);
        }

        if (! in_array($leaveType, Employee_leave::TYPES, true)) {
            $leaveType = 'other';
        }

        $this->leave->insert([
            'person_id'  => $personId,
            'leave_type' => $leaveType,
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'status'     => 'pending',
            'reason'     => $reason,
            'created_by' => $personId,
        ]);

        return $this->response->setJSON(['success' => true, 'message' => 'Leave request submitted.']);
    }

    /**
     * POST /leave/save/(:num) — admin/manager direct entry, recorded as
     * already approved (this is them logging leave they've already agreed
     * to, not filing a request that then needs their own approval).
     */
    public function postSave(int $leave_id = NEW_ENTRY): ResponseInterface
    {
        if (! $this->canManage()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Not authorized.']);
        }

        $personId  = (int) $this->request->getPost('person_id');
        $startDate = $this->request->getPost('start_date');
        $endDate   = $this->request->getPost('end_date');
        $leaveType = $this->request->getPost('leave_type') ?: 'annual';
        $reason    = $this->request->getPost('reason');

        if ($personId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Select an employee.']);
        }

        [$ok, $error] = $this->validateDates($startDate, $endDate, $personId, $leave_id === NEW_ENTRY ? 0 : $leave_id);
        if (! $ok) {
            return $this->response->setJSON(['success' => false, 'message' => $error]);
        }

        if (! in_array($leaveType, Employee_leave::TYPES, true)) {
            $leaveType = 'other';
        }

        $data = [
            'person_id'  => $personId,
            'leave_type' => $leaveType,
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'status'     => 'approved',
            'reason'     => $reason,
            'created_by' => $this->loggedInPersonId(),
        ];

        if ($leave_id === NEW_ENTRY) {
            $this->leave->insert($data);
        } else {
            $this->leave->update($leave_id, $data);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Leave saved.']);
    }

    /**
     * POST /leave/approve/(:num)
     */
    public function postApprove(int $leave_id): ResponseInterface
    {
        if (! $this->canManage()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Not authorized.']);
        }

        $record = $this->leave->find($leave_id);
        if (! $record) {
            return $this->response->setJSON(['success' => false, 'message' => 'Leave request not found.']);
        }

        if ($this->leave->overlaps((int) $record->person_id, $record->start_date, $record->end_date, $leave_id)) {
            return $this->response->setJSON(['success' => false, 'message' => 'This employee already has approved leave overlapping those dates.']);
        }

        $this->leave->update($leave_id, ['status' => 'approved']);

        return $this->response->setJSON(['success' => true, 'message' => 'Leave approved.']);
    }

    /**
     * POST /leave/reject/(:num)
     */
    public function postReject(int $leave_id): ResponseInterface
    {
        if (! $this->canManage()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Not authorized.']);
        }

        $this->leave->update($leave_id, ['status' => 'rejected']);

        return $this->response->setJSON(['success' => true, 'message' => 'Leave rejected.']);
    }

    /**
     * POST /leave/delete/(:num) — a manager can remove any record; an
     * employee can only cancel their own request while it's still pending
     * (once approved/rejected, that's now a record, not a draft).
     */
    public function postDelete(int $leave_id = 0): ResponseInterface
    {
        $record = $this->leave->find($leave_id);
        if (! $record) {
            return $this->response->setJSON(['success' => false, 'message' => 'Leave request not found.']);
        }

        $personId = $this->loggedInPersonId();
        $isOwnPending = (int) $record->person_id === $personId && $record->status === 'pending';

        if (! $this->canManage() && ! $isOwnPending) {
            return $this->response->setJSON(['success' => false, 'message' => 'Not authorized.']);
        }

        $this->leave->delete($leave_id);

        return $this->response->setJSON(['success' => true]);
    }

    /**
     * @return array{0: bool, 1: ?string}
     */
    private function validateDates(?string $startDate, ?string $endDate, int $personId = 0, int $excludeId = 0): array
    {
        if (! $startDate || ! $endDate) {
            return [false, 'Start date and end date are required.'];
        }

        if (strtotime($endDate) < strtotime($startDate)) {
            return [false, "End date can't be before the start date."];
        }

        if ($personId > 0 && $this->leave->overlaps($personId, $startDate, $endDate, $excludeId)) {
            return [false, 'This employee already has approved leave overlapping those dates.'];
        }

        return [true, null];
    }
}
