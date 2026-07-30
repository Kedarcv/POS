<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\BeamlinkSync;
use App\Libraries\Sms_lib;
use App\Models\Customer;
use App\Models\Machine;
use App\Models\Printjob;
use App\Models\Printmaterial;
use App\Models\Printmaterialusage;
use App\Models\Printpricing;

/**
 * Print Shop Module — full lifecycle matching BeamLink ERP.
 *
 * Dashboard (Kanban) → Job Detail (status pipeline) → Materials → Pricing
 */
class Printjobs extends Secure_Controller
{
    protected Printjob $printJob;
    protected Printmaterial $printMaterial;
    protected Printpricing $printPricing;
    protected Customer $customer;
    protected Machine $machine;

    public function __construct()
    {
        parent::__construct('printjobs');
        $this->printJob      = model(Printjob::class);
        $this->printMaterial = model(Printmaterial::class);
        $this->printPricing  = model(Printpricing::class);
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
     * GET /printjobs/view/(:num) — Job detail with status pipeline
     */
    public function view(int $id = 0)
    {
        $data['job'] = $this->printJob->find($id);
        if (! $data['job']) {
            return redirect()->to('/printjobs');
        }

        $data['title']     = lang('Printjobs.job_number') . ' ' . $data['job']->job_number;
        $data['employees'] = $this->employee->get_all()->getResult();
        $data['customer']  = ! empty($data['job']->customer_id)
            ? $this->customer->get_info((int) $data['job']->customer_id)
            : null;

        // Load material usage for this job
        $usageModel             = model(Printmaterialusage::class);
        $data['material_usage'] = $usageModel->getByJob($id);
        $data['materials']      = $this->printMaterial->findAll();

        echo view('printjobs/view', $data);
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

        $input = [
            'job_type'    => $this->request->getPost('job_type'),
            'quantity'    => (int) ($this->request->getPost('quantity') ?: 1),
            'width_mm'    => (int) ($this->request->getPost('width_mm') ?: 0) ?: null,
            'height_mm'   => (int) ($this->request->getPost('height_mm') ?: 0) ?: null,
            'material'    => $this->request->getPost('material'),
            'finish'      => $this->request->getPost('finish') ?: 'matte',
            'colors'      => $this->request->getPost('colors') ?: 'full_color',
            'machine'     => $this->request->getPost('machine') ?: null,
            'unit_cost'   => (float) ($this->request->getPost('unit_cost') ?: 0),
            'total_cost'  => (float) ($this->request->getPost('total_cost') ?: 0),
            'notes'       => $this->request->getPost('notes'),
            'customer_id'     => (int) ($this->request->getPost('customer_id') ?: 0) ?: null,
            'client_phone'    => $this->request->getPost('client_phone') ?: null,
            'assigned_to'     => (int) ($this->request->getPost('assigned_to') ?: 0) ?: null,
            'served_by'   => $this->employee->get_logged_in_employee_info()->person_id,
            'ticket_ref'  => $this->request->getPost('ticket_ref'),
            'deadline'    => $this->request->getPost('deadline') ?: null,
        ];

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
     * POST /printjobs/add_material_usage/(:num) — Log material used for a job
     */
    public function addMaterialUsage(int $jobId = 0)
    {
        $usageModel = model(Printmaterialusage::class);

        $materialId = (int) $this->request->getPost('material_id');
        $quantity   = (float) $this->request->getPost('quantity_used');

        if ($materialId > 0 && $quantity > 0) {
            $usageModel->insert([
                'print_job_id'  => $jobId,
                'material_id'   => $materialId,
                'quantity_used' => $quantity,
            ]);

            // Deduct from stock
            $this->printMaterial->deductStock($materialId, $quantity);
        }

        return redirect()->to('/printjobs/view/' . $jobId)->with('message', lang('Printjobs.material_added'));
    }

    /**
     * POST /printjobs/delete/(:num)
     */
    public function delete(int $id = 0)
    {
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
        $sync   = new BeamlinkSync();
        $result = $sync->pullPrintJobs();

        if (! $result['ok']) {
            return redirect()->to('/printjobs')->with('error', 'Sync failed: ' . ($result['error'] ?? 'Unknown'));
        }

        $jobs = $result['data']['jobs'] ?? ($result['data'] ?? []);
        if (! is_array($jobs)) {
            $jobs = [];
        }

        $imported = 0;
        $updated  = 0;
        $skipped  = 0;

        foreach ($jobs as $remote) {
            $externalRef = $remote['external_ref'] ?? ($remote['ticket_ref'] ?? '');
            $jobNumber   = $remote['job_number'] ?? '';

            if (! $externalRef && ! $jobNumber) {
                $skipped++;

                continue;
            }

            // Try to find existing job locally
            $existing = null;
            if ($externalRef) {
                $existing = $this->printJob->where('external_ref', $externalRef)->first();
            }
            if (! $existing && $jobNumber) {
                $existing = $this->printJob->where('job_number', $jobNumber)->first();
            }

            if ($existing) {
                // Update if remote is newer
                $remoteStatus = $remote['status'] ?? '';
                if ($remoteStatus && $remoteStatus !== $existing->status) {
                    $this->printJob->update($existing->id, [
                        'status'     => $remoteStatus,
                        'total_cost' => $remote['total_cost'] ?? $existing->total_cost,
                        'notes'      => $remote['notes'] ?? $existing->notes,
                    ]);
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                // Create new job locally
                $this->printJob->insert([
                    'job_number'      => $jobNumber ?: $this->printJob->generateJobNumber(),
                    'customer_id'     => $remote['customer_id'] ?? null,
                    'client_phone'    => $remote['client_phone'] ?? null,
                    'ticket_ref'      => $remote['ticket_ref'] ?? '',
                    'job_type'        => $remote['job_type'] ?? 'other',
                    'quantity'        => (int) ($remote['quantity'] ?? 1),
                    'width_mm'        => $remote['width_mm'] ?? null,
                    'height_mm'       => $remote['height_mm'] ?? null,
                    'material'        => $remote['material'] ?? null,
                    'machine'         => $remote['machine'] ?? null,
                    'unit_cost'       => (float) ($remote['unit_cost'] ?? 0),
                    'total_cost'      => (float) ($remote['total_cost'] ?? 0),
                    'notes'           => $remote['notes'] ?? '',
                    'status'          => $remote['status'] ?? 'pending',
                    'source'          => $remote['source'] ?? 'whatsapp',
                    'external_ref'    => $externalRef,
                    'whatsapp_images' => $remote['whatsapp_images'] ?? null,
                ]);
                $imported++;
            }
        }

        return redirect()->to('/printjobs')->with('message', "Sync complete: {$imported} imported, {$updated} updated, {$skipped} unchanged");
    }
}
