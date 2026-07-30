<?php

use App\Models\Printjob;

/**
 * Print Jobs Dashboard — Kanban Board for Printing Team
 *
 * @var string $title
 * @var array  $status_counts
 * @var array  $kanban
 * @var int    $active_count
 * @var array  $low_stock
 */
$status_config = [
    'pending'    => ['label' => 'Pending', 'color' => '#94a3b8', 'icon' => 'glyphicon-time'],
    'designing'  => ['label' => 'Designing', 'color' => '#8b5cf6', 'icon' => 'glyphicon-pencil'],
    'proof_sent' => ['label' => 'Proof Sent', 'color' => '#3b82f6', 'icon' => 'glyphicon-send'],
    'approved'   => ['label' => 'Approved', 'color' => '#22c55e', 'icon' => 'glyphicon-ok-circle'],
    'printing'   => ['label' => 'Printing', 'color' => '#f59e0b', 'icon' => 'glyphicon-print'],
    'finishing'  => ['label' => 'Finishing', 'color' => '#f97316', 'icon' => 'glyphicon-scissors'],
    'qc'         => ['label' => 'QC', 'color' => '#06b6d4', 'icon' => 'glyphicon-search'],
    'ready'      => ['label' => 'Ready', 'color' => '#22c55e', 'icon' => 'glyphicon-ok-sign'],
    'collected'  => ['label' => 'Collected', 'color' => '#64748b', 'icon' => 'glyphicon-briefcase'],
];

$next_status = [
    'pending'    => 'designing',
    'designing'  => 'proof_sent',
    'proof_sent' => 'approved',
    'approved'   => 'printing',
    'printing'   => 'finishing',
    'finishing'  => 'qc',
    'qc'         => 'ready',
    'ready'      => 'collected',
];

$type_labels = [
    'banner'        => 'Banner', 'brochure' => 'Brochure', 'business_card' => 'Business Card',
    'branded_merch' => 'Branded Merch', 'large_format' => 'Large Format', 'sticker' => 'Sticker',
    'poster'        => 'Poster', 'flyer' => 'Flyer', 'booklet' => 'Booklet', 'other' => 'Other',
];

?>

<?= view('partial/header') ?>

<div class="row" style="margin-bottom: 10px;">
    <div class="col-xs-12">
        <a href="<?= base_url('dashboard') ?>" class="btn btn-default btn-sm">
            <i class="glyphicon glyphicon-arrow-left"></i> <?= lang('Dashboard.title') ?>
        </a>
    </div>
</div>

<style>
    .kanban-board { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 15px; min-height: 400px; }
    .kanban-column { min-width: 220px; max-width: 250px; flex: 1; background: #f8f9fa; border-radius: 6px; border: 1px solid #dee2e6; }
    .kanban-header { padding: 8px 10px; font-weight: 600; font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px; border-radius: 5px 5px 0 0; color: #fff; display: flex; justify-content: space-between; align-items: center; }
    .kanban-header .badge { font-size: 0.85em; }
    .kanban-body { padding: 6px; min-height: 100px; }
    .kanban-card { background: #fff; border: 1px solid #dee2e6; border-radius: 4px; padding: 8px 10px; margin-bottom: 6px; cursor: pointer; transition: box-shadow 0.15s; font-size: 0.85em; border-left: 3px solid transparent; }
    .kanban-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.12); }
    .kanban-card.whatsapp { border-left-color: #25d366; }
    .kanban-card.overdue { border-left-color: #dc3545; }
    .kanban-card .job-type { font-weight: 600; color: #333; }
    .kanban-card .job-meta { color: #6c757d; font-size: 0.8em; margin-top: 2px; }
    .kanban-card .job-client { color: #0d6efd; font-size: 0.8em; font-weight: 500; }
    .kanban-card .job-deadline { color: #dc3545; font-size: 0.75em; font-weight: 500; }
    .kanban-card .job-machine { color: #6f42c1; font-size: 0.75em; }
    .kanban-card .advance-btn { margin-top: 5px; }
    .stat-card { text-align: center; padding: 12px; border-radius: 6px; background: #fff; border: 1px solid #dee2e6; }
    .stat-card h3 { margin: 0; font-size: 1.8em; font-weight: 700; }
    .stat-card small { color: #6c757d; }
    .low-stock-alert { border-left: 3px solid #dc3545; }
    .search-box { max-width: 350px; }
</style>

<div class="row" style="margin-bottom: 12px;">
    <div class="col-xs-12">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;"><i class="glyphicon glyphicon-print"></i> <?= esc($title) ?></h3>
            <div>
                <a href="<?= base_url('printjobs/materials') ?>" class="btn btn-default btn-sm">
                    <i class="glyphicon glyphicon-folder-open"></i> <?= lang('Printjobs.materials') ?>
                </a>
                <a href="<?= base_url('machines') ?>" class="btn btn-default btn-sm">
                    <i class="glyphicon glyphicon-cog"></i> Machines
                </a>
                <a href="<?= base_url('printjobs/pricing') ?>" class="btn btn-default btn-sm">
                    <i class="glyphicon glyphicon-usd"></i> <?= lang('Printjobs.pricing') ?>
                </a>
                <a href="<?= base_url('printjobs/sync') ?>" class="btn btn-info btn-sm" title="Pull jobs from BeamLink ERP">
                    <i class="glyphicon glyphicon-refresh"></i> Sync from ERP
                </a>
                <a href="<?= base_url('printjobs/new') ?>" class="btn btn-success btn-sm">
                    <i class="glyphicon glyphicon-plus"></i> <?= lang('Printjobs.new_job') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Search Bar -->
<div class="row" style="margin-bottom: 12px;">
    <div class="col-xs-12">
        <div class="input-group search-box">
            <input type="text" id="job_search" class="form-control input-sm" placeholder="<?= lang('Printjobs.search_placeholder') ?>">
            <span class="input-group-addon"><i class="glyphicon glyphicon-search"></i></span>
        </div>
    </div>
</div>

<!-- Stats Row -->
<div class="row" style="margin-bottom: 12px;">
    <div class="col-xs-3">
        <div class="stat-card">
            <h3><?= $active_count ?></h3>
            <small>Active Jobs</small>
        </div>
    </div>
    <div class="col-xs-3">
        <div class="stat-card">
            <h3><?= $status_counts['ready'] ?? 0 ?></h3>
            <small>Ready for Collection</small>
        </div>
    </div>
    <div class="col-xs-3">
        <div class="stat-card">
            <h3><?= to_currency($pipeline_value ?? 0) ?></h3>
            <small>Pipeline Value</small>
        </div>
    </div>
    <div class="col-xs-3">
        <div class="stat-card <?= count($low_stock) > 0 ? 'low-stock-alert' : '' ?>">
            <h3 style="<?= count($low_stock) > 0 ? 'color:#dc3545;' : '' ?>"><?= count($low_stock) ?></h3>
            <small>Low Stock Items</small>
        </div>
    </div>
</div>

<!-- Kanban Board -->
<div class="kanban-board">
    <?php foreach ($status_config as $st => $cfg): ?>
        <div class="kanban-column">
            <div class="kanban-header" style="background: <?= $cfg['color'] ?>;">
                <span><i class="glyphicon <?= $cfg['icon'] ?>"></i> <?= $cfg['label'] ?></span>
                <span class="badge" style="background: #fff; color: #333;"><?= $status_counts[$st] ?? 0 ?></span>
            </div>
            <div class="kanban-body" data-status="<?= $st ?>">
                <?php if (! empty($kanban[$st])): ?>
                    <?php foreach ($kanban[$st] as $job): ?>
                        <?php
                        $is_whatsapp = ($job->source ?? 'pos') === 'whatsapp';
                        $is_overdue  = ($job->deadline && strtotime($job->deadline) < time() && $st !== 'collected' && $st !== 'ready');
                        $card_class  = $is_whatsapp ? 'whatsapp' : ($is_overdue ? 'overdue' : '');
                        $client_name = '';
                        if (! empty($job->customer_id)) {
                            foreach ($customers as $c) {
                                if ($c->person_id === $job->customer_id) {
                                    $client_name = $c->first_name . ' ' . mb_substr($c->last_name, 0, 1) . '.';
                                    break;
                                }
                            }
                        }
                        ?>
                        <div class="kanban-card <?= $card_class ?>" data-search="<?= esc(strtolower($job->job_number . ' ' . $client_name . ' ' . ($job->notes ?? '') . ' ' . ($job->material ?? ''))) ?>">
                            <div class="job-type">
                                <?= $type_labels[$job->job_type] ?? $job->job_type ?>
                                <?php if ($is_whatsapp): ?>
                                    <span style="color: #25d366;" title="From WhatsApp"><i class="glyphicon glyphicon-comment"></i></span>
                                <?php endif; ?>
                            </div>
                            <div class="job-meta"><?= esc($job->job_number) ?> &middot; Qty: <?= $job->quantity ?></div>
                            <?php if ($client_name): ?>
                                <div class="job-client"><i class="glyphicon glyphicon-user"></i> <?= esc($client_name) ?></div>
                            <?php endif; ?>
                            <?php if (! empty($job->client_phone)): ?>
                                <div class="job-meta" style="color:#25d366;font-size:11px;"><i class="glyphicon glyphicon-earphone"></i> <?= esc($job->client_phone) ?></div>
                            <?php endif; ?>
                            <?php if (! empty($job->machine)): ?>
                                <div class="job-machine"><i class="glyphicon glyphicon-cog"></i> <?= esc($job->machine) ?></div>
                            <?php endif; ?>
                            <?php if ($job->deadline): ?>
                                <div class="job-deadline <?= $is_overdue ? 'text-danger' : '' ?>">
                                    <i class="glyphicon glyphicon-time"></i> <?= date('d M H:i', strtotime($job->deadline)) ?>
                                    <?php if ($is_overdue): ?>
                                        <strong>OVERDUE</strong>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($job->total_cost > 0): ?>
                                <div class="job-meta"><strong><?= to_currency($job->total_cost) ?></strong></div>
                            <?php endif; ?>
                            <?php if (! empty($job->notes)): ?>
                                <div class="job-meta" style="margin-top:2px; font-style:italic;"><?= esc(mb_strimwidth($job->notes, 0, 50, '...')) ?></div>
                            <?php endif; ?>
                            <?php if (isset($next_status[$st])): ?>
                                <div class="advance-btn">
                                    <button class="btn btn-xs btn-block btn-advance-status" data-id="<?= $job->id ?>" data-next="<?= $next_status[$st] ?>" style="background: <?= $cfg['color'] ?>; color: #fff; border: none;">
                                        <i class="glyphicon glyphicon-arrow-right"></i> <?= lang('Printjobs.advance_status') ?> → <?= lang('Printjobs.' . $next_status[$st]) ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script type="text/javascript">
$(document).ready(function() {
    // Search functionality
    $('#job_search').on('input', function() {
        var query = $(this).val().toLowerCase();
        if (query === '') {
            $('.kanban-card').show();
        } else {
            $('.kanban-card').each(function() {
                var searchData = $(this).data('search') || '';
                $(this).toggle(searchData.indexOf(query) !== -1);
            });
        }
    });

    // Advance status buttons
    $('.btn-advance-status').click(function(e) {
        e.stopPropagation();
        var btn = $(this);
        var jobId = btn.data('id');
        var nextStatus = btn.data('next');

        btn.prop('disabled', true).text('Updating...');

        $.post('<?= base_url('printjobs/update_status') ?>/' + jobId, {
            status: nextStatus
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Failed: ' + (response.message || 'Unknown error'));
                btn.prop('disabled', false).text('Advance →');
            }
        }).fail(function() {
            alert('Network error');
            btn.prop('disabled', false).text('Advance →');
        });
    });

    // Card click to view details
    $('.kanban-card').click(function(e) {
        if (!$(e.target).hasClass('btn-advance-status') && !$(e.target).hasClass('btn')) {
            var jobId = $(this).find('.btn-advance-status').data('id') ||
                        $(this).attr('onclick')?.match(/\/(\d+)/)?.[1];
            if (jobId) {
                window.location = '<?= base_url('printjobs/view/') ?>' + jobId;
            }
        }
    });
});
</script>

<?= view('partial/footer') ?>
