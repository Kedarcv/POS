<?php
/**
 * @var string $sale_id_num
 * @var bool $print_after_sale
 * @var string $customer_info
 * @var string $company_info
 * @var string $invoice_number
 * @var string $transaction_date
 * @var float $total
 * @var bool $include_hsn
 * @var string $discount
 * @var array $cart
 * @var float $subtotal
 * @var array $taxes
 * @var array $payments
 * @var float $amount_change
 * @var string $barcode
 * @var int $sale_id
 * @var array $config
 */
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

<div class="print_hide" id="control_buttons" style="text-align: right;">
    <a href="javascript:printdoc();">
        <div class="btn btn-info btn-sm" id="show_print_button"><?= '<span class="glyphicon glyphicon-print">&nbsp;</span>' . lang('Common.print') ?></div>
    </a>
    <?php if (isset($customer_email) && !empty($customer_email)): ?>
        <a href="javascript:void(0);">
            <div class="btn btn-info btn-sm" id="show_email_button"><?= '<span class="glyphicon glyphicon-envelope">&nbsp;</span>' . lang('Sales.send_invoice') ?></div>
        </a>
    <?php endif; ?>
    <?= anchor("sales", '<span class="glyphicon glyphicon-shopping-cart">&nbsp;</span>' . lang('Sales.register'), ['class' => 'btn btn-info btn-sm', 'id' => 'show_sales_button']) ?>
    <?= anchor("sales/manage", '<span class="glyphicon glyphicon-list-alt">&nbsp;</span>' . lang('Sales.takings'), ['class' => 'btn btn-info btn-sm', 'id' => 'show_takings_button']) ?>
</div>

<div id="page-wrap">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; padding: 15px 20px; border-bottom: 3px solid #333;">
        <div style="flex: 1;">
            <img src="<?= base_url('images/logo.png') ?>" alt="BeamLink" style="max-width: 140px; height: auto; display: block; margin-bottom: 8px;">
            <?php if ($config['receipt_show_company_name']) { ?>
                <div style="font-size: 20px; font-weight: bold; margin-bottom: 4px;"><?= esc($config['company']) ?></div>
            <?php } else { ?>
                <div style="font-size: 20px; font-weight: bold; margin-bottom: 4px;">BeamLink</div>
            <?php } ?>
            <div style="color: #555; line-height: 1.4;">
                <?= nl2br(esc($company_info)) ?>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 26px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: #333; margin-bottom: 10px;">TAX INVOICE</div>
            <table id="meta" style="margin-left: auto;">
                <tr>
                    <td class="meta-head"><?= lang('Sales.invoice_number') ?></td>
                    <td><?= esc($invoice_number) ?></td>
                </tr>
                <tr>
                    <td class="meta-head"><?= lang('Common.date') ?></td>
                    <td><?= esc($transaction_date) ?></td>
                </tr>
                <tr>
                    <td class="meta-head"><?= lang('Sales.invoice_total') ?></td>
                    <td style="font-weight: bold;"><?= to_currency($total) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; padding: 15px 20px; border-bottom: 1px solid #ddd;">
        <div style="flex: 1;">
            <h5 style="margin: 0 0 5px 0; font-weight: bold; text-transform: uppercase; color: #555;">Bill To</h5>
            <div id="customer-title">
                <?php if (isset($customer)) { ?>
                    <div id="customer"><?= nl2br(esc($customer_info)) ?></div>
                <?php } ?>
            </div>
        </div>
        <div style="flex: 1; text-align: right;">
            <h5 style="margin: 0 0 5px 0; font-weight: bold; text-transform: uppercase; color: #555;">Payment Terms</h5>
            <div style="color: #555;">
                <?= nl2br(esc($config['payment_message'])) ?>
            </div>
        </div>
    </div>

    <table id="items">
        <tr>
            <th><?= lang('Sales.item_number') ?></th>
            <?php
            $invoice_columns = 6;
            if ($include_hsn) {
                $invoice_columns += 1;
            ?>
                <th><?= lang('Sales.hsn') ?></th>
            <?php } ?>
            <th><?= lang('Sales.item_name') ?></th>
            <th><?= lang('Sales.quantity') ?></th>
            <th><?= lang('Sales.price') ?></th>
            <th><?= lang('Sales.discount') ?></th>
            <?php
            if ($discount > 0) {
                $invoice_columns += 1;
            ?>
                <th><?= lang('Sales.customer_discount') ?></th>
            <?php } ?>
            <th><?= lang('Sales.total') ?></th>
        </tr>

        <?php
        foreach ($cart as $line => $item) {
            if ($item['print_option'] == PRINT_YES) {
                ?>
                <tr class="item-row">
                    <td><?= esc($item['item_number']) ?></td>
                    <?php if ($include_hsn): ?>
                        <td style="text-align: center;"><?= esc($item['hsn_code']) ?></td>
                    <?php endif; ?>
                    <td class="item-name"><?= ($item['is_serialized'] || $item['allow_alt_description']) && !empty($item['description']) ? esc($item['description']) : esc($item['name'] . ' ' . $item['attribute_values']) ?></td>
                    <td style="text-align: center;"><?= to_quantity_decimals($item['quantity']) ?></td>
                    <td><?= to_currency($item['price']) ?></td>
                    <td style="text-align: center;"><?= ($item['discount_type'] == FIXED) ? to_currency($item['discount']) : to_decimals($item['discount']) . '%' ?></td>
                    <?php if ($discount > 0): ?>
                        <td style="text-align: center;"><?= to_currency($item['discounted_total'] / $item['quantity']) ?></td>
                    <?php endif; ?>
                    <td style="border-right: solid 1px; text-align: right;"><?= to_currency($item['discounted_total']) ?></td>
                </tr>
                <?php if ($item['is_serialized']) { ?>
                    <tr class="item-row">
                        <td class="item-description" colspan="<?= $invoice_columns - 1 ?>"></td>
                        <td style="text-align: center;"><?= esc($item['serialnumber']) ?></td>
                    </tr>
        <?php
                }
            }
        }
        ?>

        <tr>
            <td class="blank" colspan="<?= $invoice_columns ?>" style="text-align: center;"><?= '&nbsp;' ?></td>
        </tr>

        <tr>
            <td colspan="<?= $invoice_columns - 3 ?>" class="blank-bottom"> </td>
            <td colspan="2" class="total-line"><?= lang('Sales.sub_total') ?></td>
            <td class="total-value" id="subtotal"><?= to_currency($subtotal) ?></td>
        </tr>

        <?php foreach ($taxes as $tax_group_index => $tax) { ?>
            <tr>
                <td colspan="<?= $invoice_columns - 3 ?>" class="blank"> </td>
                <td colspan="2" class="total-line"><?= (float)$tax['tax_rate'] . '% ' . esc($tax['tax_group']) ?></td>
                <td class="total-value" id="taxes"><?= to_currency_tax($tax['sale_tax_amount']) ?></td>
            </tr>
        <?php } ?>

        <tr>
            <td colspan="<?= $invoice_columns - 3 ?>" class="blank"> </td>
            <td colspan="2" class="total-line" style="font-weight: bold;"><?= lang('Sales.total') ?></td>
            <td class="total-value" id="total" style="font-weight: bold;"><?= to_currency($total) ?></td>
        </tr>

        <?php
        $only_sale_check = false;
        $show_giftcard_remainder = false;
        foreach ($payments as $payment_id => $payment) {
            $only_sale_check |= $payment['payment_type'] == lang('Sales.check');
            $splitpayment = explode(':', $payment['payment_type']);
            $show_giftcard_remainder |= $splitpayment[0] == lang('Sales.giftcard');
        ?>
            <tr>
                <td colspan="<?= $invoice_columns - 3 ?>" class="blank"> </td>
                <td colspan="2" class="total-line"><?= esc($splitpayment[0]) ?></td>
                <td class="total-value" id="paid"><?= to_currency($payment['payment_amount'] * -1) ?></td>
            </tr>
        <?php } ?>

        <?php if (isset($cur_giftcard_value) && $show_giftcard_remainder) { ?>
            <tr>
                <td colspan="<?= $invoice_columns - 3 ?>" class="blank"> </td>
                <td colspan="2" class="total-line"><?= lang('Sales.giftcard_balance') ?></td>
                <td class="total-value" id="giftcard"><?= to_currency($cur_giftcard_value) ?></td>
            </tr>
        <?php } ?>

        <?php if (!empty($payments)) { ?>
            <tr>
                <td colspan="<?= $invoice_columns - 3 ?>" class="blank"> </td>
                <td colspan="2" class="total-line" style="font-weight: bold;"><?= lang($amount_change >= 0 ? ($only_sale_check ? 'Sales.check_balance' : 'Sales.change_due') : 'Sales.amount_due') ?></td>
                <td class="total-value" id="change" style="font-weight: bold;"><?= to_currency($amount_change) ?></td>
            </tr>
        <?php } ?>

    </table>

    <div id="terms" style="padding: 15px 20px; border-top: 1px solid #ddd;">
        <div style="display: flex; justify-content: space-between;">
            <div style="flex: 1;">
                <h5 style="margin: 0 0 5px 0; font-weight: bold; text-transform: uppercase; color: #555;">Banking Details</h5>
                <div style="color: #555; font-size: 12px; line-height: 1.6;">
                    Bank: [Bank Name]<br>
                    Account Name: [Account Name]<br>
                    Account Number: [Account Number]<br>
                    Branch: [Branch / Sort Code]
                </div>
            </div>
            <div style="flex: 1;">
                <div id="sale_return_policy" style="color: #555; font-size: 12px;">
                    <?= nl2br(esc($config['return_policy'])) ?>
                    <?php if (!empty($comments)) { ?>
                        <div style="margin-top: 5px;"><?= lang('Sales.comments') . ': ' . esc($comments) ?></div>
                    <?php } else { ?>
                        <div style="margin-top: 5px;"><?= esc($config['invoice_default_comments']) ?></div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 15px;">
            <div id="barcode">
                <?= $barcode ?><br>
                <?= $sale_id ?>
            </div>
        </div>

        <div style="border-top: 1px solid #ddd; margin-top: 15px; padding-top: 10px; text-align: center; font-size: 11px; color: #999;">
            Second Spark Intelligence &copy; 2026 &middot; secondspark.co.zw &middot; BeamLink POS v3.4.2
        </div>
    </div>
</div>

<script type="text/javascript">
    $(window).on("load", function() {
        if (window.jsPrintSetup) {
            <?php if (!$config['print_header']) { ?>
                jsPrintSetup.setOption('headerStrLeft', '');
                jsPrintSetup.setOption('headerStrCenter', '');
                jsPrintSetup.setOption('headerStrRight', '');
            <?php } ?>

            <?php if (!$config['print_footer']) { ?>
                jsPrintSetup.setOption('footerStrLeft', '');
                jsPrintSetup.setOption('footerStrCenter', '');
                jsPrintSetup.setOption('footerStrRight', '');
            <?php } ?>
        }
    });
</script>

<?= view('partial/footer') ?>
