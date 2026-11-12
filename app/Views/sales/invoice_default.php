<?php
/**
 * Modern A4 Tax Invoice / Receipt View
 *
 * @var string $sale_id_num
 * @var bool $print_after_sale
 * @var string $customer_info
 * @var string $company_info
 * @var string $invoice_number
 * @var string $transaction_date
 * @var string $transaction_time
 * @var float $total
 * @var bool $include_hsn
 * @var string $discount
 * @var array $cart
 * @var float $subtotal
 * @var array $taxes
 * @var array $payments
 * @var float $amount_change
 * @var string $barcode
 * @var string $sale_id
 * @var array $config
 * @var array|null $zimra
 * @var string|null $employee
 */

$companyName = !empty($config['receipt_show_company_name']) && !empty($config['company'])
    ? $config['company']
    : 'BEAMLINK TECH';
$companyEmail = !empty($config['email']) ? $config['email'] : 'sales@beamlink.co.zw';
$displayInvoiceNo = !empty($invoice_number) ? $invoice_number : ($sale_id ?? ('POS ' . $sale_id_num));
?>

<?= view('partial/header') ?>

<?php
if (isset($error_message)) {
    echo '<div class="alert alert-dismissible alert-danger">' . $error_message . '</div>';
    exit;
}
?>

<?php if (!empty($customer_email)): ?>
    <script type="text/javascript">
        $(document).ready(function() {
            var send_email = function() {
                $.get('<?= site_url() . "sales/sendPdf/$sale_id_num" ?>',
                    function(response) {
                        $.notify({
                            message: response.message
                        }, {
                            type: response.success ? 'success' : 'danger'
                        });
                    }, 'json'
                );
            };

            $("#show_email_button").click(send_email);

            <?php if (!empty($email_receipt)): ?>
                send_email();
            <?php endif; ?>
        });
    </script>
<?php endif; ?>

<?= view('partial/print_receipt', ['print_after_sale' => $print_after_sale, 'selected_printer' => 'invoice_printer']) ?>

<style>
    @media print {
        @page {
            size: A4 portrait;
            margin: 12mm 15mm;
        }
        body {
            background: #fff !important;
            color: #000 !important;
            font-size: 12px;
        }
        .print_hide, #header, #footer, nav, .navbar {
            display: none !important;
        }
        #page-wrap {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
            border: none !important;
        }
    }

    #page-wrap {
        width: 820px;
        margin: 20px auto;
        background: #fff;
        padding: 24px 30px;
        box-shadow: 0 4px 18px rgba(0,0,0,0.08);
        border: 1px solid #e1e4e8;
        border-radius: 6px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        color: #24292e;
    }

    .inv-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    .inv-table th {
        background: #f6f8fa;
        color: #24292e;
        border: 1px solid #d1d5da;
        padding: 8px 10px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .inv-table td {
        border: 1px solid #e1e4e8;
        padding: 7px 10px;
        font-size: 13px;
    }

    .inv-total-line {
        text-align: right;
        font-weight: 600;
        background: #fafbfc;
    }

    .inv-total-val {
        text-align: right;
        font-weight: 600;
    }
</style>

<div class="print_hide" id="control_buttons" style="max-width: 820px; margin: 15px auto 5px; text-align: right;">
    <a href="javascript:window.print();" class="btn btn-primary btn-sm" id="show_print_button">
        <span class="glyphicon glyphicon-print"></span> <?= lang('Common.print') ?> (A4)
    </a>
    <a href="<?= site_url("sales/receipt/$sale_id_num") ?>" class="btn btn-default btn-sm" id="show_thermal_button">
        <span class="glyphicon glyphicon-tag"></span> Thermal Receipt
    </a>
    <?php if (isset($customer_email) && !empty($customer_email)): ?>
        <a href="javascript:void(0);" class="btn btn-info btn-sm" id="show_email_button">
            <span class="glyphicon glyphicon-envelope"></span> <?= lang('Sales.send_invoice') ?>
        </a>
    <?php endif; ?>
    <?= anchor("sales", '<span class="glyphicon glyphicon-shopping-cart"></span> ' . lang('Sales.register'), ['class' => 'btn btn-default btn-sm', 'id' => 'show_sales_button']) ?>
    <?= anchor("sales/manage", '<span class="glyphicon glyphicon-list-alt"></span> ' . lang('Sales.takings'), ['class' => 'btn btn-default btn-sm', 'id' => 'show_takings_button']) ?>
</div>

<div id="page-wrap">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 15px; border-bottom: 2px solid #24292e;">
        <div style="flex: 1.2;">
            <?php if (!empty($config['company_logo'])) { ?>
                <img src="<?= base_url('uploads/' . esc($config['company_logo'], 'url')) ?>" alt="" style="max-width: 150px; max-height: 60px; height: auto; display: block; margin-bottom: 6px;" onerror="this.style.display='none'">
            <?php } ?>
            <div style="font-size: 22px; font-weight: 700; color: #1a202c; text-transform: uppercase; letter-spacing: 0.5px;">
                <?= esc($companyName) ?>
            </div>
            <div style="color: #4a5568; font-size: 13px; line-height: 1.45; margin-top: 4px;">
                <?php if (!empty($config['address'])): ?>
                    <?= nl2br(esc($config['address'])) ?><br>
                <?php endif; ?>
                <?php if (!empty($config['phone'])): ?>
                    <span>Tel / Cell: <?= esc($config['phone']) ?></span><br>
                <?php endif; ?>
                <?php if (!empty($companyEmail)): ?>
                    <span>Email: <?= esc($companyEmail) ?></span><br>
                <?php endif; ?>
                <?php if (!empty($config['tax_id'])): ?>
                    <span>TIN / VAT: <?= esc($config['tax_id']) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div style="flex: 0.9; text-align: right;">
            <div style="font-size: 24px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; color: #2d3748; margin-bottom: 8px;">
                TAX INVOICE / RECEIPT
            </div>
            <table style="margin-left: auto; font-size: 13px; text-align: left; border-collapse: collapse;">
                <tr>
                    <td style="padding: 2px 8px; color: #718096; font-weight: 600;">Receipt / Inv #:</td>
                    <td style="padding: 2px 8px; font-weight: 700;"><?= esc($displayInvoiceNo) ?></td>
                </tr>
                <tr>
                    <td style="padding: 2px 8px; color: #718096; font-weight: 600;">Date:</td>
                    <td style="padding: 2px 8px;"><?= esc($transaction_date) ?></td>
                </tr>
                <?php if (!empty($transaction_time)): ?>
                    <tr>
                        <td style="padding: 2px 8px; color: #718096; font-weight: 600;">Time:</td>
                        <td style="padding: 2px 8px;"><?= esc($transaction_time) ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($employee)): ?>
                    <tr>
                        <td style="padding: 2px 8px; color: #718096; font-weight: 600;">Cashier:</td>
                        <td style="padding: 2px 8px;"><?= esc($employee) ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td style="padding: 2px 8px; color: #718096; font-weight: 600;">Total Amount:</td>
                    <td style="padding: 2px 8px; font-weight: 800; color: #2b6cb0; font-size: 15px;"><?= to_currency($total) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid #e2e8f0; font-size: 13px;">
        <div style="flex: 1;">
            <div style="font-weight: 700; text-transform: uppercase; color: #718096; font-size: 11px; margin-bottom: 4px;">Billed To</div>
            <div style="color: #2d3748;">
                <?php if (isset($customer) && !empty($customer)): ?>
                    <strong style="font-size: 14px;"><?= esc($customer) ?></strong><br>
                    <?= nl2br(esc($customer_info ?? '')) ?>
                <?php else: ?>
                    <span style="color: #4a5568; font-style: italic;">Cash Customer</span>
                <?php endif; ?>
            </div>
        </div>

        <div style="flex: 1; text-align: right;">
            <div style="font-weight: 700; text-transform: uppercase; color: #718096; font-size: 11px; margin-bottom: 4px;">Slogan</div>
            <div style="font-style: italic; color: #4a5568;">
                BeamLink &mdash; Experience the future now...
            </div>
        </div>
    </div>

    <table class="inv-table">
        <thead>
            <tr>
                <th style="width: 8%; text-align: center;">#</th>
                <th style="width: 44%; text-align: left;"><?= lang('Sales.item_name') ?></th>
                <th style="width: 12%; text-align: center;"><?= lang('Sales.quantity') ?></th>
                <th style="width: 16%; text-align: right;"><?= lang('Sales.price') ?></th>
                <?php if ($discount > 0): ?>
                    <th style="width: 10%; text-align: right;"><?= lang('Sales.discount') ?></th>
                <?php endif; ?>
                <th style="width: 20%; text-align: right;"><?= lang('Sales.total') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $itemCount = 0;
            $grossQty = 0;
            $totalCols = ($discount > 0) ? 6 : 5;
            foreach ($cart as $line => $item) {
                if ($item['print_option'] == PRINT_YES) {
                    $itemCount++;
                    $grossQty += (float)$item['quantity'];
                    $itemTotal = $item[($config['receipt_show_total_discount'] ? 'total' : 'discounted_total')];
            ?>
                    <tr>
                        <td style="text-align: center; color: #718096;"><?= $itemCount ?></td>
                        <td>
                            <strong><?= esc(ucfirst($item['name'] . ($item['attribute_values'] ? ' ' . $item['attribute_values'] : ''))) ?></strong>
                            <?php if (!empty($item['description'])): ?>
                                <div style="font-size: 11px; color: #718096; margin-top: 2px;"><?= esc($item['description']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($item['serialnumber'])): ?>
                                <div style="font-size: 11px; color: #4a5568;">Serial: <?= esc($item['serialnumber']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;"><?= to_quantity_decimals($item['quantity']) ?></td>
                        <td style="text-align: right;"><?= to_currency($item['price']) ?></td>
                        <?php if ($discount > 0): ?>
                            <td style="text-align: right; color: #c53030;">
                                <?= ($item['discount_type'] == FIXED) ? to_currency($item['discount']) : to_decimals($item['discount']) . '%' ?>
                            </td>
                        <?php endif; ?>
                        <td style="text-align: right; font-weight: 600;"><?= to_currency($itemTotal) ?></td>
                    </tr>
            <?php
                }
            }
            ?>

            <tr>
                <td colspan="<?= $totalCols - 2 ?>" class="inv-total-line">Gross Items (Total Qty):</td>
                <td colspan="2" class="inv-total-val"><?= to_quantity_decimals($grossQty) ?></td>
            </tr>

            <?php if ($config['receipt_show_total_discount'] && $discount > 0): ?>
                <tr>
                    <td colspan="<?= $totalCols - 2 ?>" class="inv-total-line"><?= lang('Sales.sub_total') ?>:</td>
                    <td colspan="2" class="inv-total-val"><?= to_currency($prediscount_subtotal) ?></td>
                </tr>
                <tr>
                    <td colspan="<?= $totalCols - 2 ?>" class="inv-total-line" style="color: #c53030;"><?= lang('Sales.customer_discount') ?>:</td>
                    <td colspan="2" class="inv-total-val" style="color: #c53030;"><?= to_currency(abs($discount) * -1) ?></td>
                </tr>
            <?php endif; ?>

            <?php if ($config['receipt_show_taxes']): ?>
                <tr>
                    <td colspan="<?= $totalCols - 2 ?>" class="inv-total-line"><?= lang('Sales.sub_total') ?>:</td>
                    <td colspan="2" class="inv-total-val"><?= to_currency($subtotal) ?></td>
                </tr>
                <?php foreach ($taxes as $tax_group_index => $tax): ?>
                    <tr>
                        <td colspan="<?= $totalCols - 2 ?>" class="inv-total-line"><?= (float)$tax['tax_rate'] . '% ' . esc($tax['tax_group']) ?>:</td>
                        <td colspan="2" class="inv-total-val"><?= to_currency_tax($tax['sale_tax_amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>

            <tr style="font-size: 15px;">
                <td colspan="<?= $totalCols - 2 ?>" class="inv-total-line" style="font-weight: 800; background: #edf2f7; color: #1a202c;"><?= lang('Sales.total') ?>:</td>
                <td colspan="2" class="inv-total-val" style="font-weight: 800; background: #edf2f7; color: #1a202c; font-size: 16px;"><?= to_currency($total) ?></td>
            </tr>

            <?php
            $onlySaleCheck = false;
            foreach ($payments as $payment_id => $payment):
                $onlySaleCheck |= ($payment['payment_type'] == lang('Sales.check'));
                $splitpayment = explode(':', $payment['payment_type']);
            ?>
                <tr>
                    <td colspan="<?= $totalCols - 2 ?>" class="inv-total-line"><?= esc($splitpayment[0]) ?> Paid:</td>
                    <td colspan="2" class="inv-total-val" style="color: #276749;"><?= to_currency(abs((float)$payment['payment_amount'])) ?></td>
                </tr>
            <?php endforeach; ?>

            <tr>
                <td colspan="<?= $totalCols - 2 ?>" class="inv-total-line" style="font-weight: 700;">
                    <?= lang($amount_change >= 0 ? ($onlySaleCheck ? 'Sales.check_balance' : 'Sales.change_due') : 'Sales.amount_due') ?>:
                </td>
                <td colspan="2" class="inv-total-val" style="font-weight: 700; color: #2d3748;">
                    <?= to_currency(abs((float)$amount_change)) ?>
                </td>
            </tr>
        </tbody>
    </table>

    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-top: 20px; padding-top: 15px; border-top: 2px solid #e2e8f0;">
        <div style="flex: 1.3; padding-right: 25px;">
            <div style="font-weight: 700; text-transform: uppercase; font-size: 12px; color: #2d3748; margin-bottom: 6px;">
                Terms &amp; Conditions
            </div>
            <ol style="margin: 0; padding-left: 18px; font-size: 11px; color: #4a5568; line-height: 1.55;">
                <li><strong>Warranty:</strong> 3 months warranty on all appliances (subject to technical inspection; excludes physical damage, liquid ingress, misuse, and unauthorized repairs).</li>
                <li><strong>Testing:</strong> Tested products will not be accepted if returned not working.</li>
                <li><strong>Verification:</strong> Please inspect all products and count change before leaving the counter/store.</li>
                <li><strong>Exchanges &amp; Refunds:</strong> Exchanges allowed within 48 hours only, with original receipt and undamaged packaging. Strictly no cash refunds.</li>
                <li><strong>Layby Policy:</strong> Refunds/cancellations for layby attract a 5% administrative fee. Failure to pay on agreed dates attracts a 5% penalty.</li>
            </ol>
            <?php if (!empty($comments)): ?>
                <div style="margin-top: 8px; font-size: 12px; color: #4a5568;">
                    <strong>Notes:</strong> <?= esc($comments) ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="flex: 0.9; text-align: center; border-left: 1px solid #e2e8f0; padding-left: 20px;">
            <?= view('partial/zimra_receipt_block', [
                'zimra'            => $zimra ?? null,
                'sale_id'          => $sale_id ?? ('POS ' . $sale_id_num),
                'sale_id_num'      => $sale_id_num,
                'transaction_time' => $transaction_time ?? $transaction_date,
                'total'            => $total,
                'employee'         => $employee ?? '',
            ]) ?>
        </div>
    </div>

    <div style="border-top: 1px solid #e2e8f0; margin-top: 20px; padding-top: 10px; display: flex; justify-content: space-between; font-size: 11px; color: #a0aec0;">
        <div>BeamLink POS &middot; Experience the future now...</div>
        <div>Second Spark Intelligence &copy; 2026 &middot; secondspark.co.zw</div>
    </div>
</div>

<?= view('partial/footer') ?>
