<?php
/**
 * Role form — create/edit a role and the modules it grants.
 *
 * @var string      $title
 * @var object|null $role
 * @var int         $role_id
 * @var array       $all_modules
 * @var array       $all_subpermissions
 */
$is_edit = $role !== null;
?>

<?= view('partial/header') ?>

<div class="row" style="margin-bottom: 12px;">
    <div class="col-xs-12">
        <a href="<?= base_url('roles') ?>" class="btn btn-default btn-sm">
            <i class="glyphicon glyphicon-arrow-left"></i> Roles
        </a>
    </div>
</div>

<h3 class="text-center" style="margin-bottom: 5px;"><?= esc($title) ?></h3>

<div id="role_message" class="alert" style="display:none;"></div>

<form id="role_form">
    <div class="form-group form-group-sm">
        <label class="control-label col-xs-2">Role Name</label>
        <div class="col-xs-6">
            <input type="text" name="name" id="name" class="form-control input-sm" value="<?= esc($role->name ?? '') ?>" required>
        </div>
    </div>
    <div class="form-group form-group-sm">
        <label class="control-label col-xs-2">Description</label>
        <div class="col-xs-8">
            <input type="text" name="description" id="description" class="form-control input-sm" value="<?= esc($role->description ?? '') ?>">
        </div>
    </div>

    <div class="row"><div class="col-xs-12"><hr></div></div>

    <div class="row">
        <div class="col-xs-12">
            <p>Modules this role grants — pick everything a person in this role should be able to see.</p>

            <ul id="permission_list">
                <?php foreach ($all_modules as $module) { ?>
                    <li>
                        <?= form_checkbox("grant_{$module->module_id}", $module->module_id, $module->grant === true, 'class="module"') ?>
                        <?= form_dropdown(
                            "menu_group_{$module->module_id}",
                            [
                                'home'   => lang('Module.home'),
                                'office' => lang('Module.office'),
                                'both'   => lang('Module.both'),
                            ],
                            'home',
                            'class="module"',
                        ) ?>

                        <span class="medium"><?= lang("Module.{$module->module_id}") ?>:</span>
                        <span class="small"><?= lang("Module.{$module->module_id}_desc") ?></span>
                        <?php
                        foreach ($all_subpermissions as $permission) {
                            $exploded_permission = explode('_', $permission->permission_id, 2);
                            if ($permission->module_id === $module->module_id) {
                                $lang_key  = $module->module_id . '.' . $exploded_permission[1];
                                $lang_line = lang(ucfirst($lang_key));
                                $lang_line = (lang(ucfirst($lang_key)) === $lang_line) ? ucwords(str_replace('_', ' ', $exploded_permission[1])) : $lang_line;
                                if (!empty($lang_line)) {
                                    ?>
                                    <ul>
                                        <li>
                                            <?= form_checkbox("grant_{$permission->permission_id}", $permission->permission_id, $permission->grant === true) ?>
                                            <?= form_hidden("menu_group_{$permission->permission_id}", '--') ?>
                                            <span class="medium"><?= esc($lang_line) ?></span>
                                        </li>
                                    </ul>
                        <?php
                                }
                            }
                        }
                        ?>
                    </li>
                <?php } ?>
            </ul>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12 text-right">
            <button type="submit" class="btn btn-primary" id="save_role_button">
                <i class="glyphicon glyphicon-ok"></i> Save Role
            </button>
        </div>
    </div>
</form>

<script type="text/javascript">
    $(document).ready(function() {
        $('#role_form').submit(function(e) {
            e.preventDefault();

            $.post(
                '<?= base_url('roles/save' . ($is_edit ? '/' . $role_id : '')) ?>',
                $(this).serialize(),
                function(response) {
                    var box = $('#role_message');
                    if (response.success) {
                        box.removeClass('alert-danger').addClass('alert-success').text(response.message).show();
                        setTimeout(function() {
                            window.location = '<?= base_url('roles') ?>';
                        }, 600);
                    } else {
                        box.removeClass('alert-success').addClass('alert-danger').text(response.message).show();
                    }
                },
                'json'
            );
        });
    });
</script>

<?= view('partial/footer') ?>
