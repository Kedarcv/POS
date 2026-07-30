<?php
/**
 * Layby Detail View — Schedule + Payment Recording
 *
 * @var string $title
 * @var object $layby
 * @var object|null $customer
 * @var array $installments
 * @var array $payment_methods
 */

$totalPaid  = array_sum(array_column($installments, 'amount_paid'));
$pctPaid    = $layby->total_with_interest > 0
    ? round(($totalPaid / $layby->total_with_interest) * 100, 1)
    : 0;

$paidCount  = count(array_filter($installments, fn($i) => $i->status === 'paid'));
$pendingCount = count(array_filter($installments, fn($i) => $i->status !== 'paid'));

$status_colors = [
    'active'    => 'primary',
    'paid'      => 'success',
    'overdue'   => 'danger',
    'cancelled' => 'default',
];

$inst_status_colors = [
    'pending' => 'warning',
    'paid'    => 'success',
    'partial' => 'info',
];

$customerName = '';
if ($customer) {
    $customerName = trim($customer->first_name . ' ' . $customer->last_name);
}
?>

<?= view('partial/header') ?>

<div class="row" style="margin-bottom: 12px;">
    <div class="col-xs-12">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">
                <i class="bi bi-calendar-check"></i>
                <?= esc($layby->layby_number) ?>
                <span class="label label-<?= $status_colors[$layby->status] ?? 'default' ?>" style="font-size: 0.6em; vertical-align: middle;">
                    <?= lang('Laybys.' . $layby->status) ?>
                </span>
            </h3>
            <div>
                <a href="<?= base_url('laybys/edit/' . $layby->id) ?>" class="btn btn-default btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                <a href="<?= base_url('laybys') ?>" class="btn btn-default btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
            </div>
        </div>
    </div>
</div>

<?php if (session()->has('message')): ?>
<div class="alert alert-success"><?= session('message') ?></div>
<?php endif; ?>

<!-- Info Cards -->
<div class="row" style="margin-bottom: 15px;">
    <div class="col-xs-8">
        <div class="panel panel-default">
            <div class="panel-body" style="padding: 10px 15px;">
                <div class="row">
                    <div class="col-xs-4">
                        <strong>Customer:</strong> <?= esc($customerName) ?><br>
                        <strong>Salesperson:</strong> <?= $layby->employee_id ? 'ID ' . $layby->employee_id : '-' ?>
                    </div>
                    <div class="col-xs-4">
                        <strong>Total with Interest:</strong> <?= to_currency($layby->total_with_interest) ?><br>
                        <strong>Deposit:</strong> <?= to_currency($layby->deposit_amount) ?>
                    </div>
                    <div class="col-xs-4">
                        <strong>Balance Outstanding:</strong> <span style="font-size: 1.2em; color: #d9534f; font-weight: bold;"><?= to_currency($layby->balance) ?></span><br>
                        <strong>Per Installment:</strong> <?= to_currency($layby->installment_amount) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xs-4">
        <div class="panel panel-default">
            <div class="panel-body text-center" style="padding: 10px 15px;">
                <div style="font-size: 1.6em; font-weight: bold; color: <?= $layby->status === 'paid' ? '#5cb85c' : '#d9534f' ?>;">
                    <?= to_currency($layby->balance) ?>
                </div>
                <small class="text-muted">remaining</small>
                <div class="progress" style="margin-top: 8px; margin-bottom: 0; height: 12px;">
                    <div class="progress-bar progress-bar-success" style="width: <?= $pctPaid ?>%"></div>
                </div>
                <small class="text-muted"><?= $pctPaid ?>% paid (<?= $paidCount ?>/<?= count($installments) ?> installments)</small>
            </div>
        </div>
    </div>
</div>

<!-- Document Status + Actions -->
<div class="row" style="margin-bottom: 15px;">
    <div class="col-xs-6">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Details</strong></div>
            <div class="panel-body" style="padding: 8px 15px;">
                <div class="row">
                    <div class="col-xs-6">
                        <strong>Start:</strong> <?= $layby->start_date ? date('d M Y', strtotime($layby->start_date)) : '-' ?>
                    </div>
                    <div class="col-xs-6">
                        <strong>End:</strong> <?= $layby->end_date ? date('d M Y', strtotime($layby->end_date)) : '-' ?>
                    </div>
                </div>
                <div class="row" style="margin-top: 5px;">
                    <div class="col-xs-6">
                        <strong>Frequency:</strong> <?= lang('Laybys.' . $layby->frequency) ?>
                    </div>
                    <div class="col-xs-6">
                        <strong>Document Signed:</strong>
                        <?php if ($layby->document_signed): ?>
                            <span class="label label-success"><i class="bi bi-check-lg"></i> Signed</span>
                        <?php else: ?>
                            <?= form_open('laybys/mark_signed/' . $layby->id, ['style' => 'display:inline;']) ?>
                                <button type="submit" class="btn btn-xs btn-warning"><i class="bi bi-pencil"></i> Mark Signed</button>
                            <?= form_close() ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($layby->notes): ?>
                <div class="row" style="margin-top: 5px;">
                    <div class="col-xs-12">
                        <strong>Notes:</strong> <?= esc($layby->notes) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xs-6">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Actions</strong></div>
            <div class="panel-body text-center" style="padding: 15px;">
                <?php if ($layby->status === 'active'): ?>
                    <button class="btn btn-success btn-lg" data-toggle="modal" data-target="#paymentModal">
                        <i class="bi bi-cash-stack"></i> Record Payment
                    </button>
                    <br><br>
                    <?= form_open('laybys/cancel/' . $layby->id, ['style' => 'display:inline;', 'onsubmit' => "return confirm('Cancel this layby?')"]) ?>
                        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-x-lg"></i> Cancel Layby</button>
                    <?= form_close() ?>
                <?php elseif ($layby->status === 'paid'): ?>
                    <span class="text-success" style="font-size: 1.3em;"><i class="bi bi-check-circle-fill"></i> Fully Paid</span>
                <?php elseif ($layby->status === 'cancelled'): ?>
                    <span class="text-muted" style="font-size: 1.3em;"><i class="bi bi-x-circle"></i> Cancelled</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Installment Schedule -->
<div class="row">
    <div class="col-xs-12">
        <div class="panel panel-default">
            <div class="panel-heading"><strong><i class="bi bi-list-ol"></i> <?= lang('Laybys.installment_schedule') ?></strong></div>
            <table class="table table-striped table-bordered" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?= lang('Laybys.amount_due') ?></th>
                        <th><?= lang('Laybys.amount_paid') ?></th>
                        <th><?= lang('Laybys.due_date') ?></th>
                        <th><?= lang('Laybys.paid_date') ?></th>
                        <th><?= lang('Laybys.status') ?></th>
                        <th><?= lang('Laybys.payment_method') ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($installments as $inst): ?>
                    <?php
                    $isOverdue = $inst->status !== 'paid' && $inst->due_date < date('Y-m-d');
                    ?>
                    <tr>
                        <td><?= $inst->installment_number ?></td>
                        <td><?= to_currency($inst->amount_due) ?></td>
                        <td>
                            <?php if ($inst->amount_paid > 0): ?>
                                <?= to_currency($inst->amount_paid) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="<?= $isOverdue ? 'color:#d9534f; font-weight:bold;' : '' ?>">
                            <?= date('d M Y', strtotime($inst->due_date)) ?>
                            <?= $isOverdue ? ' <span class="label label-danger">OVERDUE</span>' : '' ?>
                        </td>
                        <td><?= $inst->paid_date ? date('d M Y', strtotime($inst->paid_date)) : '-' ?></td>
                        <td>
                            <span class="label label-<?= $inst_status_colors[$inst->status] ?? 'default' ?>">
                                <?= ucfirst($inst->status) ?>
                            </span>
                        </td>
                        <td><?= $inst->payment_method ? ucfirst(str_replace('_', ' ', $inst->payment_method)) : '-' ?></td>
                        <td>
                            <?php if ($inst->status !== 'paid' && $layby->status === 'active'): ?>
                            <button class="btn btn-xs btn-success" onclick="openPayModal(<?= $inst->id ?>, <?= $inst->amount_due - $inst->amount_paid ?>)">
                                <i class="bi bi-cash"></i> Pay
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<?php if ($layby->status === 'active'): ?>
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <?= form_open('laybys/pay/0', ['id' => 'pay_form']) ?>
            <input type="hidden" name="installment_id" id="pay_installment_id" value="" />
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="bi bi-cash-stack"></i> Record Payment</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Amount Due</label>
                    <input type="text" id="pay_amount_due" class="form-control" readonly />
                </div>
                <div class="form-group">
                    <label>Amount to Pay <span style="color:red;">*</span></label>
                    <input type="number" step="0.01" name="amount" id="pay_amount" class="form-control" required />
                </div>
                <div class="form-group">
                    <label>Payment Method <span style="color:red;">*</span></label>
                    <select name="payment_method" class="form-control" required>
                        <?php foreach ($payment_methods as $pm): ?>
                        <option value="<?= $pm ?>"><?= ucfirst(str_replace('_', ' ', $pm)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Reference Number</label>
                    <input type="text" name="reference_number" class="form-control" placeholder="Optional" />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> Record Payment</button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script type="text/javascript">
function openPayModal(installmentId, maxAmount) {
    $('#pay_installment_id').val(installmentId);
    $('#pay_amount_due').val('ZWL ' + maxAmount.toFixed(2));
    $('#pay_amount').val(maxAmount.toFixed(2)).attr('max', maxAmount);
    $('#pay_form').attr('action', '<?= base_url('laybys/pay/') ?>' + '/' + installmentId);
    $('#paymentModal').modal('show');
}
</script>

<?= view('partial/footer') ?>
