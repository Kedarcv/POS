<?php
/**
 * @var string $title
 * @var string $subtitle
 * @var string $location_name
 * @var array $inputs
 * @var array $selected_fields
 * @var array $available_fields
 * @var array $columns
 * @var array $data
 * @var bool $hide_quantities
 * @var array $config
 */
?>

<?= view('partial/header') ?>

<style type="text/css">
    .stock-take-sheet {
        background: #ffffff;
        color: #222222;
        padding: 15px;
    }

    .stock-take-header-meta {
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #333333;
    }

    .stock-take-meta-box {
        background: #fbfbfb;
        border: 1px solid #dcdcdc;
        border-radius: 4px;
        padding: 10px 14px;
        margin-top: 10px;
        font-size: 13px;
    }

    .stock-take-table {
        width: 100%;
        margin-top: 15px;
        border-collapse: collapse;
        font-size: 12px;
    }

    .stock-take-table th,
    .stock-take-table td {
        border: 1px solid #c0c0c0;
        padding: 6px 8px;
        vertical-align: middle;
    }

    .stock-take-table th {
        background: #f1f3f5;
        font-weight: bold;
        text-align: left;
    }

    .stock-take-write-in {
        min-width: 90px;
        height: 24px;
        border-bottom: 1px dashed #666666;
        display: inline-block;
        width: 100%;
    }

    .stock-take-box {
        min-width: 80px;
        height: 22px;
        border: 1px solid #999999;
        background: #fafafa;
        display: inline-block;
        width: 100%;
    }

    @media print {
        body {
            background: #ffffff !important;
            color: #000000 !important;
            font-size: 11pt;
        }

        .print_hide,
        #menubar,
        #footer,
        .navbar,
        header {
            display: none !important;
        }

        #content_area {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }

        .stock-take-sheet {
            padding: 0 !important;
        }

        .stock-take-table {
            font-size: 10pt;
        }

        .stock-take-table th,
        .stock-take-table td {
            border: 1px solid #333333 !important;
            padding: 4px 6px !important;
        }

        .stock-take-table th {
            background: #eaeaea !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        tr {
            page-break-inside: avoid;
        }

        thead {
            display: table-header-group;
        }
    }
</style>

<div class="stock-take-sheet">
    <div class="pull-right print_hide" style="margin-bottom: 15px;">
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print();">
            <span class="glyphicon glyphicon-print">&nbsp;</span><?= lang('Common.print') ?>
        </button>
        <?php
        $exportParams = http_build_query([
            'location_id'     => $inputs['location_id'] ?? 'all',
            'category'        => $inputs['category'] ?? 'all',
            'item_count'      => $inputs['item_count'] ?? 'all',
            'sort_by'         => $inputs['sort_by'] ?? 'name',
            'hide_quantities' => $hide_quantities ? '1' : '0',
            'fields'          => $selected_fields,
        ]);
        ?>
        <a href="<?= site_url('reports/stock_take_export?' . $exportParams) ?>" class="btn btn-success btn-sm">
            <span class="glyphicon glyphicon-download-alt">&nbsp;</span><?= lang('Reports.stock_take_export_csv') ?>
        </a>
        <a href="<?= site_url('reports/stock_take') ?>" class="btn btn-default btn-sm">
            <span class="glyphicon glyphicon-cog">&nbsp;</span><?= lang('Reports.stock_take_configure') ?>
        </a>
    </div>

    <div class="stock-take-header-meta">
        <h2 style="margin: 0 0 5px 0; font-weight: bold;"><?= esc($title) ?></h2>
        <div style="font-size: 14px; color: #555555;">
            <strong><?= lang('Reports.stock_location') ?>:</strong> <?= esc($location_name) ?>
            <?php if (!empty($inputs['category']) && $inputs['category'] !== 'all'): ?>
                &nbsp;|&nbsp; <strong><?= lang('Reports.category') ?>:</strong> <?= esc($inputs['category']) ?>
            <?php endif; ?>
            &nbsp;|&nbsp; <strong><?= lang('Reports.date') ?>:</strong> <?= date($config['dateformat'] . ' ' . $config['timeformat']) ?>
            &nbsp;|&nbsp; <strong><?= lang('Reports.stock_take_total_items') ?>:</strong> <?= count($data) ?>
        </div>

        <div class="row stock-take-meta-box">
            <div class="col-xs-4">
                <strong>Counted By:</strong> ____________________________________
            </div>
            <div class="col-xs-4">
                <strong>Verified By:</strong> ____________________________________
            </div>
            <div class="col-xs-4">
                <strong>Date &amp; Sign:</strong> ____________________________________
            </div>
        </div>
    </div>

    <?php if (empty($data)): ?>
        <div class="alert alert-warning">
            No items matched your selected stock location and filter criteria.
        </div>
    <?php else: ?>
        <table class="stock-take-table table table-bordered table-condensed">
            <thead>
                <tr>
                    <th style="width: 35px; text-align: center;">#</th>
                    <?php foreach ($selected_fields as $field): ?>
                        <th><?= esc($available_fields[$field]['label'] ?? $field) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($data as $row): ?>
                    <tr>
                        <td style="text-align: center; color: #777777;"><?= $i++ ?></td>
                        <?php foreach ($selected_fields as $field): ?>
                            <td>
                                <?php
                                match ($field) {
                                    'item_name'      => print esc($row['name'] ?? ''),
                                    'item_number'    => print esc($row['item_number'] ?? ''),
                                    'category'       => print esc($row['category'] ?? ''),
                                    'location_name'  => print esc($row['location_name'] ?? ''),
                                    'quantity'       => print $hideQuantities ? '<span style="color:#aaa;">[ &nbsp;&nbsp;&nbsp;&nbsp; ]</span>' : esc(to_quantity_decimals($row['quantity'] ?? 0)),
                                    'physical_count' => print '<span class="stock-take-write-in"></span>',
                                    'variance'       => print '<span class="stock-take-write-in"></span>',
                                    'cost_price'     => print to_currency($row['cost_price'] ?? 0),
                                    'unit_price'     => print to_currency($row['unit_price'] ?? 0),
                                    'reorder_level'  => print esc(to_quantity_decimals($row['reorder_level'] ?? 0)),
                                    'qty_per_pack'   => print esc(to_quantity_decimals($row['qty_per_pack'] ?? 1)),
                                    'description'    => print esc($row['description'] ?? ''),
                                    default          => print '',
                                };
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 25px; padding-top: 15px; border-top: 1px dashed #999999; font-size: 11px; color: #555555;">
            <strong>Notes / Discrepancies:</strong>
            <div style="height: 50px; border-bottom: 1px solid #cccccc; margin-top: 10px;"></div>
        </div>
    <?php endif; ?>
</div>

<?= view('partial/footer') ?>
