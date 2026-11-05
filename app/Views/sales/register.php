<?php
/**
 * @var string $controller_name
 * @var array $modes
 * @var array $mode
 * @var array $empty_tables
 * @var array $selected_table
 * @var array $stock_locations
 * @var array $stock_location
 * @var array $cart
 * @var bool $items_module_allowed
 * @var bool $change_price
 * @var int $customer_id
 * @var int $customer_discount_type
 * @var float $customer_discount
 * @var float $customer_total
 * @var string $customer_required
 * @var float|int $item_count
 * @var float|int $total_units
 * @var float $subtotal
 * @var array $taxes
 * @var float $total
 * @var float $payments_total
 * @var float $amount_due
 * @var bool $payments_cover_total
 * @var array $payment_options
 * @var array $selected_payment_type
 * @var bool $pos_mode
 * @var array $payments
 * @var string $mode_label
 * @var string $comment
 * @var bool $print_after_sale
 * @var bool $email_receipt
 * @var bool $price_work_orders
 * @var string $invoice_number
 * @var int $cash_mode
 * @var float $non_cash_total
 * @var float $cash_amount_due
 * @var array $config
 */

use App\Models\Employee;

?>

<?= view('partial/header') ?>

<?php
if (isset($error)) {
    echo '<div class="alert alert-dismissible alert-danger">' . esc($error) . '</div>';
}

if (!empty($warning)) {
    echo '<div class="alert alert-dismissible alert-warning">' . esc($warning) . '</div>';
}

if (isset($success)) {
    echo '<div class="alert alert-dismissible alert-success">' . esc($success) . '</div>';
}

helper('url');
?>

<div id="register_wrapper">

    <!-- Top register controls -->
    <?= form_open("$controller_name/changeMode", ['id' => 'mode_form', 'class' => 'form-horizontal panel panel-default']) ?>
        <div class="panel-body form-group">
            <ul>
                <li class="pull-left first_li">
                    <label class="control-label"><?= lang(ucfirst($controller_name) . '.mode') ?></label>
                </li>
                <li class="pull-left">
                    <?= form_dropdown('mode', $modes, $mode, ['onchange' => "$('#mode_form').submit();", 'class' => 'selectpicker show-menu-arrow', 'data-style' => 'btn-default btn-sm', 'data-width' => 'fit']) ?>
                </li>
                <?php if ($config['dinner_table_enable']) { ?>
                    <li class="pull-left first_li">
                        <label class="control-label"><?= lang(ucfirst($controller_name) . '.table') ?></label>
                    </li>
                    <li class="pull-left">
                        <?= form_dropdown('dinner_table', $empty_tables, $selected_table, ['onchange' => "$('#mode_form').submit();", 'class' => 'selectpicker show-menu-arrow', 'data-style' => 'btn-default btn-sm', 'data-width' => 'fit']) ?>
                    </li>
                <?php } ?>
                <?php if (count($stock_locations) > 1) { ?>
                    <li class="pull-left">
                        <label class="control-label"><?= lang(ucfirst($controller_name) . '.stock_location') ?></label>
                    </li>
                    <li class="pull-left">
                        <?= form_dropdown('stock_location', $stock_locations, $stock_location, ['onchange' => "$('#mode_form').submit();", 'class' => 'selectpicker show-menu-arrow', 'data-style' => 'btn-default btn-sm', 'data-width' => 'fit']) ?>
                    </li>
                <?php } ?>

                <li class="pull-right">
                    <button class="btn btn-default btn-sm modal-dlg" id="show_suspended_sales_button" data-href="<?= esc("$controller_name/suspended") ?>"
                        title="<?= lang(ucfirst($controller_name) . '.suspended_sales') ?>">
                        <span class="glyphicon glyphicon-align-justify">&nbsp;</span><?= lang(ucfirst($controller_name) . '.suspended_sales') ?>
                    </button>
                </li>

                <?php
                $employee = model(Employee::class);
                if ($employee->has_grant('reports_sales', session('person_id'))) {
                ?>
                    <li class="pull-right">
                        <?= anchor(
                            "$controller_name/manage",
                            '<span class="glyphicon glyphicon-list-alt">&nbsp;</span>' . lang(ucfirst($controller_name) . '.takings'),
                            array('class' => 'btn btn-primary btn-sm', 'id' => 'sales_takings_button', 'title' => lang(ucfirst($controller_name) . '.takings'))
                        ) ?>
                    </li>
                <?php } ?>
            </ul>
        </div>
    <?= form_close() ?>

    <?php $tabindex = 0; ?>

    <?= form_open("$controller_name/add", ['id' => 'add_item_form', 'class' => 'form-horizontal panel panel-default']) ?>
        <div class="panel-body form-group">
            <ul>
                <li class="pull-left first_li">
                    <label for="item" class="control-label"><?= lang(ucfirst($controller_name) . '.find_or_scan_item_or_receipt') ?></label>
                </li>
                <li class="pull-left">
                    <?= form_input(['name' => 'item', 'id' => 'item', 'class' => 'form-control input-sm', 'size' => '50', 'tabindex' => ++$tabindex]) ?>
                    <span class="ui-helper-hidden-accessible" role="status"></span>
                </li>
                <li class="pull-right">
                    <button id="new_item_button" class="btn btn-info btn-sm pull-right modal-dlg" data-btn-new="<?= lang('Common.new') ?>" data-btn-submit="<?= lang('Common.submit') ?>" data-href="<?= "items/view" ?>" title="<?= lang(ucfirst($controller_name) . ".new_item") ?>">
                        <span class="glyphicon glyphicon-tag">&nbsp;</span><?= lang(ucfirst($controller_name) . ".new_item") ?>
                    </button>
                </li>
            </ul>
        </div>
    <?= form_close() ?>


    <!-- Sale Items List -->

    <table class="sales_table_100" id="register">
        <thead>
            <tr>
                <th style="width: 5%;"><?= lang('Common.delete') ?></th>
                <th style="width: 15%;"><?= lang(ucfirst($controller_name) . '.item_number') ?></th>
                <th style="width: 30%;"><?= lang(ucfirst($controller_name) . '.item_name') ?></th>
                <th style="width: 10%;"><?= lang(ucfirst($controller_name) . '.price') ?></th>
                <th style="width: 10%;"><?= lang(ucfirst($controller_name) . '.quantity') ?></th>
                <th style="width: 15%;"><?= lang(ucfirst($controller_name) . '.discount') ?></th>
                <th style="width: 10%;"><?= lang(ucfirst($controller_name) . '.total') ?></th>
                <th style="width: 5%;"><?= lang(ucfirst($controller_name) . '.update') ?></th>
            </tr>
        </thead>

        <tbody id="cart_contents">
            <?php if (count($cart) == 0) { ?>
                <tr>
                    <td colspan="8">
                        <div class="alert alert-dismissible alert-info"><?= lang(ucfirst($controller_name) . '.no_items_in_cart') ?></div>
                    </td>
                </tr>
            <?php
            } else {
                foreach (array_reverse($cart, true) as $line => $item) {
            ?>
                    <?= form_open("$controller_name/editItem/$line", ['class' => 'form-horizontal', 'id' => "cart_$line"]) ?>
                        <tr>
                            <td>
                                <?php
                                // deleteItem is a POST-only route; the plain GET link this
                                // anchor() produced was never actually reachable — the
                                // .delete_item_button click handler below is what's supposed
                                // to intercept it and POST instead, but it needs this class
                                // and data-item-id to find the element at all.
                                echo anchor(
                                    "$controller_name/deleteItem/$line",
                                    '<span class="glyphicon glyphicon-trash"></span>',
                                    ['class' => 'delete_item_button', 'data-item-id' => $line],
                                );
                                echo form_hidden('location', (string)$item['item_location']);
                                echo form_input(['type' => 'hidden', 'name' => 'item_id', 'value' => $item['item_id']]);
                                ?>
                            </td>
                            <?php if ($item['item_type'] == ITEM_TEMP) { ?>
                                <td><?= form_input(['name' => 'item_number', 'id' => 'item_number', 'class' => 'form-control input-sm', 'value' => $item['item_number'], 'tabindex' => ++$tabindex]) ?></td>
                                <td style="text-align: center;">
                                    <?= form_input(['name' => 'name', 'id' => 'name', 'class' => 'form-control input-sm', 'value' => $item['name'], 'tabindex' => ++$tabindex]) ?>
                                </td>
                            <?php } else { ?>
                                <td><?= esc($item['item_number']) ?></td>
                                <td style="text-align: center;">
                                    <?= esc($item['name']) . ' ' . esc(implode(' ', [$item['attribute_values'], $item['attribute_dtvalues']])) ?>
                                    <br>
                                    <?php if ($item['stock_type'] == '0'): echo '[' . to_quantity_decimals($item['in_stock']) . ' in ' . esc($item['stock_name']) . ']';
                                    endif; ?>
                                </td>
                            <?php } ?>

                            <td>
                                <?php
                                if ($items_module_allowed && $change_price) {
                                    echo form_input(['name' => 'price', 'class' => 'form-control input-sm', 'value' => to_currency_no_money($item['price']), 'tabindex' => ++$tabindex, 'onClick' => 'this.select();']);
                                } else {
                                    echo to_currency($item['price']);
                                    echo form_hidden('price', to_currency_no_money($item['price']));
                                }
                                ?>
                            </td>

                            <td>
                                <?php
                                if ($item['is_serialized']) {
                                    echo to_quantity_decimals($item['quantity']);
                                    echo form_hidden('quantity', $item['quantity']);
                                } else {
                                    echo form_input(['name' => 'quantity', 'class' => 'form-control input-sm', 'value' => to_quantity_decimals($item['quantity']), 'tabindex' => ++$tabindex, 'onClick' => 'this.select();']);
                                }
                                ?>
                            </td>

                            <td>
                                <div class="input-group">
                                    <?= form_input(['name' => 'discount', 'class' => 'form-control input-sm', 'value' => $item['discount_type'] ? to_currency_no_money($item['discount']) : to_decimals($item['discount']), 'tabindex' => ++$tabindex, 'onClick' => 'this.select();']) ?>
                                    <span class="input-group-btn">
                                        <?= form_checkbox(['id' => 'discount_toggle', 'name' => 'discount_toggle', 'value' => 1, 'data-toggle' => "toggle", 'data-size' => 'small', 'data-onstyle' => 'success', 'data-on' => '<b>' . $config['currency_symbol'] . '</b>', 'data-off' => '<b>%</b>', 'data-line' => $line, 'checked' => $item['discount_type'] == 1]) ?>
                                    </span>
                                </div>
                            </td>

                            <td>
                                <?php
                                if ($item['item_type'] == ITEM_AMOUNT_ENTRY) {    // TODO: === ?
                                    echo form_input(['name' => 'discounted_total', 'class' => 'form-control input-sm', 'value' => to_currency_no_money($item['discounted_total']), 'tabindex' => ++$tabindex, 'onClick' => 'this.select();']);
                                } else {
                                    echo to_currency($item['discounted_total']);
                                }
                                ?>
                            </td>

                            <td>
                                <a href="javascript:document.getElementById('<?= "cart_$line" ?>').submit();" title="<?= lang(ucfirst($controller_name) . '.update') ?>">
                                    <span class="glyphicon glyphicon-refresh"></span>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <?php if ($item['item_type'] == ITEM_TEMP) { ?>
                                <td><?= form_input(['type' => 'hidden', 'name' => 'item_id', 'value' => $item['item_id']]) ?></td>
                                <td style="text-align: center;" colspan="6">
                                    <?= form_input(['name' => 'item_description', 'id' => 'item_description', 'class' => 'form-control input-sm', 'value' => $item['description'], 'tabindex' => ++$tabindex]) ?>
                                </td>
                                <td> </td>
                            <?php } else { ?>
                                <td>&nbsp;</td>
                                <?php if ($item['allow_alt_description']) { ?>
                                    <td style="color: #2F4F4F;"><?= lang(ucfirst($controller_name) . '.description_abbrv') ?></td>
                                <?php } ?>

                                <td colspan="2" style="text-align: left;">
                                    <?php
                                    if ($item['allow_alt_description']) {
                                        echo form_input(['name' => 'description', 'class' => 'form-control input-sm', 'value' => $item['description'], 'onClick' => 'this.select();']);
                                    } else {
                                        if ($item['description'] != '') {
                                            echo esc($item['description']);
                                            echo form_hidden('description', $item['description']);
                                        } else {
                                            echo lang(ucfirst($controller_name) . '.no_description');
                                            echo form_hidden('description', '');
                                        }
                                    }
                                    ?>
                                </td>
                                <td>&nbsp;</td>
                                <td style="color: #2F4F4F;">
                                    <?php
                                    if ($item['is_serialized']) {
                                        echo lang(ucfirst($controller_name) . '.serial');
                                    }
                                    ?>
                                </td>
                                <td colspan="4" style="text-align: left;">
                                    <?php
                                    if ($item['is_serialized']) {
                                        echo form_input(['name' => 'serialnumber', 'class' => 'form-control input-sm', 'value' => $item['serialnumber'], 'onClick' => 'this.select();']);
                                    } else {
                                        echo form_hidden('serialnumber', '');
                                    }
                                    ?>
                                </td>
                            <?php } ?>
                        </tr>
                    <?= form_close() ?>
            <?php
                }
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Overall Sale -->

<div id="overall_sale" class="panel panel-default">
    <div class="panel-body">
        <?= form_open("$controller_name/selectCustomer", ['id' => 'select_customer_form', 'class' => 'form-horizontal']) ?>
            <?php if (isset($customer)) { ?>
                <table class="sales_table_100">
                    <tr>
                        <th style="width: 55%;"><?= lang(ucfirst($controller_name) . '.customer') ?></th>
                        <th style="width: 45%; text-align: right;"><?= anchor("customers/view/$customer_id", esc($customer), ['class' => 'modal-dlg', 'data-btn-submit' => lang('Common.submit'), 'title' => lang('Customers.update')]) ?></th>
                    </tr>
                    <?php if (!empty($customer_email)) { ?>
                        <tr>
                            <th style="width: 55%;"><?= lang(ucfirst($controller_name) . '.customer_email') ?></th>
                            <th style="width: 45%; text-align: right;"><?= esc($customer_email) ?></th>
                        </tr>
                    <?php } ?>
                    <?php if (!empty($customer_address)) { ?>
                        <tr>
                            <th style="width: 55%;"><?= lang(ucfirst($controller_name) . '.customer_address') ?></th>
                            <th style="width: 45%; text-align: right;"><?= esc($customer_address) ?></th>
                        </tr>
                    <?php } ?>
                    <?php if (!empty($customer_location)) { ?>
                        <tr>
                            <th style="width: 55%;"><?= lang(ucfirst($controller_name) . '.customer_location') ?></th>
                            <th style="width: 45%; text-align: right;"><?= esc($customer_location) ?></th>
                        </tr>
                    <?php } ?>
                    <tr>
                        <th style="width: 55%;"><?= lang(ucfirst($controller_name) . '.customer_discount') ?></th>
                        <th style="width: 45%; text-align: right;"><?= ($customer_discount_type == FIXED) ? to_currency($customer_discount) : $customer_discount . '%' ?></th>
                    </tr>
                    <?php if ($config['customer_reward_enable']): ?>
                        <?php if (!empty($customer_rewards)) { ?>
                            <tr>
                                <th style="width: 55%;"><?= lang(ucfirst($controller_name) . '.rewards_package') ?></th>
                                <th style="width: 45%; text-align: right;"><?= esc($customer_rewards['package_name']) ?></th>
                            </tr>
                            <tr>
                                <th style="width: 55%;"><?= lang('Customers.available_points') ?></th>
                                <th style="width: 45%; text-align: right;"><?= esc($customer_rewards['points']) ?></th>
                            </tr>
                        <?php } ?>
                    <?php endif; ?>
                    <tr>
                        <th style="width: 55%;"><?= lang(ucfirst($controller_name) . '.customer_total') ?></th>
                        <th style="width: 45%; text-align: right;"><?= to_currency($customer_total) ?></th>
                    </tr>
                    <?php if (!empty($mailchimp_info)) { ?>
                        <tr>
                            <th style="width: 55%;"><?= lang(ucfirst($controller_name) . '.customer_mailchimp_status') ?></th>
                            <th style="width: 45%; text-align: right;"><?= esc($mailchimp_info['status']) ?></th>
                        </tr>
                    <?php } ?>
                </table>

                <?= anchor(
                    "$controller_name/removeCustomer",
                    '<span class="glyphicon glyphicon-remove">&nbsp;</span>' . lang('Common.remove') . ' ' . lang('Customers.customer'),
                    ['class' => 'btn btn-danger btn-sm', 'id' => 'remove_customer_button', 'title' => lang('Common.remove') . ' ' . lang('Customers.customer')]
                )
                ?>
            <?php } else { ?>
                <div class="form-group" id="select_customer">
                    <label id="customer_label" for="customer" class="control-label" style="margin-bottom: 1em; margin-top: -1em;">
                        <?= lang(ucfirst($controller_name) . '.select_customer') . esc(" $customer_required") ?>
                    </label>
                    <?= form_input(['name' => 'customer', 'id' => 'customer', 'class' => 'form-control input-sm', 'value' => lang(ucfirst($controller_name) . '.start_typing_customer_name')]) ?>

                    <button class="btn btn-info btn-sm modal-dlg" data-btn-submit="<?= lang('Common.submit') ?>" data-href="<?= "customers/view" ?>" title="<?= lang(ucfirst($controller_name) . ".new_customer") ?>">
                        <span class="glyphicon glyphicon-user">&nbsp;</span><?= lang(ucfirst($controller_name) . ".new_customer") ?>
                    </button>
                    <button class="btn btn-default btn-sm modal-dlg" id="show_keyboard_help" data-href="<?= esc("$controller_name/salesKeyboardHelp") ?>" title="<?= lang(ucfirst($controller_name) . '.key_title') ?>">
                        <span class="glyphicon glyphicon-share-alt">&nbsp;</span><?= lang(ucfirst($controller_name) . '.key_help') ?>
                    </button>
                </div>
            <?php } ?>
        <?= form_close() ?>

        <table class="sales_table_100" id="sale_totals">
            <tr>
                <th style="width: 55%;"><?= lang(ucfirst($controller_name) . '.quantity_of_items', [$item_count]) ?></th>
                <th style="width: 45%; text-align: right;"><?= $total_units ?></th>
            </tr>
            <tr>
                <th style="width: 55%;"><?= lang(ucfirst($controller_name) . '.sub_total') ?></th>
                <th style="width: 45%; text-align: right;"><?= to_currency($subtotal) ?></th>
            </tr>
            <?php foreach ($taxes as $tax_group_index => $tax) { ?>
                <tr>
                    <th style="width: 55%;"><?= (float)$tax['tax_rate'] . '% ' . $tax['tax_group'] ?></th>
                    <th style="width: 45%; text-align: right;"><?= to_currency_tax($tax['sale_tax_amount']) ?></th>
                </tr>
            <?php } ?>
            <tr>
                <th style="width: 55%; font-size: 150%"><?= lang(ucfirst($controller_name) . '.total') ?></th>
                <th style="width: 45%; font-size: 150%; text-align: right;"><span id="sale_total"><?= to_currency($total) ?></span></th>
            </tr>
        </table>

        <?php if (count($cart) > 0) { // Only show this part if there are Items already in the register ?>
            <table class="sales_table_100" id="payment_totals">
                <tr>
                    <th style="width: 55%;"><?= lang(ucfirst($controller_name) . '.payments_total') ?></th>
                    <th style="width: 45%; text-align: right;"><?= to_currency($payments_total) ?></th>
                </tr>
                <tr>
                    <th style="width: 55%; font-size: 120%"><?= lang(ucfirst($controller_name) . '.amount_due') ?></th>
                    <th style="width: 45%; font-size: 120%; text-align: right;"><span id="sale_amount_due"><?= to_currency($amount_due) ?></span></th>
                </tr>
            </table>

            <div id="payment_details">
                <?php if ($payments_cover_total) { // Show Complete sale button instead of Add Payment if there is no amount due left ?>
                    <?= form_open("$controller_name/addPayment", ['id' => 'add_payment_form', 'class' => 'form-horizontal']) ?>
                        <input type="hidden" name="complete_after_payment" value="0">
                        <table class="sales_table_100">
                            <tr>
                                <td><?= lang(ucfirst($controller_name) . '.payment') ?></td>
                                <td>
                                    <?= form_dropdown('payment_type', $payment_options, $selected_payment_type, ['id' => 'payment_types', 'class' => 'selectpicker show-menu-arrow', 'data-style' => 'btn-default btn-sm', 'data-width' => '100%', 'disabled' => 'disabled']) ?>
                                </td>
                            </tr>
                            <tr>
                                <td><span id="amount_tendered_label"><?= lang(ucfirst($controller_name) . '.amount_tendered') ?></span></td>
                                <td>
                                    <?= form_input([
                                        'name' => 'amount_tendered',
                                        'id' => 'amount_tendered',
                                        'class' => 'form-control input-sm disabled',
                                        'disabled' => 'disabled',
                                        'value' => '0',
                                        'size' => '5',
                                        'tabindex' => ++$tabindex,
                                        'onClick' => 'this.select();'
                                    ]) ?>
                                </td>
                            </tr>
                            <?php if ($amount_change > 0) { ?>
                                <tr>
                                    <td><span><?= lang(ucfirst($controller_name) . '.change_due') ?></span></td>
                                    <td><span class="label label-success" style="font-size: 100%;"><?= to_currency($amount_change) ?></span></td>
                                </tr>
                            <?php } ?>
                            <tr class="reference-code-input reference-code-input-hidden">
                                <td style="padding-top: 8px;"><span id='reference_code_label'><?= lang('Sales.reference_code') ?></span></td>
                                <td style="padding-top: 8px;">
                                    <?php echo form_input(array(
                                        'name'	=> 'reference_code',
                                        'id'	=> 'reference_code',
                                        'class'	=> 'form-control input-sm non-giftcard-input',
                                        'disabled' => true,
                                        'value'	=> '',
                                        'size' => 5,
                                        'tabindex'	=> ++$tabindex,
                                        'onClick'	=> 'this.select();'));
                                    ?>
                                </td>
                            </tr>
                        </table>
                    <?= form_close() ?>

                    <?php
                    // Only show this part if in sale or return mode
                    if ($pos_mode) {
                        $due_payment = false;

                        if (count($payments) > 0) {
                            foreach ($payments as $payment_id => $payment) {
                                if ($payment['payment_type'] == lang(ucfirst($controller_name) . '.due')) {
                                    $due_payment = true;
                                }
                            }
                        }

                        if (!$due_payment || ($due_payment && isset($customer))) {    // TODO: $due_payment is not needed because the first clause insures that it will always be true if it gets to this point.  Can be shortened to if (!$due_payment || isset($customer))
                    ?>
                            <div class="btn btn-sm btn-success pull-right" id="finish_sale_button" tabindex="<?= ++$tabindex ?>" title="Complete the sale and print the receipt">
                                <span class="glyphicon glyphicon-ok">&nbsp;</span><?= lang(ucfirst($controller_name) . '.complete_sale') ?> &amp; <?= lang('Common.print') ?>
                            </div>
                    <?php
                        }
                    }
                    ?>
                <?php } else { ?>
                    <?= form_open("$controller_name/addPayment", ['id' => 'add_payment_form', 'class' => 'form-horizontal']) ?>
                        <input type="hidden" name="complete_after_payment" value="0">
                        <table class="sales_table_100">
                            <tr>
                                <td colspan="2">
                                    <div class="btn-group btn-group-justified" role="group" id="quick_payment_buttons">
                                        <button type="button" class="btn btn-default btn-sm quick-payment-btn" data-payment-type="<?= esc(lang(ucfirst($controller_name) . '.cash'), 'attr') ?>">
                                            <span class="glyphicon glyphicon-usd"></span> <?= lang(ucfirst($controller_name) . '.cash') ?>
                                        </button>
                                        <button type="button" class="btn btn-default btn-sm quick-payment-btn" data-payment-type="<?= esc(lang('Sales.ecocash'), 'attr') ?>">
                                            <span class="glyphicon glyphicon-phone"></span> <?= lang('Sales.ecocash') ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><?= lang(ucfirst($controller_name) . '.payment') ?></td>
                                <td>
                                    <?= form_dropdown('payment_type', $payment_options,  $selected_payment_type, ['id' => 'payment_types', 'class' => 'selectpicker show-menu-arrow', 'data-style' => 'btn-default btn-sm', 'data-width' => '100%']) ?>
                                </td>
                            </tr>
                            <tr>
                                <td><span id="amount_tendered_label"><?= lang(ucfirst($controller_name) . '.amount_tendered') ?></span></td>
                                <td>
                                    <?= form_input(['name' => 'amount_tendered', 'id' => 'amount_tendered', 'class' => 'form-control input-sm non-giftcard-input', 'value' => to_currency_no_money($amount_due), 'size' => '5', 'tabindex' => ++$tabindex, 'onClick' => 'this.select();']) ?>
                                    <?= form_input(['name' => 'amount_tendered', 'id' => 'amount_tendered', 'class' => 'form-control input-sm giftcard-input', 'disabled' => true, 'value' => to_currency_no_money($amount_due), 'size' => '5', 'tabindex' => ++$tabindex]) ?>
                                </td>
                            </tr>
                            <tr id="change_row" style="display: none;">
                                <td><span><?= lang(ucfirst($controller_name) . '.change_due') ?></span></td>
                                <td><span id="change_due_amount" class="label label-success" style="font-size: 100%;">0</span></td>
                            </tr>
                            <tr class="reference-code-input reference-code-input-hidden">
                                <td style="padding-top: 8px;"><span id='reference_code_label'><?= lang('Sales.reference_code') ?></span></td>
                                <td style="padding-top: 8px;">
                                    <?php echo form_input(array(
                                        'name'     => 'reference_code',
                                        'id'       => 'reference_code',
                                        'class'    => 'form-control input-sm non-giftcard-input',
                                        'disabled' => true,
                                        'value'    => '',
                                        'size'     => 5,
                                        'tabindex' => ++$tabindex,
                                        'onClick'  => 'this.select();'));
                                    ?>
                                </td>
                            </tr>
                        </table>
                    <?= form_close() ?>

                    <div class="btn btn-sm btn-success pull-right" id="add_payment_button" tabindex="<?= ++$tabindex ?>">
                        <span class="glyphicon glyphicon-credit-card">&nbsp;</span><?= lang(ucfirst($controller_name) . '.add_payment') ?>
                    </div>
                <?php } ?>

                <?php if (count($payments) > 0) { // Only show this part if there is at least one payment entered. ?>
                    <table class="sales_table_100" id="register">
                        <thead>
                            <tr>
                                <th style="width: 10%;"><?= lang('Common.delete') ?></th>
                                <th style="width: 60%;"><?= lang(ucfirst($controller_name) . '.payment_type') ?></th>
                                <th style="width: 20%;"><?= lang(ucfirst($controller_name) . '.payment_amount') ?></th>
                            </tr>
                        </thead>

                        <tbody id="payment_contents">
                            <?php foreach ($payments as $payment_id => $payment) { ?>
                                <tr>
                                    <td><?= anchor("$controller_name/deletePayment/". esc(base64url_encode($payment_id), 'url'), '<span class="glyphicon glyphicon-trash"></span>') ?></td>
                                    <td><?= $payment['payment_type'] ?></td>
                                    <td style="text-align: right;"><?= to_currency($payment['payment_amount']) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            </div>

            <?= form_open("$controller_name/cancel", ['id' => 'buttons_form']) ?>
            <div class="form-group" id="buttons_sale">
                <div class="btn btn-sm btn-default pull-left" id="suspend_sale_button"><span class="glyphicon glyphicon-align-justify">&nbsp;</span><?= lang(ucfirst($controller_name) . '.suspend_sale') ?></div>
                <?php if (!$pos_mode && isset($customer)) { // Only show this part if the payment covers the total ?>
                    <div class="btn btn-sm btn-success" id="finish_invoice_quote_button"><span class="glyphicon glyphicon-ok">&nbsp;</span><?= esc($mode_label) ?></div>
                <?php } ?>

                <div class="btn btn-sm btn-danger pull-right" id="cancel_sale_button"><span class="glyphicon glyphicon-remove">&nbsp;</span><?= lang(ucfirst($controller_name) . '.cancel_sale') ?></div>
            </div>
            <?= form_close() ?>

            <?php // Reprints the receipt for the last sale rung up by this employee — a
                  // jammed/skipped print or a customer asking for another copy shouldn't
                  // mean digging through Sales > Manage. Opens in a new tab so it doesn't
                  // disturb whatever's currently in the cart.
                  //
                  // #buttons_sale above is all pull-left/pull-right floats with no
                  // clearfix, so its parent collapses to ~0 height — without clearing
                  // that float here first, this row renders on top of Suspend/Cancel
                  // instead of below them. ?>
            <div class="form-group" style="clear: both; margin-top: 8px;">
                <a href="<?= site_url('sales/reprint_last') ?>" target="_blank" class="btn btn-sm btn-default btn-block">
                    <span class="glyphicon glyphicon-print">&nbsp;</span> Print Last Receipt
                </a>
            </div>

            <?php if ($payments_cover_total || !$pos_mode) { // Only show this part if the payment cover the total ?>
                <div class="container-fluid">
                    <div class="no-gutter row">
                        <div class="form-group form-group-sm">
                            <div class="col-xs-12">
                                <?= form_label(lang('Common.comments'), 'comments', ['class' => 'control-label', 'id' => 'comment_label', 'for' => 'comment']) ?>
                                <?= form_textarea(['name' => 'comment', 'id' => 'comment', 'class' => 'form-control input-sm', 'value' => $comment, 'rows' => '2']) ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group form-group-sm">
                            <div class="col-xs-6">
                                <label for="sales_print_after_sale" class="control-label checkbox">
                                    <?= form_checkbox(['name' => 'sales_print_after_sale', 'id' => 'sales_print_after_sale', 'value' => 1, 'checked' => $print_after_sale]) ?>
                                    <?= lang(ucfirst($controller_name) . '.print_after_sale') ?>
                                </label>
                            </div>

                            <?php if (!empty($customer_email)) { ?>
                                <div class="col-xs-6">
                                    <label for="email_receipt" class="control-label checkbox">
                                        <?= form_checkbox(['name' => 'email_receipt', 'id' => 'email_receipt', 'value' => 1, 'checked' => $email_receipt]) ?>
                                        <?= lang(ucfirst($controller_name) . '.email_receipt') ?>
                                    </label>
                                </div>
                            <?php } ?>
                            <?php if ($mode == 'sale_work_order') { ?>
                                <div class="col-xs-6">
                                    <label for="price_work_orders" class="control-label checkbox">
                                        <?= form_checkbox(['name' => 'price_work_orders', 'id' => 'price_work_orders', 'value' => 1, 'checked' => $price_work_orders]) ?>
                                        <?= lang(ucfirst($controller_name) . '.include_prices') ?>
                                    </label>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                    <?php if (($mode == 'sale_invoice') && $config['invoice_enable']) { ?>
                        <div class="row">
                            <div class="form-group form-group-sm">
                                <div class="col-xs-6">
                                    <label for="sales_invoice_number" class="control-label checkbox">
                                        <?= lang(ucfirst($controller_name) . '.invoice_enable') ?>
                                    </label>
                                </div>

                                <div class="col-xs-6">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-addon input-sm">#</span>
                                        <?= form_input(['name' => 'sales_invoice_number', 'id' => 'sales_invoice_number', 'class' => 'form-control input-sm', 'value' => $invoice_number]) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
        <?php
            }
        }
        ?>
    </div>
</div>

<script type="text/javascript">
    const keyboardShortcuts = <?= json_encode($keyboardShortcuts ?? []) ?>;
    const paymentsCoverTotal = <?= json_encode((bool) $payments_cover_total) ?>;
    const referenceCodePaymentTypes = <?= json_encode($reference_code_payment_types) ?>;
    const amountDue = <?= json_encode((float) $amount_due) ?>;
    const cashAmountDue = <?= json_encode((float) $cash_amount_due) ?>;
    const cashMode = <?= json_encode((bool) $cash_mode) ?>;
    const totalsDecimals = <?= json_encode(totals_decimals()) ?>;
    const cashLabel = <?= json_encode(lang(ucfirst($controller_name) . '.cash')) ?>;
    const shortcutCodes = {
        items: keyboardShortcuts?.items?.code ?? null,
        customers: keyboardShortcuts?.customers?.code ?? null,
        suspend: keyboardShortcuts?.suspend?.code ?? null,
        suspended: keyboardShortcuts?.suspended?.code ?? null,
        amount: keyboardShortcuts?.amount?.code ?? null,
        payment: keyboardShortcuts?.payment?.code ?? null,
        complete: keyboardShortcuts?.complete?.code ?? null,
        finish: keyboardShortcuts?.finish?.code ?? null,
        help: keyboardShortcuts?.help?.code ?? null,
        cancel: keyboardShortcuts?.cancel?.code ?? null
    };

    $(document).ready(function() {
        const redirect = function() {
            window.location.href = "<?= site_url('sales'); ?>";
        };

        $("#remove_customer_button").click(function() {
            $.post("<?= site_url('sales/removeCustomer'); ?>", redirect);
        });

        $(".delete_item_button").click(function(e) {
            e.preventDefault(); // otherwise the anchor's own GET href fires too, and deleteItem is POST-only
            const item_id = $(this).data('item-id');
            $.post("<?= site_url('sales/deleteItem/'); ?>" + item_id, redirect);
        });

        $(".delete_payment_button").click(function() {
            const item_id = $(this).data('payment-id');
            $.post("<?= site_url('sales/deletePayment/'); ?>" + item_id, redirect);
        });

        $("input[name='item_number']").change(function() {
            const item_id = $(this).parents('tr').find("input[name='item_id']").val();
            const item_number = $(this).val();
            $.ajax({
                url: "<?= site_url('sales/changeItemNumber') ?>",
                method: 'post',
                data: {
                    'item_id': item_id,
                    'item_number': item_number,
                },
                dataType: 'json'
            });
        });

        $("input[name='name']").change(function() {
            const item_id = $(this).parents('tr').find("input[name='item_id']").val();
            const item_name = $(this).val();
            $.ajax({
                url: "<?= site_url('sales/changeItemName') ?>",
                method: 'post',
                data: {
                    'item_id': item_id,
                    'item_name': item_name,
                },
                dataType: 'json'
            });
        });

        $("input[name='item_description']").change(function() {
            const item_id = $(this).parents('tr').find("input[name='item_id']").val();
            const item_description = $(this).val();
            $.ajax({
                url: "<?= site_url('sales/changeItemDescription') ?>",
                method: 'post',
                data: {
                    'item_id': item_id,
                    'item_description': item_description,
                },
                dataType: 'json'
            });
        });

        $('#item').focus();

        $('#item').blur(function() {
            $(this).val("<?= lang(ucfirst($controller_name) . '.start_typing_item_name') ?>");
        });

        $('#item').autocomplete({
            source: "<?= esc("$controller_name/itemSearch") ?>",
            minChars: 0,
            autoFocus: false,
            delay: 500,
            select: function(a, ui) {
                $(this).val(ui.item.value);
                $('#add_item_form').submit();
                return false;
            }
        });

        $('#item').keypress(function(e) {
            if (e.which == 13) {
                $('#add_item_form').submit();
                return false;
            }
        });

        const clear_fields = function() {
            if ($(this).val().match("<?= lang(ucfirst($controller_name) . '.start_typing_item_name') . '|' . lang(ucfirst($controller_name) . '.start_typing_customer_name') ?>")) {
                $(this).val('');
            }
        };

        $('#item, #customer').click(clear_fields).dblclick(function(event) {
            $(this).autocomplete('search');
        });

        $('#customer').blur(function() {
            $(this).val("<?= lang(ucfirst($controller_name) . '.start_typing_customer_name') ?>");
        });

        $('#customer').autocomplete({
            source: "<?= site_url('customers/suggest') ?>",
            minChars: 0,
            delay: 10,
            select: function(a, ui) {
                $(this).val(ui.item.value);
                $('#select_customer_form').submit();
                return false;
            }
        });

        $('#customer').keypress(function(e) {
            if (e.which == 13) {
                $('#select_customer_form').submit();
                return false;
            }
        });

        $('.giftcard-input').autocomplete({
            source: "<?= site_url('giftcards/suggest') ?>",
            minChars: 0,
            delay: 10,
            select: function(a, ui) {
                $(this).val(ui.item.value);
                $('#add_payment_form').submit();
                return false;
            }
        });

        $('#comment').keyup(function() {
            $.post("<?= esc(site_url("$controller_name/setComment")) ?>", {
                comment: $('#comment').val()
            });
        });

        <?php if ($config['invoice_enable']) { ?>
            $('#sales_invoice_number').keyup(function() {
                $.post("<?= esc(site_url("$controller_name/setInvoiceNumber")) ?>", {
                    sales_invoice_number: $('#sales_invoice_number').val()
                });
            });

        <?php } ?>

        $('#sales_print_after_sale').change(function() {
            $.post("<?= esc(site_url("$controller_name/setPrintAfterSale")) ?>", {
                sales_print_after_sale: $(this).is(':checked')
            });
        });

        $('#price_work_orders').change(function() {
            $.post("<?= esc(site_url("$controller_name/setPriceWorkOrders")) ?>", {
                price_work_orders: $(this).is(':checked')
            });
        });

        $('#email_receipt').change(function() {
            $.post("<?= esc(site_url("$controller_name/setEmailReceipt")) ?>", {
                email_receipt: $(this).is(':checked')
            });
        });

        $('#finish_sale_button').click(function() {
            $('#buttons_form').attr('action', "<?= "$controller_name/complete" ?>");
            $('#buttons_form').submit();
        });

        $('#finish_invoice_quote_button').click(function() {
            $('#buttons_form').attr('action', "<?= "$controller_name/complete" ?>");
            $('#buttons_form').submit();
        });

        $('#suspend_sale_button').click(function() {
            $('#buttons_form').attr('action', "<?= site_url("$controller_name/suspend") ?>");
            $('#buttons_form').submit();
        });

        $('#cancel_sale_button').click(function() {
            if (confirm("<?= lang(ucfirst($controller_name) . '.confirm_cancel_sale') ?>")) {
                $('#buttons_form').attr('action', "<?= site_url("$controller_name/cancel") ?>");
                $('#buttons_form').submit();
            }
        });

        $('#add_payment_button').click(function() {
            var paymentType = $("#payment_types").val();
            var pesepay = "<?= lang('Sales.pesepay') ?>";
            var ecocash = "<?= lang('Sales.ecocash') ?>";
            var card = "<?= lang('Sales.card') ?>";
            var layby = "<?= lang('Sales.layby') ?>";

            if (paymentType === pesepay) {
                $("#pesepay_amount").text("<?= to_currency($amount_due) ?>");
                $("#pesepay_modal").modal('show');
            } else if (paymentType === ecocash) {
                $("#ecocash_amount").text("<?= to_currency($amount_due) ?>");
                $("#ecocash_phone").val('').focus();
                $("#ecocash_modal").modal('show');
            } else if (paymentType === card) {
                $("#card_amount").text("<?= to_currency($amount_due) ?>");
                $("#card_number").val('').focus();
                $("#card_modal").modal('show');
            } else if (paymentType === layby) {
                $("#layby_amount").text("<?= to_currency($amount_due) ?>");
                $("#layby_deposit").val("<?= to_currency_no_money($amount_due) ?>");
                $("#layby_modal").modal('show');
            } else {
                $('#add_payment_form').find('input[name="complete_after_payment"]').val('0');
                $('#add_payment_form').submit();
            }
        });

        $('#payment_types').change(check_payment_type).ready(check_payment_type);

        // Clear one-tap payment method buttons: Cash / EcoCash.
        $('#quick_payment_buttons').on('click', '.quick-payment-btn', function() {
            var $select = $('#payment_types');
            $select.val($(this).data('payment-type'));
            if ($select.data('selectpicker')) {
                $select.selectpicker('refresh');
            }
            $select.trigger('change');
            $('#amount_tendered:enabled').focus().select();
        });

        // Live change calculation while the cashier types the cash received.
        $('#amount_tendered').on('input keyup change', update_change_due);
        update_change_due();

        $('#cart_contents input').keypress(function(event) {
            if (event.which == 13) {
                $(this).parents('tr').prevAll('form:first').submit();
            }
        });

        $('#amount_tendered').keypress(function(event) {
            if (event.which == 13) {
                $('#add_payment_form').submit();
            }
        });

        $('#finish_sale_button').keypress(function(event) {
            if (event.which == 13) {
                $('#finish_sale_form').submit();
            }
        });

        dialog_support.init('a.modal-dlg, button.modal-dlg');

        table_support.handle_submit = function(resource, response, stay_open) {
            $.notify({
                message: response.message
            }, {
                type: response.success ? 'success' : 'danger'
            })

            if (response.success) {
                if (resource.match(/customers$/)) {
                    $('#customer').val(response.id);
                    $('#select_customer_form').submit();
                } else {
                    const $stock_location = $("select[name='stock_location']").val();
                    $('#item_location').val($stock_location);
                    $('#item').val(response.id);
                    if (stay_open) {
                        $('#add_item_form').ajaxSubmit();
                    } else {
                        $('#add_item_form').submit();
                    }
                }
            }
        }

        $('[name="price"],[name="quantity"],[name="discount"],[name="description"],[name="serialnumber"],[name="discounted_total"]').change(function() {
            $(this).parents('tr').prevAll('form:first').submit()
        });

        $('[name="discount_toggle"]').change(function() {
            const input = $('<input>').attr('type', 'hidden').attr('name', 'discount_type').val(($(this).prop('checked')) ? 1 : 0);
            $('#cart_' + $(this).attr('data-line')).append($(input));
            $('#cart_' + $(this).attr('data-line')).submit();
        });
    });

    // Shows the change the customer is owed as soon as the cash received is
    // entered, instead of only on the printed receipt.
    function update_change_due() {
        var $row = $('#change_row');
        if (!$row.length) {
            return;
        }

        var paymentType = $('#payment_types').val();
        var isCash = paymentType == cashLabel;

        if (!isCash) {
            $row.hide();
            return;
        }

        var $input = $('#amount_tendered:enabled');
        if (!$input.length || $input.prop('disabled')) {
            $row.hide();
            return;
        }

        var due = (cashMode ? cashAmountDue : amountDue);
        var received = parseFloat($input.val());
        if (isNaN(received) || received <= 0) {
            $row.hide();
            return;
        }

        var difference = received - due;
        var $badge = $('#change_due_amount');

        $row.show();
        if (difference >= 0) {
            $badge.removeClass('label-warning').addClass('label-success').text(difference.toFixed(totalsDecimals));
        } else {
            $badge.removeClass('label-success').addClass('label-warning').text('<?= esc(lang('Sales.amount_due'), 'js') ?>: ' + Math.abs(difference).toFixed(totalsDecimals));
        }
    }

    function check_payment_type() {
        const cash_mode = <?= json_encode($cash_mode) ?>;
        const paymentType = $("#payment_types").val();
        const isGiftCard = paymentType == "<?= lang(ucfirst($controller_name) . '.giftcard') ?>";
        const isCash = paymentType == "<?= lang(ucfirst($controller_name) . '.cash') ?>";
        const needsReferenceCode = referenceCodePaymentTypes.indexOf(paymentType) !== -1;

        if (isGiftCard) {
            $("#sale_total").html("<?= to_currency($total) ?>");
            $("#sale_amount_due").html("<?= to_currency($amount_due) ?>");
            $("#amount_tendered_label").html("<?= lang(ucfirst($controller_name) . '.giftcard_number') ?>");
            $("#amount_tendered:enabled").val('').focus();
            $(".giftcard-input").attr('disabled', false);
            $(".non-giftcard-input").attr('disabled', true);
            $(".giftcard-input:enabled").val('').focus();
            $(".reference-code-input").hide();
        } else if (isCash && cash_mode == '1') {
            $("#sale_total").html("<?= to_currency($non_cash_total) ?>");
            $("#sale_amount_due").html("<?= to_currency($cash_amount_due) ?>");
            $("#amount_tendered_label").html("<?= lang(ucfirst($controller_name) . '.amount_tendered') ?>");
            $("#amount_tendered:enabled").val("<?= to_currency_no_money($cash_amount_due) ?>");
            $(".giftcard-input").attr('disabled', true);
            $(".non-giftcard-input").attr('disabled', false);
            $(".reference-code-input").hide();
        } else {
            $("#sale_total").html("<?= to_currency($non_cash_total) ?>");
            $("#sale_amount_due").html("<?= to_currency($amount_due) ?>");
            $("#amount_tendered_label").html("<?= lang(ucfirst($controller_name) . '.amount_tendered') ?>");
            $("#amount_tendered:enabled").val("<?= to_currency_no_money($amount_due) ?>");
            $(".giftcard-input").attr('disabled', true);
            $(".non-giftcard-input").attr('disabled', false);
            if (needsReferenceCode) {
                $("#reference_code_label").html("<?= lang(ucfirst($controller_name) . '.reference_code') ?>");
                $(".reference-code-input").show();
                $(".reference-code-input input").attr('disabled', false);
            } else {
                $(".reference-code-input").hide();
                $(".reference-code-input input").attr('disabled', true);
            }
        }

        update_change_due();
    }

    // Add Keyboard Shortcuts/Hotkeys to Sale Register
    document.body.onkeyup = function(event) {
        if ($(event.target).closest('.modal').length || $('.modal.in').length) {
            return;
        }
        if (event.altKey) {
            switch (event.keyCode) {
                case shortcutCodes.items:
                    $("#item").focus();
                    $("#item").select();
                    break;
                case shortcutCodes.customers:
                    $("#customer").focus();
                    $("#customer").select();
                    break;
                case shortcutCodes.suspend:
                    $("#suspend_sale_button").click();
                    break;
                case shortcutCodes.suspended:
                    $("#show_suspended_sales_button").click();
                    break;
                case shortcutCodes.amount:
                    $("#amount_tendered").focus();
                    $("#amount_tendered").select();
                    break;
                case shortcutCodes.payment:
                    $("#add_payment_button").click();
                    break;
                case shortcutCodes.complete:
                    if (paymentsCoverTotal && $("#finish_sale_button").length) {
                        $("#finish_sale_button").click();
                    } else {
                        $("#add_payment_button").click();
                    }
                    break;
                case shortcutCodes.finish:
                    $("#finish_invoice_quote_button").click();
                    break;
                case shortcutCodes.help:
                    $("#show_keyboard_help").click();
                    break;
            }
        }

        switch (event.keyCode) {
            case shortcutCodes.cancel:
                $("#cancel_sale_button").click();
                break;
        }
    }
</script>

<!-- ═══════════════════════════════════════ -->
<!-- PAYMENT MODALS                         -->
<!-- ═══════════════════════════════════════ -->

<!-- Pesepay Modal -->
<div class="modal fade" id="pesepay_modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-cloud-upload"></i> <?= lang('Sales.initiate_pesepay') ?></h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong><?= lang('Sales.amount_due') ?>:</strong> <span id="pesepay_amount"></span>
                </div>
                <p>A Pesepay checkout link will be generated for this transaction.</p>
                <div class="form-group">
                    <label>Customer Phone (for WhatsApp notification)</label>
                    <input type="text" id="pesepay_phone" class="form-control" placeholder="e.g. 0771234567">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="pesepay_confirm">
                    <i class="glyphicon glyphicon-ok"></i> <?= lang('Sales.initiate_pesepay') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- EcoCash Modal -->
<div class="modal fade" id="ecocash_modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-phone"></i> <?= lang('Sales.initiate_ecocash') ?></h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong><?= lang('Sales.amount_due') ?>:</strong> <span id="ecocash_amount"></span>
                </div>
                <div class="form-group">
                    <label><?= lang('Sales.phone_number') ?> <span style="color:red;">*</span></label>
                    <input type="text" id="ecocash_phone" class="form-control" placeholder="<?= lang('Sales.ecocash_phone_hint') ?>" required>
                    <small class="text-muted">EcoCash will send a payment prompt to this number</small>
                </div>
                <div id="ecocash_status" class="alert alert-info" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="ecocash_confirm">
                    <i class="glyphicon glyphicon-ok"></i> <?= lang('Sales.initiate_ecocash') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Card Payment Modal -->
<div class="modal fade" id="card_modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-credit-card"></i> <?= lang('Sales.initiate_card') ?></h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong><?= lang('Sales.amount_due') ?>:</strong> <span id="card_amount"></span>
                </div>
                <div class="form-group">
                    <label><?= lang('Sales.card_number') ?> <span style="color:red;">*</span></label>
                    <input type="text" id="card_number" class="form-control" placeholder="Enter card number" maxlength="19">
                </div>
                <div class="form-group">
                    <label><?= lang('Sales.card_holder') ?> <span style="color:red;">*</span></label>
                    <input type="text" id="card_holder" class="form-control" placeholder="Name on card">
                </div>
                <div class="row">
                    <div class="col-xs-6">
                        <div class="form-group">
                            <label>Expiry <span style="color:red;">*</span></label>
                            <input type="text" id="card_expiry" class="form-control" placeholder="MM/YY" maxlength="5">
                        </div>
                    </div>
                    <div class="col-xs-6">
                        <div class="form-group">
                            <label>CVV <span style="color:red;">*</span></label>
                            <input type="text" id="card_cvv" class="form-control" placeholder="CVV" maxlength="4">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="card_confirm">
                    <i class="glyphicon glyphicon-ok"></i> <?= lang('Sales.initiate_card') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Layby Modal -->
<div class="modal fade" id="layby_modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="glyphicon glyphicon-calendar"></i> <?= lang('Sales.setup_layby') ?></h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong><?= lang('Sales.amount_due') ?>:</strong> <span id="layby_amount"></span>
                </div>
                <div class="row">
                    <div class="col-xs-6">
                        <div class="form-group">
                            <label><?= lang('Sales.layby_deposit') ?></label>
                            <input type="number" step="0.01" id="layby_deposit" class="form-control" value="">
                        </div>
                    </div>
                    <div class="col-xs-6">
                        <div class="form-group">
                            <label><?= lang('Sales.layby_installments') ?></label>
                            <input type="number" min="1" id="layby_installment_count" class="form-control" value="3">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xs-6">
                        <div class="form-group">
                            <label><?= lang('Sales.layby_frequency') ?></label>
                            <select id="layby_frequency" class="form-control">
                                <option value="weekly"><?= lang('Sales.layby_weekly') ?></option>
                                <option value="biweekly"><?= lang('Sales.layby_biweekly') ?></option>
                                <option value="monthly" selected><?= lang('Sales.layby_monthly') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="col-xs-6">
                        <div class="form-group">
                            <label><?= lang('Sales.reference_code') ?></label>
                            <input type="text" id="layby_ref" class="form-control" placeholder="Optional reference">
                        </div>
                    </div>
                </div>
                <div id="layby_summary" class="alert alert-default" style="margin-top:10px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="layby_confirm">
                    <i class="glyphicon glyphicon-ok"></i> <?= lang('Sales.setup_layby') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
// Pesepay confirm
$('#pesepay_confirm').click(function() {
    var phone = $('#pesepay_phone').val();
    $('#reference_code').val('PES' + (phone || 'MANUAL'));
    $('#pesepay_modal').modal('hide');
    $('#add_payment_form').find('input[name="complete_after_payment"]').val('0');
    $('#add_payment_form').submit();
});

// EcoCash confirm — sends the USSD push, then actually waits (polling
// sales/check_ecocash) for the customer to approve it on their phone before
// completing the sale. It used to treat "prompt sent" as "paid" and finish
// the sale immediately, which both completed sales the customer never
// actually approved, and separately fed the gateway's hyphenated
// clientCorrelator (e.g. "POS-1735500000-123") straight into #reference_code,
// which fails the alpha_numeric-only validation on submit.
var ecocashPollTimer = null;
var ecocashPollDeadline = null;
const ECOCASH_POLL_INTERVAL_MS = 4000;
const ECOCASH_POLL_TIMEOUT_MS = 90000; // typical USSD prompt expiry

function stopEcocashPolling() {
    if (ecocashPollTimer) {
        clearTimeout(ecocashPollTimer);
        ecocashPollTimer = null;
    }
}

function ecocashSanitizedReference(clientCorrelator, phone) {
    var base = 'ECO' + (clientCorrelator || phone || '').replace(/[^a-zA-Z0-9]/g, '');
    return base.slice(0, 20);
}

function ecocashComplete(refCode) {
    stopEcocashPolling();
    $('#reference_code').val(refCode);
    $('#ecocash_modal').modal('hide');
    $('#add_payment_form').find('input[name="complete_after_payment"]').val('0');
    $('#add_payment_form').submit();
}

function ecocashFail(message) {
    stopEcocashPolling();
    $('#ecocash_status').removeClass('alert-info').addClass('alert-danger')
        .text(message || 'EcoCash payment was not completed.');
    $('#ecocash_confirm').prop('disabled', false).html('<i class="glyphicon glyphicon-ok"></i> <?= esc(lang('Sales.initiate_ecocash'), 'js') ?>');
    $('#ecocash_phone').prop('disabled', false);
}

function pollEcocashStatus(phone, clientCorrelator, refCode) {
    if (Date.now() > ecocashPollDeadline) {
        ecocashFail('Customer did not respond to the prompt in time. Ask them to check their phone, or try again.');
        return;
    }

    $.ajax({
        url: "<?= site_url('sales/check_ecocash') ?>/" + encodeURIComponent(phone) + "/" + encodeURIComponent(clientCorrelator),
        type: 'GET',
        dataType: 'json',
        success: function(res) {
            if (res.complete) {
                $('#ecocash_status').text('Payment confirmed — completing sale...');
                ecocashComplete(refCode);
            } else if (res.success === false) {
                ecocashFail('EcoCash payment failed: ' + (res.message || 'declined or cancelled.'));
            } else {
                // still pending — keep waiting
                ecocashPollTimer = setTimeout(function() { pollEcocashStatus(phone, clientCorrelator, refCode); }, ECOCASH_POLL_INTERVAL_MS);
            }
        },
        error: function() {
            // Transient network hiccup while polling — keep trying until the deadline rather than failing the sale outright.
            ecocashPollTimer = setTimeout(function() { pollEcocashStatus(phone, clientCorrelator, refCode); }, ECOCASH_POLL_INTERVAL_MS);
        }
    });
}

$('#ecocash_confirm').click(function() {
    var phone = $('#ecocash_phone').val();
    if (!phone || phone.length < 7) {
        alert('Please enter a valid phone number');
        return;
    }
    var amountDue = $('#amount_tendered').val() || 0;
    var $btn = $(this);
    $btn.prop('disabled', true).html('<i class="glyphicon glyphicon-refresh glyphicon-spin"></i> Sending prompt to customer phone...');
    $('#ecocash_phone').prop('disabled', true);
    $('#ecocash_status').removeClass('alert-danger').addClass('alert-info')
        .text('Sending payment prompt...').show();

    $.ajax({
        url: "<?= site_url('sales/initiate_ecocash') ?>",
        type: 'POST',
        data: {
            phone: phone,
            amount: amountDue,
            "<?= csrf_token() ?>": "<?= csrf_hash() ?>"
        },
        dataType: 'json',
        success: function(res) {
            if (res.success && res.clientCorrelator) {
                var refCode = ecocashSanitizedReference(res.clientCorrelator, phone);
                $btn.html('<i class="glyphicon glyphicon-refresh glyphicon-spin"></i> Waiting for customer to approve on their phone...');
                $('#ecocash_status').text('Prompt sent — waiting for the customer to approve it on their phone...');
                ecocashPollDeadline = Date.now() + ECOCASH_POLL_TIMEOUT_MS;
                pollEcocashStatus(res.phone || phone, res.clientCorrelator, refCode);
            } else {
                ecocashFail(res.message || 'Could not initiate transaction.');
            }
        },
        error: function() {
            ecocashFail('Could not reach the payment gateway. Check the connection and try again.');
        }
    });
});

// Stop any in-flight polling if the cashier closes the modal without waiting it out.
$('#ecocash_modal').on('hidden.bs.modal', function() {
    stopEcocashPolling();
    $('#ecocash_confirm').prop('disabled', false).html('<i class="glyphicon glyphicon-ok"></i> <?= esc(lang('Sales.initiate_ecocash'), 'js') ?>');
    $('#ecocash_phone').prop('disabled', false);
    $('#ecocash_status').hide();
});

// Card confirm
$('#card_confirm').click(function() {
    var num = $('#card_number').val();
    var holder = $('#card_holder').val();
    var expiry = $('#card_expiry').val();
    var cvv = $('#card_cvv').val();
    if (!num || !holder || !expiry || !cvv) {
        alert('Please fill in all card details');
        return;
    }
    var last4 = num.slice(-4);
    $('#reference_code').val('CARD' + last4);
    $('#card_modal').modal('hide');
    $('#add_payment_form').find('input[name="complete_after_payment"]').val('0');
    $('#add_payment_form').submit();
});

// Layby confirm
$('#layby_confirm').click(function() {
    var ref = $('#layby_ref').val() || 'LAYBY' + Date.now();
    $('#reference_code').val(ref);
    $('#layby_modal').modal('hide');
    $('#add_payment_form').find('input[name="complete_after_payment"]').val('0');
    $('#add_payment_form').submit();
});

// Layby summary update
$('#layby_deposit, #layby_installment_count').on('input', function() {
    var total = <?= to_currency_no_money($amount_due) ?>;
    var deposit = parseFloat($('#layby_deposit').val()) || 0;
    var count = parseInt($('#layby_installment_count').val()) || 1;
    var balance = total - deposit;
    var perInstallment = count > 0 ? balance / count : balance;
    $('#layby_summary').html(
        '<strong>Balance:</strong> ' + balance.toFixed(2) +
        ' | <strong>Per installment:</strong> ' + perInstallment.toFixed(2)
    );
});
</script>

<?= view('partial/footer') ?>
