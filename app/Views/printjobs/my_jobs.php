<?php

use App\Models\Printjob;

/**
 * My Print Jobs — an operator's own queue, jobs assigned to them and still
 * waiting to be worked, soonest deadline first.
 *
 * @var string $title
 * @var array  $jobs
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

<div class="row" style="margin-bottom: 12px;">
    <div class="col-xs-12">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;"><i class="glyphicon glyphicon-user"></i> <?= esc($title) ?></h3>
            <div>
                <a href="<?= base_url('printjobs') ?>" class="btn btn-default btn-sm">
                    <i class="glyphicon glyphicon-th-large"></i> All Jobs (Kanban)
                </a>
            </div>
        </div>
        <p class="text-muted" style="margin-top: 4px;">Jobs assigned to you, waiting to be worked — soonest deadline first.</p>
    </div>
</div>

<?php if (empty($jobs)): ?>
    <div class="alert alert-info text-center">
        <i class="glyphicon glyphicon-ok-sign"></i> Nothing assigned to you right now.
    </div>
<?php else: ?>
    <div class="row" id="my_jobs_list">
        <?php foreach ($jobs as $job): ?>
            <?php
            $cfg = $status_config[$job->status] ?? ['label' => ucfirst($job->status), 'color' => '#94a3b8', 'icon' => 'glyphicon-question-sign'];
            $overdue = !empty($job->deadline) && strtotime($job->deadline) < time() && $job->status !== 'ready';
            ?>
            <div class="col-xs-12 col-sm-6 col-md-4" style="margin-bottom: 14px;">
                <div class="panel panel-default my-job-card" data-id="<?= (int) $job->id ?>"
                     style="border-left: 4px solid <?= esc($cfg['color']) ?>; cursor: pointer; margin-bottom: 0;">
                    <div class="panel-body">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <strong><?= esc($job->job_number) ?></strong>
                            <span class="label" style="background: <?= esc($cfg['color']) ?>;">
                                <i class="glyphicon <?= esc($cfg['icon']) ?>"></i> <?= esc($cfg['label']) ?>
                            </span>
                        </div>
                        <div style="margin: 6px 0; color: #555;">
                            <?= esc($type_labels[$job->job_type] ?? ucfirst($job->job_type)) ?>
                            &middot; Qty <?= (int) $job->quantity ?>
                            <?php if (!empty($job->machine)): ?>
                                &middot; <i class="glyphicon glyphicon-cog"></i> <?= esc($job->machine) ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($job->notes)): ?>
                            <div style="font-size: 85%; color: #777; margin-bottom: 6px;">
                                <?= esc(mb_strimwidth($job->notes, 0, 90, '…')) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($job->deadline)): ?>
                            <div style="font-size: 85%; <?= $overdue ? 'color:#dc3545; font-weight:bold;' : 'color:#777;' ?>">
                                <i class="glyphicon glyphicon-calendar"></i>
                                Due <?= esc(date('M j, g:ia', strtotime($job->deadline))) ?>
                                <?= $overdue ? ' (overdue)' : '' ?>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top: 10px; display: flex; gap: 6px;">
                            <a href="<?= base_url('printjobs/view/' . $job->id) ?>" class="btn btn-default btn-xs">
                                <i class="glyphicon glyphicon-eye-open"></i> View
                            </a>
                            <?php if (isset($next_status[$job->status])): ?>
                                <button type="button" class="btn btn-success btn-xs btn-advance-status"
                                        data-id="<?= (int) $job->id ?>" data-next="<?= esc($next_status[$job->status]) ?>">
                                    <?= esc($status_config[$next_status[$job->status]]['label']) ?> →
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script type="text/javascript">
    $(document).ready(function() {
        $('.btn-advance-status').click(function(e) {
            e.stopPropagation();
            var btn = $(this);
            var jobId = btn.data('id');
            var nextStatus = btn.data('next');
            var origText = btn.text();

            btn.prop('disabled', true).text('Updating...');

            $.post('<?= base_url('printjobs/update_status') ?>/' + jobId, {
                status: nextStatus
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed: ' + (response.message || 'Unknown error'));
                    btn.prop('disabled', false).text(origText);
                }
            }).fail(function() {
                alert('Network error');
                btn.prop('disabled', false).text(origText);
            });
        });

        $('.my-job-card').click(function(e) {
            if (!$(e.target).hasClass('btn') && !$(e.target).hasClass('btn-advance-status') && !$(e.target).closest('a').length) {
                window.location = '<?= base_url('printjobs/view/') ?>' + $(this).data('id');
            }
        });
    });
</script>

<?= view('partial/footer') ?>
