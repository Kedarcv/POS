<?php
/**
 * @var array $config
 */
?>

<?= form_open('config/saveMessage/', ['id' => 'message_config_form', 'enctype' => 'multipart/form-data', 'class' => 'form-horizontal']) ?>
    <div id="config_wrapper">
        <fieldset id="config_info">

            <div id="required_fields_message"><?= lang('Common.fields_required_message') ?></div>
            <ul id="message_error_message_box" class="error_message_box"></ul>

            <div class="form-group form-group-sm">
                <label class="col-xs-2 control-label">
                    <span style="color: #25d366;">&#128172;</span> WhatsApp Config
                </label>
                <div class="col-xs-10">
                    <p class="form-control-static text-muted" style="font-size: 12px;">
                        WhatsApp messages are sent via your bot number using the Cloud API.
                        Credentials are configured in <code>.env</code> — see below for the saved message template.
                    </p>
                </div>
            </div>

            <div class="form-group form-group-sm">
                <label class="col-xs-2 control-label">Bot Number</label>
                <div class="col-xs-4">
                    <p class="form-control-static">
                        <span class="label label-success"><?= esc(getenv('WHATSAPP_PHONE_NUMBER_ID') ?: 'Not configured') ?></span>
                    </p>
                </div>
            </div>

            <div class="form-group form-group-sm">
                <?= form_label(lang('Config.msg_msg'), 'msg_msg', ['class' => 'control-label col-xs-2']) ?>
                <div class="col-xs-4">
                    <?= form_textarea([
                        'name'        => 'msg_msg',
                        'id'          => 'msg_msg',
                        'class'       => 'form-control input-sm',
                        'value'       => $config['msg_msg'],
                        'placeholder' => lang('Config.msg_msg_placeholder')
                    ]) ?>
                    <span class="help-block">Default message template used when sending WhatsApp messages to customers.</span>
                </div>
            </div>

            <?= form_submit([
                'name'  => 'submit_message',
                'id'    => 'submit_message',
                'value' => lang('Common.submit'),
                'class' => 'btn btn-success btn-sm pull-right'
            ]) ?>

        </fieldset>
    </div>
<?= form_close() ?>

<script type="text/javascript">
    // Validation and submit handling
    $(document).ready(function() {
        $('#message_config_form').validate($.extend(form_support.handler, {

            errorLabelContainer: "#message_error_message_box",

            rules: {
                msg_msg: { required: false }
            }
        }));
    });
</script>
