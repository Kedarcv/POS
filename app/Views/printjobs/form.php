<?php

use App\Models\Printjob;

/**
 * Print Job Form — Till Workflow
 * Captures: job card number, date, machine, material, client, description, amount, served by
 *
 * @var string      $title
 * @var object|null $job
 * @var array       $employees
 * @var array       $customers
 * @var array       $pricing
 */
$is_edit        = ! empty($job);
$logged_in      = $employee_info ?? null;
$served_by_name = $logged_in ? $logged_in->first_name . ' ' . mb_substr($logged_in->last_name, 0, 1) : '';
?>

<?= view('partial/header') ?>

<div class="row" style="margin-bottom: 10px;">
    <div class="col-xs-12">
        <a href="<?= base_url('printjobs') ?>" class="btn btn-default btn-sm">
            <i class="glyphicon glyphicon-arrow-left"></i> <?= lang('Printjobs.title') ?>
        </a>
    </div>
</div>

<div id="required_fields_message"><?= lang('Common.fields_required_message') ?></div>
<ul id="error_message_box" class="error_message_box"></ul>

<?= form_open('printjobs/save', ['id' => 'printjob_form', 'class' => 'form-horizontal']) ?>
<?php if ($is_edit): ?>
    <input type="hidden" name="id" value="<?= $job->id ?>">
<?php endif; ?>

<div class="row">
    <div class="col-xs-12">
        <h3 class="text-center" style="margin-bottom: 5px;">
            <i class="glyphicon glyphicon-print"></i> <?= esc($title) ?>
        </h3>
    </div>
</div>

<!-- Job Card Header -->
<div class="row" style="margin-bottom: 15px;">
    <div class="col-xs-4">
        <div class="panel panel-default" style="margin-bottom: 0;">
            <div class="panel-body text-center" style="padding: 10px;">
                <small class="text-muted"><?= lang('Printjobs.job_number') ?></small><br>
                <strong style="font-size: 16px;">
                    <?php if ($is_edit): ?>
                        <?= esc($job->job_number) ?>
                    <?php else: ?>
                        <span id="job_number_display" class="text-muted">Auto-generated</span>
                    <?php endif; ?>
                </strong>
            </div>
        </div>
    </div>
    <div class="col-xs-4">
        <div class="panel panel-default" style="margin-bottom: 0;">
            <div class="panel-body text-center" style="padding: 10px;">
                <small class="text-muted"><?= lang('Printjobs.date_created') ?></small><br>
                <strong style="font-size: 16px;"><?= date('d M Y') ?></strong>
            </div>
        </div>
    </div>
    <div class="col-xs-4">
        <div class="panel panel-default" style="margin-bottom: 0;">
            <div class="panel-body text-center" style="padding: 10px;">
                <small class="text-muted"><?= lang('Printjobs.served_by') ?></small><br>
                <strong style="font-size: 16px;"><?= esc($served_by_name) ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left: Job Details -->
    <div class="col-xs-12 col-md-6">
        <fieldset>
            <legend><i class="glyphicon glyphicon-list"></i> <?= lang('Printjobs.job_details') ?></legend>

            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4"><?= lang('Printjobs.job_type') ?> <span class="text-danger">*</span></label>
                <div class="col-xs-7">
                    <select name="job_type" id="job_type" class="form-control input-sm" required>
                        <option value="">-- Select --</option>
                        <?php foreach (Printjob::JOB_TYPES as $type): ?>
                            <option value="<?= $type ?>" <?= ($job && $job->job_type === $type) ? 'selected' : '' ?>>
                                <?= lang('Printjobs.' . $type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php $processes = $processes ?? []; ?>
            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4"><?= lang('Printjobs.process') ?></label>
                <div class="col-xs-7">
                    <select name="process_id" id="process_id" class="form-control input-sm"
                        data-selected="<?= (int) ($job->process_id ?? 0) ?>">
                        <option value="">-- None --</option>
                        <?php foreach ($processes as $proc): ?>
                            <option value="<?= (int) $proc->id ?>"
                                data-basis="<?= esc($proc->pricing_basis) ?>"
                                data-coverage="<?= $proc->default_coverage_pct ?>"
                                <?= ($job && (int) ($job->process_id ?? 0) === (int) $proc->id) ? 'selected' : '' ?>>
                                <?= esc($proc->name) ?> (<?= $proc->pricing_basis === 'per_sheet' ? lang('Printjobs.sheets') : 'm²' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">DTF / UV / Xerox / Eco-solvent — drives the ink estimate &amp; suggested price</small>
                </div>
            </div>

            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4"><?= lang('Printjobs.coverage') ?></label>
                <div class="col-xs-3">
                    <input type="number" name="coverage_pct" id="coverage_pct" class="form-control input-sm" min="1" max="300" step="1"
                        value="<?= $job->coverage_pct ?? '' ?>" placeholder="auto">
                </div>
                <div class="col-xs-4" id="sheets_wrap" style="display:none;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-addon"><?= lang('Printjobs.sheets') ?></span>
                        <input type="number" name="sheets" id="sheets" class="form-control input-sm" min="0" step="1"
                            value="<?= $job->sheets ?? '' ?>">
                    </div>
                </div>
            </div>

            <?php $substrates = $substrates ?? []; ?>
            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4"><?= lang('Printjobs.substrate') ?></label>
                <div class="col-xs-7">
                    <select name="substrate_material_id" id="substrate_material_id" class="form-control input-sm">
                        <option value="">-- None / supplied by client --</option>
                        <?php foreach ($substrates as $s): ?>
                            <option value="<?= (int) $s->id ?>"
                                <?= ($job && (int) ($job->material_id ?? 0) === (int) $s->id) ? 'selected' : '' ?>>
                                <?= esc($s->name) ?> (<?= esc($s->unit) ?> · <?= to_currency($s->unit_cost) ?>/<?= esc($s->unit) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Vinyl / film / paper drawn from stock &amp; costed per m²</small>
                </div>
            </div>

            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4"><?= lang('Printjobs.machine') ?></label>
                <div class="col-xs-7">
                    <select name="machine" class="form-control input-sm">
                        <option value="">-- Select Machine --</option>
                        <?php foreach (($machines ?? []) as $m): ?>
                            <option value="<?= esc($m->name) ?>" <?= ($job && ($job->machine ?? '') === $m->name) ? 'selected' : '' ?>>
                                <?= esc($m->name) ?><?= $m->location ? ' (' . esc($m->location) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4"><?= lang('Printjobs.quantity') ?></label>
                <div class="col-xs-3">
                    <input type="number" name="quantity" id="quantity" class="form-control input-sm" value="<?= $job->quantity ?? 1 ?>" min="1" required>
                </div>
            </div>

            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4">Size (mm)</label>
                <div class="col-xs-7">
                    <div class="input-group input-group-sm">
                        <input type="number" name="width_mm" class="form-control input-sm" value="<?= $job->width_mm ?? '' ?>" placeholder="Width">
                        <span class="input-group-addon">×</span>
                        <input type="number" name="height_mm" class="form-control input-sm" value="<?= $job->height_mm ?? '' ?>" placeholder="Height">
                    </div>
                </div>
            </div>

            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4"><?= lang('Printjobs.material') ?></label>
                <div class="col-xs-7">
                    <input type="text" name="material" class="form-control input-sm" value="<?= esc($job->material ?? '') ?>" placeholder="e.g. Glossy Paper, Vinyl">
                </div>
            </div>

            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4"><?= lang('Printjobs.finish') ?></label>
                <div class="col-xs-7">
                    <select name="finish" class="form-control input-sm">
                        <?php foreach (Printjob::FINISHES as $f): ?>
                            <option value="<?= $f ?>" <?= ($job && ($job->finish ?? '') === $f) ? 'selected' : '' ?>><?= ucfirst($f) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4"><?= lang('Printjobs.colors') ?></label>
                <div class="col-xs-7">
                    <select name="colors" class="form-control input-sm">
                        <option value="full_color" <?= ($job && ($job->colors ?? '') === 'full_color') ? 'selected' : '' ?>><?= lang('Printjobs.full_color') ?></option>
                        <option value="bw" <?= ($job && ($job->colors ?? '') === 'bw') ? 'selected' : '' ?>><?= lang('Printjobs.bw') ?></option>
                        <option value="spot_color" <?= ($job && ($job->colors ?? '') === 'spot_color') ? 'selected' : '' ?>><?= lang('Printjobs.spot_color') ?></option>
                    </select>
                </div>
            </div>
        </fieldset>
    </div>

    <!-- Right: Client & Pricing -->
    <div class="col-xs-12 col-md-6">
        <fieldset>
            <legend><i class="glyphicon glyphicon-user"></i> <?= lang('Printjobs.client') ?> & <?= lang('Printjobs.pricing') ?></legend>

            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4"><?= lang('Printjobs.client') ?> <span class="text-danger">*</span></label>
                <div class="col-xs-7">
                    <select name="customer_id" class="form-control input-sm" required>
                        <option value="0">-- Walk-in / None --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c->person_id ?>" <?= ($job && ($job->customer_id ?? 0) === $c->person_id) ? 'selected' : '' ?>>
                                <?= esc($c->first_name . ' ' . $c->last_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4">Client Phone</label>
                <div class="col-xs-7">
                    <input type="tel" name="client_phone" class="form-control input-sm"
                           value="<?= esc($job->client_phone ?? '') ?>"
                           placeholder="WhatsApp number for updates">
                    <small class="text-muted">Used to send WhatsApp status updates to the client</small>
                </div>
            </div>

            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4"><?= lang('Printjobs.assigned_to') ?></label>
                <div class="col-xs-7">
                    <select name="assigned_to" class="form-control input-sm">
                        <option value="0">-- Unassigned --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp->person_id ?>" <?= ($job && ($job->assigned_to ?? 0) === $emp->person_id) ? 'selected' : '' ?>>
                                <?= esc($emp->first_name . ' ' . $emp->last_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4"><?= lang('Printjobs.deadline') ?></label>
                <div class="col-xs-7">
                    <input type="datetime-local" name="deadline" class="form-control input-sm" value="<?= $job && $job->deadline ? date('Y-m-d\TH:i', strtotime($job->deadline)) : '' ?>">
                </div>
            </div>

            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4"><?= lang('Printjobs.description') ?></label>
                <div class="col-xs-7">
                    <textarea name="notes" class="form-control input-sm" rows="2" placeholder="Job description / special instructions"><?= esc($job->notes ?? '') ?></textarea>
                </div>
            </div>

            <div class="panel panel-info" id="estimate_panel" style="display:none; margin-bottom:12px;">
                <div class="panel-heading" style="padding:6px 10px;">
                    <strong><i class="glyphicon glyphicon-tint"></i> Cost &amp; price estimate</strong>
                    <button type="button" class="btn btn-xs btn-default pull-right" id="apply_price_btn">Use suggested price</button>
                </div>
                <div class="panel-body" style="padding:8px 10px;">
                    <table class="table table-condensed" style="margin-bottom:6px;">
                        <tr><td><?= lang('Printjobs.area') ?></td><td class="text-right"><strong id="est_area">–</strong> m²</td></tr>
                        <tr><td><?= lang('Printjobs.est_ink') ?> (<span id="est_ml">–</span> ml)</td><td class="text-right" id="est_cost">–</td></tr>
                        <tr><td><?= lang('Printjobs.est_substrate_cost') ?> (<span id="est_sub_m2">–</span> m²)</td><td class="text-right" id="est_sub_cost">–</td></tr>
                        <tr><td><?= lang('Printjobs.est_overhead') ?></td><td class="text-right" id="est_oh">–</td></tr>
                        <tr><td><?= lang('Printjobs.est_spoilage') ?></td><td class="text-right" id="est_spoil">–</td></tr>
                        <tr class="active"><td><strong><?= lang('Printjobs.est_total_cost') ?></strong></td><td class="text-right"><strong id="est_total">–</strong></td></tr>
                        <tr><td><?= lang('Printjobs.suggested_price') ?></td><td class="text-right" id="est_price">–</td></tr>
                        <tr><td><?= lang('Printjobs.floor_price') ?></td><td class="text-right" id="est_floor">–</td></tr>
                        <tr><td><strong><?= lang('Printjobs.est_margin') ?></strong></td><td class="text-right"><strong id="est_margin">–</strong> <span class="text-muted" id="est_margin_pct"></span></td></tr>
                    </table>
                    <table class="table table-condensed table-striped" id="est_channels" style="margin-bottom:0; font-size:11px;"></table>
                </div>
            </div>

            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4"><?= lang('Printjobs.unit_cost') ?></label>
                <div class="col-xs-4">
                    <input type="number" step="0.01" name="unit_cost" id="unit_cost" class="form-control input-sm" value="<?= $job->unit_cost ?? 0 ?>">
                </div>
            </div>

            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4"><strong><?= lang('Printjobs.amount_due') ?></strong></label>
                <div class="col-xs-4">
                    <input type="number" step="0.01" name="total_cost" id="total_cost" class="form-control input-sm" style="font-weight: bold; font-size: 14px;" value="<?= $job->total_cost ?? 0 ?>">
                </div>
            </div>

            <div class="form-group form-group-sm">
                <label class="control-label col-xs-4">Ticket Ref</label>
                <div class="col-xs-7">
                    <input type="text" name="ticket_ref" class="form-control input-sm" value="<?= esc($job->ticket_ref ?? '') ?>" placeholder="External reference (optional)">
                </div>
            </div>
        </fieldset>
    </div>
</div>

<?php if (! empty($pricing)): ?>
<div class="row" style="margin-top:10px;">
    <div class="col-xs-12">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Quick Pricing</strong> — click a row to auto-fill</div>
            <div class="panel-body" style="padding:0;">
                <table class="table table-striped table-condensed table-hover" style="margin-bottom:0;">
                    <thead>
                        <tr><th>Name</th><th>Type</th><th>Material</th><th>Size</th><th>Min Qty</th><th>Price/Unit</th><th>Setup Fee</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pricing as $p): ?>
                        <tr class="pricing-row" style="cursor:pointer;"
                            data-type="<?= esc($p->job_type) ?>"
                            data-price="<?= $p->price_per_unit ?>"
                            data-material="<?= esc($p->material ?? '') ?>">
                            <td><?= esc($p->name) ?></td>
                            <td><?= lang('Printjobs.' . $p->job_type) ?></td>
                            <td><?= esc($p->material ?? '-') ?></td>
                            <td><?= esc($p->size_description ?? '-') ?></td>
                            <td><?= (int) $p->min_quantity ?></td>
                            <td><?= to_currency($p->price_per_unit) ?></td>
                            <td><?= to_currency($p->setup_fee) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row" style="margin-top: 15px;">
    <div class="col-xs-12 text-center">
        <button type="submit" class="btn btn-success btn-lg">
            <i class="glyphicon glyphicon-ok"></i> <?= lang('Common.save') ?>
        </button>
        <a href="<?= base_url('printjobs') ?>" class="btn btn-default btn-lg">
            <?= lang('Common.cancel') ?>
        </a>
    </div>
</div>

<?= form_close() ?>

<script type="text/javascript">
$(document).ready(function() {
    var csrfName = '<?= csrf_token() ?>';

    // Auto-calculate total
    $('#quantity, #unit_cost').on('input', function() {
        var qty = parseFloat($('#quantity').val()) || 0;
        var price = parseFloat($('#unit_cost').val()) || 0;
        $('#total_cost').val((qty * price).toFixed(2));
    });

    // Quick pricing — click to fill
    $('.pricing-row').on('click', function() {
        var price = parseFloat($(this).data('price')) || 0;
        var material = $(this).data('material') || '';
        $('#unit_cost').val(price.toFixed(2));
        if (material) {
            $('input[name="material"]').val(material);
        }
        var qty = parseFloat($('#quantity').val()) || 1;
        $('#total_cost').val((qty * price).toFixed(2));
    });

    // ── Ink + suggested-price estimate ─────────────────────────────
    var lastSuggested = 0;

    function money(n) { return (parseFloat(n) || 0).toFixed(2); }

    function toggleSheets() {
        var basis = $('#process_id option:selected').data('basis');
        $('#sheets_wrap').toggle(basis === 'per_sheet');
    }

    function refreshEstimate() {
        var processId = parseInt($('#process_id').val(), 10) || 0;
        if (!processId) { $('#estimate_panel').hide(); return; }

        var payload = {
            process_id:            processId,
            width_mm:              $('input[name="width_mm"]').val() || 0,
            height_mm:             $('input[name="height_mm"]').val() || 0,
            quantity:              $('#quantity').val() || 1,
            coverage_pct:          $('#coverage_pct').val() || '',
            sheets:                $('#sheets').val() || '',
            substrate_material_id: $('#substrate_material_id').val() || '',
            charged_price:         $('#total_cost').val() || ''
        };
        payload[csrfName] = $('#printjob_form input[name="' + csrfName + '"]').val();

        $.post('<?= site_url('printjobs/estimate') ?>', payload, function(res) {
            if (!res || !res.success) { $('#estimate_panel').hide(); return; }
            var e = res.estimate;
            lastSuggested = e.suggested_price;

            $('#est_area').text((e.area_m2 || 0).toFixed(4));
            $('#est_ml').text((e.total_ml || 0).toFixed(1));
            $('#est_cost').text(money(e.total_ink_cost));
            $('#est_sub_m2').text(e.substrate ? (e.substrate.m2 || 0).toFixed(3) : '0');
            $('#est_sub_cost').text(money(e.substrate_cost));
            $('#est_oh').text(money(e.overhead_cost));
            $('#est_spoil').text(money(e.spoilage_cost));
            $('#est_total').text(money(e.total_cost));
            $('#est_price').text(money(e.suggested_price));
            $('#est_floor').text(money(e.floor_price));
            $('#est_margin').text(money(e.margin));
            $('#est_margin_pct').text('(' + (e.margin_pct || 0).toFixed(1) + '%)');
            $('#est_margin').css('color', (e.margin < 0) ? '#a94442' : '');

            var rows = '<tr><th>Line</th><th class="text-right">Qty</th><th class="text-right">Cost</th></tr>';
            (e.channels || []).forEach(function(c) {
                rows += '<tr><td>' + c.channel + (c.material_name ? '' : ' <span class=\"text-danger\">(no material)</span>') +
                        '</td><td class="text-right">' + (c.ml || 0).toFixed(2) + ' ml' +
                        '</td><td class="text-right">' + money(c.cost) + '</td></tr>';
            });
            if (e.substrate) {
                rows += '<tr><td>' + (e.substrate.material_name || 'substrate') +
                        '</td><td class="text-right">' + (e.substrate.m2 || 0).toFixed(3) + ' m²' +
                        '</td><td class="text-right">' + money(e.substrate_cost) + '</td></tr>';
            }
            $('#est_channels').html(rows);
            $('#estimate_panel').show();
        }, 'json');
    }

    $('#apply_price_btn').on('click', function() {
        if (lastSuggested > 0) {
            $('#total_cost').val(lastSuggested.toFixed(2));
            var qty = parseFloat($('#quantity').val()) || 1;
            $('#unit_cost').val((lastSuggested / qty).toFixed(2));
            refreshEstimate();
        }
    });

    $('#process_id, #substrate_material_id').on('change', function() { toggleSheets(); refreshEstimate(); });
    $('#coverage_pct, #sheets, #quantity, #total_cost, input[name="width_mm"], input[name="height_mm"]')
        .on('input change', refreshEstimate);

    toggleSheets();
    refreshEstimate();
});
</script>

<?= view('partial/footer') ?>
