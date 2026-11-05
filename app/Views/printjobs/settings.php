<?php
/**
 * Print Shop — economics defaults
 *
 * @var string $title
 * @var array  $settings   print_* config values (strings)
 */
?>
<?= view('partial/header') ?>

<div class="row" style="margin-bottom:12px;">
    <div class="col-xs-12">
        <h3 style="margin:0;">
            <a href="<?= base_url('printjobs') ?>" class="text-muted" style="text-decoration:none;"><i class="bi bi-arrow-left"></i></a>
            <i class="bi bi-gear"></i> <?= esc($title) ?>
        </h3>
    </div>
</div>

<?php if (session('message')): ?>
    <div class="alert alert-success"><?= esc(session('message')) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-xs-12 col-md-7">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Shop-wide cost defaults</strong></div>
            <div class="panel-body">
                <p class="text-muted">
                    Applied to every job's cost estimate. A process type can override any of
                    these on the <a href="<?= base_url('printjobs/processes') ?>">Process Types</a> screen;
                    a blank field there means “use the value below”.
                </p>
                <?= form_open('printjobs/save_settings', ['class' => 'form-horizontal']) ?>
                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-5"><?= lang('Printjobs.overhead_per_sqm') ?></label>
                        <div class="col-xs-4">
                            <input type="number" step="0.0001" min="0" name="print_overhead_per_sqm" class="form-control input-sm"
                                value="<?= esc($settings['print_overhead_per_sqm']) ?>">
                        </div>
                        <div class="col-xs-3"><small class="text-muted">labour + power + machine</small></div>
                    </div>
                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-5"><?= lang('Printjobs.overhead_per_job') ?></label>
                        <div class="col-xs-4">
                            <input type="number" step="0.01" min="0" name="print_overhead_per_job" class="form-control input-sm"
                                value="<?= esc($settings['print_overhead_per_job']) ?>">
                        </div>
                        <div class="col-xs-3"><small class="text-muted">fixed setup / handling</small></div>
                    </div>
                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-5"><?= lang('Printjobs.overhead_pct') ?></label>
                        <div class="col-xs-4">
                            <input type="number" step="0.1" min="0" name="print_overhead_pct" class="form-control input-sm"
                                value="<?= esc($settings['print_overhead_pct']) ?>">
                        </div>
                        <div class="col-xs-3"><small class="text-muted">% uplift on ink + substrate</small></div>
                    </div>
                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-5"><?= lang('Printjobs.spoilage_pct') ?></label>
                        <div class="col-xs-4">
                            <input type="number" step="0.1" min="0" name="print_spoilage_pct" class="form-control input-sm"
                                value="<?= esc($settings['print_spoilage_pct']) ?>">
                        </div>
                        <div class="col-xs-3"><small class="text-muted">misprint / damage provision</small></div>
                    </div>
                    <div class="form-group form-group-sm">
                        <label class="control-label col-xs-5"><?= lang('Printjobs.target_margin_pct') ?></label>
                        <div class="col-xs-4">
                            <input type="number" step="0.1" min="0" max="99" name="print_target_margin_pct" class="form-control input-sm"
                                value="<?= esc($settings['print_target_margin_pct']) ?>">
                        </div>
                        <div class="col-xs-3"><small class="text-muted">drives the cost-plus floor price</small></div>
                    </div>
                    <div class="form-group form-group-sm">
                        <div class="col-xs-offset-5 col-xs-7">
                            <button type="submit" class="btn btn-success btn-sm">Save</button>
                        </div>
                    </div>
                <?= form_close() ?>
            </div>
        </div>
    </div>
</div>

<?= view('partial/footer') ?>
