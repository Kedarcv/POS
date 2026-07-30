<?php
/**
 * Layby Dashboard — Summary + List
 *
 * @var string $title
 * @var array $status_counts
 * @var array $summary
 * @var array $laybys
 * @var array $overdue
 * @var array $upcoming
 */

$status_colors = [
    'active'    => 'primary',
    'paid'      => 'success',
    'overdue'   => 'danger',
    'cancelled' => 'default',
];
?>

<?= view('partial/header') ?>

<div class="row" style="margin-bottom: 10px;">
    <div class="col-xs-12">
        <a href="<?= base_url('dashboard') ?>" class="btn btn-default btn-sm"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
    </div>
</div>

<div class="row" style="margin-bottom: 12px;">
    <div class="col-xs-12">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;"><i class="bi bi-calendar-check"></i> <?= esc($title) ?></h3>
            <a href="<?= base_url('laybys/new') ?>" class="btn btn-success btn-sm">
                <i class="bi bi-plus-lg"></i> <?= lang('Laybys.new_layby') ?>
            </a>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row" style="margin-bottom: 15px;">
    <div class="col-xs-3">
        <div class="panel panel-primary">
            <div class="panel-heading text-center"><strong><?= $summary['total_laybys'] ?></strong></div>
            <div class="panel-body text-center" style="padding:5px;"><?= lang('Laybys.total_laybys') ?></div>
        </div>
    </div>
    <div class="col-xs-3">
        <div class="panel panel-info">
            <div class="panel-heading text-center"><strong><?= to_currency($summary['total_value']) ?></strong></div>
            <div class="panel-body text-center" style="padding:5px;"><?= lang('Laybys.total_value') ?></div>
        </div>
    </div>
    <div class="col-xs-3">
        <div class="panel panel-success">
            <div class="panel-heading text-center"><strong><?= to_currency($summary['total_deposits']) ?></strong></div>
            <div class="panel-body text-center" style="padding:5px;"><?= lang('Laybys.total_deposits') ?></div>
        </div>
    </div>
    <div class="col-xs-3">
        <div class="panel panel-warning">
            <div class="panel-heading text-center"><strong><?= to_currency($summary['total_outstanding']) ?></strong></div>
            <div class="panel-body text-center" style="padding:5px;"><?= lang('Laybys.total_outstanding') ?></div>
        </div>
    </div>
</div>

<!-- Status Counts -->
<div class="row" style="margin-bottom: 15px;">
    <?php foreach ($status_colors as $st => $color): ?>
    <div class="col-xs-3">
        <div class="text-center" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <span class="label label-<?= $color ?>" style="font-size: 1em;"><?= $status_counts[$st] ?? 0 ?></span>
            <br><small class="text-muted"><?= lang('Laybys.' . $st) ?></small>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Overdue Alert -->
<?php if (! empty($overdue)): ?>
<div class="row" style="margin-bottom: 15px;">
    <div class="col-xs-12">
        <div class="alert alert-danger">
            <strong><i class="bi bi-exclamation-triangle"></i> <?= count($overdue) ?> Overdue Installment(s)</strong> — payments past due
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Layby List -->
<div class="row">
    <div class="col-xs-12">
        <table class="table table-striped table-bordered table-hover" id="laybys_table">
            <thead>
                <tr>
                    <th><?= lang('Laybys.layby_number') ?></th>
                    <th><?= lang('Laybys.customer') ?></th>
                    <th><?= lang('Laybys.total_amount') ?></th>
                    <th><?= lang('Laybys.deposit_amount') ?></th>
                    <th><?= lang('Laybys.balance') ?></th>
                    <th><?= lang('Laybys.end_date') ?></th>
                    <th><?= lang('Laybys.status') ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($laybys)): ?>
                <tr><td colspan="8" class="text-center text-muted">No laybys yet.</td></tr>
                <?php else: ?>
                <?php foreach ($laybys as $l): ?>
                <tr>
                    <td><a href="<?= base_url('laybys/view/' . $l->id) ?>"><?= esc($l->layby_number) ?></a></td>
                    <td><?= $l->customer_id ?></td>
                    <td><?= to_currency($l->total_with_interest) ?></td>
                    <td><?= to_currency($l->deposit_amount) ?></td>
                    <td><strong><?= to_currency($l->balance) ?></strong></td>
                    <td><?= $l->end_date ? date('d M Y', strtotime($l->end_date)) : '-' ?></td>
                    <td><span class="label label-<?= $status_colors[$l->status] ?? 'default' ?>"><?= lang('Laybys.' . $l->status) ?></span></td>
                    <td>
                        <a href="<?= base_url('laybys/view/' . $l->id) ?>" class="btn btn-xs btn-info"><i class="bi bi-eye"></i></a>
                        <a href="<?= base_url('laybys/edit/' . $l->id) ?>" class="btn btn-xs btn-default"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script type="text/javascript">
$(document).ready(function() {
    $("#laybys_table").DataTable({ "order": [[5, "asc"]] });
});
</script>

<?= view('partial/footer') ?>
