<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class Machine extends Model
{
    public const STATUSES         = ['active', 'maintenance', 'offline', 'retired'];
    public const CONNECTION_TYPES = ['network', 'usb', 'bluetooth', 'cloud'];
    public const MACHINE_TYPES    = [
        'large_format_printer' => 'Large Format Printer',
        'digital_press'        => 'Digital Press',
        'offset_press'         => 'Offset Press',
        'laser_cutter'         => 'Laser Cutter',
        'vinyl_cutter'         => 'Vinyl Cutter',
        'sublimation_printer'  => 'Sublimation Printer',
        'screen_printing'      => 'Screen Printing',
        'binding_machine'      => 'Binding Machine',
        'lamination_machine'   => 'Lamination Machine',
        'scanner'              => 'Scanner',
        'other'                => 'Other',
    ];

    protected $table            = 'print_machines';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'name', 'machine_type', 'brand', 'model_number', 'serial_number',
        'status', 'ip_address', 'connection_type', 'location',
        'max_width_mm', 'max_height_mm', 'notes',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getActive(): array
    {
        return $this->where('status', 'active')->orderBy('name', 'ASC')->findAll();
    }

    public function getAvailable(): array
    {
        return $this->where('status !=', 'retired')->orderBy('name', 'ASC')->findAll();
    }

    public function getWithJobCount(): array
    {
        $builder = $this->db->table('print_machines m');
        $builder->select('m.*, COUNT(pj.id) as active_jobs');
        $builder->join('print_jobs pj', 'pj.machine = m.name AND pj.status NOT IN ("collected","ready")', 'left');
        $builder->groupBy('m.id');
        $builder->orderBy('m.name', 'ASC');

        return $builder->get()->getResult();
    }

    public function getStatusCounts(): array
    {
        $builder = $this->builder();
        $rows    = $builder->select('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()->getResult();

        $counts = ['active' => 0, 'maintenance' => 0, 'offline' => 0, 'retired' => 0];

        foreach ($rows as $row) {
            $counts[$row->status] = (int) $row->count;
        }

        return $counts;
    }
}
