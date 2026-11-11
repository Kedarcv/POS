<?php
/**
 * @var array $stock_locations
 * @var array $categories
 * @var array $item_count
 * @var array $sort_options
 * @var array $available_fields
 * @var array $default_fields
 */
?>

<?= view('partial/header') ?>

<div id="page_title"><?= lang('Reports.stock_take') ?> - <?= lang('Reports.stock_take_sheet') ?></div>

<div class="row">
    <div class="col-md-10 col-md-offset-1">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <span class="glyphicon glyphicon-list-alt">&nbsp;</span><?= lang('Reports.stock_take_configure') ?>
                </h3>
            </div>
            <div class="panel-body">
                <p class="help-block" style="margin-bottom: 20px;">
                    Select the store location, category, filters, and <strong>choose which columns/fields</strong> to include on the count sheet.
                    The generated sheet is formatted for clean printing on standard A4 / Letter paper and can also be exported to Excel / CSV.
                </p>

                <?= form_open('reports/stock_take_sheet', ['id' => 'stock_take_form', 'method' => 'get', 'class' => 'form-horizontal']) ?>

                    <div class="form-group form-group-sm">
                        <?= form_label(lang('Reports.stock_location'), 'location_id', ['class' => 'control-label col-xs-3 required']) ?>
                        <div class="col-xs-6">
                            <?= form_dropdown('location_id', $stock_locations, 'all', 'id="location_id" class="form-control input-sm"') ?>
                        </div>
                    </div>

                    <div class="form-group form-group-sm">
                        <?= form_label(lang('Reports.category'), 'category', ['class' => 'control-label col-xs-3']) ?>
                        <div class="col-xs-6">
                            <?= form_dropdown('category', $categories, 'all', 'id="category" class="form-control input-sm"') ?>
                        </div>
                    </div>

                    <div class="form-group form-group-sm">
                        <?= form_label(lang('Reports.item_count'), 'item_count', ['class' => 'control-label col-xs-3']) ?>
                        <div class="col-xs-6">
                            <?= form_dropdown('item_count', $item_count, 'all', 'id="item_count" class="form-control input-sm"') ?>
                        </div>
                    </div>

                    <div class="form-group form-group-sm">
                        <?= form_label(lang('Reports.stock_take_sort_by'), 'sort_by', ['class' => 'control-label col-xs-3']) ?>
                        <div class="col-xs-6">
                            <?= form_dropdown('sort_by', $sort_options, 'name', 'id="sort_by" class="form-control input-sm"') ?>
                        </div>
                    </div>

                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-3"><?= lang('Reports.stock_take_options') ?></label>
                        <div class="col-xs-6">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="hide_quantities" id="hide_quantities" value="1">
                                    <strong><?= lang('Reports.stock_take_blind_count') ?></strong>
                                    <span class="help-block" style="margin-top: 2px;">Leave current stock quantity blank on printout so counters must perform an independent count.</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label class="control-label col-xs-3"><?= lang('Reports.stock_take_fields') ?></label>
                        <div class="col-xs-9">
                            <div style="margin-bottom: 10px;">
                                <button type="button" class="btn btn-default btn-xs" id="btn_select_all_fields">
                                    <span class="glyphicon glyphicon-check"></span> <?= lang('Reports.stock_take_select_all') ?>
                                </button>
                                <button type="button" class="btn btn-default btn-xs" id="btn_clear_fields">
                                    <span class="glyphicon glyphicon-unchecked"></span> <?= lang('Reports.stock_take_clear_all') ?>
                                </button>
                                <button type="button" class="btn btn-default btn-xs" id="btn_reset_default_fields">
                                    <span class="glyphicon glyphicon-refresh"></span> <?= lang('Reports.stock_take_default_fields') ?>
                                </button>
                            </div>

                            <div class="row" id="fields_container">
                                <?php foreach ($available_fields as $field_key => $field_meta): ?>
                                    <div class="col-sm-6 col-md-4">
                                        <div class="checkbox" style="margin-top: 5px; margin-bottom: 5px;">
                                            <label>
                                                <input type="checkbox"
                                                       name="fields[]"
                                                       class="stock-take-field-cb"
                                                       value="<?= esc($field_key, 'attr') ?>"
                                                       data-default="<?= !empty($field_meta['default']) ? '1' : '0' ?>"
                                                       <?= in_array($field_key, $default_fields, true) ? 'checked' : '' ?>>
                                                <?= esc($field_meta['label']) ?>
                                                <?php if (!empty($field_meta['is_input'])): ?>
                                                    <span class="label label-info" style="font-size: 9px;">Write-in</span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <div class="col-xs-9 col-xs-offset-3">
                            <button type="submit" id="btn_generate" class="btn btn-primary">
                                <span class="glyphicon glyphicon-print"></span> <?= lang('Reports.stock_take_generate_sheet') ?>
                            </button>
                            <button type="button" id="btn_export_csv" class="btn btn-success" style="margin-left: 8px;">
                                <span class="glyphicon glyphicon-download-alt"></span> <?= lang('Reports.stock_take_export_csv') ?>
                            </button>
                            <a href="<?= site_url('reports') ?>" class="btn btn-default" style="margin-left: 8px;">
                                <?= lang('Common.back') ?>
                            </a>
                        </div>
                    </div>

                <?= form_close() ?>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        var $form = $('#stock_take_form');

        $('#btn_select_all_fields').click(function() {
            $('.stock-take-field-cb').prop('checked', true);
        });

        $('#btn_clear_fields').click(function() {
            $('.stock-take-field-cb').prop('checked', false);
        });

        $('#btn_reset_default_fields').click(function() {
            $('.stock-take-field-cb').each(function() {
                $(this).prop('checked', $(this).data('default') === 1 || $(this).data('default') === '1');
            });
        });

        $('#btn_export_csv').click(function(e) {
            e.preventDefault();
            var prevAction = $form.attr('action');
            $form.attr('action', "<?= site_url('reports/stock_take_export') ?>");
            $form.submit();
            $form.attr('action', prevAction);
        });

        $form.submit(function(e) {
            if ($('.stock-take-field-cb:checked').length === 0) {
                alert('<?= lang('Reports.stock_take_select_at_least_one_field') ?>');
                e.preventDefault();
                return false;
            }
        });
    });
</script>

<?= view('partial/footer') ?>
