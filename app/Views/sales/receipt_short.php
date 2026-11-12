<?php
/**
 * Short Thermal Receipt View (compact 58mm / 80mm)
 *
 * @var string $transaction_time
 * @var int $sale_id_num
 * @var string $sale_id
 * @var string $employee
 * @var float $discount
 * @var array $cart
 * @var float $subtotal
 * @var array $taxes
 * @var float $total
 * @var array $payments
 * @var float $amount_change
 * @var string $barcode
 * @var array $config
 * @var string|null $customer
 * @var array|null $zimra
 */

$fontSize = !empty($config['receipt_font_size']) ? (int)$config['receipt_font_size'] : 12;
$companyName = !empty($config['receipt_show_company_name']) && !empty($config['company'])
    ? $config['company']
    : 'BEAMLINK TECH';
$companyEmail = !empty($config['email']) ? $config['email'] : 'sales@beamlink.co.zw';
?>

<div id="receipt_wrapper" style="font-size: <?= $fontSize ?>px; font-family: 'Courier New', Courier, monospace, 'Lucida Console'; width: 100%; max-width: 320px; margin: 0 auto; color: #000; line-height: 1.25;">
    <div id="receipt_header" style="text-align: center;">
        <?php if (!empty($config['company_logo'])) { ?>
            <div style="margin-bottom: 4px;">
                <img id="image" src="<?= base_url('uploads/' . esc($config['company_logo'], 'url')) ?>" alt="" style="max-width: 140px; height: auto;" onerror="this.style.display='none'">
            </div>
        <?php } ?>

        <div id="company_name" style="font-weight: bold; font-size: 130%; text-transform: uppercase; margin-bottom: 2px;">
            <?= nl2br(esc($companyName)) ?>
        </div>

        <?php if (!empty($config['address'])) { ?>
            <div id="company_address"><?= nl2br(esc($config['address'])) ?></div>
        <?php } ?>

        <?php if (!empty($config['phone'])) { ?>
            <div id="company_phone">CELL: <?= esc($config['phone']) ?></div>
        <?php } ?>

        <?php if (!empty($companyEmail)) { ?>
            <div id="company_email"><?= esc($companyEmail) ?></div>
        <?php } ?>

        <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

        <div style="font-weight: bold;"><?= lang('Sales.receipt') ?></div>
        <div>Receipt: <?= esc($sale_id) ?></div>
        <div><?= esc($transaction_time) ?></div>

        <?php if (isset($customer) && !empty($customer)) { ?>
            <div><?= lang('Customers.customer') . ': ' . esc($customer) ?></div>
        <?php } ?>

        <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
    </div>

    <table id="receipt_items" style="width: 100%; border-collapse: collapse; table-layout: fixed; margin: 4px 0;">
        <thead>
            <tr style="border-bottom: 1px dashed #000;">
                <th style="width: 50%; text-align: left; padding: 2px 2px; font-weight: bold;"><?= lang('Sales.description_abbrv') ?></th>
                <th style="width: 20%; text-align: center; padding: 2px 2px; font-weight: bold;"><?= lang('Sales.quantity') ?></th>
                <th style="width: 30%; text-align: right; padding: 2px 2px; font-weight: bold;"><?= lang('Sales.total') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $grossQuantity = 0;
            foreach ($cart as $line => $item) {
                if ($item['print_option'] == PRINT_YES) {
                    $grossQuantity += (float)$item['quantity'];
                    $itemTotal = $item[($config['receipt_show_total_discount'] ? 'total' : 'discounted_total')];
            ?>
                    <tr>
                        <td style="text-align: left; padding: 2px 2px; word-break: break-word;">
                            <?= esc(ucfirst($item['name'] . ($item['attribute_values'] ? ' ' . $item['attribute_values'] : ''))) ?>
                        </td>
                        <td style="text-align: center; padding: 2px 2px; white-space: nowrap;">
                            <?= to_quantity_decimals($item['quantity']) ?>
                        </td>
                        <td style="text-align: right; padding: 2px 2px; white-space: nowrap;">
                            <?= to_currency($itemTotal) ?>
                        </td>
                    </tr>
                    <?php if ($item['discount'] > 0) { ?>
                        <tr>
                            <td colspan="2" style="text-align: right; font-size: 85%; padding: 0 2px;">
                                <?= ($item['discount_type'] == FIXED) ? to_currency($item['discount']) . ' ' . lang('Sales.discount') : to_decimals($item['discount']) . '% ' . lang('Sales.discount') ?>
                            </td>
                            <td style="text-align: right; font-size: 85%; padding: 0 2px;"><?= to_currency($item['discounted_total']) ?></td>
                        </tr>
            <?php
                    }
                }
            }
            ?>
        </tbody>
    </table>

    <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

    <table style="width: 100%; border-collapse: collapse; margin: 2px 0;">
        <tr>
            <td style="text-align: left; padding: 1px 2px;">Gross Total (Qty):</td>
            <td style="text-align: right; padding: 1px 2px;"><?= to_quantity_decimals($grossQuantity) ?></td>
        </tr>

        <tr style="font-weight: bold; font-size: 110%;">
            <td style="text-align: left; padding: 4px 2px; border-top: 1px dashed #000; border-bottom: 1px dashed #000;">Net Total:</td>
            <td style="text-align: right; padding: 4px 2px; border-top: 1px dashed #000; border-bottom: 1px dashed #000;"><?= to_currency($total) ?></td>
        </tr>
    </table>

    <table style="width: 100%; border-collapse: collapse; margin: 4px 0 2px;">
        <?php
        $onlySaleCheck = false;
        foreach ($payments as $payment_id => $payment) {
            $onlySaleCheck |= ($payment['payment_type'] == lang('Sales.check'));
            $splitpayment = explode(':', $payment['payment_type']);
        ?>
            <tr>
                <td style="text-align: left; padding: 1px 2px;"><?= esc($splitpayment[0]) ?> Paid:</td>
                <td style="text-align: right; padding: 1px 2px;"><?= to_currency(abs((float)$payment['payment_amount'])) ?></td>
            </tr>
        <?php } ?>

        <tr style="font-weight: bold;">
            <td style="text-align: left; padding: 2px 2px;">
                <?= lang($amount_change >= 0 ? ($onlySaleCheck ? 'Sales.check_balance' : 'Sales.change_due') : 'Sales.amount_due') ?>:
            </td>
            <td style="text-align: right; padding: 2px 2px;">
                <?= to_currency(abs((float)$amount_change)) ?>
            </td>
        </tr>
    </table>

    <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

    <div style="text-align: center; margin: 2px 0;">
        Sales Person: <?= esc($employee) ?>
    </div>

    <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

    <div style="text-align: center; font-style: italic; margin: 3px 0;">
        Beamlink,<br>
        Experience the future now...
    </div>

    <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

    <div id="receipt_terms_summary" style="font-size: 82%; line-height: 1.35; text-align: left; padding: 2px 2px;">
        <div style="font-weight: bold; text-align: center; margin-bottom: 3px; text-transform: uppercase;">Terms &amp; Conditions</div>
        <div>&bull; Please check all products &amp; change before leaving the counter.</div>
        <div>&bull; Tested products will not be accepted if returned not working.</div>
        <div>&bull; 3 months warranty on appliances (excludes physical/liquid damage &amp; misuse).</div>
        <div>&bull; Exchanges within 48hrs with original receipt &amp; packaging. No cash refunds.</div>
    </div>

    <?= view('partial/zimra_receipt_block', [
        'zimra'            => $zimra ?? null,
        'sale_id'          => $sale_id,
        'sale_id_num'      => $sale_id_num,
        'transaction_time' => $transaction_time,
        'total'            => $total,
        'employee'         => $employee,
    ]) ?>

    <?php if (!empty($barcode)) { ?>
        <div id="barcode" style="text-align: center; margin: 4px 0 2px;">
            <?= $barcode ?><br>
            <span style="font-size: 80%;"><?= esc($sale_id) ?></span>
        </div>
    <?php } ?>

    <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>

    <div style="text-align: center; font-size: 75%; color: #444;">
        BeamLink POS &middot; secondspark.co.zw
    </div>
</div>
