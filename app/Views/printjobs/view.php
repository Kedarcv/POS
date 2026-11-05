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

        <!-- Process & Ink Estimate -->
        <?php if (! empty($process)): ?>
        <div class="panel panel-info">
            <div class="panel-heading"><strong><i class="bi bi-droplet-half"></i> <?= esc($process->name) ?> — ink &amp; price estimate</strong>
                <?php if (! empty($job->ink_posted)): ?>
                    <span class="label label-success pull-right">ink drawn from stock</span>
                <?php else: ?>
                    <span class="label label-default pull-right">posts when job → printing</span>
                <?php endif; ?>
            </div>
            <div class="panel-body" style="padding:0;">
                <table class="table table-condensed" style="margin-bottom:0;">
                    <tr>
                        <th style="width:25%;"><?= lang('Printjobs.area') ?></th>
                        <td><?= $ink_estimate ? number_format($ink_estimate['area_m2'], 4) . ' m²' : '-' ?></td>
                        <th style="width:20%;"><?= lang('Printjobs.coverage') ?></th>
                        <td><?= $ink_estimate ? number_format($ink_estimate['coverage_pct'], 1) . '%' : '-' ?></td>
                    </tr>
                    <tr>
                        <th><?= lang('Printjobs.est_ink') ?></th>
                        <td><?= $ink_estimate ? number_format($ink_estimate['total_ml'], 1) . ' ml · ' . to_currency($ink_estimate['total_ink_cost']) : '-' ?></td>
                        <th><?= lang('Printjobs.est_substrate_cost') ?></th>
                        <td><?= $ink_estimate ? ($ink_estimate['substrate'] ? number_format($ink_estimate['substrate']['m2'], 3) . ' m² · ' . to_currency($ink_estimate['substrate_cost']) : '—') : '-' ?></td>
                    </tr>
                    <tr>
                        <th><?= lang('Printjobs.est_overhead') ?></th>
                        <td><?= $ink_estimate ? to_currency($ink_estimate['overhead_cost']) : '-' ?></td>
                        <th><?= lang('Printjobs.est_spoilage') ?></th>
                        <td><?= $ink_estimate ? to_currency($ink_estimate['spoilage_cost']) : '-' ?></td>
                    </tr>
                    <tr class="active">
                        <th><?= lang('Printjobs.est_total_cost') ?></th>
                        <td><strong><?= $ink_estimate ? to_currency($ink_estimate['total_cost']) : '-' ?></strong></td>
                        <th><?= lang('Printjobs.est_margin') ?></th>
                        <td>
                            <?php if ($ink_estimate): ?>
                                <strong style="color:<?= $ink_estimate['margin'] < 0 ? '#a94442' : 'inherit' ?>;">
                                    <?= to_currency($ink_estimate['margin']) ?> (<?= number_format($ink_estimate['margin_pct'], 1) ?>%)
                                </strong>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?= lang('Printjobs.suggested_price') ?></th>
                        <td><?= $ink_estimate ? to_currency($ink_estimate['suggested_price']) : '-' ?><?= $ink_estimate && $ink_estimate['floor_price'] > $ink_estimate['suggested_price'] ? ' <span class="text-danger" title="below cost-plus floor">▲ floor ' . to_currency($ink_estimate['floor_price']) . '</span>' : '' ?></td>
                        <th>Charged</th>
                        <td><strong><?= to_currency($job->total_cost) ?></strong></td>
                    </tr>
                </table>
                <?php if ($ink_estimate && ! empty($ink_estimate['channels'])): ?>
                <table class="table table-striped table-condensed" style="margin-bottom:0; font-size:12px;">
                    <thead><tr><th>Channel</th><th class="text-right">ml</th><th class="text-right">Cost</th><th>Stock item</th></tr></thead>
                    <tbody>
                    <?php foreach ($ink_estimate['channels'] as $c): ?>
                        <tr>
                            <td><?= esc($c['channel']) ?></td>
                            <td class="text-right"><?= number_format($c['ml'], 2) ?></td>
                            <td class="text-right"><?= to_currency($c['cost']) ?></td>
                            <td>
                                <?php if ($c['material_id'] === null): ?>
                                    <span class="text-danger">no material set</span>
                                <?php elseif ($c['item_id'] === null): ?>
                                    <span class="text-warning"><?= esc($c['material_name']) ?> (not linked to POS stock)</span>
                                <?php else: ?>
                                    <?= esc($c['material_name']) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Notes -->
        <?php if (! empty($job->notes)): ?>
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Notes</strong></div>
            <div class="panel-body"><?= nl2br(esc($job->notes)) ?></div>
        </div>
        <?php endif; ?>

        <!-- Attachments — artwork/reference files (a design forwarded from
             WhatsApp, a customer's own PDF, ...) the team needs on hand to
             actually run the job. Images/PDFs are served straight from
             public/uploads for inline viewing; Download forces the file's
             original name instead of the randomized on-disk one. -->
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Attachments</strong></div>
            <div class="panel-body">
                <?php if (empty($attachments)): ?>
                    <p class="text-muted" style="margin-bottom:10px;">No files attached to this job yet.</p>
                <?php else: ?>
                    <div class="row" style="margin-bottom:10px;">
                        <?php foreach ($attachments as $file): ?>
                            <?php $isImage = in_array(strtolower($file->file_type), ['png', 'jpg', 'jpeg', 'gif', 'webp'], true); ?>
                            <div class="col-xs-6 col-sm-4 col-md-3" style="margin-bottom:12px;">
                                <div class="thumbnail" style="margin-bottom:4px;">
                                    <?php if ($isImage): ?>
                                        <a href="<?= base_url($file->file_path) ?>" target="_blank">
                                            <img src="<?= base_url($file->file_path) ?>" alt="<?= esc($file->file_name) ?>" style="height:110px; width:100%; object-fit:cover;">
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= base_url($file->file_path) ?>" target="_blank" class="text-center" style="display:block; height:110px; line-height:110px;">
                                            <i class="glyphicon glyphicon-file" style="font-size:2.5em;"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:0.85em; word-break:break-word;" title="<?= esc($file->file_name) ?>">
                                    <?= esc(mb_strimwidth($file->file_name, 0, 28, '…')) ?>
                                </div>
                                <a href="<?= base_url('printjobs/download_file/' . $file->id) ?>" class="btn btn-xs btn-default" title="Download">
                                    <i class="glyphicon glyphicon-download-alt"></i>
                                </a>
                                <button class="btn btn-xs btn-danger delete-attachment" data-id="<?= $file->id ?>" title="Remove">
                                    <i class="glyphicon glyphicon-trash"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?= form_open_multipart('printjobs/upload_file/' . $job->id, ['class' => 'form-inline']) ?>
                    <input type="file" name="attachment" accept="image/png,image/jpeg,image/gif,image/webp,application/pdf" required>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="glyphicon glyphicon-upload"></i> Attach File</button>
                <?= form_close() ?>
                <small class="text-muted">Images (PNG/JPG/GIF/WebP) or PDF, up to 10MB.</small>
            </div>
        </div>

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
                            <th class="text-right">Cost</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($material_usage as $mu): ?>
                        <tr>
                            <td><?= esc($mu->material_name) ?></td>
                            <td><?= number_format($mu->quantity_used, 2) ?></td>
                            <td><?= esc($mu->material_unit) ?></td>
                            <td class="text-right"><?= isset($mu->total_cost) && $mu->total_cost !== null ? to_currency((float) $mu->total_cost) : '-' ?></td>
                            <td>
                                <?php if (! empty($mu->material_item_id)): ?>
                                    <span class="label label-info">Main stock</span>
                                <?php else: ?>
                                    <span class="label label-default">Print store</span>
                                <?php endif; ?>
                            </td>
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
                            <option value="<?= $m->id ?>">
                                <?= esc($m->name) ?>
                                (<?= rtrim(rtrim(number_format((float) $m->available, 2), '0'), '.') ?> <?= esc($m->unit) ?><?= $m->item_id ? ' in main stock' : '' ?><?= (float) $m->unit_cost > 0 ? ' · ' . to_currency((float) $m->unit_cost) . '/' . esc($m->unit) : '' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="quantity_used" class="form-control input-sm" placeholder="Qty" step="0.01" min="0.01" required style="width:18%;">
                    <label class="checkbox-inline" style="margin-left:8px;" title="Log even if it takes stock below zero">
                        <input type="checkbox" name="allow_negative" value="1"> allow negative
                    </label>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Add</button>
                    <p class="help-block" style="margin-top:6px;">Materials linked to a stock item are drawn from the main location and booked as a Print&nbsp;Dept expense.</p>
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
                                <?= esc($emp->first_name . ' ' . $emp->last_name) ?><?= in_array($emp->person_id, $on_leave_ids ?? [], true) ? ' (on leave today)' : '' ?>
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

    $('.delete-attachment').on('click', function() {
        if (! confirm('Remove this attachment?')) return;
        var id = $(this).data('id');
        $.post('<?= base_url('printjobs/delete_file') ?>/' + id, {
            '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message || 'Could not remove file.');
            }
        }, 'json');
    });
});
</script>

<?= view('partial/footer') ?>
