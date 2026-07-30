<?php
/**
 * Print Machines — Management Dashboard
 *
 * @var string $title
 * @var array $machines
 * @var array $status_counts
 */

$status_colors = [
    'active'      => '#22c55e',
    'maintenance' => '#f59e0b',
    'offline'     => '#dc3545',
    'retired'     => '#6c757d',
];

$status_icons = [
    'active'      => 'glyphicon-ok-circle',
    'maintenance' => 'glyphicon-wrench',
    'offline'     => 'glyphicon-off',
    'retired'     => 'glyphicon-ban-circle',
];

$type_labels = \App\Models\Machine::MACHINE_TYPES;
$connection_labels = ['network' => 'Network', 'usb' => 'USB', 'bluetooth' => 'Bluetooth', 'cloud' => 'Cloud'];
?>

<?= view('partial/header') ?>

<div class="row" style="margin-bottom: 10px;">
    <div class="col-xs-12">
        <a href="<?= base_url('printjobs') ?>" class="btn btn-default btn-sm">
            <i class="glyphicon glyphicon-arrow-left"></i> <?= lang('Printjobs.title') ?>
        </a>
    </div>
</div>

<style>
    .machine-card { border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: #fff; transition: box-shadow 0.2s; }
    .machine-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .machine-card .machine-name { font-size: 16px; font-weight: 600; color: #333; margin-bottom: 5px; }
    .machine-card .machine-type { font-size: 12px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
    .machine-card .machine-status { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; color: #fff; }
    .machine-card .machine-meta { font-size: 12px; color: #6c757d; margin-top: 8px; }
    .machine-card .machine-meta i { width: 16px; text-align: center; margin-right: 3px; }
    .machine-card .job-count { font-size: 24px; font-weight: 700; }
    .stat-box { text-align: center; padding: 15px; border-radius: 8px; background: #fff; border: 1px solid #dee2e6; }
    .stat-box h3 { margin: 0; font-size: 28px; font-weight: 700; }
    .stat-box small { color: #6c757d; }
    .actions { margin-top: 10px; }
    .actions .btn { margin-right: 5px; }
</style>

<div class="row" style="margin-bottom: 15px;">
    <div class="col-xs-12">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;"><i class="glyphicon glyphicon-cog"></i> <?= esc($title) ?></h3>
            <a href="<?= base_url('machines/new') ?>" class="btn btn-success btn-sm">
                <i class="glyphicon glyphicon-plus"></i> Add Machine
            </a>
        </div>
    </div>
</div>

<!-- Status Stats -->
<div class="row" style="margin-bottom: 15px;">
    <div class="col-xs-3">
        <div class="stat-box" style="border-top: 3px solid <?= $status_colors['active'] ?>;">
            <h3 style="color: <?= $status_colors['active'] ?>;"><?= $status_counts['active'] ?></h3>
            <small>Active</small>
        </div>
    </div>
    <div class="col-xs-3">
        <div class="stat-box" style="border-top: 3px solid <?= $status_colors['maintenance'] ?>;">
            <h3 style="color: <?= $status_colors['maintenance'] ?>;"><?= $status_counts['maintenance'] ?></h3>
            <small>Maintenance</small>
        </div>
    </div>
    <div class="col-xs-3">
        <div class="stat-box" style="border-top: 3px solid <?= $status_colors['offline'] ?>;">
            <h3 style="color: <?= $status_colors['offline'] ?>;"><?= $status_counts['offline'] ?></h3>
            <small>Offline</small>
        </div>
    </div>
    <div class="col-xs-3">
        <div class="stat-box" style="border-top: 3px solid <?= $status_colors['retired'] ?>;">
            <h3 style="color: <?= $status_colors['retired'] ?>;"><?= $status_counts['retired'] ?></h3>
            <small>Retired</small>
        </div>
    </div>
</div>

<!-- Machines Grid -->
<div class="row">
    <?php if (empty($machines)): ?>
        <div class="col-xs-12">
            <div class="alert alert-info">
                <i class="glyphicon glyphicon-info-sign"></i>
                No machines registered yet. Click <strong>Add Machine</strong> to get started.
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($machines as $m): ?>
            <?php
            $st = $m->status ?? 'active';
            $st_color = $status_colors[$st] ?? '#6c757d';
            $st_icon = $status_icons[$st] ?? 'glyphicon-question-sign';
            $type_label = $type_labels[$m->machine_type] ?? $m->machine_type;
            $conn_label = $connection_labels[$m->connection_type] ?? '';
            ?>
            <div class="col-xs-12 col-sm-6 col-md-4">
                <div class="machine-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <div class="machine-name"><?= esc($m->name) ?></div>
                            <div class="machine-type"><?= esc($type_label) ?></div>
                        </div>
                        <span class="machine-status" style="background: <?= $st_color ?>;">
                            <i class="glyphicon <?= $st_icon ?>"></i> <?= ucfirst($st) ?>
                        </span>
                    </div>

                    <div class="machine-meta">
                        <?php if ($m->brand): ?>
                            <div><i class="glyphicon glyphicon-tag"></i> <?= esc($m->brand) ?> <?= esc($m->model_number ?? '') ?></div>
                        <?php endif; ?>
                        <?php if ($m->ip_address): ?>
                            <div><i class="glyphicon glyphicon-transfer"></i> <?= esc($conn_label) ?> <?= esc($m->ip_address) ?></div>
                        <?php endif; ?>
                        <?php if ($m->location): ?>
                            <div><i class="glyphicon glyphicon-map-marker"></i> <?= esc($m->location) ?></div>
                        <?php endif; ?>
                        <?php if ($m->max_width_mm && $m->max_height_mm): ?>
                            <div><i class="glyphicon glyphicon-resize-full"></i> Max: <?= $m->max_width_mm ?>×<?= $m->max_height_mm ?>mm</div>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee;">
                        <div>
                            <span class="job-count" style="color: <?= $m->active_jobs > 0 ? '#0d6efd' : '#6c757d' ?>;"><?= $m->active_jobs ?></span>
                            <span style="font-size: 11px; color: #6c757d;">active jobs</span>
                        </div>
                        <div class="actions">
                            <a href="<?= base_url('machines/edit/' . $m->id) ?>" class="btn btn-xs btn-default" title="Edit">
                                <i class="glyphicon glyphicon-pencil"></i>
                            </a>
                            <a href="<?= base_url('machines/delete/' . $m->id) ?>" class="btn btn-xs btn-danger" title="Delete"
                               onclick="return confirm('Delete this machine?')">
                                <i class="glyphicon glyphicon-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?= view('partial/footer') ?>
