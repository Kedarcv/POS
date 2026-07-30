<?php

declare(strict_types=1);

namespace App\Models;

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
}
