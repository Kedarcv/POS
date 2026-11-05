<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\BeamlinkSync;
use CodeIgniter\Model;

class Printjob extends Model
{
    public const STATUSES = [
        'pending', 'designing', 'proof_sent', 'approved',
        'printing', 'finishing', 'qc', 'ready', 'collected',
    ];
    public const JOB_TYPES = [
        'banner', 'brochure', 'business_card', 'branded_merch',
        'large_format', 'sticker', 'poster', 'flyer', 'booklet', 'other',
    ];
    public const FINISHES    = ['matte', 'gloss', 'satin', 'laminate'];
    public const COLOR_MODES = ['full_color', 'bw', 'spot_color'];
    public const MACHINES    = [
        'large_format_printer' => 'Large Format Printer',
        'digital_press'        => 'Digital Press',
        'offset_press'         => 'Offset Press',
        'laser_cutter'         => 'Laser Cutter',
        'vinyl_cutter'         => 'Vinyl Cutter',
        'sublimation_printer'  => 'Sublimation Printer',
        'screen_printing'      => 'Screen Printing',
        'binding_machine'      => 'Binding Machine',
        'lamination_machine'   => 'Lamination Machine',
        'other'                => 'Other',
    ];

    protected $table            = 'print_jobs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'job_number', 'customer_id', 'client_phone', 'ticket_ref', 'job_type', 'quantity',
        'width_mm', 'height_mm', 'material', 'material_id', 'finish', 'colors',
        'design_file_url', 'proof_url', 'proof_approved', 'status',
        'assigned_to', 'served_by', 'unit_cost', 'total_cost', 'notes',
        'source', 'external_ref', 'completed_at', 'machine', 'deadline',
        'whatsapp_images',
        'process_id', 'area_m2', 'coverage_pct', 'sheets', 'est_ink_ml', 'est_ink_cost', 'ink_posted',
        'substrate_m2', 'substrate_cost', 'overhead_cost', 'spoilage_cost', 'est_total_cost',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function generateJobNumber(): string
    {
        $prefix   = 'PRINT-' . date('Y');
        $existing = $this->where('job_number LIKE', $prefix . '-%')
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()
            ->getRow();

        $seq = $existing
            ? (int) ltrim(substr((string) $existing->job_number, -3), '0') + 1
            : 1;

        return $prefix . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    public function getByStatus(string $status): array
    {
        return $this->where('status', $status)
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }

    public function getStatusCounts(): array
    {
        $builder = $this->builder();
        $rows    = $builder->select('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()->getResult();

        $counts = array_fill_keys(self::STATUSES, 0);

        foreach ($rows as $row) {
            $counts[$row->status] = (int) $row->count;
        }

        return $counts;
    }

    public function getKanbanData(): array
    {
        $jobs   = $this->orderBy('created_at', 'ASC')->findAll();
        $kanban = array_fill_keys(self::STATUSES, []);

        foreach ($jobs as $job) {
            $kanban[$job->status][] = $job;
        }

        return $kanban;
    }

    public function getActiveJobs(): array
    {
        return $this->where('status !=', 'collected')
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    public function assign(int $printJobId, int $employeeId): bool
    {
        return $this->update($printJobId, ['assigned_to' => $employeeId]);
    }

    /**
     * The queue a printer operator sees: jobs assigned to them that aren't
     * finished yet, soonest deadline first (undated jobs sort last).
     */
    public function getAssignedToEmployee(int $employeeId, bool $activeOnly = true): array
    {
        $builder = $this->where('assigned_to', $employeeId);

        if ($activeOnly) {
            $builder = $builder->where('status !=', 'collected');
        }

        return $builder
            ->orderBy('deadline IS NULL', 'ASC', false)
            ->orderBy('deadline', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }

    public function getAssignedCount(int $employeeId): int
    {
        return $this->where('assigned_to', $employeeId)
            ->where('status !=', 'collected')
            ->countAllResults();
    }

    public function updateStatus(int $id, string $newStatus): bool
    {
        $update = ['status' => $newStatus];
        if (in_array($newStatus, ['ready', 'collected'], true)) {
            $update['completed_at'] = date('Y-m-d H:i:s');
        }
        if ($newStatus === 'approved') {
            $update['proof_approved'] = 1;
        }

        return $this->update($id, $update);
    }

    /**
     * Insert or update a local job from a GET /api/pos/print-jobs record.
     * The ERP identifies jobs by its own `id` (uuid) — there is no
     * `external_ref`/`job_number` in that response, so `id` is what we
     * store in our `external_ref` column and match future syncs against.
     * `assigned_to` on the ERP side is one of ITS staff uuids, not one of
     * our local employee ids, so it's deliberately not carried over — a
     * local operator claims the job from the POS side instead.
     *
     * @return string 'imported' | 'updated' | 'skipped'
     */
    public function upsertFromRemote(array $remote): string
    {
        $externalRef = (string) ($remote['id'] ?? $remote['external_ref'] ?? $remote['ticket_ref'] ?? '');
        $jobNumber   = (string) ($remote['job_number'] ?? '');

        if ($externalRef === '' && $jobNumber === '') {
            return 'skipped';
        }

        $existing = null;
        if ($externalRef !== '') {
            $existing = $this->where('external_ref', $externalRef)->first();
        }
        if (! $existing && $jobNumber !== '') {
            $existing = $this->where('job_number', $jobNumber)->first();
        }

        $status = in_array($remote['status'] ?? '', self::STATUSES, true) ? $remote['status'] : null;

        if ($existing) {
            $changes = [];
            if ($status !== null && $status !== $existing->status) {
                $changes['status'] = $status;
            }
            if (isset($remote['total_cost']) && (float) $remote['total_cost'] !== (float) $existing->total_cost) {
                $changes['total_cost'] = (float) $remote['total_cost'];
            }
            if (isset($remote['notes']) && $remote['notes'] !== $existing->notes) {
                $changes['notes'] = $remote['notes'];
            }

            if (empty($changes)) {
                return 'skipped';
            }

            $this->update($existing->id, $changes);

            return 'updated';
        }

        $createdAt = null;
        if (! empty($remote['created_at'])) {
            $timestamp = strtotime((string) $remote['created_at']);
            $createdAt = $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
        }

        $this->insert(array_filter([
            'job_number'   => $jobNumber !== '' ? $jobNumber : $this->generateJobNumber(),
            'ticket_ref'   => $remote['ticket_id'] ?? ($remote['ticket_ref'] ?? ''),
            'job_type'     => in_array($remote['job_type'] ?? '', self::JOB_TYPES, true) ? $remote['job_type'] : 'other',
            'quantity'     => (int) ($remote['quantity'] ?? 1),
            'width_mm'     => $remote['width_mm'] ?? null,
            'height_mm'    => $remote['height_mm'] ?? null,
            'material'     => $remote['material'] ?? null,
            'finish'       => in_array($remote['finish'] ?? '', self::FINISHES, true) ? $remote['finish'] : null,
            'unit_cost'    => (float) ($remote['unit_cost'] ?? 0),
            'total_cost'   => (float) ($remote['total_cost'] ?? 0),
            'notes'        => $remote['notes'] ?? '',
            'customer_id'  => $remote['customer_id'] ?? null,
            'client_phone' => $remote['client_phone'] ?? null,
            'status'       => $status ?? 'pending',
            'source'       => $remote['source'] ?? 'beamlink-erp',
            'external_ref' => $externalRef,
            'created_at'   => $createdAt,
        ], static fn ($v) => $v !== null && $v !== ''));

        return 'imported';
    }

    /**
     * Pull every job currently on the ERP and upsert it locally. Shared by
     * the authenticated "Sync" button (Printjobs::sync) and the
     * unauthenticated background/webhook triggers (Webhook::pullPrintJobs)
     * so there's exactly one place that knows how to reconcile a remote
     * job list against the local table.
     *
     * @return array{imported:int,updated:int,skipped:int}|null null if the ERP couldn't be reached
     */
    public function pullAndUpsertFromErp(BeamlinkSync $sync): ?array
    {
        $result = $sync->pullPrintJobs();
        if (! $result['ok']) {
            return null;
        }

        $jobs = $result['data']['jobs'] ?? ($result['data'] ?? []);
        if (! is_array($jobs)) {
            $jobs = [];
        }

        $counts = ['imported' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($jobs as $remote) {
            if (! is_array($remote)) {
                continue;
            }
            $counts[$this->upsertFromRemote($remote)]++;
        }

        return $counts;
    }
}
