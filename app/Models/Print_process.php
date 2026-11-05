<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * A printing technology (DTF, UV, Xerox, Eco-solvent). Carries its own
 * pricing basis + rate and, via print_process_channels, the per-channel ink
 * consumption model used to estimate ink (and cost) for a job.
 */
class Print_process extends Model
{
    protected $table            = 'print_processes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'name', 'code', 'pricing_basis', 'sell_rate', 'min_charge', 'setup_fee',
        'waste_pct', 'default_coverage_pct', 'sheet_area_m2', 'currency', 'is_active',
        'overhead_per_sqm', 'overhead_per_job', 'overhead_pct', 'spoilage_pct', 'target_margin_pct',
    ];

    /**
     * Economic parameters that inherit: process value -> shop default
     * (app_config) -> 0. One place for the estimator to ask "what rate applies".
     *
     * @return array{overhead_per_sqm:float,overhead_per_job:float,overhead_pct:float,spoilage_pct:float,target_margin_pct:float}
     */
    public static function effectiveEconomics(?object $process): array
    {
        $settings = config(\Config\OSPOS::class)->settings;

        $pick = static function (string $field, string $settingKey) use ($process, $settings): float {
            if ($process !== null && isset($process->{$field}) && $process->{$field} !== null && $process->{$field} !== '') {
                return (float) $process->{$field};
            }

            return (float) ($settings[$settingKey] ?? 0);
        };

        return [
            'overhead_per_sqm'  => $pick('overhead_per_sqm', 'print_overhead_per_sqm'),
            'overhead_per_job'  => $pick('overhead_per_job', 'print_overhead_per_job'),
            'overhead_pct'      => $pick('overhead_pct', 'print_overhead_pct'),
            'spoilage_pct'      => $pick('spoilage_pct', 'print_spoilage_pct'),
            'target_margin_pct' => $pick('target_margin_pct', 'print_target_margin_pct'),
        ];
    }
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public const PRICING_BASES = ['per_sqm' => 'Per m²', 'per_sheet' => 'Per sheet / click'];

    public function getActive(): array
    {
        return $this->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    /**
     * A process with its channel rows attached as ->channels.
     */
    public function getWithChannels(int $id): ?object
    {
        $process = $this->find($id);
        if (! $process) {
            return null;
        }
        $process->channels = model(Print_process_channel::class)->getForProcess($id);

        return $process;
    }

    /**
     * Every active process keyed by id, each with ->channels, for the job form.
     *
     * @return array<int,object>
     */
    public function getAllWithChannels(): array
    {
        $channelModel = model(Print_process_channel::class);
        $out          = [];
        foreach ($this->getActive() as $process) {
            $process->channels = $channelModel->getForProcess((int) $process->id);
            $out[(int) $process->id] = $process;
        }

        return $out;
    }
}
