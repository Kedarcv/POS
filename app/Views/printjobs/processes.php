<?php
/**
 * Print Process Types — DTF / UV / Xerox / Eco-solvent
 *
 * @var string $title
 * @var array  $processes       list of process objects, each with ->channels
 * @var array  $pricing_bases   code => label
 * @var array  $channels        allowed channel names
 * @var array  $materials       print_materials rows (for the channel -> material picker)
 */
?>
<?= view('partial/header') ?>

<div class="row" style="margin-bottom: 12px;">
    <div class="col-xs-12">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0;">
                <a href="<?= base_url('printjobs') ?>" class="text-muted" style="text-decoration:none;"><i class="bi bi-arrow-left"></i></a>
                <i class="bi bi-sliders"></i> <?= esc($title) ?>
            </h3>
            <button class="btn btn-success btn-sm" id="add_process_btn"><i class="bi bi-plus-lg"></i> Add Process</button>
        </div>
    </div>
</div>

<?php if (session('message')): ?>
    <div class="alert alert-success"><?= esc(session('message')) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-xs-12">
        <?php foreach ($processes as $p): ?>
            <div class="panel panel-default">
                <div class="panel-heading" style="display:flex; justify-content:space-between; align-items:center;">
                    <strong><?= esc($p->name) ?></strong>
                    <span>
                        <span class="label label-<?= $p->is_active ? 'success' : 'default' ?>"><?= $p->is_active ? 'Active' : 'Inactive' ?></span>
                        <span class="label label-info"><?= esc($pricing_bases[$p->pricing_basis] ?? $p->pricing_basis) ?></span>
                        <button class="btn btn-xs btn-default edit-process"
                            data-json='<?= esc(json_encode($p), 'attr') ?>'><i class="bi bi-pencil"></i> Edit</button>
                        <?= form_open('printjobs/delete_process/' . $p->id, 'style="display:inline;" onsubmit="return confirm(\'Delete this process?\');"') ?>
                            <button type="submit" class="btn btn-xs btn-danger"><i class="bi bi-trash"></i></button>
                        <?= form_close() ?>
                    </span>
                </div>
                <div class="panel-body">
                    <div class="row" style="margin-bottom:8px;">
                        <div class="col-xs-4"><small class="text-muted">Sell rate</small><br><strong><?= to_currency($p->sell_rate) ?></strong> / <?= $p->pricing_basis === 'per_sheet' ? 'sheet' : 'm²' ?></div>
                        <div class="col-xs-2"><small class="text-muted">Min charge</small><br><?= to_currency($p->min_charge) ?></div>
                        <div class="col-xs-2"><small class="text-muted">Setup</small><br><?= to_currency($p->setup_fee) ?></div>
                        <div class="col-xs-2"><small class="text-muted">Waste</small><br><?= (float) $p->waste_pct ?>%</div>
                        <div class="col-xs-2"><small class="text-muted">Def. coverage</small><br><?= (float) $p->default_coverage_pct ?>%</div>
                    </div>
                    <table class="table table-condensed table-striped" style="margin-bottom:0;">
                        <thead><tr><th>Channel</th><th>Stock material</th><th class="text-right">ml / m² (@100%)</th><th class="text-right">Unit cost</th></tr></thead>
                        <tbody>
                        <?php if (empty($p->channels)): ?>
                            <tr><td colspan="4" class="text-muted text-center">No channels configured — ink won't be estimated for this process.</td></tr>
                        <?php else: foreach ($p->channels as $c): ?>
                            <tr>
                                <td><?= esc($c->channel) ?></td>
                                <td>
                                    <?= esc($c->material_name ?? '—') ?>
                                    <?php if (! empty($c->material_id) && empty($c->material_item_id)): ?>
                                        <span class="label label-warning" style="font-size:0.7em;">not linked to POS stock</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right"><?= (float) $c->ml_per_sqm ?></td>
                                <td class="text-right"><?= isset($c->material_unit_cost) ? to_currency($c->material_unit_cost) : '—' ?>/ml</td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($processes)): ?>
            <div class="panel panel-default"><div class="panel-body text-center text-muted">No process types yet. Click “Add Process”.</div></div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit modal -->
<div class="modal fade" id="processModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <?= form_open('printjobs/save_process', ['class' => 'form-horizontal', 'id' => 'process_form']) ?>
        <input type="hidden" name="id" id="p_id" value="">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal">&times;</button>
            <h4 class="modal-title" id="p_modal_title">Add Process</h4>
        </div>
        <div class="modal-body">
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-4">Name</label>
                        <div class="col-xs-8"><input type="text" name="name" id="p_name" class="form-control input-sm" required placeholder="DTF"></div>
                    </div>
                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-4">Code</label>
                        <div class="col-xs-8"><input type="text" name="code" id="p_code" class="form-control input-sm" placeholder="dtf (auto from name)"></div>
                    </div>
                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-4"><?= lang('Printjobs.pricing_basis') ?></label>
                        <div class="col-xs-8">
                            <select name="pricing_basis" id="p_basis" class="form-control input-sm">
                                <?php foreach ($pricing_bases as $code => $label): ?>
                                    <option value="<?= esc($code) ?>"><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-4"><?= lang('Printjobs.sell_rate') ?></label>
                        <div class="col-xs-8"><input type="number" step="0.0001" name="sell_rate" id="p_rate" class="form-control input-sm" value="0"></div>
                    </div>
                    <div class="form-group form-group-sm" id="p_sheet_area_wrap" style="display:none;">
                        <label class="control-label col-xs-4">Sheet area (m²)</label>
                        <div class="col-xs-8"><input type="number" step="0.000001" name="sheet_area_m2" id="p_sheet_area" class="form-control input-sm" placeholder="A4 = 0.06237"></div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-4"><?= lang('Printjobs.min_charge') ?></label>
                        <div class="col-xs-8"><input type="number" step="0.01" name="min_charge" id="p_min" class="form-control input-sm" value="0"></div>
                    </div>
                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-4">Setup fee</label>
                        <div class="col-xs-8"><input type="number" step="0.01" name="setup_fee" id="p_setup" class="form-control input-sm" value="0"></div>
                    </div>
                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-4"><?= lang('Printjobs.waste_pct') ?></label>
                        <div class="col-xs-8"><input type="number" step="0.1" name="waste_pct" id="p_waste" class="form-control input-sm" value="10"></div>
                    </div>
                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-4">Default coverage %</label>
                        <div class="col-xs-8"><input type="number" step="1" name="default_coverage_pct" id="p_cov" class="form-control input-sm" value="70"></div>
                    </div>
                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-4">&nbsp;</label>
                        <div class="col-xs-8"><label><input type="checkbox" name="is_active" id="p_active" value="1" checked> Active</label></div>
                    </div>
                </div>
            </div>

            <hr>
            <p>
                <strong>Economics</strong> — leave any field blank to inherit the
                <a href="<?= base_url('printjobs/settings') ?>" target="_blank">shop default</a>
                (currently:
                overhead <?= to_currency($shop_defaults['overhead_per_sqm']) ?>/m² +
                <?= to_currency($shop_defaults['overhead_per_job']) ?>/job +
                <?= (float) $shop_defaults['overhead_pct'] ?>%,
                spoilage <?= (float) $shop_defaults['spoilage_pct'] ?>%,
                target margin <?= (float) $shop_defaults['target_margin_pct'] ?>%).
            </p>
            <div class="row">
                <div class="col-sm-4"><div class="form-group form-group-sm">
                    <label class="control-label col-xs-6"><?= lang('Printjobs.overhead_per_sqm') ?></label>
                    <div class="col-xs-6"><input type="number" step="0.0001" name="overhead_per_sqm" id="p_oh_sqm" class="form-control input-sm" placeholder="default"></div>
                </div></div>
                <div class="col-sm-4"><div class="form-group form-group-sm">
                    <label class="control-label col-xs-6"><?= lang('Printjobs.overhead_per_job') ?></label>
                    <div class="col-xs-6"><input type="number" step="0.01" name="overhead_per_job" id="p_oh_job" class="form-control input-sm" placeholder="default"></div>
                </div></div>
                <div class="col-sm-4"><div class="form-group form-group-sm">
                    <label class="control-label col-xs-6"><?= lang('Printjobs.overhead_pct') ?></label>
                    <div class="col-xs-6"><input type="number" step="0.1" name="overhead_pct" id="p_oh_pct" class="form-control input-sm" placeholder="default"></div>
                </div></div>
                <div class="col-sm-4"><div class="form-group form-group-sm">
                    <label class="control-label col-xs-6"><?= lang('Printjobs.spoilage_pct') ?></label>
                    <div class="col-xs-6"><input type="number" step="0.1" name="spoilage_pct" id="p_spoil" class="form-control input-sm" placeholder="default"></div>
                </div></div>
                <div class="col-sm-4"><div class="form-group form-group-sm">
                    <label class="control-label col-xs-6"><?= lang('Printjobs.target_margin_pct') ?></label>
                    <div class="col-xs-6"><input type="number" step="0.1" name="target_margin_pct" id="p_margin" class="form-control input-sm" placeholder="default"></div>
                </div></div>
            </div>

            <hr>
            <p><strong>Ink channels</strong> — ml consumed per m² at 100% coverage, and the stock material each draws from.</p>
            <table class="table table-condensed" id="p_channels">
                <thead><tr><th style="width:30%;">Channel</th><th style="width:45%;">Stock material</th><th style="width:20%;">ml / m²</th><th></th></tr></thead>
                <tbody></tbody>
            </table>
            <button type="button" class="btn btn-xs btn-default" id="p_add_channel"><i class="bi bi-plus-lg"></i> Add channel</button>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success btn-sm">Save</button>
        </div>
      <?= form_close() ?>
    </div>
  </div>
</div>

<script type="text/javascript">
$(function () {
    var CHANNELS  = <?= json_encode($channels) ?>;
    var MATERIALS = <?= json_encode(array_map(fn ($m) => ['id' => (int) $m->id, 'name' => $m->name, 'unit' => $m->unit], $materials)) ?>;

    function channelRow(sel) {
        sel = sel || {};
        var chOpts = '<option value="">--</option>' + CHANNELS.map(function (c) {
            return '<option value="' + c + '"' + (c === sel.channel ? ' selected' : '') + '>' + c + '</option>';
        }).join('');
        var matOpts = '<option value="">-- none --</option>' + MATERIALS.map(function (m) {
            return '<option value="' + m.id + '"' + (parseInt(sel.material_id, 10) === m.id ? ' selected' : '') +
                   '>' + m.name + ' (' + m.unit + ')</option>';
        }).join('');
        return '<tr>' +
            '<td><select name="channel[]" class="form-control input-sm">' + chOpts + '</select></td>' +
            '<td><select name="material_id[]" class="form-control input-sm">' + matOpts + '</select></td>' +
            '<td><input type="number" step="0.0001" name="ml_per_sqm[]" class="form-control input-sm" value="' + (sel.ml_per_sqm || 0) + '"></td>' +
            '<td><button type="button" class="btn btn-xs btn-danger p-del-channel">&times;</button></td>' +
        '</tr>';
    }

    $('#p_add_channel').on('click', function () { $('#p_channels tbody').append(channelRow()); });
    $('#p_channels').on('click', '.p-del-channel', function () { $(this).closest('tr').remove(); });

    function toggleSheetArea() { $('#p_sheet_area_wrap').toggle($('#p_basis').val() === 'per_sheet'); }
    $('#p_basis').on('change', toggleSheetArea);

    function resetModal() {
        $('#process_form')[0].reset();
        $('#p_id').val('');
        $('#p_channels tbody').empty();
    }

    $('#add_process_btn').on('click', function () {
        resetModal();
        $('#p_modal_title').text('Add Process');
        $('#p_channels tbody').append(channelRow());
        toggleSheetArea();
        $('#processModal').modal('show');
    });

    $('.edit-process').on('click', function () {
        resetModal();
        var p = $(this).data('json');
        $('#p_modal_title').text('Edit ' + p.name);
        $('#p_id').val(p.id);
        $('#p_name').val(p.name);
        $('#p_code').val(p.code);
        $('#p_basis').val(p.pricing_basis);
        $('#p_rate').val(p.sell_rate);
        $('#p_min').val(p.min_charge);
        $('#p_setup').val(p.setup_fee);
        $('#p_waste').val(p.waste_pct);
        $('#p_cov').val(p.default_coverage_pct);
        $('#p_sheet_area').val(p.sheet_area_m2);
        $('#p_oh_sqm').val(p.overhead_per_sqm);
        $('#p_oh_job').val(p.overhead_per_job);
        $('#p_oh_pct').val(p.overhead_pct);
        $('#p_spoil').val(p.spoilage_pct);
        $('#p_margin').val(p.target_margin_pct);
        $('#p_active').prop('checked', parseInt(p.is_active, 10) === 1);
        (p.channels || []).forEach(function (c) { $('#p_channels tbody').append(channelRow(c)); });
        if (!(p.channels || []).length) { $('#p_channels tbody').append(channelRow()); }
        toggleSheetArea();
        $('#processModal').modal('show');
    });
});
</script>

<?= view('partial/footer') ?>
