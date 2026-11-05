<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\BeamlinkSync;
use App\Libraries\Print_ink_estimator;
use App\Libraries\Sms_lib;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Expense_category;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\Item_quantity;
use App\Models\Machine;
use App\Models\Print_process;
use App\Models\Print_process_channel;
use App\Models\Printjob;
use App\Models\Printjobfile;
use App\Models\Printmaterial;
use App\Models\Printmaterialusage;
use App\Models\Printpricing;
use App\Models\Stock_location;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Print Shop Module — full lifecycle matching BeamLink ERP.
 *
 * Dashboard (Kanban) → Job Detail (status pipeline) → Materials → Pricing
 */
class Printjobs extends Secure_Controller
{
    protected Printjob $printJob;
    protected Printjobfile $printJobFile;
    protected Printmaterial $printMaterial;
    protected Printpricing $printPricing;
    protected Print_process $printProcess;
    protected Customer $customer;
    protected Machine $machine;

    public function __construct()
    {
        parent::__construct('printjobs');
        $this->printJob      = model(Printjob::class);
        $this->printJobFile  = model(Printjobfile::class);
        $this->printMaterial = model(Printmaterial::class);
        $this->printPricing  = model(Printpricing::class);
        $this->printProcess  = model(Print_process::class);
        $this->customer      = model(Customer::class);
        $this->machine       = model(Machine::class);
    }

    /**
     * GET /printjobs — Kanban dashboard
     */
    public function index()
    {
        $data['title']          = lang('Printjobs.title');
        $data['status_counts']  = $this->printJob->getStatusCounts();
        $data['kanban']         = $this->printJob->getKanbanData();
        $activeJobs             = $this->printJob->getActiveJobs();
        $data['active_count']   = count($activeJobs);
        $data['pipeline_value'] = array_sum(array_column($activeJobs, 'total_cost'));
        $data['low_stock']      = $this->printMaterial->getLowStock();
        $data['customers']      = $this->customer->get_all()->getResult();

        echo view('printjobs/index', $data);
    }

    /**
     * GET /printjobs/myjobs — An operator's own queue: everything assigned
     * to them, waiting to be worked, soonest deadline first.
     */
    public function myJobs()
    {
        $employeeInfo = $this->employee->get_logged_in_employee_info();

        $data['title']  = 'My Print Jobs';
        $data['jobs']   = $this->printJob->getAssignedToEmployee((int) $employeeInfo->person_id);
        $data['status_counts'] = $this->printJob->getStatusCounts();

        echo view('printjobs/my_jobs', $data);
    }

    /**
     * GET /printjobs/view/(:num) — Job detail with status pipeline
     */
    public function view(int $id = 0)
    {
        $data['job'] = $this->printJob->find($id);
        if (! $data['job']) {
            return redirect()->to('/printjobs');
        }

        $data['title']         = lang('Printjobs.job_number') . ' ' . $data['job']->job_number;
        $data['employees']     = $this->employee->get_all()->getResult();
        // So the assignment dropdown can flag who's actually available today
        // instead of a manager finding out only after handing someone a job.
        $data['on_leave_ids']  = model(\App\Models\Employee_leave::class)->getPersonIdsOnLeaveToday();
        $data['customer']  = ! empty($data['job']->customer_id)
            ? $this->customer->get_info((int) $data['job']->customer_id)
            : null;

        // Artwork/reference files — a design sent over WhatsApp, a customer's
        // own PDF, whatever the team needs on hand to actually run the job.
        $data['attachments'] = $this->printJobFile->getForJob($id);

        // Load material usage for this job
        $usageModel             = model(Printmaterialusage::class);
        $data['material_usage'] = $usageModel->getByJob($id);

        // Process + ink estimate (recomputed live so it reflects current rates)
        $data['process']      = null;
        $data['ink_estimate'] = null;
        if (! empty($data['job']->process_id)) {
            $data['process']      = $this->printProcess->find((int) $data['job']->process_id);
            $data['ink_estimate'] = (new Print_ink_estimator())->estimate(
                (int) $data['job']->process_id,
                $data['job']->width_mm !== null ? (float) $data['job']->width_mm : null,
                $data['job']->height_mm !== null ? (float) $data['job']->height_mm : null,
                (float) ($data['job']->quantity ?: 1),
                $data['job']->coverage_pct !== null ? (float) $data['job']->coverage_pct : null,
                $data['job']->sheets !== null ? (int) $data['job']->sheets : null,
                ! empty($data['job']->material_id) ? (int) $data['job']->material_id : null,
                (float) ($data['job']->total_cost ?: 0) ?: null,
            );
        }

        // For materials linked to a POS item, show live stock at the main
        // location instead of the standalone print_materials counter.
        $materials     = $this->printMaterial->findAll();
        $itemQuantity  = model(Item_quantity::class);
        $mainLocation  = model(Stock_location::class)->get_main_location_id();
        foreach ($materials as $material) {
            $material->available = $material->item_id
                ? (float)$itemQuantity->get_item_quantity((int)$material->item_id, $mainLocation)->quantity
                : (float)$material->current_stock;
        }
        $data['materials'] = $materials;

        echo view('printjobs/view', $data);
    }

    /**
     * POST /printjobs/upload_file/(:num) — attach an artwork/reference file
     * (an image or PDF forwarded from WhatsApp, a customer's own file,
     * whatever the team needs on hand) to a job.
     */
    public function postUploadFile(int $jobId = 0): ResponseInterface
    {
        if (! $this->printJob->find($jobId)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Print job not found.']);
        }

        $file = $this->request->getFile('attachment');
        if (! $file) {
            return $this->response->setJSON(['success' => false, 'message' => 'Choose a file first.']);
        }

        $allowedExt = array_merge(Printjobfile::IMAGE_TYPES, Printjobfile::DOC_TYPES);
        $validation_rule = [
            'attachment' => [
                'label' => 'Attachment',
                'rules' => [
                    'uploaded[attachment]',
                    'max_size[attachment,10240]', // 10MB — WhatsApp forwards and scanned PDFs can be sizable
                    'mime_in[attachment,image/png,image/jpeg,image/gif,image/webp,application/pdf]',
                    'ext_in[attachment,' . implode(',', $allowedExt) . ']',
                ],
            ],
        ];

        if (! $this->validate($validation_rule)) {
            return $this->response->setJSON(['success' => false, 'message' => $this->validator->getError('attachment')]);
        }

        $uploadDir = FCPATH . 'uploads/print_jobs/';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $originalName = $file->getClientName();
        $ext          = $file->guessExtension();
        $storedName   = $jobId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $fileSize     = $file->getSize();

        $file->move($uploadDir, $storedName, true);

        $this->printJobFile->insert([
            'print_job_id' => $jobId,
            'file_name'    => $originalName,
            'file_path'    => 'uploads/print_jobs/' . $storedName,
            'file_type'    => $ext,
            'file_size'    => $fileSize,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['success' => true, 'message' => 'File attached.']);
    }

    /**
     * GET /printjobs/download_file/(:num) — serves an attachment for
     * viewing/downloading, named after the file the team actually recognizes
     * rather than the randomized on-disk filename.
     */
    public function getDownloadFile(int $fileId = 0): ResponseInterface
    {
        $file = $this->printJobFile->find($fileId);
        if (! $file) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $fullPath = FCPATH . $file->file_path;
        if (! is_file($fullPath)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($fullPath, null)->setFileName($file->file_name);
    }

    /**
     * POST /printjobs/delete_file/(:num)
     */
    public function postDeleteFile(int $fileId = 0): ResponseInterface
    {
        $file = $this->printJobFile->find($fileId);
        if (! $file) {
            return $this->response->setJSON(['success' => false, 'message' => 'File not found.']);
        }

        $fullPath = FCPATH . $file->file_path;
        if (is_file($fullPath)) {
            unlink($fullPath);
        }

        $this->printJobFile->delete($fileId);

        return $this->response->setJSON(['success' => true]);
    }

    /**
     * GET /printjobs/new — Create new print job
     */
    public function new()
    {
        $data['title']         = lang('Printjobs.new_job');
        $data['job']           = null;
        $data['employees']     = $this->employee->get_all()->getResult();
        $data['customers']     = $this->customer->get_all()->getResult();
        $data['pricing']       = $this->printPricing->getActive();
        $data['processes']     = $this->printProcess->getAllWithChannels();
        $data['substrates']    = $this->printMaterial->getSubstrates();
        $data['machines']      = $this->machine->getAvailable();
        $data['employee_info'] = $this->employee->get_logged_in_employee_info();

        echo view('printjobs/form', $data);
    }

    /**
     * GET /printjobs/edit/(:num) — Edit existing print job
     */
    public function edit(int $id = 0)
    {
        $data['job'] = $this->printJob->find($id);
        if (! $data['job']) {
            return redirect()->to('/printjobs');
        }

        $data['title']         = lang('Printjobs.edit_job') . ' #' . $data['job']->job_number;
        $data['employees']     = $this->employee->get_all()->getResult();
        $data['customers']     = $this->customer->get_all()->getResult();
        $data['pricing']       = $this->printPricing->getActive();
        $data['processes']     = $this->printProcess->getAllWithChannels();
        $data['substrates']    = $this->printMaterial->getSubstrates();
        $data['machines']      = $this->machine->getAvailable();
        $data['employee_info'] = $this->employee->get_logged_in_employee_info();

        echo view('printjobs/form', $data);
    }

    /**
     * POST /printjobs/save — Save new or update existing print job
     */
    public function save()
    {
        $id = (int) $this->request->getPost('id');

        $processId   = (int) ($this->request->getPost('process_id') ?: 0) ?: null;
        $widthMm     = (float) ($this->request->getPost('width_mm') ?: 0) ?: null;
        $heightMm    = (float) ($this->request->getPost('height_mm') ?: 0) ?: null;
        $quantity    = (int) ($this->request->getPost('quantity') ?: 1);
        $coveragePct = $this->request->getPost('coverage_pct');
        $coveragePct = ($coveragePct !== null && $coveragePct !== '') ? (float) $coveragePct : null;
        $sheets      = (int) ($this->request->getPost('sheets') ?: 0) ?: null;
        $substrateId = (int) ($this->request->getPost('substrate_material_id') ?: 0) ?: null;
        $totalCost   = (float) ($this->request->getPost('total_cost') ?: 0);

        $input = [
            'job_type'    => $this->request->getPost('job_type'),
            'process_id'  => $processId,
            'quantity'    => $quantity,
            'width_mm'    => $widthMm !== null ? (int) $widthMm : null,
            'height_mm'   => $heightMm !== null ? (int) $heightMm : null,
            'coverage_pct' => $coveragePct,
            'sheets'      => $sheets,
            'material_id' => $substrateId,
            'material'    => $this->request->getPost('material'),
            'finish'      => $this->request->getPost('finish') ?: 'matte',
            'colors'      => $this->request->getPost('colors') ?: 'full_color',
            'machine'     => $this->request->getPost('machine') ?: null,
            'unit_cost'   => (float) ($this->request->getPost('unit_cost') ?: 0),
            'total_cost'  => $totalCost,
            'notes'       => $this->request->getPost('notes'),
            'customer_id'     => (int) ($this->request->getPost('customer_id') ?: 0) ?: null,
            'client_phone'    => $this->request->getPost('client_phone') ?: null,
            'assigned_to'     => (int) ($this->request->getPost('assigned_to') ?: 0) ?: null,
            'served_by'   => $this->employee->get_logged_in_employee_info()->person_id,
            'ticket_ref'  => $this->request->getPost('ticket_ref'),
            'deadline'    => $this->request->getPost('deadline') ?: null,
        ];

        // Full cost estimate (ink + substrate + overhead + spoilage) and the
        // suggested price. The customer price is area x sell_rate (decision:
        // rate is the price); the cost figures drive margin only.
        if ($processId !== null) {
            $estimate = (new Print_ink_estimator())->estimate(
                $processId, $widthMm, $heightMm, (float) $quantity, $coveragePct, $sheets, $substrateId,
                $totalCost > 0 ? $totalCost : null
            );
            if ($estimate !== null) {
                $input['area_m2']       = $estimate['area_m2'];
                $input['coverage_pct']  = $estimate['coverage_pct'];
                $input['est_ink_ml']    = $estimate['total_ml'];
                $input['est_ink_cost']  = $estimate['total_ink_cost'];
                $input['substrate_m2']  = $estimate['substrate']['m2'] ?? null;
                $input['substrate_cost'] = $estimate['substrate_cost'];
                $input['overhead_cost'] = $estimate['overhead_cost'];
                $input['spoilage_cost'] = $estimate['spoilage_cost'];
                $input['est_total_cost'] = $estimate['total_cost'];

                // Fill the price from the estimate only when the operator left it
                // blank and the process actually has a sell rate configured.
                if ($totalCost <= 0 && $estimate['suggested_price'] > 0) {
                    $input['total_cost'] = $estimate['suggested_price'];
                    $input['unit_cost']  = $quantity > 0 ? round($estimate['suggested_price'] / $quantity, 2) : $estimate['suggested_price'];
                }
            }
        }

        $sync = new BeamlinkSync();

        if ($id > 0) {
            $this->printJob->update($id, $input);
            $job = $this->printJob->find($id);
            $sync->pushPrintJob('print_job_updated', (array) $job);

            return redirect()->to('/printjobs/view/' . $id)->with('message', lang('Printjobs.saved'));
        }

        $input['job_number'] = $this->printJob->generateJobNumber();
        $input['status']     = 'pending';
        $newId               = $this->printJob->insert($input, true);

        $job = $this->printJob->find($newId);
        $sync->pushPrintJob('print_job_created', (array) $job);

        return redirect()->to('/printjobs/view/' . $newId)->with('message', lang('Printjobs.saved'));
    }

    /**
     * POST /printjobs/update_status/(:num) — Change job status (Ajax)
     */
    public function updateStatus(int $id = 0)
    {
        $newStatus = $this->request->getPost('status');
        if (! in_array($newStatus, Printjob::STATUSES, true)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid status']);
        }

        $this->printJob->updateStatus($id, $newStatus);

        $job  = $this->printJob->find($id);
        $sync = new BeamlinkSync();

        // First time a job starts printing, draw the estimated ink from stock
        // and book it to the Print Dept expense.
        if ($newStatus === 'printing' && $job && (int) ($job->ink_posted ?? 0) === 0 && $job->process_id) {
            $posted = $this->postProcessInkConsumption($job);
            if ($posted !== null) {
                $job = $this->printJob->find($id);
            }
        }

        // Push status update to ERP (WhatsApp sync)
        $sync->pushPrintJob('print_job_status_changed', [
            'job_type'     => $job->job_type,
            'quantity'     => $job->quantity,
            'status'       => $newStatus,
            'notes'        => $job->notes,
            'external_ref' => $job->external_ref ?? $job->job_number,
            'job_number'   => $job->job_number,
        ]);

        // Send WhatsApp to client if phone number available
        $clientPhone = $job->client_phone;
        if (empty($clientPhone) && ! empty($job->customer_id)) {
            $customer = $this->customer->get_info((int) $job->customer_id);
            if ($customer && ! empty($customer->phone_number)) {
                $clientPhone = $customer->phone_number;
            }
        }

        if (! empty($clientPhone)) {
            $statusLabels = [
                'pending' => 'Pending', 'designing' => 'Designing', 'proof_sent' => 'Proof Sent',
                'approved' => 'Approved', 'printing' => 'Printing', 'finishing' => 'Finishing',
                'qc' => 'Quality Check', 'ready' => 'Ready for Collection', 'collected' => 'Collected',
            ];
            $label = $statusLabels[$newStatus] ?? ucfirst($newStatus);
            $msg   = "Hello! Your print job {$job->job_number} ({$job->job_type}) "
                   . "has been updated to: {$label}.";
            if ($newStatus === 'ready') {
                $msg .= " Your order is ready for collection at BeamLink. Please come collect at your convenience.";
            }

            $smsLib = new Sms_lib();
            $smsLib->sendSMS($clientPhone, $msg);
        }

        // Create notification for job owner / assigned employee
        $notification = model(\App\Models\Notification::class);
        $statusLabels2 = [
            'pending' => 'Pending', 'designing' => 'Designing', 'proof_sent' => 'Proof Sent',
            'approved' => 'Approved', 'printing' => 'Printing', 'finishing' => 'Finishing',
            'qc' => 'QC', 'ready' => 'Ready', 'collected' => 'Collected', 'cancelled' => 'Cancelled',
        ];
        $notifTitle = 'Status Update: ' . $job->job_number;
        $notifMsg   = 'Status changed to ' . ($statusLabels2[$newStatus] ?? $newStatus);
        $notifLink  = '/printjobs/view/' . $id;

        // Notify assigned employee if different from current user
        if ($job->assigned_to) {
            $currentUser = $this->employee->get_logged_in_employee_info();
            if ($currentUser && (int) $currentUser->person_id !== (int) $job->assigned_to) {
                $notification->broadcastToPerson((int) $job->assigned_to, $notifTitle, $notifMsg, 'job_status', $notifLink);
            }
        }

        return $this->response->setJSON(['success' => true]);
    }

    /**
     * POST /printjobs/assign/(:num) — Assign to employee
     */
    public function assign(int $id = 0)
    {
        $employeeId = (int) $this->request->getPost('employee_id');
        $this->printJob->assign($id, $employeeId ?: 0);

        if ($employeeId > 0) {
            $job = $this->printJob->find($id);
            $notification = model(\App\Models\Notification::class);
            $employee     = $this->employee->get_info($employeeId);
            $assignedBy   = $this->employee->get_logged_in_employee_info();
            $assignerName = $assignedBy ? $assignedBy->first_name . ' ' . $assignedBy->last_name : 'System';

            $notification->broadcastToPerson(
                $employeeId,
                'Job Assigned: ' . ($job->job_number ?? '#'.$id),
                'Assigned by ' . $assignerName . ' — ' . ucfirst($job->job_type ?? 'job'),
                'job_assigned',
                '/printjobs/view/' . $id
            );
        }

        return redirect()->to('/printjobs/view/' . $id)->with('message', lang('Printjobs.assigned'));
    }

    /**
     * POST /printjobs/add_material_usage/(:num) — Log material used for a job.
     *
     * If the material is linked to a POS item, the quantity is drawn from the
     * main stock location (item_quantities + inventory movement, exactly like a
     * sale) and an internal expense is booked against the "Print Dept -
     * Materials" category at the material's unit cost. Print-only materials
     * (no linked item) keep decrementing the standalone print_materials counter.
     */
    public function addMaterialUsage(int $jobId = 0)
    {
        $job = $this->printJob->find($jobId);
        if (! $job) {
            return redirect()->to('/printjobs')->with('error', lang('Printjobs.material_invalid'));
        }

        $materialId   = (int) $this->request->getPost('material_id');
        $quantity     = (float) $this->request->getPost('quantity_used');
        $allowNegative = (bool) $this->request->getPost('allow_negative');

        if ($materialId <= 0 || $quantity <= 0) {
            return redirect()->to('/printjobs/view/' . $jobId)->with('error', lang('Printjobs.material_invalid'));
        }

        $material = $this->printMaterial->find($materialId);
        if (! $material) {
            return redirect()->to('/printjobs/view/' . $jobId)->with('error', lang('Printjobs.material_invalid'));
        }

        $employeeId = (int) $this->employee->get_logged_in_employee_info()->person_id;
        $itemId     = $material->item_id ? (int) $material->item_id : null;
        $unitCost   = (float) $material->unit_cost;
        $totalCost  = round($unitCost * $quantity, 2);
        $jobRef     = $job->job_number ?: ('#' . $jobId);

        $db = \Config\Database::connect();
        $db->transStart();

        $locationId = null;
        $expenseId  = null;

        if ($itemId !== null) {
            $itemQuantity = model(Item_quantity::class);
            $locationId   = model(Stock_location::class)->get_main_location_id();
            $onHand       = (float) $itemQuantity->get_item_quantity($itemId, $locationId)->quantity;

            if ($onHand - $quantity < 0 && ! $allowNegative) {
                $db->transComplete();

                return redirect()->to('/printjobs/view/' . $jobId)->with(
                    'error',
                    sprintf(lang('Printjobs.insufficient_stock'), esc($material->name), number_format($onHand, 2), esc($material->unit))
                );
            }

            // Mirror Sale::save_value: write the running quantity directly so
            // decimal stock (ml, metres) isn't truncated to an int.
            $itemQuantity->save_value(
                ['quantity' => $onHand - $quantity, 'item_id' => $itemId, 'location_id' => $locationId],
                $itemId,
                $locationId
            );

            model(Inventory::class)->insert([
                'trans_date'      => date('Y-m-d H:i:s'),
                'trans_items'     => $itemId,
                'trans_user'      => $employeeId,
                'trans_location'  => $locationId,
                'trans_comment'   => 'Print job ' . $jobRef,
                'trans_inventory' => -$quantity,
            ], false);

            // Internal transfer: reallocate the already-paid stock cost to the
            // print department. Flagged category keeps it out of P&L totals.
            if ($totalCost > 0) {
                $expenseData = [
                    'date'                => date('Y-m-d H:i:s'),
                    'amount'              => $totalCost,
                    'tax_amount'          => 0,
                    'payment_type'        => lang('Printjobs.expense_payment_type'),
                    'expense_category_id' => model(Expense_category::class)->get_print_materials_category_id(),
                    'description'         => sprintf(
                        '%s — %s %s @ %s (job %s)',
                        $material->name,
                        rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.'),
                        $material->unit,
                        to_currency($unitCost),
                        $jobRef
                    ),
                    'employee_id'         => $employeeId,
                    'supplier_id'         => null,
                    'deleted'             => 0,
                ];
                model(Expense::class)->save_value($expenseData);
                $expenseId = $expenseData['expense_id'] ?? null;
            }
        } else {
            // Print-only consumable — legacy standalone counter.
            $this->printMaterial->deductStock($materialId, $quantity);
        }

        model(Printmaterialusage::class)->insert([
            'print_job_id'  => $jobId,
            'material_id'   => $materialId,
            'quantity_used' => $quantity,
            'location_id'   => $locationId,
            'unit_cost'     => $unitCost,
            'total_cost'    => $totalCost,
            'expense_id'    => $expenseId,
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->to('/printjobs/view/' . $jobId)->with('error', lang('Printjobs.material_invalid'));
        }

        return redirect()->to('/printjobs/view/' . $jobId)->with('message', lang('Printjobs.material_added'));
    }

    /**
     * POST /printjobs/delete/(:num)
     */
    public function delete(int $id = 0)
    {
        // Reverse any material consumption logged against this job: return the
        // stock and void the internal expense so deleting a job doesn't leave
        // stock and books out of balance.
        $job = $this->printJob->find($id);
        if ($job) {
            $usageModel   = model(Printmaterialusage::class);
            $itemQuantity = model(Item_quantity::class);
            $expenseModel = model(Expense::class);
            $employeeId   = (int) $this->employee->get_logged_in_employee_info()->person_id;
            $jobRef       = $job->job_number ?: ('#' . $id);

            foreach ($usageModel->where('print_job_id', $id)->findAll() as $usage) {
                $material = $this->printMaterial->find((int) $usage->material_id);
                $itemId   = $material && $material->item_id ? (int) $material->item_id : null;

                if ($itemId !== null && $usage->location_id !== null) {
                    $onHand = (float) $itemQuantity->get_item_quantity($itemId, (int) $usage->location_id)->quantity;
                    $itemQuantity->save_value(
                        ['quantity' => $onHand + (float) $usage->quantity_used, 'item_id' => $itemId, 'location_id' => (int) $usage->location_id],
                        $itemId,
                        (int) $usage->location_id
                    );
                    model(Inventory::class)->insert([
                        'trans_date'      => date('Y-m-d H:i:s'),
                        'trans_items'     => $itemId,
                        'trans_user'      => $employeeId,
                        'trans_location'  => (int) $usage->location_id,
                        'trans_comment'   => 'Print job ' . $jobRef . ' deleted',
                        'trans_inventory' => (float) $usage->quantity_used,
                    ], false);
                } elseif ($material) {
                    $this->printMaterial->update((int) $usage->material_id, [
                        'current_stock' => (float) $material->current_stock + (float) $usage->quantity_used,
                    ]);
                }

                if ($usage->expense_id) {
                    $expenseModel->delete_list([(int) $usage->expense_id]);
                }
            }
        }

        $this->printJob->delete($id);

        return redirect()->to('/printjobs')->with('message', 'Print job deleted.');
    }

    // ═══════════════════════════════════════════
    // MATERIALS INVENTORY
    // ═══════════════════════════════════════════

    /**
     * GET /printjobs/materials — Materials inventory
     */
    public function materials()
    {
        $data['title']      = lang('Printjobs.materials');
        $data['grouped']    = $this->printMaterial->getGroupedByCategory();
        $data['low_stock']  = $this->printMaterial->getLowStock();
        $data['categories'] = Printmaterial::CATEGORIES;
        $data['units']      = Printmaterial::UNITS;

        // POS items a material can be linked to, so consumption draws from the
        // same stock we sell.
        $data['pos_items'] = model(Item::class)->get_all(NEW_ENTRY)->getResult();

        echo view('printjobs/materials', $data);
    }

    /**
     * POST /printjobs/save_material — Create or update a material
     */
    public function saveMaterial()
    {
        $id = (int) $this->request->getPost('id');

        $input = [
            'name'          => $this->request->getPost('name'),
            'sku'           => $this->request->getPost('sku'),
            'category'      => $this->request->getPost('category') ?: 'other',
            'unit'          => $this->request->getPost('unit') ?: 'sheet',
            'current_stock' => (float) ($this->request->getPost('current_stock') ?: 0),
            'reorder_level' => (float) ($this->request->getPost('reorder_level') ?: 10),
            'unit_cost'     => (float) ($this->request->getPost('unit_cost') ?: 0),
            'supplier'      => $this->request->getPost('supplier'),
            'item_id'       => (int) ($this->request->getPost('item_id') ?: 0) ?: null,
        ];

        if ($id > 0) {
            $this->printMaterial->update($id, $input);
        } else {
            $this->printMaterial->insert($input);
        }

        return redirect()->to('/printjobs/materials')->with('message', lang('Printjobs.material_saved'));
    }

    /**
     * POST /printjobs/delete_material/(:num)
     */
    public function deleteMaterial(int $id = 0)
    {
        $this->printMaterial->delete($id);

        return redirect()->to('/printjobs/materials')->with('message', 'Material deleted.');
    }

    /**
     * POST /printjobs/receive_material/(:num) — record a delivery/stock
     * intake without having to re-enter the whole material record.
     */
    public function receiveMaterial(int $id = 0)
    {
        $quantity = (float) ($this->request->getPost('quantity_received') ?: 0);
        $unitCost = $this->request->getPost('unit_cost');
        $supplier = $this->request->getPost('supplier');

        if ($quantity <= 0) {
            return redirect()->to('/printjobs/materials')->with('error', 'Enter a quantity received greater than zero.');
        }

        $this->printMaterial->receiveStock(
            $id,
            $quantity,
            $unitCost !== null && $unitCost !== '' ? (float) $unitCost : null,
            $supplier ?: null,
        );

        return redirect()->to('/printjobs/materials')->with('message', 'Stock received.');
    }

    // ═══════════════════════════════════════════
    // PRICING
    // ═══════════════════════════════════════════

    /**
     * GET /printjobs/pricing — Price list management
     */
    public function pricing()
    {
        $data['title']     = lang('Printjobs.pricing');
        $data['grouped']   = $this->printPricing->getGroupedByType();
        $data['job_types'] = Printjob::JOB_TYPES;

        echo view('printjobs/pricing', $data);
    }

    /**
     * POST /printjobs/save_pricing — Create or update pricing entry
     */
    public function savePricing()
    {
        $id = (int) $this->request->getPost('id');

        $input = [
            'name'             => $this->request->getPost('name'),
            'job_type'         => $this->request->getPost('job_type'),
            'material'         => $this->request->getPost('material'),
            'size_description' => $this->request->getPost('size_description'),
            'min_quantity'     => (int) ($this->request->getPost('min_quantity') ?: 1),
            'price_per_unit'   => (float) ($this->request->getPost('price_per_unit') ?: 0),
            'setup_fee'        => (float) ($this->request->getPost('setup_fee') ?: 0),
            'currency'         => $this->request->getPost('currency') ?: 'USD',
            'is_active'        => 1,
        ];

        if ($id > 0) {
            $this->printPricing->update($id, $input);
        } else {
            $this->printPricing->insert($input);
        }

        return redirect()->to('/printjobs/pricing')->with('message', lang('Printjobs.pricing_saved'));
    }

    /**
     * POST /printjobs/delete_pricing/(:num)
     */
    public function deletePricing(int $id = 0)
    {
        $this->printPricing->delete($id);

        return redirect()->to('/printjobs/pricing')->with('message', 'Pricing entry deleted.');
    }

    /**
     * GET /printjobs/sync — Pull all jobs from Vercel ERP into local DB
     */
    public function sync()
    {
        $counts = $this->printJob->pullAndUpsertFromErp(new BeamlinkSync());

        if ($counts === null) {
            return redirect()->to('/printjobs')->with('error', 'Sync failed: could not reach BeamLink ERP');
        }

        return redirect()->to('/printjobs')->with(
            'message',
            "Sync complete: {$counts['imported']} imported, {$counts['updated']} updated, {$counts['skipped']} unchanged",
        );
    }

    // ═══════════════════════════════════════════
    // INK ESTIMATE / CONSUMPTION
    // ═══════════════════════════════════════════

    /**
     * POST /printjobs/estimate — live ink + suggested-price estimate for the
     * job form (Ajax). Body: process_id, width_mm, height_mm, quantity,
     * coverage_pct, sheets.
     */
    public function estimate()
    {
        $processId = (int) $this->request->getPost('process_id');
        if ($processId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'No process selected']);
        }

        $coverage = $this->request->getPost('coverage_pct');
        $charged  = $this->request->getPost('charged_price');

        $result = (new Print_ink_estimator())->estimate(
            $processId,
            (float) ($this->request->getPost('width_mm') ?: 0) ?: null,
            (float) ($this->request->getPost('height_mm') ?: 0) ?: null,
            (float) ($this->request->getPost('quantity') ?: 1),
            ($coverage !== null && $coverage !== '') ? (float) $coverage : null,
            (int) ($this->request->getPost('sheets') ?: 0) ?: null,
            (int) ($this->request->getPost('substrate_material_id') ?: 0) ?: null,
            ($charged !== null && $charged !== '') ? (float) $charged : null,
        );

        if ($result === null) {
            return $this->response->setJSON(['success' => false, 'message' => 'Unknown process']);
        }

        return $this->response->setJSON(['success' => true, 'estimate' => $result]);
    }

    /**
     * Draw a job's estimated ink from stock (per channel, at the main location)
     * and book one internal expense for the lot. Idempotent via print_jobs.ink_posted.
     *
     * @return array{total_ml:float,total_cost:float}|null  null when there is nothing to post
     */
    private function postProcessInkConsumption(object $job): ?array
    {
        $estimate = (new Print_ink_estimator())->estimate(
            (int) $job->process_id,
            $job->width_mm !== null ? (float) $job->width_mm : null,
            $job->height_mm !== null ? (float) $job->height_mm : null,
            (float) ($job->quantity ?: 1),
            $job->coverage_pct !== null ? (float) $job->coverage_pct : null,
            $job->sheets !== null ? (int) $job->sheets : null,
            ! empty($job->material_id) ? (int) $job->material_id : null,
            (float) ($job->total_cost ?: 0) ?: null,
        );

        // Physical consumables to draw from stock: each ink channel + the
        // substrate. Overhead and spoilage are costing-only and not posted.
        $lines = [];
        if ($estimate !== null) {
            foreach ($estimate['channels'] as $c) {
                if ($c['material_id'] !== null && $c['ml'] > 0) {
                    $lines[] = ['material_id' => $c['material_id'], 'qty' => (float) $c['ml'],
                        'unit_cost' => (float) $c['unit_cost'], 'cost' => (float) $c['cost'], 'channel' => $c['channel']];
                }
            }
            if (! empty($estimate['substrate']) && $estimate['substrate']['material_id'] && $estimate['substrate']['m2'] > 0) {
                $s = $estimate['substrate'];
                $lines[] = ['material_id' => (int) $s['material_id'], 'qty' => (float) $s['m2'],
                    'unit_cost' => (float) $s['unit_cost'], 'cost' => (float) $s['cost'], 'channel' => 'substrate'];
            }
        }

        if ($lines === []) {
            $this->printJob->update((int) $job->id, ['ink_posted' => 1]);

            return null;
        }

        $employeeId = (int) $this->employee->get_logged_in_employee_info()->person_id;
        $jobRef     = $job->job_number ?: ('#' . $job->id);
        $mainLoc    = model(Stock_location::class)->get_main_location_id();
        $totalCost  = array_sum(array_column($lines, 'cost'));

        $db = \Config\Database::connect();
        $db->transStart();

        $expenseId = null;
        if ($totalCost > 0) {
            $expenseData = [
                'date'                => date('Y-m-d H:i:s'),
                'amount'              => round($totalCost, 2),
                'tax_amount'          => 0,
                'payment_type'        => lang('Printjobs.expense_payment_type'),
                'expense_category_id' => model(Expense_category::class)->get_print_materials_category_id(),
                'description'         => sprintf(
                    '%s ink + media — %s m² @ %s%% coverage (job %s)',
                    $estimate['process_name'],
                    rtrim(rtrim(number_format((float) $estimate['area_m2'], 4, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format((float) $estimate['coverage_pct'], 2, '.', ''), '0'), '.'),
                    $jobRef
                ),
                'employee_id'         => $employeeId,
                'supplier_id'         => null,
                'deleted'             => 0,
            ];
            model(Expense::class)->save_value($expenseData);
            $expenseId = $expenseData['expense_id'] ?? null;
        }

        $itemQuantity = model(Item_quantity::class);
        $usageModel   = model(Printmaterialusage::class);

        foreach ($lines as $ln) {
            $material = $this->printMaterial->find((int) $ln['material_id']);
            if (! $material) {
                continue;
            }
            $itemId = $material->item_id ? (int) $material->item_id : null;
            $qty    = (float) $ln['qty'];

            if ($itemId !== null) {
                $onHand = (float) $itemQuantity->get_item_quantity($itemId, $mainLoc)->quantity;
                $itemQuantity->save_value(
                    ['quantity' => $onHand - $qty, 'item_id' => $itemId, 'location_id' => $mainLoc],
                    $itemId,
                    $mainLoc
                );
                model(Inventory::class)->insert([
                    'trans_date'      => date('Y-m-d H:i:s'),
                    'trans_items'     => $itemId,
                    'trans_user'      => $employeeId,
                    'trans_location'  => $mainLoc,
                    'trans_comment'   => 'Print job ' . $jobRef . ' — ' . $ln['channel'],
                    'trans_inventory' => -$qty,
                ], false);
            } else {
                $this->printMaterial->deductStock((int) $ln['material_id'], $qty);
            }

            $usageModel->insert([
                'print_job_id'  => (int) $job->id,
                'material_id'   => (int) $ln['material_id'],
                'quantity_used' => $qty,
                'location_id'   => $itemId !== null ? $mainLoc : null,
                'unit_cost'     => (float) $ln['unit_cost'],
                'total_cost'    => (float) $ln['cost'],
                'expense_id'    => $expenseId,
                'channel'       => $ln['channel'],
                'estimated_ml'  => $ln['channel'] === 'substrate' ? null : $qty,
                'source'        => 'process_estimate',
            ]);
        }

        $this->printJob->update((int) $job->id, [
            'ink_posted'     => 1,
            'est_ink_ml'     => $estimate['total_ml'],
            'est_ink_cost'   => $estimate['total_ink_cost'],
            'substrate_m2'   => $estimate['substrate']['m2'] ?? null,
            'substrate_cost' => $estimate['substrate_cost'],
            'overhead_cost'  => $estimate['overhead_cost'],
            'spoilage_cost'  => $estimate['spoilage_cost'],
            'est_total_cost' => $estimate['total_cost'],
        ]);

        $db->transComplete();

        return ['total_ml' => (float) $estimate['total_ml'], 'total_cost' => round($totalCost, 2)];
    }

    // ═══════════════════════════════════════════
    // PROCESS TYPES (DTF / UV / Xerox / Eco-solvent)
    // ═══════════════════════════════════════════

    /**
     * GET /printjobs/processes — process types + per-channel ink rates.
     */
    public function processes()
    {
        $data['title']    = lang('Printjobs.processes');
        $list             = $this->printProcess->orderBy('name', 'ASC')->findAll();
        $channelModel     = model(Print_process_channel::class);
        foreach ($list as $p) {
            $p->channels = $channelModel->getForProcess((int) $p->id);
        }
        $data['processes']     = $list;
        $data['pricing_bases'] = Print_process::PRICING_BASES;
        $data['channels']      = Print_process_channel::CHANNELS;
        $data['materials']     = $this->printMaterial->findAll();
        $data['shop_defaults'] = Print_process::effectiveEconomics(null);

        echo view('printjobs/processes', $data);
    }

    /**
     * GET /printjobs/settings — shop-wide print economics defaults.
     */
    public function settings()
    {
        $s = config(\Config\OSPOS::class)->settings;
        $data['title']    = lang('Printjobs.settings');
        $data['settings'] = [
            'print_overhead_per_sqm'  => $s['print_overhead_per_sqm'] ?? '0',
            'print_overhead_per_job'  => $s['print_overhead_per_job'] ?? '0',
            'print_overhead_pct'      => $s['print_overhead_pct'] ?? '0',
            'print_spoilage_pct'      => $s['print_spoilage_pct'] ?? '5',
            'print_target_margin_pct' => $s['print_target_margin_pct'] ?? '0',
        ];

        echo view('printjobs/settings', $data);
    }

    /**
     * POST /printjobs/save_settings
     */
    public function saveSettings()
    {
        $save = [];
        foreach ([
            'print_overhead_per_sqm', 'print_overhead_per_job', 'print_overhead_pct',
            'print_spoilage_pct', 'print_target_margin_pct',
        ] as $key) {
            $save[$key] = (string) (float) ($this->request->getPost($key) ?: 0);
        }

        model(\App\Models\Appconfig::class)->batch_save($save);

        return redirect()->to('/printjobs/settings')->with('message', lang('Printjobs.settings_saved'));
    }

    /**
     * POST /printjobs/save_process — create/update a process and its channels.
     * Channel rows come as parallel arrays: channel[], material_id[], ml_per_sqm[].
     */
    public function saveProcess()
    {
        $id = (int) $this->request->getPost('id');

        $name = trim((string) $this->request->getPost('name'));
        $code = trim((string) ($this->request->getPost('code') ?: $name));
        $code = strtolower(preg_replace('/[^a-z0-9]+/i', '', $code)) ?: ('proc' . time());

        $input = [
            'name'                 => $name,
            'code'                 => $code,
            'pricing_basis'        => array_key_exists($this->request->getPost('pricing_basis'), Print_process::PRICING_BASES)
                ? $this->request->getPost('pricing_basis') : 'per_sqm',
            'sell_rate'            => parse_decimals($this->request->getPost('sell_rate') ?? '0'),
            'min_charge'           => parse_decimals($this->request->getPost('min_charge') ?? '0'),
            'setup_fee'            => parse_decimals($this->request->getPost('setup_fee') ?? '0'),
            'waste_pct'            => (float) ($this->request->getPost('waste_pct') ?: 0),
            'default_coverage_pct' => (float) ($this->request->getPost('default_coverage_pct') ?: 70),
            'sheet_area_m2'        => ($v = $this->request->getPost('sheet_area_m2')) !== null && $v !== '' ? (float) $v : null,
            'currency'             => $this->request->getPost('currency') ?: 'USD',
            'is_active'            => $this->request->getPost('is_active') !== null ? 1 : 0,
            // Economic overrides — blank means "inherit the shop default".
            'overhead_per_sqm'    => ($v = $this->request->getPost('overhead_per_sqm')) !== null && $v !== '' ? (float) $v : null,
            'overhead_per_job'    => ($v = $this->request->getPost('overhead_per_job')) !== null && $v !== '' ? (float) $v : null,
            'overhead_pct'        => ($v = $this->request->getPost('overhead_pct')) !== null && $v !== '' ? (float) $v : null,
            'spoilage_pct'        => ($v = $this->request->getPost('spoilage_pct')) !== null && $v !== '' ? (float) $v : null,
            'target_margin_pct'   => ($v = $this->request->getPost('target_margin_pct')) !== null && $v !== '' ? (float) $v : null,
        ];

        if ($id > 0) {
            $this->printProcess->update($id, $input);
        } else {
            $id = (int) $this->printProcess->insert($input, true);
        }

        // Rebuild channel rows from scratch — simplest reconciliation.
        $channelModel = model(Print_process_channel::class);
        $channelModel->deleteForProcess($id);

        $chNames   = (array) $this->request->getPost('channel');
        $chMats    = (array) $this->request->getPost('material_id');
        $chRates   = (array) $this->request->getPost('ml_per_sqm');
        foreach ($chNames as $i => $chName) {
            $chName = trim((string) $chName);
            if ($chName === '') {
                continue;
            }
            $channelModel->insert([
                'process_id'  => $id,
                'channel'     => $chName,
                'material_id' => ! empty($chMats[$i]) ? (int) $chMats[$i] : null,
                'ml_per_sqm'  => (float) ($chRates[$i] ?? 0),
            ]);
        }

        return redirect()->to('/printjobs/processes')->with('message', lang('Printjobs.process_saved'));
    }

    /**
     * POST /printjobs/delete_process/(:num)
     */
    public function deleteProcess(int $id = 0)
    {
        model(Print_process_channel::class)->deleteForProcess($id);
        $this->printProcess->delete($id);

        return redirect()->to('/printjobs/processes')->with('message', lang('Printjobs.process_deleted'));
    }
}
