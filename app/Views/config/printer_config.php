<?php
/**
 * @var array $printer_config
 */
?>

<?= form_open('config/printer/save', ['id' => 'printer_config_form', 'class' => 'form-horizontal']) ?>
    <div id="config_wrapper">
        <fieldset id="config_info">

            <div class="row" style="margin-bottom: 10px;">
                <div class="col-xs-12">
                    <a href="<?= base_url('config') ?>" class="btn btn-default btn-sm"><i class="bi bi-arrow-left"></i> Back to Config</a>
                </div>
            </div>

            <div id="required_fields_message"><?= lang('Common.fields_required_message') ?></div>
            <ul id="printer_error_message_box" class="error_message_box"></ul>

            <!-- ═══════════════════════════════════════ -->
            <!-- RECEIPT PRINTER SETTINGS               -->
            <!-- ═══════════════════════════════════════ -->

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <span class="glyphicon glyphicon-print"></span>
                        Receipt Printer Settings
                    </h4>
                </div>
                <div class="panel-body">

                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-3" for="printer_connection">Printer Connection</label>
                        <div class="col-xs-4">
                            <?= form_dropdown(
                                'printer_connection',
                                [
                                    'browser'   => 'Browser Print',
                                    'bluetooth' => 'Bluetooth',
                                    'usb'       => 'USB',
                                    'network'   => 'Network / TCP'
                                ],
                                $printer_config['printer_connection'],
                                'id="printer_connection" class="form-control input-sm"'
                            ) ?>
                        </div>
                    </div>

                    <!-- Bluetooth fields -->
                    <div class="form-group form-group-sm printer-connection-field" data-connection="bluetooth" style="<?= $printer_config['printer_connection'] !== 'bluetooth' ? 'display: none;' : '' ?>">
                        <label class="control-label col-xs-3" for="printer_bluetooth_name">Bluetooth Printer Name</label>
                        <div class="col-xs-4">
                            <?= form_input([
                                'type'  => 'text',
                                'name'  => 'printer_bluetooth_name',
                                'id'    => 'printer_bluetooth_name',
                                'class' => 'form-control input-sm',
                                'placeholder' => 'e.g. EPSON TM-T20II',
                                'value' => $printer_config['printer_bluetooth_name']
                            ]) ?>
                        </div>
                    </div>

                    <!-- USB fields -->
                    <div class="form-group form-group-sm printer-connection-field" data-connection="usb" style="<?= $printer_config['printer_connection'] !== 'usb' ? 'display: none;' : '' ?>">
                        <label class="control-label col-xs-3" for="printer_usb_vendor_id">USB Vendor ID</label>
                        <div class="col-xs-4">
                            <?= form_input([
                                'type'  => 'text',
                                'name'  => 'printer_usb_vendor_id',
                                'id'    => 'printer_usb_vendor_id',
                                'class' => 'form-control input-sm',
                                'placeholder' => 'e.g. 0x04b8',
                                'value' => $printer_config['printer_usb_vendor_id']
                            ]) ?>
                        </div>
                    </div>

                    <!-- Network fields -->
                    <div class="form-group form-group-sm printer-connection-field" data-connection="network" style="<?= $printer_config['printer_connection'] !== 'network' ? 'display: none;' : '' ?>">
                        <label class="control-label col-xs-3" for="printer_network_ip">Network Printer IP</label>
                        <div class="col-xs-4">
                            <?= form_input([
                                'type'  => 'text',
                                'name'  => 'printer_network_ip',
                                'id'    => 'printer_network_ip',
                                'class' => 'form-control input-sm',
                                'placeholder' => 'e.g. 192.168.1.100',
                                'value' => $printer_config['printer_network_ip']
                            ]) ?>
                        </div>
                    </div>

                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-3" for="paper_width">Paper Width</label>
                        <div class="col-xs-3">
                            <?= form_dropdown(
                                'paper_width',
                                [
                                    '58mm' => '58mm',
                                    '80mm' => '80mm'
                                ],
                                $printer_config['paper_width'],
                                'id="paper_width" class="form-control input-sm"'
                            ) ?>
                        </div>
                    </div>

                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-3" for="auto_cut_receipt">Auto-cut Receipt</label>
                        <div class="col-xs-1">
                            <?= form_checkbox([
                                'name'    => 'auto_cut_receipt',
                                'id'      => 'auto_cut_receipt',
                                'value'   => 'auto_cut_receipt',
                                'checked' => $printer_config['auto_cut_receipt'] == 1
                            ]) ?>
                        </div>
                    </div>

                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-3" for="print_customer_copy">Print Customer Copy</label>
                        <div class="col-xs-1">
                            <?= form_checkbox([
                                'name'    => 'print_customer_copy',
                                'id'      => 'print_customer_copy',
                                'value'   => 'print_customer_copy',
                                'checked' => $printer_config['print_customer_copy'] == 1
                            ]) ?>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ═══════════════════════════════════════ -->
            <!-- PAYMENT TERMINAL SETTINGS              -->
            <!-- ═══════════════════════════════════════ -->

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <span class="glyphicon glyphicon-credit-card"></span>
                        Payment Terminal Settings
                    </h4>
                </div>
                <div class="panel-body">

                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-3" for="terminal_enabled">Terminal Enabled</label>
                        <div class="col-xs-1">
                            <?= form_checkbox([
                                'name'    => 'terminal_enabled',
                                'id'      => 'terminal_enabled',
                                'value'   => 'terminal_enabled',
                                'checked' => $printer_config['terminal_enabled'] == 1
                            ]) ?>
                        </div>
                    </div>

                    <div class="form-group form-group-sm" id="terminal_type_group" style="<?= $printer_config['terminal_enabled'] != 1 ? 'display: none;' : '' ?>">
                        <label class="control-label col-xs-3" for="terminal_type">Terminal Type</label>
                        <div class="col-xs-4">
                            <?= form_dropdown(
                                'terminal_type',
                                [
                                    'manual'   => 'Manual Entry',
                                    'pesepay'  => 'Pesepay',
                                    'ecocash'  => 'EcoCash QR',
                                    'paynow'   => 'Paynow QR'
                                ],
                                $printer_config['terminal_type'],
                                'id="terminal_type" class="form-control input-sm"'
                            ) ?>
                        </div>
                    </div>

                    <div class="form-group form-group-sm" id="auto_approve_group" style="<?= $printer_config['terminal_enabled'] != 1 ? 'display: none;' : '' ?>">
                        <label class="control-label col-xs-3" for="auto_approve_payments">Auto-approve Payments</label>
                        <div class="col-xs-1">
                            <?= form_checkbox([
                                'name'    => 'auto_approve_payments',
                                'id'      => 'auto_approve_payments',
                                'value'   => 'auto_approve_payments',
                                'checked' => $printer_config['auto_approve_payments'] == 1
                            ]) ?>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ═══════════════════════════════════════ -->
            <!-- RECEIPT CUSTOMIZATION                  -->
            <!-- ═══════════════════════════════════════ -->

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <span class="glyphicon glyphicon-list-alt"></span>
                        Receipt Customization
                    </h4>
                </div>
                <div class="panel-body">

                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-3" for="receipt_show_logo">Show Logo on Receipt</label>
                        <div class="col-xs-1">
                            <?= form_checkbox([
                                'name'    => 'receipt_show_logo',
                                'id'      => 'receipt_show_logo',
                                'value'   => 'receipt_show_logo',
                                'checked' => $printer_config['receipt_show_logo'] == 1
                            ]) ?>
                        </div>
                    </div>

                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-3" for="receipt_custom_footer">Custom Footer Text</label>
                        <div class="col-xs-6">
                            <?= form_textarea([
                                'name'  => 'receipt_custom_footer',
                                'id'    => 'receipt_custom_footer',
                                'class' => 'form-control input-sm',
                                'rows'  => 3,
                                'placeholder' => 'e.g. Thank you for your purchase!',
                                'value' => $printer_config['receipt_custom_footer']
                            ]) ?>
                        </div>
                    </div>

                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-3" for="receipt_show_item_codes">Show Item Codes</label>
                        <div class="col-xs-1">
                            <?= form_checkbox([
                                'name'    => 'receipt_show_item_codes',
                                'id'      => 'receipt_show_item_codes',
                                'value'   => 'receipt_show_item_codes',
                                'checked' => $printer_config['receipt_show_item_codes'] == 1
                            ]) ?>
                        </div>
                    </div>

                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-3" for="receipt_show_cashier_name">Show Cashier Name</label>
                        <div class="col-xs-1">
                            <?= form_checkbox([
                                'name'    => 'receipt_show_cashier_name',
                                'id'      => 'receipt_show_cashier_name',
                                'value'   => 'receipt_show_cashier_name',
                                'checked' => $printer_config['receipt_show_cashier_name'] == 1
                            ]) ?>
                        </div>
                    </div>

                </div>
            </div>

            <?= form_submit([
                'name'  => 'submit_printer_config',
                'id'    => 'submit_printer_config',
                'value' => lang('Common.submit'),
                'class' => 'btn btn-primary btn-sm pull-right'
            ]) ?>

        </fieldset>
    </div>
<?= form_close() ?>

<script type="text/javascript">
    $(document).ready(function() {
        // Toggle connection-specific fields
        $('#printer_connection').change(function() {
            var connection = $(this).val();
            $('.printer-connection-field').hide();
            $('.printer-connection-field[data-connection="' + connection + '"]').show();
        });

        // Toggle terminal fields
        $('#terminal_enabled').change(function() {
            if ($(this).is(':checked')) {
                $('#terminal_type_group, #auto_approve_group').show();
            } else {
                $('#terminal_type_group, #auto_approve_group').hide();
            }
        });

        // Form submission
        $('#printer_config_form').validate($.extend(form_support.handler, {
            submitHandler: function(form) {
                $(form).ajaxSubmit({
                    success: function(response) {
                        $.notify({
                            message: response.message
                        }, {
                            type: response.success ? 'success' : 'danger'
                        });
                    },
                    dataType: 'json'
                });
            },
            errorLabelContainer: "#printer_error_message_box"
        }));
    });
</script>
