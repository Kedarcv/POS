<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * One ink channel of a print process: its consumption rate (ml per m² at 100%
 * coverage) and the stock material it draws from.
 */
class Print_process_channel extends Model
{
    protected $table            = 'print_process_channels';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['process_id', 'channel', 'material_id', 'ml_per_sqm'];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public const CHANNELS = [
        'cyan', 'magenta', 'yellow', 'black',
        'light_cyan', 'light_magenta', 'orange', 'green',
        'white', 'primer', 'varnish', 'gloss',
    ];

    /**
     * Channels for a process, each with the material's name/unit/cost joined in.
     */
    public function getForProcess(int $processId): array
    {
        return $this->select('print_process_channels.*,
                print_materials.name AS material_name,
                print_materials.unit AS material_unit,
                print_materials.unit_cost AS material_unit_cost,
                print_materials.item_id AS material_item_id')
            ->join('print_materials', 'print_materials.id = print_process_channels.material_id', 'left')
            ->where('process_id', $processId)
            ->orderBy('print_process_channels.id', 'ASC')
            ->findAll();
    }

    public function deleteForProcess(int $processId): void
    {
        $this->where('process_id', $processId)->delete();
    }
}
