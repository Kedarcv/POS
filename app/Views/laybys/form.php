<?php
/**
 * Layby Form — Create / Edit
 *
 * @var string $title
 * @var object|null $layby
 * @var array $customers
 * @var array $employees
 */

$isEdit = $layby && ! empty($layby->id);
?>

<?= view('partial/header') ?>

<div class="row" style="margin-bottom: 10px;">
    <div class="col-xs-12">
        <a href="<?= base_url('laybys') ?>" class="btn btn-default btn-sm"><i class="bi bi-arrow-left"></i> Back to Laybys</a>
    </div>
</div>

<div class="row" style="margin-bottom: 12px;">
    <div class="col-xs-12">
        <h3 style="margin: 0;"><i class="bi bi-calendar-check"></i> <?= esc($title) ?></h3>
    </div>
</div>

<?php if (session()->has('message')): ?>
<div class="alert alert-success"><?= session('message') ?></div>
<?php endif; ?>

<?php if (validation_errors()): ?>
<div class="alert alert-danger"><?= validation_errors() ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-xs-12">
        <?= form_open('laybys/save', ['id' => 'layby_form', 'class' => 'form-horizontal']) ?>

        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= $layby->id ?>" />
        <?php endif; ?>

        <!-- Customer + Salesperson -->
        <div class="form-group">
            <div class="col-xs-6">
                <label class="control-label">Customer <span style="color:red;">*</span></label>
                <select name="customer_id" id="customer_id" class="form-control" required>
                    <option value="">-- Select Customer --</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= $c->person_id ?>" <?= ($isEdit && $layby->customer_id == $c->person_id) ? 'selected' : '' ?>>
                        <?= esc($c->first_name . ' ' . $c->last_name) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xs-6">
                <label class="control-label">Salesperson</label>
                <select name="employee_id" class="form-control">
                    <option value="">-- Select Salesperson --</option>
                    <?php foreach ($employees as $e): ?>
                    <option value="<?= $e->person_id ?>" <?= ($isEdit && isset($layby->employee_id) && $layby->employee_id == $e->person_id) ? 'selected' : '' ?>>
                        <?= esc($e->first_name . ' ' . $e->last_name) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Total Amount + Deposit -->
        <div class="form-group">
            <div class="col-xs-4">
                <label class="control-label">Total Amount (ZWL) <span style="color:red;">*</span></label>
                <input type="number" step="0.01" name="total_amount" id="total_amount" class="form-control"
                       value="<?= $isEdit ? $layby->total_amount : set_value('total_amount', '0.00') ?>" required />
            </div>
            <div class="col-xs-4">
                <label class="control-label">Deposit Amount (ZWL)</label>
                <input type="number" step="0.01" name="deposit_amount" id="deposit_amount" class="form-control"
                       value="<?= $isEdit ? $layby->deposit_amount : set_value('deposit_amount', '0.00') ?>" />
            </div>
            <div class="col-xs-4">
                <label class="control-label">Interest Rate (%)</label>
                <input type="number" step="0.01" name="interest_rate" id="interest_rate" class="form-control"
                       value="<?= $isEdit ? $layby->interest_rate : set_value('interest_rate', '0.00') ?>" />
            </div>
        </div>

        <!-- Installments + Frequency + Dates -->
        <div class="form-group">
            <div class="col-xs-3">
                <label class="control-label">Installments <span style="color:red;">*</span></label>
                <input type="number" min="1" name="installment_count" id="installment_count" class="form-control"
                       value="<?= $isEdit ? $layby->installment_count : set_value('installment_count', '1') ?>" required />
            </div>
            <div class="col-xs-3">
                <label class="control-label">Frequency <span style="color:red;">*</span></label>
                <select name="frequency" id="frequency" class="form-control" required>
                    <option value="weekly" <?= ($isEdit && $layby->frequency === 'weekly') ? 'selected' : '' ?>>Weekly</option>
                    <option value="biweekly" <?= ($isEdit && $layby->frequency === 'biweekly') ? 'selected' : '' ?>>Bi-weekly</option>
                    <option value="monthly" <?= (!$isEdit || $layby->frequency === 'monthly') ? 'selected' : '' ?>>Monthly</option>
                </select>
            </div>
            <div class="col-xs-3">
                <label class="control-label">Start Date <span style="color:red;">*</span></label>
                <input type="date" name="start_date" id="start_date" class="form-control"
                       value="<?= $isEdit ? $layby->start_date : set_value('start_date', date('Y-m-d')) ?>" required />
            </div>
            <div class="col-xs-3">
                <label class="control-label">Document Signed</label>
                <select name="document_signed" class="form-control">
                    <option value="0" <?= ($isEdit && ! $layby->document_signed) ? 'selected' : '' ?>>No</option>
                    <option value="1" <?= ($isEdit && $layby->document_signed) ? 'selected' : '' ?>>Yes</option>
                </select>
            </div>
        </div>

        <!-- Calculated Summary -->
        <div class="panel panel-default" style="margin: 15px 0;">
            <div class="panel-heading"><strong>Payment Summary</strong></div>
            <div class="panel-body" style="padding: 10px 15px;">
                <div class="row">
                    <div class="col-xs-3"><strong>Interest Amount:</strong> <span id="calc_interest">ZWL 0.00</span></div>
                    <div class="col-xs-3"><strong>Total with Interest:</strong> <span id="calc_total">ZWL 0.00</span></div>
                    <div class="col-xs-3"><strong>Balance:</strong> <span id="calc_balance">ZWL 0.00</span></div>
                    <div class="col-xs-3"><strong>Per Installment:</strong> <span id="calc_per_installment">ZWL 0.00</span></div>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="form-group">
            <div class="col-xs-12">
                <label class="control-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Optional notes about this layby"><?= $isEdit ? esc($layby->notes) : '' ?></textarea>
            </div>
        </div>

        <div class="form-group">
            <div class="col-xs-12">
                <button type="submit" class="btn btn-success"><i class="bi bi-check-lg"></i> <?= $isEdit ? 'Update Layby' : 'Create Layby' ?></button>
                <a href="<?= base_url('laybys') ?>" class="btn btn-default">Cancel</a>
            </div>
        </div>

        <?= form_close() ?>
    </div>
</div>

<script type="text/javascript">
$(document).ready(function() {
    function recalculate() {
        var total = parseFloat($('#total_amount').val()) || 0;
        var deposit = parseFloat($('#deposit_amount').val()) || 0;
        var rate = parseFloat($('#interest_rate').val()) || 0;
        var count = parseInt($('#installment_count').val()) || 1;

        var interestAmt = total * (rate / 100);
        var totalWithInterest = total + interestAmt;
        var balance = totalWithInterest - deposit;
        var perInstallment = count > 0 ? balance / count : balance;

        $('#calc_interest').text('ZWL ' + interestAmt.toFixed(2));
        $('#calc_total').text('ZWL ' + totalWithInterest.toFixed(2));
        $('#calc_balance').text('ZWL ' + Math.max(0, balance).toFixed(2));
        $('#calc_per_installment').text('ZWL ' + perInstallment.toFixed(2));
    }

    $('#total_amount, #deposit_amount, #interest_rate, #installment_count').on('input change', recalculate);
    recalculate();
});
</script>

<?= view('partial/footer') ?>
