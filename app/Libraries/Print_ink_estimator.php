<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\Print_process;
use App\Models\Print_process_channel;
use App\Models\Printmaterial;

/**
 * Estimates the full cost of a print job — ink, substrate/media, overhead and a
 * spoilage/damage provision — plus the suggested customer price, from the job's
 * process, size and coverage.
 *
 *   area_m²        = (width_mm / 1000) x (height_mm / 1000) x quantity
 *   channel_ml     = area_m² x ml_per_sqm x coverage% x (1 + ink_waste%)
 *   ink_cost       = Σ channel_ml x material $/ml
 *   substrate_m²   = area_m² x (1 + ink_waste%)          (media over-run)
 *   substrate_cost = substrate_m² x material $/m²
 *   overhead_cost  = area_m² x overhead_per_sqm + overhead_per_job
 *                    + (ink_cost + substrate_cost) x overhead%
 *   spoilage_cost  = (ink_cost + substrate_cost) x spoilage%
 *   total_cost     = ink + substrate + overhead + spoilage
 *
 *   suggested_price = max(min_charge, billable x sell_rate) + setup_fee
 *                     billable = area_m² (per_sqm) | sheets (per_sheet)
 *   floor_price     = total_cost / (1 - target_margin%)   (cost-plus minimum)
 *
 * Ink is stocked in ml ($/ml); substrate in m² ($/m²). Overhead / spoilage are
 * costing-only — they are not deducted from stock or booked as an expense.
 *
 * Every rate inherits: process value -> shop default (app_config) -> 0.
 */
class Print_ink_estimator
{
    /**
     * @param int        $processId
     * @param float|null  $widthMm
     * @param float|null  $heightMm
     * @param float       $quantity
     * @param float|null  $coveragePct         operator override; null uses the process default
     * @param int|null    $sheets              sheet/click count for per_sheet processes
     * @param int|null    $substrateMaterialId print_materials row for the media (vinyl, film, ...)
     * @param float|null  $chargedPrice        the price actually on the job, for the margin figure
     *
     * @return array<string,mixed>|null  null when the process id is unknown
     */
    public function estimate(
        int $processId,
        ?float $widthMm,
        ?float $heightMm,
        float $quantity = 1.0,
        ?float $coveragePct = null,
        ?int $sheets = null,
        ?int $substrateMaterialId = null,
        ?float $chargedPrice = null
    ): ?array {
        $process = model(Print_process::class)->find($processId);
        if (! $process) {
            return null;
        }

        $channels = model(Print_process_channel::class)->getForProcess($processId);
        $econ     = Print_process::effectiveEconomics($process);

        $quantity    = max(0.0, $quantity);
        $coveragePct = max(0.0, $coveragePct ?? (float) $process->default_coverage_pct);
        $wastePct    = max(0.0, (float) $process->waste_pct);

        $areaFromSize = null;
        if ($widthMm !== null && $heightMm !== null && $widthMm > 0 && $heightMm > 0) {
            $areaFromSize = ($widthMm / 1000.0) * ($heightMm / 1000.0) * max($quantity, 1.0);
        }

        if ($process->pricing_basis === 'per_sheet') {
            $sheets       = max(0, (int) ($sheets ?? (int) round($quantity)));
            $area_m2      = $areaFromSize ?? ((float) ($process->sheet_area_m2 ?? 0) * $sheets);
            $billable_qty = (float) $sheets;
        } else {
            $area_m2      = $areaFromSize ?? 0.0;
            $billable_qty = $area_m2;
        }

        $coverageFactor = $coveragePct / 100.0;
        $wasteFactor    = 1.0 + ($wastePct / 100.0);

        // ── Ink, per channel ──────────────────────────────────────────
        $channelRows  = [];
        $totalMl      = 0.0;
        $totalInkCost = 0.0;
        foreach ($channels as $ch) {
            $ml       = round($area_m2 * (float) $ch->ml_per_sqm * $coverageFactor * $wasteFactor, 4);
            $unitCost = (float) ($ch->material_unit_cost ?? 0);
            $cost     = round($ml * $unitCost, 2);

            $channelRows[] = [
                'channel'       => $ch->channel,
                'material_id'   => $ch->material_id !== null ? (int) $ch->material_id : null,
                'material_name' => $ch->material_name,
                'item_id'       => isset($ch->material_item_id) && $ch->material_item_id !== null ? (int) $ch->material_item_id : null,
                'ml'            => $ml,
                'unit_cost'     => $unitCost,
                'cost'          => $cost,
            ];
            $totalMl      += $ml;
            $totalInkCost += $cost;
        }

        // ── Substrate / media ─────────────────────────────────────────
        $substrate = null;
        if ($substrateMaterialId !== null && $substrateMaterialId > 0) {
            $mat = model(Printmaterial::class)->find($substrateMaterialId);
            if ($mat) {
                $subM2   = round($area_m2 * $wasteFactor, 4);
                $subCost = round($subM2 * (float) $mat->unit_cost, 2);
                $substrate = [
                    'material_id'   => (int) $mat->id,
                    'material_name' => $mat->name,
                    'item_id'       => $mat->item_id !== null ? (int) $mat->item_id : null,
                    'unit'          => $mat->unit,
                    'm2'            => $subM2,
                    'unit_cost'     => (float) $mat->unit_cost,
                    'cost'          => $subCost,
                ];
            }
        }
        $substrateCost = $substrate['cost'] ?? 0.0;

        // ── Overhead + spoilage (costing only) ───────────────────────
        $materialsCost = $totalInkCost + $substrateCost;
        $overheadCost  = round(
            $area_m2 * $econ['overhead_per_sqm']
            + $econ['overhead_per_job']
            + $materialsCost * $econ['overhead_pct'] / 100.0,
            2
        );
        $spoilageCost = round($materialsCost * $econ['spoilage_pct'] / 100.0, 2);

        $totalCost = round($totalInkCost + $substrateCost + $overheadCost + $spoilageCost, 2);

        // ── Price ────────────────────────────────────────────────────
        $suggestedPrice = round(
            max((float) $process->min_charge, $billable_qty * (float) $process->sell_rate) + (float) $process->setup_fee,
            2
        );

        $targetMargin = $econ['target_margin_pct'];
        $floorPrice   = ($targetMargin > 0 && $targetMargin < 100)
            ? round($totalCost / (1 - $targetMargin / 100.0), 2)
            : $totalCost;

        $priceForMargin = ($chargedPrice !== null && $chargedPrice > 0) ? $chargedPrice : $suggestedPrice;
        $margin         = round($priceForMargin - $totalCost, 2);
        $marginPct      = $priceForMargin > 0 ? round($margin / $priceForMargin * 100, 2) : 0.0;

        return [
            'process_id'      => (int) $process->id,
            'process_name'    => $process->name,
            'pricing_basis'   => $process->pricing_basis,
            'area_m2'         => round($area_m2, 4),
            'billable_qty'    => round($billable_qty, 4),
            'coverage_pct'    => round($coveragePct, 3),
            'waste_pct'       => round($wastePct, 3),
            'channels'        => $channelRows,
            'total_ml'        => round($totalMl, 4),
            'total_ink_cost'  => round($totalInkCost, 2),
            'substrate'       => $substrate,
            'substrate_cost'  => round($substrateCost, 2),
            'overhead_cost'   => $overheadCost,
            'spoilage_cost'   => $spoilageCost,
            'total_cost'      => $totalCost,
            'suggested_price' => $suggestedPrice,
            'floor_price'     => $floorPrice,
            'price_for_margin' => round($priceForMargin, 2),
            'margin'          => $margin,
            'margin_pct'      => $marginPct,
            'economics'       => $econ,
        ];
    }
}
