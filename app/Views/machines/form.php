<?php
/**
 * Machine Form — Add / Edit
 *
 * @var string $title
 * @var object|null $machine
 */

$is_edit = ! empty($machine);
$type_labels = \App\Models\Machine::MACHINE_TYPES;
$status_labels = \App\Models\Machine::STATUSES;
$connection_labels = \App\Models\Machine::CONNECTION_TYPES;
?>

<?= view('partial/header') ?>

<div class="row" style="margin-bottom: 10px;">
    <div class="col-xs-12">
        <a href="<?= base_url('machines') ?>" class="btn btn-default btn-sm">
            <i class="glyphicon glyphicon-arrow-left"></i> Print Machines
        </a>
    </div>
</div>

<div id="required_fields_message"><?= lang('Common.fields_required_message') ?></div>
<ul id="error_message_box" class="error_message_box"></ul>

<?= form_open('machines/save', ['id' => 'machine_form', 'class' => 'form-horizontal']) ?>
<?php if ($is_edit): ?>
    <input type="hidden" name="id" value="<?= $machine->id ?>">
<?php endif; ?>

<div class="row">
    <div class="col-xs-12">
        <h3 class="text-center" style="margin-bottom: 5px;">
            <i class="glyphicon glyphicon-cog"></i> <?= esc($title) ?>
        </h3>
    </div>
</div>

<div class="row">
    <!-- Machine Details -->
    <div class="col-xs-12 col-md-6">
        <fieldset>
            <legend><i class="glyphicon glyphicon-cog"></i> Machine Details</legend>

            <div class="form-group">
                <label class="control-label col-xs-4">Machine Name <span class="text-danger">*</span></label>
                <div class="col-xs-7">
                    <input type="text" name="name" class="form-control" value="<?= esc($machine->name ?? '') ?>" placeholder="e.g. HP LaserJet Pro MFP" required>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-xs-4">Machine Type <span class="text-danger">*</span></label>
                <div class="col-xs-7">
                    <select name="machine_type" class="form-control" required>
                        <option value="">-- Select Type --</option>
                        <?php foreach ($type_labels as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($machine->machine_type ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-xs-4">Brand</label>
                <div class="col-xs-7">
                    <input type="text" name="brand" class="form-control" value="<?= esc($machine->brand ?? '') ?>" placeholder="e.g. HP, Canon, Epson">
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-xs-4">Model Number</label>
                <div class="col-xs-7">
                    <input type="text" name="model_number" class="form-control" value="<?= esc($machine->model_number ?? '') ?>" placeholder="e.g. MFP M428fdn">
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-xs-4">Serial Number</label>
                <div class="col-xs-7">
                    <input type="text" name="serial_number" class="form-control" value="<?= esc($machine->serial_number ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-xs-4">Status <span class="text-danger">*</span></label>
                <div class="col-xs-7">
                    <select name="status" class="form-control" required>
                        <?php foreach ($status_labels as $s): ?>
                            <option value="<?= $s ?>" <?= ($machine->status ?? 'active') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </fieldset>
    </div>

    <!-- Connection & Specs -->
    <div class="col-xs-12 col-md-6">
        <fieldset>
            <legend><i class="glyphicon glyphicon-transfer"></i> Connection & Specifications</legend>

            <div class="form-group">
                <label class="control-label col-xs-4">Connection Type</label>
                <div class="col-xs-7">
                    <select name="connection_type" class="form-control">
                        <option value="">-- Select --</option>
                        <?php foreach ($connection_labels as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($machine->connection_type ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-xs-4">IP Address</label>
                <div class="col-xs-7">
                    <input type="text" name="ip_address" class="form-control" value="<?= esc($machine->ip_address ?? '') ?>" placeholder="e.g. 192.168.1.100">
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-xs-4">Location</label>
                <div class="col-xs-7">
                    <input type="text" name="location" class="form-control" value="<?= esc($machine->location ?? '') ?>" placeholder="e.g. Front desk, Production floor">
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-xs-4">Max Width (mm)</label>
                <div class="col-xs-4">
                    <input type="number" name="max_width_mm" class="form-control" value="<?= $machine->max_width_mm ?? '' ?>" placeholder="mm">
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-xs-4">Max Height (mm)</label>
                <div class="col-xs-4">
                    <input type="number" name="max_height_mm" class="form-control" value="<?= $machine->max_height_mm ?? '' ?>" placeholder="mm">
                </div>
            </div>

            <div class="form-group">
                <label class="control-label col-xs-4">Notes</label>
                <div class="col-xs-7">
                    <textarea name="notes" class="form-control" rows="3"><?= esc($machine->notes ?? '') ?></textarea>
                </div>
            </div>
        </fieldset>
    </div>
</div>

<div class="row" style="margin-top: 15px;">
    <div class="col-xs-12 text-center">
        <button type="submit" class="btn btn-success btn-lg">
            <i class="glyphicon glyphicon-ok"></i> <?= lang('Common.save') ?>
        </button>
        <a href="<?= base_url('machines') ?>" class="btn btn-default btn-lg">
            <?= lang('Common.cancel') ?>
        </a>
    </div>
</div>

<?= form_close() ?>

<?= view('partial/footer') ?>
