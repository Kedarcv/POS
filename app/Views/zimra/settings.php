<?php
/**
 * ZIMRA Fiscalisation — settings, device registration, fiscal day control.
 *
 * @var string $title
 * @var object $settings
 * @var array $tax_categories
 * @var array $tax_map
 * @var object|null $open_day
 * @var array $recent_days
 * @var array $recent_receipts
 * @var int $pending_count
 */
?>

<?= view('partial/header') ?>

<div class="row">
    <div class="col-xs-12">
        <h3 class="text-center" style="margin-bottom: 5px;">
            <i class="glyphicon glyphicon-globe"></i> <?= esc($title) ?>
        </h3>
        <p class="text-center text-muted">
            Integration with ZIMRA's Fiscal Device Gateway (FDMS) for receipt fiscalisation, per
            <a href="https://www.zimra.co.zw/domestic-taxes/corporate/fiscalisation-explained" target="_blank" rel="noopener">ZIMRA's fiscalisation guidance</a>.
        </p>
    </div>
</div>

<?php if (session('success')): ?>
    <div class="alert alert-success"><?= esc(session('success')) ?></div>
<?php endif; ?>
<?php if (session('error')): ?>
    <div class="alert alert-danger"><?= esc(session('error')) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>1. General Settings</strong></div>
            <div class="panel-body">
                <?= form_open('zimra/save_settings', ['class' => 'form-horizontal']) ?>
                <div class="form-group">
                    <label class="col-sm-5 control-label">Enable Fiscalisation</label>
                    <div class="col-sm-7">
                        <input type="checkbox" name="enabled" value="1" <?= $settings->enabled ? 'checked' : '' ?>>
                        <span class="text-muted">Submit completed sales to ZIMRA automatically.</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-5 control-label">Environment</label>
                    <div class="col-sm-7">
                        <select name="environment" class="form-control">
                            <option value="test" <?= $settings->environment === 'test' ? 'selected' : '' ?>>Test (fdmstest.zimra.co.zw)</option>
                            <option value="production" <?= $settings->environment === 'production' ? 'selected' : '' ?>>Production (live)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-sm-5 control-label">Default Currency</label>
                    <div class="col-sm-7">
                        <select name="default_currency" class="form-control">
                            <option value="USD" <?= $settings->default_currency === 'USD' ? 'selected' : '' ?>>USD</option>
                            <option value="ZWG" <?= $settings->default_currency === 'ZWG' ? 'selected' : '' ?>>ZWG</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-5 col-sm-7">
                        <button type="submit" class="btn btn-primary btn-sm">Save Settings</button>
                    </div>
                </div>
                <?= form_close() ?>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><strong>2. Device Registration</strong></div>
            <div class="panel-body">
                <?php if (!empty($settings->device_id) && !empty($settings->certificate_path)): ?>
                    <p class="text-success">
                        <i class="glyphicon glyphicon-ok"></i>
                        Device <strong><?= esc($settings->device_id) ?></strong> registered
                        <?= $settings->registered_at ? 'on ' . esc($settings->registered_at) : '' ?>.
                    </p>
                <?php else: ?>
                    <p class="text-muted">
                        Register this till <em>after</em> ZIMRA has issued a Device ID and Activation Key for it
                        on their fiscalisation portal (registration there can take up to 3 working days).
                        A private key is generated on this machine and never leaves it — only the certificate
                        request is sent to ZIMRA.
                    </p>
                    <?= form_open('zimra/register_device', ['class' => 'form-horizontal']) ?>
                    <div class="form-group">
                        <label class="col-sm-5 control-label">Taxpayer TIN</label>
                        <div class="col-sm-7"><input type="text" name="tin" class="form-control" required></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-5 control-label">Device ID</label>
                        <div class="col-sm-7"><input type="number" name="device_id" class="form-control" required></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-5 control-label">Activation Key</label>
                        <div class="col-sm-7"><input type="text" name="activation_key" class="form-control" required></div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-5 control-label">Device Serial No.</label>
                        <div class="col-sm-7"><input type="text" name="device_serial_no" class="form-control" value="BEAMLINK-POS-01" required></div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-offset-5 col-sm-7">
                            <button type="submit" class="btn btn-primary btn-sm">Register Device</button>
                        </div>
                    </div>
                    <?= form_close() ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>3. Fiscal Day</strong></div>
            <div class="panel-body">
                <?php if ($open_day): ?>
                    <p>Fiscal day <strong><?= esc($open_day->fiscal_day_no) ?></strong> is <span class="label label-success">OPEN</span>
                        since <?= esc($open_day->opened_at) ?>.</p>
                    <?= form_open('zimra/close_day') ?>
                    <button type="submit" class="btn btn-warning btn-sm">Close Fiscal Day</button>
                    <?= form_close() ?>
                <?php else: ?>
                    <p class="text-muted">No fiscal day is currently open. Sales will queue until one is opened.</p>
                    <?= form_open('zimra/open_day') ?>
                    <button type="submit" class="btn btn-success btn-sm">Open Fiscal Day</button>
                    <?= form_close() ?>
                <?php endif; ?>

                <hr>
                <p>
                    Pending/failed receipts: <strong><?= (int) $pending_count ?></strong>
                    <?php if ($pending_count > 0): ?>
                        <?= form_open('zimra/retry_pending', ['style' => 'display:inline']) ?>
                        <button type="submit" class="btn btn-default btn-xs">Retry Now</button>
                        <?= form_close() ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><strong>4. Tax Category Mapping</strong></div>
            <div class="panel-body">
                <p class="text-muted">Map each tax category to the tax ID ZIMRA assigned this device (see GetConfig, or your registration paperwork).</p>
                <?= form_open('zimra/save_tax_map') ?>
                <table class="table table-condensed">
                    <thead>
                        <tr><th>Tax Category</th><th>ZIMRA Tax ID</th><th>Percent</th><th>Name</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tax_categories as $category): ?>
                            <?php $mapped = $tax_map[$category->tax_category_id] ?? null; ?>
                            <tr>
                                <td>
                                    <?= esc($category->tax_category) ?>
                                    <input type="hidden" name="tax_category_id[]" value="<?= esc($category->tax_category_id) ?>">
                                </td>
                                <td><input type="number" name="zimra_tax_id[]" class="form-control input-sm" value="<?= esc($mapped->zimra_tax_id ?? '') ?>"></td>
                                <td><input type="number" step="0.01" name="zimra_tax_percent[]" class="form-control input-sm" value="<?= esc($mapped->zimra_tax_percent ?? '') ?>"></td>
                                <td><input type="text" name="zimra_tax_name[]" class="form-control input-sm" value="<?= esc($mapped->zimra_tax_name ?? '') ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" class="btn btn-primary btn-sm">Save Tax Mapping</button>
                <?= form_close() ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Recent Fiscal Days</strong></div>
            <div class="panel-body">
                <table class="table table-condensed">
                    <thead><tr><th>Day #</th><th>Date</th><th>Status</th><th>Opened</th><th>Closed</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent_days as $day): ?>
                            <tr>
                                <td><?= esc($day->fiscal_day_no) ?></td>
                                <td><?= esc($day->fiscal_date) ?></td>
                                <td><?= esc($day->status) ?></td>
                                <td><?= esc($day->opened_at) ?></td>
                                <td><?= esc($day->closed_at ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Recent Receipts</strong></div>
            <div class="panel-body">
                <table class="table table-condensed">
                    <thead><tr><th>Sale</th><th>Status</th><th>Global No.</th><th>Verification</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent_receipts as $receipt): ?>
                            <tr>
                                <td><?= esc($receipt->sale_id) ?></td>
                                <td>
                                    <?php $badge = ['SUBMITTED' => 'success', 'PENDING' => 'warning', 'FAILED' => 'danger'][$receipt->status] ?? 'default'; ?>
                                    <span class="label label-<?= $badge ?>"><?= esc($receipt->status) ?></span>
                                </td>
                                <td><?= esc($receipt->receipt_global_no ?? '—') ?></td>
                                <td><?= esc($receipt->verification_code ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= view('partial/footer') ?>
