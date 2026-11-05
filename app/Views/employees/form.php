<?php
/**
 * @var string $controller_name
 * @var object $person_info
 * @var array  $all_modules
 * @var array  $all_subpermissions
 * @var int    $employee_id
 */
?>

<div id="required_fields_message"><?= lang('Common.fields_required_message') ?></div>
<ul id="error_message_box" class="error_message_box"></ul>

<?= form_open("{$controller_name}/save/{$person_info->person_id}", ['id' => 'employee_form', 'class' => 'form-horizontal']) ?>

    <ul class="nav nav-tabs nav-justified" data-tabs="tabs">
        <li class="active" role="presentation">
            <a data-toggle="tab" href="#employee_basic_info"><?= lang('Employees.basic_information') ?></a>
        </li>
        <li role="presentation">
            <a data-toggle="tab" href="#employee_login_info"><?= lang('Employees.login_info') ?></a>
        </li>
        <li role="presentation">
            <a data-toggle="tab" href="#employee_payroll_info">Payroll & Banking</a>
        </li>
        <li role="presentation">
            <a data-toggle="tab" href="#employee_permission_info"><?= lang('Employees.permission_info') ?></a>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade in active" id="employee_basic_info">
            <fieldset>
                <?= view('people/form_basic_info') ?>
            </fieldset>
        </div>

        <div class="tab-pane" id="employee_login_info">
            <fieldset>
                <div class="form-group form-group-sm">
                    <?= form_label(lang('Employees.username'), 'username', ['class' => 'required control-label col-xs-3']) ?>
                    <div class="col-xs-8">
                        <div class="input-group">
                            <span class="input-group-addon input-sm"><span class="glyphicon glyphicon-user"></span></span>
                            <?= form_input([
                                'name'  => 'username',
                                'id'    => 'username',
                                'class' => 'form-control input-sm',
                                'value' => $person_info->username,
                            ]) ?>
                        </div>
                    </div>
                </div>

                <?php $password_label_attributes = $person_info->person_id === '' ? ['class' => 'required'] : []; ?>

                <div class="form-group form-group-sm">
                    <?= form_label(lang('Employees.password'), 'password', array_merge($password_label_attributes, ['class' => 'control-label col-xs-3'])) ?>
                    <div class="col-xs-8">
                        <div class="input-group">
                            <span class="input-group-addon input-sm"><span class="glyphicon glyphicon-lock"></span></span>
                            <?= form_password([
                                'name'  => 'password',
                                'id'    => 'password',
                                'class' => 'form-control input-sm',
                            ]) ?>
                        </div>
                    </div>
                </div>

                <div class="form-group form-group-sm">
                    <?= form_label(lang('Employees.repeat_password'), 'repeat_password', array_merge($password_label_attributes, ['class' => 'control-label col-xs-3'])) ?>
                    <div class="col-xs-8">
                        <div class="input-group">
                            <span class="input-group-addon input-sm"><span class="glyphicon glyphicon-lock"></span></span>
                            <?= form_password([
                                'name'  => 'repeat_password',
                                'id'    => 'repeat_password',
                                'class' => 'form-control input-sm',
                            ]) ?>
                        </div>
                    </div>
                </div>

                <div class="form-group form-group-sm">
                    <?= form_label(lang('Employees.language'), 'language', ['class' => 'control-label col-xs-3']) ?>
                    <div class="col-xs-8">
                        <div class="input-group">
                            <?php
                            $languages = get_languages();
$languages[':']                        = lang('Employees.system_language');
$language_code                         = current_language_code();
$language                              = current_language();

// If No language is set then it will display "System Language"
if ($language_code === current_language_code(true)) {
    $language_code = '';
    $language      = '';
}

echo form_dropdown(
    'language',
    $languages,
    "{$language_code}:{$language}",
    ['class' => 'form-control input-sm'],
);
?>
                        </div>
                    </div>
                </div>
            </fieldset>
        </div>

        <div class="tab-pane" id="employee_payroll_info">
            <fieldset>
                <legend style="border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 15px;">
                    <span class="glyphicon glyphicon-briefcase"></span> Employment Details
                </legend>

                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-3">Employee ID</label>
                    <div class="col-xs-8">
                        <?= form_input([
'name'        => 'employee_id',
'id'          => 'employee_id',
'class'       => 'form-control input-sm',
'value'       => $person_info->employee_id ?? '',
'placeholder' => 'e.g. EMP001',
                        ]) ?>
                    </div>
                </div>

                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-3">Department</label>
                    <div class="col-xs-8">
                        <?= form_dropdown('department', [
''                 => '-- Select Department --',
'Management'       => 'Management',
'Sales'            => 'Sales',
'Print Production' => 'Print Production',
'Design'           => 'Design',
'Finance'          => 'Finance',
'Human Resources'  => 'Human Resources',
'IT'               => 'IT',
'Warehouse'        => 'Warehouse',
'Delivery'         => 'Delivery',
'Customer Service' => 'Customer Service',
'Maintenance'      => 'Maintenance',
                        ], $person_info->department ?? '', ['class' => 'form-control input-sm']) ?>
                    </div>
                </div>

                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-3">Job Title</label>
                    <div class="col-xs-8">
                        <?= form_input([
'name'        => 'job_title',
'id'          => 'job_title',
'class'       => 'form-control input-sm',
'value'       => $person_info->job_title ?? '',
'placeholder' => 'e.g. Senior Printer',
                        ]) ?>
                    </div>
                </div>

                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-3">Employment Date</label>
                    <div class="col-xs-4">
                        <?= form_input([
'name'  => 'employment_date',
'id'    => 'employment_date',
'type'  => 'date',
'class' => 'form-control input-sm',
'value' => $person_info->employment_date ?? '',
                        ]) ?>
                    </div>
                </div>

                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-3">Employment Status</label>
                    <div class="col-xs-4">
                        <?= form_dropdown('employment_status', [
'active'     => 'Active',
'probation'  => 'Probation',
'suspended'  => 'Suspended',
'terminated' => 'Terminated',
'resigned'   => 'Resigned',
                        ], $person_info->employment_status ?? 'active', ['class' => 'form-control input-sm']) ?>
                    </div>
                </div>

                <legend style="border-bottom: 1px solid #eee; padding-bottom: 5px; margin: 20px 0 15px;">
                    <span class="glyphicon glyphicon-usd"></span> Salary & Payroll
                </legend>

                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-3">Salary</label>
                    <div class="col-xs-4">
                        <div class="input-group">
                            <span class="input-group-addon input-sm">$</span>
                            <?= form_input([
    'name'        => 'salary',
    'id'          => 'salary',
    'type'        => 'number',
    'step'        => '0.01',
    'class'       => 'form-control input-sm',
    'value'       => $person_info->salary ?? '',
    'placeholder' => '0.00',
]) ?>
                        </div>
                    </div>
                </div>

                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-3">Pay Frequency</label>
                    <div class="col-xs-4">
                        <?= form_dropdown('pay_frequency', [
'weekly'    => 'Weekly',
'biweekly'  => 'Bi-weekly',
'monthly'   => 'Monthly',
'quarterly' => 'Quarterly',
'annual'    => 'Annual',
                        ], $person_info->pay_frequency ?? 'monthly', ['class' => 'form-control input-sm']) ?>
                    </div>
                </div>

                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-3">Payment Method</label>
                    <div class="col-xs-4">
                        <?= form_dropdown('payment_method', [
'bank_transfer' => 'Bank Transfer',
'cash'          => 'Cash',
'mobile_money'  => 'Mobile Money',
'check'         => 'Check',
                        ], $person_info->payment_method ?? 'bank_transfer', ['class' => 'form-control input-sm']) ?>
                    </div>
                </div>

                <legend style="border-bottom: 1px solid #eee; padding-bottom: 5px; margin: 20px 0 15px;">
                    <span class="glyphicon glyphicon-piggy-bank"></span> Banking Details
                </legend>

                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-3">Bank Name</label>
                    <div class="col-xs-8">
                        <?= form_dropdown('bank_name', [
''                   => '-- Select Bank --',
'CBZ Bank'           => 'CBZ Bank',
'Stanbic Bank'       => 'Stanbic Bank',
'Standard Chartered' => 'Standard Chartered',
'NMB Bank'           => 'NMB Bank',
'FBC Bank'           => 'FBC Bank',
'Ecobank'            => 'Ecobank',
'BancABC'            => 'BancABC',
'CABS'               => 'CABS',
'Steward Bank'       => 'Steward Bank',
'ZB Bank'            => 'ZB Bank',
'Access Bank'        => 'Access Bank',
'First Capital Bank' => 'First Capital Bank',
'Agribank'           => 'Agribank',
'Infibank'           => 'Infibank',
'Other'              => 'Other',
                        ], $person_info->bank_name ?? '', ['class' => 'form-control input-sm']) ?>
                    </div>
                </div>

                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-3">Branch</label>
                    <div class="col-xs-8">
                        <?= form_input([
'name'        => 'bank_branch',
'id'          => 'bank_branch',
'class'       => 'form-control input-sm',
'value'       => $person_info->bank_branch ?? '',
'placeholder' => 'e.g. Borrowdale, Avondale',
                        ]) ?>
                    </div>
                </div>

                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-3">Account Number</label>
                    <div class="col-xs-8">
                        <?= form_input([
'name'        => 'account_number',
'id'          => 'account_number',
'class'       => 'form-control input-sm',
'value'       => $person_info->account_number ?? '',
'placeholder' => 'e.g. 1234567890',
                        ]) ?>
                    </div>
                </div>

                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-3">Account Name</label>
                    <div class="col-xs-8">
                        <?= form_input([
'name'        => 'account_name',
'id'          => 'account_name',
'class'       => 'form-control input-sm',
'value'       => $person_info->account_name ?? '',
'placeholder' => 'e.g. John Doe',
                        ]) ?>
                    </div>
                </div>
            </fieldset>
        </div>

        <div class="tab-pane" id="employee_permission_info">
            <fieldset>
                <p><?= lang('Employees.permission_desc') ?></p>

                <div class="form-group form-group-sm">
                    <label class="control-label col-xs-3">Role</label>
                    <div class="col-xs-6">
                        <?= form_dropdown(
                            'role_id',
                            ['' => 'Custom (pick modules below)'] + array_column($all_roles ?? [], 'name', 'role_id'),
                            (string) ($person_info->role_id ?? ''),
                            'id="role_id" class="form-control input-sm"',
                        ) ?>
                    </div>
                    <div class="col-xs-3">
                        <span class="text-muted small">Picking a role checks the matching modules below for you — still editable before saving.</span>
                        &middot; <a href="<?= base_url('roles') ?>" target="_blank">Manage roles</a>
                    </div>
                </div>

                <script type="text/javascript">
                    $(document).ready(function() {
                        $('#role_id').change(function() {
                            var roleId = $(this).val();
                            if (!roleId) {
                                return; // "Custom" — leave whatever's checked alone
                            }

                            $.get('<?= base_url('roles/permissions/') ?>' + roleId, function(response) {
                                var granted = response.permission_ids || [];

                                $('#permission_list input[type="checkbox"]').each(function() {
                                    var $box = $(this);
                                    var isGranted = granted.indexOf($box.val()) !== -1;
                                    $box.prop('checked', isGranted).prop('disabled', false).trigger('change');
                                });
                            }, 'json');
                        });
                    });
                </script>

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
                                $module->menu_group,
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
                                    if (! empty($lang_line)) {
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
            </fieldset>
        </div>
    </div>

<?= form_close() ?>

<script type="text/javascript">
    // Validation and submit handling
    $(document).ready(function() {
        $.validator.setDefaults({
            ignore: []
        });

        $.validator.addMethod('module', function(value, element) {
            // Scoped to this checkbox's own <li> only — a module that isn't
            // checked, or has no sub-permissions at all, is always valid.
            // (Previously this recomputed a single shared pass/fail across
            // every module in the list and reported it identically against
            // every module checkbox, so one unmet module produced the same
            // error duplicated once per module in the form.)
            var $li = $(element).closest('li');
            if (!$(element).is(':checked') || $('ul', $li).length === 0) {
                return true;
            }
            return $('ul > li > input:checked', $li).length > 0;
        }, "<?= lang('Employees.subpermission_required') ?>");

        $('ul#permission_list > li > input.module').each(function() {
            var $this = $(this);
            $('ul > li > input,select', $this.parent()).each(function() {
                var $that = $(this);
                var updateInputs = function(checked) {
                    $that.prop('disabled', !checked);
                    !checked && $that.prop('checked', false);
                }
                $this.change(function() {
                    updateInputs($this.is(':checked'));
                });
                updateInputs($this.is(':checked'));
            });
        });

        $('#employee_form').validate($.extend({
            submitHandler: function(form) {
                $(form).ajaxSubmit({
                    success: function(response) {
                        dialog_support.hide();
                        table_support.handle_submit("<?= esc($controller_name) ?>", response);
                    },
                    dataType: 'json'
                });
            },

            errorLabelContainer: '#error_message_box',

            rules: {
                first_name: 'required',
                last_name: 'required',
                username: {

                    required: true,
                    minlength: 5,
                    remote: '<?= esc("{$controller_name}/checkUsername/{$employee_id}") ?>'
                },
                password: {
                    <?php if ($person_info->person_id === '') { ?>
                        required: true,
                    <?php } ?>
                    minlength: 8
                },
                repeat_password: {
                    equalTo: '#password'
                },
                email: 'email'
            },

            messages: {
                first_name: "<?= lang('Common.first_name_required') ?>",
                last_name: "<?= lang('Common.last_name_required') ?>",
                username: {
                    required: "<?= lang('Employees.username_required') ?>",
                    minlength: "<?= lang('Employees.username_minlength') ?>",
                    remote: "<?= lang('Employees.username_duplicate') ?>"
                },
                password: {
                    <?php if ($person_info->person_id === '') { ?>
                        required: "<?= lang('Employees.password_required') ?>",
                    <?php } ?>
                    minlength: "<?= lang('Employees.password_minlength') ?>"
                },
                repeat_password: {
                    equalTo: "<?= lang('Employees.password_must_match') ?>"
                },
                email: "<?= lang('Common.email_invalid_format') ?>"
            }
        }, form_support.error));
    });
</script>
