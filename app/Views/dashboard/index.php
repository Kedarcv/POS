<?= view('partial/header') ?>

<script type="text/javascript">
    dialog_support.init("a.modal-dlg");
</script>

<style>
    .kpi-card {
        text-align: center;
        padding: 18px 10px;
        border-radius: 4px;
        margin-bottom: 15px;
    }
    .kpi-card .kpi-value {
        font-size: 28px;
        font-weight: 700;
        line-height: 1.2;
    }
    .kpi-card .kpi-label {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 4px;
    }
    .kpi-green .kpi-value { color: #5cb85c; }
    .kpi-blue .kpi-value { color: #337ab7; }
    .kpi-purple .kpi-value { color: #6b4f8a; }
    .kpi-orange .kpi-value { color: #f0ad4e; }
    .kpi-red .kpi-value { color: #d9534f; }

    .bar-chart-row {
        display: flex;
        align-items: flex-end;
        height: 160px;
        gap: 6px;
        padding: 0 10px;
        margin-bottom: 8px;
    }
    .bar-col {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-end;
        height: 100%;
    }
    .bar-fill {
        width: 100%;
        max-width: 40px;
        background: #6b4f8a;
        border-radius: 3px 3px 0 0;
        min-height: 2px;
        transition: height 0.3s;
    }
    .bar-amount {
        font-size: 9px;
        color: #666;
        margin-bottom: 3px;
        white-space: nowrap;
    }
    .bar-labels {
        display: flex;
        gap: 6px;
        padding: 0 10px;
    }
    .bar-labels span {
        flex: 1;
        text-align: center;
        font-size: 11px;
        color: #888;
    }
    .panel-title { font-weight: 600; }
</style>

<h3 class="text-center" style="margin-bottom: 20px;">
    <?= lang('Dashboard.title') ?> — <?= esc(config('OSPOS')->settings['company']) ?>
</h3>

<!-- Big POS Button -->
<div class="row" style="margin-bottom: 20px;">
    <div class="col-xs-12 text-center">
        <a href="<?= base_url('sales') ?>" style="display: inline-block; padding: 30px 80px; font-size: 32px; font-weight: 700; color: #fff; background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); border-radius: 12px; text-decoration: none; box-shadow: 0 4px 15px rgba(34,197,94,0.4); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(34,197,94,0.5)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(34,197,94,0.4)';">
            <i class="glyphicon glyphicon-shopping-cart" style="font-size: 36px;"></i><br>
            OPEN POS REGISTER
        </a>
    </div>
</div>

<!-- Row 1: KPI Cards -->
<div class="row">
    <div class="col-xs-2">
        <div class="panel panel-default kpi-card kpi-green">
            <div class="kpi-value"><?= to_currency($today_revenue) ?></div>
            <div class="kpi-label"><?= lang('Dashboard.today_revenue') ?></div>
        </div>
    </div>
    <div class="col-xs-2">
        <div class="panel panel-default kpi-card kpi-blue">
            <div class="kpi-value"><?= $today_count ?></div>
            <div class="kpi-label"><?= lang('Dashboard.today_transactions') ?></div>
        </div>
    </div>
    <div class="col-xs-2">
        <div class="panel panel-default kpi-card kpi-green">
            <div class="kpi-value"><?= to_currency($monthly_revenue) ?></div>
            <div class="kpi-label"><?= lang('Dashboard.monthly_revenue') ?></div>
        </div>
    </div>
    <div class="col-xs-2">
        <div class="panel panel-default kpi-card kpi-purple">
            <div class="kpi-value"><?= $layby_count ?></div>
            <div class="kpi-label"><?= lang('Dashboard.active_laybys') ?></div>
        </div>
    </div>
    <div class="col-xs-2">
        <div class="panel panel-default kpi-card kpi-orange">
            <div class="kpi-value"><?= $printjob_count ?></div>
            <div class="kpi-label"><?= lang('Dashboard.print_jobs_pipeline') ?></div>
        </div>
    </div>
    <div class="col-xs-2">
        <div class="panel panel-default kpi-card kpi-red">
            <div class="kpi-value"><?= $low_stock_count ?></div>
            <div class="kpi-label"><?= lang('Dashboard.low_stock_items') ?></div>
        </div>
    </div>
</div>

<!-- Row 2: Bar Chart + Top Items -->
<div class="row">
    <div class="col-xs-7">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-bar-chart"></i> <?= lang('Dashboard.last_7_days') ?></h3>
            </div>
            <div class="panel-body">
                <?php if ($max_daily_revenue > 0): ?>
                    <div class="bar-chart-row">
                        <?php foreach ($daily_chart as $day): ?>
                            <?php $pct = $max_daily_revenue > 0 ? round(($day['revenue'] / $max_daily_revenue) * 100) : 0; ?>
                            <div class="bar-col">
                                <div class="bar-amount"><?= $day['revenue'] > 0 ? to_currency_no_money($day['revenue']) : '' ?></div>
                                <div class="bar-fill" style="height: <?= $pct ?>%;"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bar-labels">
                        <?php
                        $dayNames = ['day_sun', 'day_mon', 'day_tue', 'day_wed', 'day_thu', 'day_fri', 'day_sat'];
                        foreach ($daily_chart as $day):
                            $d = date('w', strtotime($day['date']));
                        ?>
                            <span><?= lang('Dashboard.' . $dayNames[$d]) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center" style="padding: 40px 0;"><?= lang('Dashboard.no_data') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xs-5">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-trophy"></i> <?= lang('Dashboard.top_5_items') ?></h3>
            </div>
            <table class="table table-striped table-condensed" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th style="width: 40px;"><?= lang('Dashboard.rank') ?></th>
                        <th><?= lang('Dashboard.item_name') ?></th>
                        <th style="width: 70px; text-align: right;"><?= lang('Dashboard.qty_sold') ?></th>
                        <th style="width: 100px; text-align: right;"><?= lang('Dashboard.revenue') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_items)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted"><?= lang('Dashboard.no_data') ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($top_items as $i => $item): ?>
                            <tr>
                                <td><span class="label label-default"><?= $i + 1 ?></span></td>
                                <td><?= esc($item->name) ?></td>
                                <td style="text-align: right;"><?= (int) $item->qty_sold ?></td>
                                <td style="text-align: right;"><?= to_currency($item->revenue) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Row 3: Expenses + Layby Overview -->
<div class="row">
    <div class="col-xs-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-money"></i> <?= lang('Dashboard.monthly_expenses') ?></h3>
            </div>
            <div class="panel-body text-center" style="padding: 25px;">
                <div style="font-size: 36px; font-weight: 700; color: #d9534f;">
                    <?= to_currency($monthly_expenses) ?>
                </div>
                <div style="margin-top: 6px; color: #888;">
                    <?= lang('Dashboard.total_expenses') ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xs-6">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-file-text-o"></i> <?= lang('Dashboard.layby_overview') ?></h3>
            </div>
            <div class="panel-body">
                <div class="row" style="text-align: center;">
                    <div class="col-xs-6">
                        <div style="font-size: 28px; font-weight: 700; color: #6b4f8a;">
                            <?= to_currency($layby_outstanding) ?>
                        </div>
                        <div style="color: #888; font-size: 12px; text-transform: uppercase;">
                            <?= lang('Dashboard.total_outstanding') ?>
                        </div>
                    </div>
                    <div class="col-xs-6">
                        <div style="font-size: 28px; font-weight: 700; color: #d9534f;">
                            <?= $overdue_count ?>
                        </div>
                        <div style="color: #888; font-size: 12px; text-transform: uppercase;">
                            <?= lang('Dashboard.overdue_count') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?= view('partial/footer') ?>
