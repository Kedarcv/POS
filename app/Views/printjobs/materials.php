<?php
/**
 * Print Materials Inventory
 *
 * @var string $title
 * @var array $grouped
 * @var array $low_stock
 * @var array $categories
 * @var array $units
 */

$category_icons = [
    'paper' => 'bi-file-earmark', 'vinyl' => 'bi-signpost', 'ink' => 'bi-droplet',
    'toner' => 'bi-circle', 'laminate' => 'bi-layers', 'substrate' => 'bi-grid',
    'other' => 'bi-box',
];
?>

<?= view('partial/header') ?>

<div class="row" style="margin-bottom: 12px;">
    <div class="col-xs-12">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">
                <a href="<?= base_url('printjobs') ?>" class="text-muted" style="text-decoration:none;"><i class="bi bi-arrow-left"></i></a>
                <i class="bi bi-box-seam"></i> <?= esc($title) ?>
            </h3>
            <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#addMaterialModal">
                <i class="bi bi-plus-lg"></i> Add Material
            </button>
        </div>
    </div>
</div>

<?php if (! empty($low_stock)): ?>
<div class="row" style="margin-bottom: 12px;">
    <div class="col-xs-12">
        <div class="alert alert-warning" style="margin-bottom:0;">
            <strong><i class="bi bi-exclamation-triangle"></i> Low Stock Alert:</strong>
            <?= count($low_stock) ?> item(s) are at or below reorder level.
        </div>
    </div>
</div>
<?php endif; ?>

<?php foreach ($grouped as $category => $items): ?>
    <?php if (empty($items)): continue; endif; ?>
    <div class="row" style="margin-bottom: 15px;">
        <div class="col-xs-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <strong><i class="bi <?= $category_icons[$category] ?? 'bi-box' ?>"></i> <?= ucfirst($category) ?></strong>
                    <span class="badge"><?= count($items) ?></span>
                </div>
                <div class="panel-body" style="padding:0;">
                    <table class="table table-striped table-condensed" style="margin-bottom:0;">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>SKU</th>
                                <th>Stock</th>
                                <th>Reorder Level</th>
                                <th>Unit Cost</th>
                                <th>Supplier</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $m): ?>
                            <tr class="<?= $m->current_stock <= $m->reorder_level ? 'warning' : '' ?>">
                                <td><strong><?= esc($m->name) ?></strong></td>
                                <td><?= esc($m->sku ?? '-') ?></td>
                                <td>
                                    <?= number_format($m->current_stock, 2) ?> <?= esc($m->unit) ?>
                                    <?php if ($m->current_stock <= $m->reorder_level): ?>
                                        <span class="label label-danger" style="font-size:0.7em;">Low</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($m->reorder_level, 2) ?></td>
                                <td><?= to_currency($m->unit_cost) ?></td>
                                <td><?= esc($m->supplier ?? '-') ?></td>
                                <td>
                                    <button class="btn btn-xs btn-default edit-material"
                                        data-id="<?= $m->id ?>"
                                        data-name="<?= esc($m->name) ?>"
                                        data-sku="<?= esc($m->sku ?? '') ?>"
                                        data-category="<?= $m->category ?>"
                                        data-unit="<?= $m->unit ?>"
                                        data-stock="<?= $m->current_stock ?>"
                                        data-reorder="<?= $m->reorder_level ?>"
                                        data-cost="<?= $m->unit_cost ?>"
                                        data-supplier="<?= esc($m->supplier ?? '') ?>"
                                        title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?= form_open('printjobs/delete_material/' . $m->id, 'style="display:inline;" onsubmit="return confirm(\'Delete?\');"') ?>
                                        <button type="submit" class="btn btn-xs btn-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    <?= form_close() ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if (empty($grouped) || ! array_filter($grouped)): ?>
<div class="row">
    <div class="col-xs-12">
        <div class="panel panel-default">
            <div class="panel-body text-center text-muted">
                No materials in inventory yet. Click "Add Material" to get started.
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Add/Edit Modal -->
<div class="modal fade" id="addMaterialModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <?= form_open('printjobs/save_material', ['class' => 'form-horizontal']) ?>
            <input type="hidden" name="id" id="material_id" value="">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="modalTitle">Add Material</h4>
            </div>
            <div class="modal-body">
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Name</label>
                    <div class="col-xs-7"><input type="text" name="name" id="material_name" class="form-control input-sm" required></div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">SKU</label>
                    <div class="col-xs-7"><input type="text" name="sku" id="material_sku" class="form-control input-sm"></div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Category</label>
                    <div class="col-xs-7">
                        <select name="category" id="material_category" class="form-control input-sm">
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c ?>"><?= ucfirst($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Unit</label>
                    <div class="col-xs-7">
                        <select name="unit" id="material_unit" class="form-control input-sm">
                            <?php foreach ($units as $u): ?>
                                <option value="<?= $u ?>"><?= ucfirst($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Current Stock</label>
                    <div class="col-xs-4"><input type="number" step="0.01" name="current_stock" id="material_stock" class="form-control input-sm" value="0"></div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Reorder Level</label>
                    <div class="col-xs-4"><input type="number" step="0.01" name="reorder_level" id="material_reorder" class="form-control input-sm" value="10"></div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Unit Cost</label>
                    <div class="col-xs-4"><input type="number" step="0.01" name="unit_cost" id="material_cost" class="form-control input-sm" value="0"></div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Supplier</label>
                    <div class="col-xs-7"><input type="text" name="supplier" id="material_supplier" class="form-control input-sm"></div>
                </div>
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
$(document).ready(function() {
    $('.edit-material').on('click', function() {
        $('#modalTitle').text('Edit Material');
        $('#material_id').val($(this).data('id'));
        $('#material_name').val($(this).data('name'));
        $('#material_sku').val($(this).data('sku'));
        $('#material_category').val($(this).data('category'));
        $('#material_unit').val($(this).data('unit'));
        $('#material_stock').val($(this).data('stock'));
        $('#material_reorder').val($(this).data('reorder'));
        $('#material_cost').val($(this).data('cost'));
        $('#material_supplier').val($(this).data('supplier'));
        $('#addMaterialModal').modal('show');
    });

    // Reset modal on close
    $('#addMaterialModal').on('hidden.bs.modal', function() {
        $('#modalTitle').text('Add Material');
        $('#material_id').val('');
        $('#material_form')[0].reset();
    });
});
</script>

<?= view('partial/footer') ?>
