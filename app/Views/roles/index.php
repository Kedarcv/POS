<?php
/**
 * Roles — list of role-based access profiles.
 *
 * @var string $title
 * @var array  $roles
 * @var array  $employee_counts
 */
?>

<?= view('partial/header') ?>

<div class="row" style="margin-bottom: 12px;">
    <div class="col-xs-12">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;"><i class="glyphicon glyphicon-user"></i> <?= esc($title) ?></h3>
            <a href="<?= base_url('roles/new') ?>" class="btn btn-success btn-sm">
                <i class="glyphicon glyphicon-plus"></i> New Role
            </a>
        </div>
        <p class="text-muted" style="margin-top: 4px;">
            Assign an employee a role instead of picking individual modules — the role decides what they can see.
            Manage employees and pick their role from the <a href="<?= base_url('employees') ?>">Employees</a> page.
        </p>
    </div>
</div>

<div id="role_message" class="alert" style="display:none;"></div>

<table class="table table-striped">
    <thead>
        <tr>
            <th>Name</th>
            <th>Description</th>
            <th>Employees</th>
            <th style="width: 160px;"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($roles as $role): ?>
            <?php $count = $employee_counts[$role->role_id] ?? 0; ?>
            <tr>
                <td><strong><?= esc($role->name) ?></strong></td>
                <td class="text-muted"><?= esc($role->description) ?></td>
                <td><?= (int) $count ?></td>
                <td>
                    <a href="<?= base_url('roles/edit/' . $role->role_id) ?>" class="btn btn-default btn-xs">
                        <i class="glyphicon glyphicon-edit"></i> Edit
                    </a>
                    <button type="button" class="btn btn-danger btn-xs btn-delete-role" data-id="<?= (int) $role->role_id ?>" data-count="<?= (int) $count ?>">
                        <i class="glyphicon glyphicon-trash"></i> Delete
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script type="text/javascript">
    $(document).ready(function() {
        $('.btn-delete-role').click(function() {
            var btn = $(this);
            var count = parseInt(btn.data('count'), 10);

            if (count > 0) {
                alert('This role is assigned to ' + count + ' employee(s). Reassign them to another role first.');
                return;
            }
            if (!confirm('Delete this role?')) {
                return;
            }

            $.post('<?= base_url('roles/delete/') ?>' + btn.data('id'), {}, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    $('#role_message').removeClass('alert-success').addClass('alert-danger').text(response.message).show();
                }
            }, 'json');
        });
    });
</script>

<?= view('partial/footer') ?>
