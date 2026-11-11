<?php
/**
 * @var int $sale_id_num
 * @var bool $print_after_sale
 * @var bool $is_new_sale
 * @var string $receipt_template_view
 * @var array $config
 */

use App\Models\Employee;

$template = $receipt_template_view ?? 'receipt_default';
$is_new_sale = !empty($is_new_sale);

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
                $.get('<?= site_url() . esc("/sales/sendPdf/$sale_id_num/receipt") ?>',
                    function(response) {
                        $.notify({
                            message: response.message
                        }, {
                            type: response.success ? 'success' : 'danger'
                        })
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

<?php
// A freshly completed sale gets the full-screen printing status flow
// ("Printing receipt…" → "Sale complete" → "Start Another Sale"), so the
// auto-print/auto-return block in the partial is switched off for it and
// the overlay below drives the printing instead.
?>
<?= view('partial/print_receipt', [
    'print_after_sale' => (!empty($print_after_sale) && !$is_new_sale),
    'selected_printer' => 'receipt_printer'
]) ?>

<?php if ($is_new_sale && !empty($print_after_sale)) { ?>
    <style>
        .sale-complete-overlay {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 20000;
            background: rgba(18, 28, 43, 0.62);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        }

        .sale-complete-card {
            width: 430px;
            max-width: 94vw;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
            padding: 34px 30px 26px;
            text-align: center;
        }

        .sale-complete-card h2 {
            margin: 18px 0 8px;
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
        }

        .sale-complete-card p {
            color: #7b8794;
            font-size: 14px;
            margin-bottom: 22px;
        }

        .sale-complete-spinner {
            width: 54px;
            height: 54px;
            margin: 0 auto;
            border: 5px solid #e3e8ee;
            border-top-color: #27ae60;
            border-radius: 50%;
            animation: sale-complete-spin 0.9s linear infinite;
        }

        @keyframes sale-complete-spin {
            to { transform: rotate(360deg); }
        }

        .sale-complete-icon {
            width: 66px;
            height: 66px;
            margin: 0 auto;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #fff;
        }

        .sale-complete-icon-success { background: #27ae60; }
        .sale-complete-icon-error { background: #e74c3c; }

        .sale-complete-error-message {
            color: #c0392b !important;
            background: #fdecea;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 13px !important;
            word-break: break-word;
        }

        .sale-complete-card .btn-block {
            margin-top: 8px;
        }

        .sale-complete-card .btn-block:first-of-type {
            margin-top: 0;
        }

        @media print {
            .sale-complete-overlay { display: none !important; }
        }
    </style>

    <div id="sale-complete-overlay" class="sale-complete-overlay" role="dialog" aria-modal="true">
        <div class="sale-complete-card">
            <div id="sale-complete-printing" class="sale-complete-state">
                <div class="sale-complete-spinner"></div>
                <h2>Printing receipt&hellip;</h2>
                <p id="sale-complete-printer">Connecting to the receipt printer&hellip;</p>
            </div>

            <div id="sale-complete-success" class="sale-complete-state" style="display: none;">
                <div class="sale-complete-icon sale-complete-icon-success"><span class="glyphicon glyphicon-ok"></span></div>
                <h2>Sale complete</h2>
                <p>The receipt has been sent to the printer.</p>
                <button type="button" class="btn btn-success btn-lg btn-block" id="start-another-sale-button">
                    <span class="glyphicon glyphicon-shopping-cart"></span> Start Another Sale
                </button>
                <button type="button" class="btn btn-default btn-block" id="print-again-button">
                    <span class="glyphicon glyphicon-print"></span> Print Receipt Again
                </button>
                <button type="button" class="btn btn-link btn-block" id="view-receipt-button">View Receipt</button>
            </div>

            <div id="sale-complete-error" class="sale-complete-state" style="display: none;">
                <div class="sale-complete-icon sale-complete-icon-error"><span class="glyphicon glyphicon-exclamation-sign"></span></div>
                <h2>Could not print the receipt</h2>
                <p id="sale-complete-error-message" class="sale-complete-error-message"></p>
                <button type="button" class="btn btn-primary btn-lg btn-block" id="retry-print-button">
                    <span class="glyphicon glyphicon-refresh"></span> Try Printing Again
                </button>
                <button type="button" class="btn btn-success btn-block" id="start-another-sale-button-error">
                    <span class="glyphicon glyphicon-shopping-cart"></span> Start Another Sale
                </button>
                <button type="button" class="btn btn-link btn-block" id="view-receipt-button-error">Continue Without Printing</button>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        (function() {
            var overlay = document.getElementById('sale-complete-overlay');
            if (!overlay) {
                return;
            }

            var printing = document.getElementById('sale-complete-printing');
            var success = document.getElementById('sale-complete-success');
            var failed = document.getElementById('sale-complete-error');
            var errorMessage = document.getElementById('sale-complete-error-message');
            var printerLabel = document.getElementById('sale-complete-printer');
            var started = false;

            var showOnly = function(state) {
                [printing, success, failed].forEach(function(node) {
                    if (node) {
                        node.style.display = 'none';
                    }
                });
                if (state) {
                    state.style.display = 'block';
                }
            };

            var printReceipt = function() {
                showOnly(printing);

                var configuredDevice = '<?= esc($config['receipt_printer'] ?? $config['printer_device_name'] ?? '', 'js') ?>';
                var result;
                try {
                    result = printdoc(configuredDevice);
                } catch (err) {
                    result = Promise.reject(err);
                }

                if (!result || typeof result.then !== 'function') {
                    result = Promise.resolve({success: true});
                }

                result.then(function(response) {
                    if (response && response.success === false) {
                        errorMessage.textContent = response.error || 'The printer did not accept the receipt.';
                        showOnly(failed);
                    } else {
                        showOnly(success);
                    }
                }).catch(function(err) {
                    errorMessage.textContent = (err && err.message) ? err.message : 'The receipt printer could not be reached.';
                    showOnly(failed);
                });
            };

            var onClick = function(id, handler) {
                var button = document.getElementById(id);
                if (button) {
                    button.addEventListener('click', handler);
                }
            };

            onClick('start-another-sale-button', function() {
                window.location.href = "<?= site_url('sales') ?>";
            });
            onClick('start-another-sale-button-error', function() {
                window.location.href = "<?= site_url('sales') ?>";
            });
            onClick('view-receipt-button', function() {
                overlay.style.display = 'none';
            });
            onClick('view-receipt-button-error', function() {
                overlay.style.display = 'none';
            });
            onClick('print-again-button', printReceipt);
            onClick('retry-print-button', printReceipt);

            var begin = function() {
                if (started) {
                    return;
                }
                started = true;

                var configuredDevice = '<?= esc($config['receipt_printer'] ?? $config['printer_device_name'] ?? '', 'js') ?>';
                if (window.beamlinkPrint && window.beamlinkPrint.resolvePrinter) {
                    window.beamlinkPrint.resolvePrinter('receipt_printer', configuredDevice).then(function(info) {
                        if (!printerLabel) {
                            return;
                        }

                        if (info && info.name) {
                            printerLabel.textContent = (info.source === 'detected' ? 'Detected printer: ' : 'Sending to: ')
                                + (info.displayName || info.name);
                        } else {
                            printerLabel.textContent = 'Connecting to the receipt printer…';
                        }
                    }).catch(function() {});
                }

                // Let the browser paint the "Printing receipt…" state before
                // the (blocking in some browsers) print call is made.
                setTimeout(printReceipt, 300);
            };

            if (document.readyState === 'complete') {
                begin();
            } else {
                window.addEventListener('load', begin);
            }
        })();
    </script>
<?php } ?>

<div class="print_hide" id="control_buttons" style="text-align: right;">
    <a href="javascript:printdoc();">
        <div class="btn btn-info btn-sm" id="show_print_button"><?= '<span class="glyphicon glyphicon-print">&nbsp;</span>' . lang('Common.print') ?></div>
    </a>
    <?php if (!empty($customer_email)): ?>
        <a href="javascript:void(0);">
            <div class="btn btn-info btn-sm" id="show_email_button"><?= '<span class="glyphicon glyphicon-envelope">&nbsp;</span>' . lang('Sales.send_receipt') ?></div>
        </a>
    <?php endif; ?>
    <?= anchor('sales', '<span class="glyphicon glyphicon-shopping-cart">&nbsp;</span>' . lang('Sales.register'), ['class' => 'btn btn-info btn-sm', 'id' => 'show_sales_button']) ?>
    <?php
    $employee = model(Employee::class);
    if ($employee->has_grant('reports_sales', session('person_id'))): ?>
        <?= anchor('sales/manage', '<span class="glyphicon glyphicon-list-alt">&nbsp;</span>' . lang('Sales.takings'), ['class' => 'btn btn-info btn-sm', 'id' => 'show_takings_button']) ?>
    <?php endif; ?>
</div>

<?= view('sales/' . $template) ?>

<?= view('partial/footer') ?>
