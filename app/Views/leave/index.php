<?php
/**
 * @var bool  $can_manage
 * @var array $on_leave_today
 * @var array $records
 * @var array $employees
 * @var array $types
 * @var int   $my_person_id
 */
?>
<?= view('partial/header') ?>

<h3 class="text-center" style="margin-bottom: 5px;"><?= esc($title) ?></h3>

<div id="leave_message" class="alert" style="display:none;"></div>

<div class="row" style="margin-bottom: 12px;">
    <div class="col-xs-12 text-right">
        <?php if ($can_manage): ?>
            <button type="button" class="btn btn-primary btn-sm" id="new_leave_button">
                <i class="glyphicon glyphicon-plus"></i> Record Leave
            </button>
        <?php else: ?>
            <button type="button" class="btn btn-primary btn-sm" id="apply_leave_button">
                <i class="glyphicon glyphicon-plus"></i> Apply for Leave
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($can_manage): ?>
<div class="row">
    <div class="col-xs-12">
        <div class="panel <?= empty($on_leave_today) ? 'panel-default' : 'panel-warning' ?>">
            <div class="panel-heading">
                <strong><i class="glyphicon glyphicon-user"></i> On Leave Today</strong>
            </div>
            <div class="panel-body">
                <?php if (empty($on_leave_today)): ?>
                    <span class="text-muted">Everyone is available today.</span>
                <?php else: ?>
                    <?php foreach ($on_leave_today as $row): ?>
                        <span class="label label-warning" style="font-size: 0.9em; margin-right: 6px;">
                            <?= esc($row['first_name'] . ' ' . $row['last_name']) ?> — <?= esc(ucfirst($row['leave_type'])) ?>
                            (back <?= esc(date('M j', strtotime($row['end_date']) + 86400)) ?>)
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-xs-12">
        <table class="table table-striped table-condensed">
            <thead>
                <tr>
                    <?php if ($can_manage): ?><th>Employee</th><?php endif; ?>
                    <th>Type</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="<?= $can_manage ? 6 : 5 ?>" class="text-center text-muted">
                        <?= $can_manage ? 'No leave recorded yet.' : 'You have no leave on record. Use "Apply for Leave" above.' ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($records as $row): ?>
                    <?php
                        $isCurrent = $row['status'] === 'approved' && $row['start_date'] <= date('Y-m-d') && $row['end_date'] >= date('Y-m-d');
                        $statusClass = ['pending' => 'label-default', 'approved' => 'label-success', 'rejected' => 'label-danger'][$row['status']] ?? 'label-default';
                    ?>
                    <tr class="<?= $isCurrent ? 'warning' : '' ?>">
                        <?php if ($can_manage): ?><td><?= esc($row['first_name'] . ' ' . $row['last_name']) ?></td><?php endif; ?>
                        <td><?= esc(ucfirst($row['leave_type'])) ?></td>
                        <td><?= esc($row['start_date']) ?></td>
                        <td><?= esc($row['end_date']) ?></td>
                        <td><?= esc($row['reason'] ?? '') ?></td>
                        <td><span class="label <?= $statusClass ?>"><?= esc(ucfirst($row['status'])) ?></span></td>
                        <td class="text-right">
                            <?php if ($can_manage && $row['status'] === 'pending'): ?>
                                <button class="btn btn-xs btn-success approve-leave" data-id="<?= $row['id'] ?>" title="Approve">
                                    <i class="glyphicon glyphicon-ok"></i> Approve
                                </button>
                                <button class="btn btn-xs btn-warning reject-leave" data-id="<?= $row['id'] ?>" title="Reject">
                                    <i class="glyphicon glyphicon-remove"></i> Reject
                                </button>
                            <?php endif; ?>
                            <?php if ($can_manage): ?>
                                <button class="btn btn-xs btn-default edit-leave"
                                    data-id="<?= $row['id'] ?>"
                                    data-person="<?= $row['person_id'] ?>"
                                    data-type="<?= esc($row['leave_type']) ?>"
                                    data-start="<?= esc($row['start_date']) ?>"
                                    data-end="<?= esc($row['end_date']) ?>"
                                    data-reason="<?= esc($row['reason'] ?? '') ?>"
                                    title="Edit">
                                    <i class="glyphicon glyphicon-pencil"></i>
                                </button>
                                <button class="btn btn-xs btn-danger delete-leave" data-id="<?= $row['id'] ?>" title="Delete">
                                    <i class="glyphicon glyphicon-trash"></i>
                                </button>
                            <?php elseif ($row['status'] === 'pending'): ?>
                                <button class="btn btn-xs btn-danger delete-leave" data-id="<?= $row['id'] ?>" title="Cancel Request">
                                    <i class="glyphicon glyphicon-remove"></i> Cancel
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($can_manage): ?>
<!-- Add/Edit Modal (admin/manager direct entry — recorded already approved) -->
<div class="modal fade" id="leaveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <?= form_open('leave/save', ['class' => 'form-horizontal', 'id' => 'leave_form']) ?>
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="leaveModalTitle">Record Leave</h4>
            </div>
            <div class="modal-body">
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Employee</label>
                    <div class="col-xs-7">
                        <select name="person_id" id="leave_person" class="form-control input-sm" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp->person_id ?>"><?= esc($emp->first_name . ' ' . $emp->last_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Type</label>
                    <div class="col-xs-7">
                        <select name="leave_type" id="leave_type" class="form-control input-sm">
                            <?php foreach ($types as $t): ?>
                                <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Start Date</label>
                    <div class="col-xs-7"><input type="date" name="start_date" id="leave_start" class="form-control input-sm" required></div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">End Date</label>
                    <div class="col-xs-7"><input type="date" name="end_date" id="leave_end" class="form-control input-sm" required></div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Reason <small class="text-muted">(optional)</small></label>
                    <div class="col-xs-7"><textarea name="reason" id="leave_reason" class="form-control input-sm" rows="2"></textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Apply Modal (self-service — always lands as pending) -->
<div class="modal fade" id="applyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <?= form_open('leave/apply', ['class' => 'form-horizontal', 'id' => 'apply_form']) ?>
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Apply for Leave</h4>
            </div>
            <div class="modal-body">
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Type</label>
                    <div class="col-xs-7">
                        <select name="leave_type" class="form-control input-sm">
                            <?php foreach ($types as $t): ?>
                                <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Start Date</label>
                    <div class="col-xs-7"><input type="date" name="start_date" class="form-control input-sm" required></div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">End Date</label>
                    <div class="col-xs-7"><input type="date" name="end_date" class="form-control input-sm" required></div>
                </div>
                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-4">Reason <small class="text-muted">(optional)</small></label>
                    <div class="col-xs-7"><textarea name="reason" class="form-control input-sm" rows="2"></textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script type="text/javascript">
$(function() {
    function showMessage(success, message) {
        var box = $('#leave_message');
        box.removeClass('alert-success alert-danger')
            .addClass(success ? 'alert-success' : 'alert-danger')
            .text(message).show();
    }

    function post(url, data) {
        return $.post(url, $.extend({ '<?= csrf_token() ?>': '<?= csrf_hash() ?>' }, data), null, 'json');
    }

    <?php if ($can_manage): ?>
    $('#new_leave_button').on('click', function() {
        $('#leaveModalTitle').text('Record Leave');
        $('#leave_form').attr('action', '<?= base_url('leave/save') ?>')[0].reset();
        $('#leaveModal').modal('show');
    });

    $('.edit-leave').on('click', function() {
        var id = $(this).data('id');
        $('#leaveModalTitle').text('Edit Leave');
        $('#leave_form').attr('action', '<?= base_url('leave/save') ?>/' + id);
        $('#leave_person').val($(this).data('person'));
        $('#leave_type').val($(this).data('type'));
        $('#leave_start').val($(this).data('start'));
        $('#leave_end').val($(this).data('end'));
        $('#leave_reason').val($(this).data('reason'));
        $('#leaveModal').modal('show');
    });

    $('#leave_form').on('submit', function(e) {
        e.preventDefault();
        $.post($(this).attr('action'), $(this).serialize(), function(res) {
            if (res.success) {
                window.location.reload();
            } else {
                $('#leaveModal').modal('hide');
                showMessage(false, res.message || 'Could not save.');
            }
        }, 'json');
    });

    $('.approve-leave').on('click', function() {
        post('<?= base_url('leave/approve') ?>/' + $(this).data('id')).done(function(res) {
            if (res.success) window.location.reload(); else showMessage(false, res.message);
        });
    });

    $('.reject-leave').on('click', function() {
        if (! confirm('Reject this leave request?')) return;
        post('<?= base_url('leave/reject') ?>/' + $(this).data('id')).done(function(res) {
            if (res.success) window.location.reload(); else showMessage(false, res.message);
        });
    });
    <?php else: ?>
    $('#apply_leave_button').on('click', function() {
        $('#apply_form')[0].reset();
        $('#applyModal').modal('show');
    });

    $('#apply_form').on('submit', function(e) {
        e.preventDefault();
        $.post($(this).attr('action'), $(this).serialize(), function(res) {
            if (res.success) {
                window.location.reload();
            } else {
                $('#applyModal').modal('hide');
                showMessage(false, res.message || 'Could not submit request.');
            }
        }, 'json');
    });
    <?php endif; ?>

    $('.delete-leave').on('click', function() {
        if (! confirm('<?= $can_manage ? 'Delete this leave record?' : 'Cancel this leave request?' ?>')) return;
        var id = $(this).data('id');
        post('<?= base_url('leave/delete') ?>/' + id).done(function(res) {
            if (res.success) {
                window.location.reload();
            } else {
                showMessage(false, res.message || 'Could not delete.');
            }
        });
    });
});
</script>

<?= view('partial/footer') ?>
