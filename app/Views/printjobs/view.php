<?php
/**
 * Print Job Detail — Status Pipeline + Material Usage + Actions
 *
 * @var string $title
 * @var object $job
 * @var array $employees
 * @var object|null $customer
 * @var array $material_usage
 * @var array $materials
 */

$status_config = [
    'pending'     => ['label' => 'Pending',     'color' => '#94a3b8'],
    'designing'   => ['label' => 'Designing',   'color' => '#8b5cf6'],
    'proof_sent'  => ['label' => 'Proof Sent',  'color' => '#3b82f6'],
    'approved'    => ['label' => 'Approved',    'color' => '#22c55e'],
    'printing'    => ['label' => 'Printing',    'color' => '#f59e0b'],
    'finishing'   => ['label' => 'Finishing',   'color' => '#f97316'],
    'qc'          => ['label' => 'QC',          'color' => '#06b6d4'],
    'ready'       => ['label' => 'Ready',       'color' => '#22c55e'],
    'collected'   => ['label' => 'Collected',   'color' => '#64748b'],
];

$all_statuses = array_keys($status_config);
$current_index = array_search($job->status, $all_statuses);
$is_terminal = in_array($job->status, ['collected', 'cancelled']);
?>

<?= view('partial/header') ?>

<div class="row">
    <div class="col-xs-12">
        <h3 style="margin-top:0;">
            <a href="<?= base_url('printjobs') ?>" class="text-muted" style="text-decoration:none;"><i class="bi bi-arrow-left"></i></a>
            <?= esc($title) ?>
            <span class="label" style="background: <?= $status_config[$job->status]['color'] ?? '#999' ?>; font-size: 0.5em; vertical-align: middle;">
                <?= $status_config[$job->status]['label'] ?? $job->status ?>
            </span>
        </h3>
    </div>
</div>

<!-- Status Pipeline -->
<div class="row" style="margin-bottom: 15px;">
    <div class="col-xs-12">
        <div style="display: flex; gap: 4px; flex-wrap: wrap;">
            <?php foreach ($all_statuses as $i => $st): ?>
                <?php
                $is_current = ($st === $job->status);
                $is_past    = ($current_index !== false && $i < $current_index);
                $bg         = $is_current ? $status_config[$st]['color'] : ($is_past ? '#e9ecef' : '#f8f9fa');
                $fg         = $is_current ? '#fff' : ($is_past ? '#333' : '#aaa');
                ?>
                <button type="button"
                    class="btn btn-xs status-btn <?= $is_current ? 'active' : '' ?>"
                    data-status="<?= $st ?>"
                    <?= $is_terminal && ! $is_current ? 'disabled' : '' ?>
                    style="background:<?= $bg ?>; color:<?= $fg ?>; border:1px solid #ccc; flex:1; min-width:80px; font-size:0.75em;">
                    <?= $status_config[$st]['label'] ?>
                </button>
                <?php if ($i < count($all_statuses) - 1): ?>
                    <span style="line-height:28px; color:#ccc;">&#9656;</span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row">
    <!-- Main Content -->
    <div class="col-xs-12 col-md-8">

        <!-- Specifications -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>Specifications</strong>
                <div class="pull-right">
                    <a href="<?= base_url('printjobs/edit/' . $job->id) ?>" class="btn btn-xs btn-default">
                        <i class="bi bi-pencil"></i> <?= lang('Common.edit') ?>
                    </a>
                </div>
                <div class="clearfix"></div>
            </div>
            <div class="panel-body">
                <table class="table table-bordered table-condensed" style="margin-bottom:0;">
                    <tr>
                        <th style="width:25%;">Job Type</th>
                        <td><?= lang('Printjobs.' . $job->job_type) ?></td>
                        <th style="width:15%;">Quantity</th>
                        <td><strong><?= (int) $job->quantity ?></strong></td>
                    </tr>
                    <tr>
                        <th>Dimensions</th>
                        <td><?= ($job->width_mm && $job->height_mm) ? $job->width_mm . ' × ' . $job->height_mm . ' mm' : '-' ?></td>
                        <th>Material</th>
                        <td><?= esc($job->material ?: '-') ?></td>
                    </tr>
                    <tr>
                        <th>Finish</th>
                        <td><?= lang('Printjobs.' . ($job->finish ?: 'matte')) ?></td>
                        <th>Colors</th>
                        <td><?= lang('Printjobs.' . ($job->colors ?: 'full_color')) ?></td>
                    </tr>
                    <tr>
                        <th>Design File</th>
                        <td><?= $job->design_file_url ? '<a href="' . esc($job->design_file_url) . '" target="_blank"><i class="bi bi-file-earmark"></i> View</a>' : '-' ?></td>
                        <th>Proof</th>
                        <td>
                            <?php if ($job->proof_url): ?>
                                <a href="<?= esc($job->proof_url) ?>" target="_blank"><i class="bi bi-file-earmark"></i> View</a>
                                <?php if ($job->proof_approved): ?>
                                    <span class="label label-success">Approved</span>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Notes -->
        <?php if (! empty($job->notes)): ?>
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Notes</strong></div>
            <div class="panel-body"><?= nl2br(esc($job->notes)) ?></div>
        </div>
        <?php endif; ?>

        <!-- Material Usage -->
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Material Usage</strong></div>
            <div class="panel-body" style="padding:0;">
                <?php if (! empty($material_usage)): ?>
                <table class="table table-striped table-condensed" style="margin-bottom:0;">
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Quantity Used</th>
                            <th>Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($material_usage as $mu): ?>
                        <tr>
                            <td><?= esc($mu->material_name) ?></td>
                            <td><?= number_format($mu->quantity_used, 2) ?></td>
                            <td><?= esc($mu->material_unit) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-muted text-center" style="padding:15px;">No materials logged for this job.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add Material Usage -->
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Log Material Usage</strong></div>
            <div class="panel-body">
                <?= form_open('printjobs/add_material_usage/' . $job->id, 'class="form-inline"') ?>
                    <select name="material_id" class="form-control input-sm" required style="width:40%;">
                        <option value="">-- Select Material --</option>
                        <?php foreach ($materials as $m): ?>
                            <option value="<?= $m->id ?>"><?= esc($m->name) ?> (<?= $m->current_stock ?> <?= $m->unit ?> in stock)</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="quantity_used" class="form-control input-sm" placeholder="Qty" step="0.01" min="0.01" required style="width:20%;">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Add</button>
                <?= form_close() ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-xs-12 col-md-4">

        <!-- Pricing -->
        <div class="panel panel-primary">
            <div class="panel-heading"><strong><?= lang('Printjobs.total') ?></strong></div>
            <div class="panel-body text-center" style="font-size:1.6em;">
                <?= to_currency($job->total_cost) ?>
            </div>
            <table class="table table-condensed" style="margin-bottom:0;">
                <tr><th>Unit Cost</th><td class="text-right"><?= to_currency($job->unit_cost) ?></td></tr>
                <tr><th>Quantity</th><td class="text-right"><?= (int) $job->quantity ?></td></tr>
            </table>
        </div>

        <!-- Assignment -->
        <div class="panel panel-default">
            <div class="panel-heading"><strong><?= lang('Printjobs.assigned_to') ?></strong></div>
            <div class="panel-body">
                <?= form_open('printjobs/assign/' . $job->id, 'class="form-inline"') ?>
                    <select name="employee_id" class="form-control input-sm" style="width:70%;">
                        <option value="0">-- <?= lang('Common.none') ?> --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp->person_id ?>" <?= ($job->assigned_to ?? 0) == $emp->person_id ? 'selected' : '' ?>>
                                <?= esc($emp->first_name . ' ' . $emp->last_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check"></i></button>
                <?= form_close() ?>
            </div>
        </div>

        <!-- Customer -->
        <?php if ($customer): ?>
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Customer</strong></div>
            <div class="panel-body">
                <?= esc($customer->first_name . ' ' . $customer->last_name) ?>
                <?php if (!empty($customer->email)): ?><br><small class="text-muted"><?= esc($customer->email) ?></small><?php endif; ?>
                <?php if (!empty($customer->phone_number)): ?><br><small class="text-muted"><span style="color:#25d366;">&#128172;</span> <?= esc($customer->phone_number) ?></small><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($job->client_phone)): ?>
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Client Phone</strong> <span style="color:#25d366;">&#9742;</span></div>
            <div class="panel-body">
                <?= esc($job->client_phone) ?>
                <small class="text-muted">(WhatsApp updates active)</small>
            </div>
        </div>
        <?php endif; ?>

        <!-- Meta -->
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Details</strong></div>
            <table class="table table-condensed" style="margin-bottom:0;">
                <tr><th>Created</th><td><?= $job->created_at ? date('d M Y H:i', strtotime($job->created_at)) : '-' ?></td></tr>
                <tr><th>Updated</th><td><?= $job->updated_at ? date('d M Y H:i', strtotime($job->updated_at)) : '-' ?></td></tr>
                <?php if ($job->completed_at): ?>
                <tr><th>Completed</th><td><?= date('d M Y H:i', strtotime($job->completed_at)) ?></td></tr>
                <?php endif; ?>
                <tr><th>Source</th><td><?= esc($job->source ?: 'pos') ?></td></tr>
                <?php if ($job->ticket_ref): ?>
                <tr><th>Ticket Ref</th><td><?= esc($job->ticket_ref) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- Actions -->
        <div class="text-center" style="margin-top:10px;">
            <?= form_open('printjobs/delete/' . $job->id, 'style="display:inline;" onsubmit="return confirm(\'Delete this print job?\');"') ?>
                <button type="submit" class="btn btn-xs btn-danger">
                    <i class="bi bi-trash"></i> <?= lang('Common.delete') ?>
                </button>
            <?= form_close() ?>
        </div>
    </div>
</div>

<script type="text/javascript">
$(document).ready(function() {
    // Status pipeline click handlers
    $('.status-btn:not([disabled])').on('click', function() {
        var newStatus = $(this).data('status');
        $.ajax({
            url: '<?= base_url('printjobs/update_status/' . $job->id) ?>',
            type: 'POST',
            data: {
                status: newStatus,
                '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.message || 'Error updating status');
                }
            }
        });
    });
});
</script>

<?= view('partial/footer') ?>
