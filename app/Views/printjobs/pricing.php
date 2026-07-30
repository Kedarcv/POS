<?php
/**
 * Print Pricing Management
 *
 * @var string $title
 * @var array $grouped
 * @var array $job_types
 */

$type_labels = [
    'banner' => 'Banner', 'brochure' => 'Brochure', 'business_card' => 'Business Card',
    'branded_merch' => 'Branded Merch', 'large_format' => 'Large Format', 'sticker' => 'Sticker',
    'poster' => 'Poster', 'flyer' => 'Flyer', 'booklet' => 'Booklet', 'other' => 'Other',
];
?>

<?= view('partial/header') ?>

<div class="row" style="margin-bottom: 12px;">
    <div class="col-xs-12">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">
                <a href="<?= base_url('printjobs') ?>" class="text-muted" style="text-decoration:none;"><i class="bi bi-arrow-left"></i></a>
                <i class="bi bi-currency-dollar"></i> <?= esc($title) ?>
            </h3>
            <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#addPricingModal">
                <i class="bi bi-plus-lg"></i> Add Pricing
            </button>
        </div>
    </div>
</div>

<?php foreach ($job_types as $type): ?>
    <?php $items = $grouped[$type] ?? []; ?>
    <div class="row" style="margin-bottom: 15px;">
        <div class="col-xs-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <strong><?= $type_labels[$type] ?? ucfirst($type) ?></strong>
                    <span class="badge"><?= count($items) ?></span>
                </div>
                <?php if (! empty($items)): ?>
                <div class="panel-body" style="padding:0;">
                    <table class="table table-striped table-condensed" style="margin-bottom:0;">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Material</th>
                                <th>Size</th>
                                <th>Min Qty</th>
                                <th>Price/Unit</th>
                                <th>Setup Fee</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $p): ?>
                            <tr>
                                <td><strong><?= esc($p->name) ?></strong></td>
                                <td><?= esc($p->material ?? '-') ?></td>
                                <td><?= esc($p->size_description ?? '-') ?></td>
                                <td><?= (int) $p->min_quantity ?></td>
                                <td><?= to_currency($p->price_per_unit) ?></td>
                                <td><?= to_currency($p->setup_fee) ?></td>
                                <td>
                                    <button class="btn btn-xs btn-default edit-pricing"
                                        data-id="<?= $p->id ?>"
                                        data-name="<?= esc($p->name) ?>"
                                        data-type="<?= $p->job_type ?>"
                                        data-material="<?= esc($p->material ?? '') ?>"
                                        data-size="<?= esc($p->size_description ?? '') ?>"
                                        data-minqty="<?= $p->min_quantity ?>"
                                        data-price="<?= $p->price_per_unit ?>"
                                        data-fee="<?= $p->setup_fee ?>"
                                        title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?= form_open('printjobs/delete_pricing/' . $p->id, 'style="display:inline;" onsubmit="return confirm(\'Delete?\');"') ?>
                                        <button type="submit" class="btn btn-xs btn-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    <?= form_close() ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="panel-body text-muted text-center">No pricing entries for this type.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Add/Edit Modal -->
<div class="modal fade" id="addPricingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <?= form_open('printjobs/save_pricing', ['class' => 'form-horizontal']) ?>
            <input type="hidden" name="id" id="pricing_id" value="">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="pricingModalTitle">Add Pricing</h4>
            </div>
            <div class="modal-body">
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Name</label>
                    <div class="col-xs-7"><input type="text" name="name" id="pricing_name" class="form-control input-sm" required></div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Job Type</label>
                    <div class="col-xs-7">
                        <select name="job_type" id="pricing_type" class="form-control input-sm" required>
                            <?php foreach ($job_types as $t): ?>
                                <option value="<?= $t ?>"><?= $type_labels[$t] ?? ucfirst($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Material</label>
                    <div class="col-xs-7"><input type="text" name="material" id="pricing_material" class="form-control input-sm"></div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Size Description</label>
                    <div class="col-xs-7"><input type="text" name="size_description" id="pricing_size" class="form-control input-sm" placeholder="e.g. A4, 3x2m"></div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Min Quantity</label>
                    <div class="col-xs-3"><input type="number" name="min_quantity" id="pricing_minqty" class="form-control input-sm" value="1" min="1"></div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Price Per Unit</label>
                    <div class="col-xs-4"><input type="number" step="0.01" name="price_per_unit" id="pricing_price" class="form-control input-sm" required></div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Setup Fee</label>
                    <div class="col-xs-4"><input type="number" step="0.01" name="setup_fee" id="pricing_fee" class="form-control input-sm" value="0"></div>
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
    $('.edit-pricing').on('click', function() {
        $('#pricingModalTitle').text('Edit Pricing');
        $('#pricing_id').val($(this).data('id'));
        $('#pricing_name').val($(this).data('name'));
        $('#pricing_type').val($(this).data('type'));
        $('#pricing_material').val($(this).data('material'));
        $('#pricing_size').val($(this).data('size'));
        $('#pricing_minqty').val($(this).data('minqty'));
        $('#pricing_price').val($(this).data('price'));
        $('#pricing_fee').val($(this).data('fee'));
        $('#addPricingModal').modal('show');
    });

    $('#addPricingModal').on('hidden.bs.modal', function() {
        $('#pricingModalTitle').text('Add Pricing');
        $('#pricing_id').val('');
    });
});
</script>

<?= view('partial/footer') ?>
