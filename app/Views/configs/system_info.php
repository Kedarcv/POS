<?php
/**
 * @var string $dbVersion
 * @var array $config
 */

use Config\OSPOS;

?>

<style>
    a:hover {
        cursor: pointer;
    }

    hidden {
        visibility: hidden;
    }
</style>

<script type="text/javascript" src="js/clipboard.min.js"></script>

<div id="config_wrapper" class="col-sm-12">
    <div style="margin-bottom: 15px; padding: 12px 16px; background: #f8f9fa; border: 1px solid #dcdcdc; border-radius: 4px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <strong style="font-size: 14px;"><span class="glyphicon glyphicon-hdd"></span> <?= lang('Config.backup_database') ?></strong>
            <p class="text-muted" style="margin: 2px 0 0 0;">Download a complete, self-contained SQL backup of all database tables and records.</p>
        </div>
        <a href="<?= site_url('config/backup_db') ?>" class="btn btn-primary btn-sm">
            <span class="glyphicon glyphicon-download-alt"></span> <?= lang('Config.backup_button') ?> (SQL)
        </a>
    </div>
    <?= lang('Config.server_notice') ?>
    <div class="container">
        <div class="row">
            <div class="col-sm-2" style="text-align: left;"><br>
                <p style="min-height: 17.7em; font-weight: bold;">General Info</p>
                <p style="min-height: 12.2em; font-weight: bold;">User Setup</p><br>
                <p style="font-weight: bold;">Permissions</p>
            </div>
            <div class="col-sm-8" id="issuetemplate" style="text-align: left;"><br>
                <?= lang('Config.ospos_info') . ':' ?>
                <?= esc(config('App')->application_version) ?> - <?= esc(substr(config(OSPOS::class)->commit_sha1, 0, 6)) ?><br>
                Language Code: <?= current_language_code() ?><br><br>
                <div id="TimeError"></div>
                Extensions & Modules:<br>
                <?php
                    echo "&#187; GD: ", extension_loaded('gd') ? '<span style="color: green;">Enabled &#x2713</span>' : '<span style="color: red;">Disabled &#x2717</span>', '<br>';
                    echo "&#187; BC Math: ", extension_loaded('bcmath') ? '<span style="color: green;">Enabled &#x2713</span>' : '<span style="color: red;">Disabled &#x2717</span>', '<br>';
                    echo "&#187; INTL: ", extension_loaded('intl') ? '<span style="color: green;">Enabled &#x2713</span>' : '<span style="color: red;">Disabled &#x2717</span>', '<br>';
                    echo "&#187; OpenSSL: ", extension_loaded('openssl') ? '<span style="color: green;">Enabled &#x2713</span>' : '<span style="color: red;">Disabled &#x2717</span>', '<br>';
                    echo "&#187; MBString: ", extension_loaded('mbstring') ? '<span style="color: green;">Enabled &#x2713</span>' : '<span style="color: red;">Disabled &#x2717</span>', '<br>';
                    echo "&#187; Curl: ", extension_loaded('curl') ? '<span style="color: green;">Enabled &#x2713</span>' : '<span style="color: red;">Disabled &#x2717</span>', '<br>';
                    echo "&#187; Json: ", extension_loaded('json') ? '<span style="color: green;">Enabled &#x2713</span>' : '<span style="color: red;">Disabled &#x2717</span>', '<br>';
                    echo "&#187; Xml: ", extension_loaded('xml') ? '<span style="color: green;">Enabled &#x2713</span>' : '<span style="color: red;">Disabled &#x2717</span>', '<br><br>';
                ?>
                User Configuration:<br>
                .Browser:
                <?php
                /**
                 * @param string $userAgent
                 * @return string
                 */
                function getBrowserNameAndVersion(string $userAgent): string
                {
                    $browser = match (true) {
                        strpos($userAgent, 'Opera')   !== false || strpos($userAgent, 'OPR/') !== false => 'Opera',
                        strpos($userAgent, 'Edge')    !== false => 'Edge',
                        strpos($userAgent, 'Chrome')  !== false => 'Chrome',
                        strpos($userAgent, 'Safari')  !== false => 'Safari',
                        strpos($userAgent, 'Firefox') !== false => 'Firefox',
                        strpos($userAgent, 'MSIE')    !== false || strpos($userAgent, 'Trident/7') !== false => 'Internet Explorer',
                        default                       => 'Other',
                    };

                    $version = match ($browser) {
                        'Opera'             => preg_match('/(Opera|OPR)\/([0-9.]+)/', $userAgent, $matches) ? $matches[2] : '',
                        'Edge'              => preg_match('/Edge\/([0-9.]+)/', $userAgent, $matches) ? $matches[1] : '',
                        'Chrome'            => preg_match('/Chrome\/([0-9.]+)/', $userAgent, $matches) ? $matches[1] : '',
                        'Safari'            => preg_match('/Version\/([0-9.]+)/', $userAgent, $matches) ? $matches[1] : '',
                        'Firefox'           => preg_match('/Firefox\/([0-9.]+)/', $userAgent, $matches) ? $matches[1] : '',
                        'Internet Explorer' => preg_match('/(MSIE|rv:)([0-9.]+)/', $userAgent, $matches) ? $matches[2] : '',
                        default             => '',
                    };

                    return $browser . ($version ? ' ' . $version : '');
                }
                echo esc(getBrowserNameAndVersion($_SERVER['HTTP_USER_AGENT']));
                ?><br>
                Server Software: <?= esc($_SERVER['SERVER_SOFTWARE']) ?><br>
                PHP Version: <?= PHP_VERSION ?><br>
                DB Version: <?= esc($dbVersion) ?><br>
                Server Port: <?= esc($_SERVER['SERVER_PORT']) ?><br>
                OS: <?= php_uname('s') . ' ' . php_uname('r') ?><br><br>
                <br><br>

                <?php if (DIRECTORY_SEPARATOR !== '\\'): ?>
                File Permissions:<br>
                &#187; [writable/logs:]
                <?php $logs = WRITEPATH . 'logs/';
                $uploads = FCPATH. 'uploads/';
                $images = FCPATH. 'uploads/item_pics/';
                $importCustomers = WRITEPATH . '/uploads/importCustomers.csv';    // TODO: This variable does not follow naming conventions for the project.

                // fileperms() throws on a path that doesn't exist (e.g. importCustomers.csv
                // before any customer CSV has ever been imported) — this diagnostics page
                // must never itself be the thing that errors out.
                $safe_fileperms = static fn ($path) => file_exists($path) ? fileperms($path) : 0;

                if (is_writable($logs)) {
                    echo ' -  ' . substr(sprintf("%o", $safe_fileperms($logs)), -4) . ' |  ' . '<span style="color: green;">  Writable &#x2713 </span>';
                } else {
                    echo ' -  ' . substr(sprintf("%o", $safe_fileperms($logs)), -4) . ' |  ' . '<span style="color: red;">    Not Writable &#x2717 </span>';
                }

                clearstatcache();
                if (is_writable($logs) && substr(decoct($safe_fileperms($logs)), -4) != 750) {
                    echo ' | <span style="color: red;">Vulnerable or Incorrect Permissions &#x2717</span>';
                } else {
                    echo ' | <span style="color: green;">Security Check Passed &#x2713</span>';
                }
                clearstatcache();
                ?>
                <br>
                &#187; [public/uploads:]
                <?php
                if (is_writable($uploads)) {
                    echo ' -  ' . substr(sprintf("%o", $safe_fileperms($uploads)), -4) . ' |  ' . '<span style="color: green;">     Writable &#x2713 </span>';
                } else {
                    echo ' -  ' . substr(sprintf("%o", $safe_fileperms($uploads)), -4) . ' |  ' . '<span style="color: red;"> Not Writable &#x2717 </span>';
                }

                clearstatcache();

                if (is_writable($uploads) && substr(decoct($safe_fileperms($uploads)), -4) != 750) {
                    echo ' | <span style="color: red;">Vulnerable or Incorrect Permissions &#x2717</span>';
                } else {
                    echo ' |  <span style="color: green;">Security Check Passed &#x2713 </span>';
                }

                clearstatcache();
                ?>
                <br>
                &#187; [public/uploads/item_pics:]
                <?php
                if (is_writable($images)) {
                    echo ' -  ' . substr(sprintf("%o", $safe_fileperms($images)), -4) . ' |     ' . '<span style="color: green;"> Writable &#x2713 </span>';
                } else {
                    echo ' -  ' . substr(sprintf("%o", $safe_fileperms($images)), -4) . ' |     ' . '<span style="color: red;"> Not Writable &#x2717 </span>';
                }

                clearstatcache();

                if (substr(decoct($safe_fileperms($images)), -4) != 750) {
                    echo ' | <span style="color: red;">Vulnerable or Incorrect Permissions &#x2717</span>';
                } else {
                    echo ' | <span style="color: green;">Security Check Passed &#x2713 </span>';
                }

                clearstatcache();
                ?>
                <br>
                &#187; [importCustomers.csv:]
                <?php
                if (is_readable($importCustomers)) {
                    echo ' -  ' . substr(sprintf("%o", $safe_fileperms($importCustomers)), -4) . ' |  ' . '<span style="color: green;">     Readable &#x2713 </span>';
                } else {
                    echo ' -  ' . substr(sprintf("%o", $safe_fileperms($importCustomers)), -4) . ' |  ' . '<span style="color: red;"> Not Readable &#x2717 </span>';
                }
                clearstatcache();

                if (!((substr(decoct($safe_fileperms($importCustomers)), -4) == 640) || (substr(decoct($safe_fileperms($importCustomers)), -4) == 660))) {
                    echo ' | <span style="color: red;">Vulnerable or Incorrect Permissions &#x2717</span>';
                } else {
                    echo ' | <span style="color: green;">Security Check Passed &#x2713 </span>';
                }
                clearstatcache();
                ?>
                <br>
                <?php
                if (!((substr(decoct($safe_fileperms($logs)), -4) == 750)
                    && (substr(decoct($safe_fileperms($uploads)), -4) == 750)
                    && (substr(decoct($safe_fileperms($images)), -4) == 750)
                    && ((substr(decoct($safe_fileperms($importCustomers)), -4) == 640)
                        || (substr(decoct($safe_fileperms($importCustomers)), -4) == 660)))) {
                    echo '<br><span style="color: red;"><strong>' . lang('Config.security_issue') . '</strong> <br>' . lang('Config.perm_risk') . '</span><br>';
                } else {
                    echo '<br><span style="color: green;">' . lang('Config.no_risk') . '</strong> <br> </span>';
                }

                if (substr(decoct($safe_fileperms($logs)), -4) != 750) {
                    echo '<br><span style="color: red;"> &#187; [writable/logs:] ' . lang('Config.is_writable') . '</span>';
                }

                if (substr(decoct($safe_fileperms($uploads)), -4) != 750) {
                    echo '<br><span style="color: red;"> &#187; [writable/uploads:] ' . lang('Config.is_writable') . '</span>';
                }

                if (substr(decoct($safe_fileperms($images)), -4) != 750) {
                    echo '<br><span style="color: red;"> &#187; [writable/uploads/item_pics:] ' . lang('Config.is_writable') . '</span>';
                }

                if (!((substr(decoct($safe_fileperms($importCustomers)), -4) == 640)
                    || (substr(decoct($safe_fileperms($importCustomers)), -4) == 660))) {
                    echo '<br><span style="color: red;"> &#187; [importCustomers.csv:] ' . lang('Config.is_readable') . '</span>';
                }
                ?>
                <?php else: ?>
                    File Permissions: <span style="color: green;">N/A on Windows &#x2713</span><br>
                    <small>
                        Windows uses NTFS access-control lists instead of Unix-style chmod bits, so the
                        750/640 checks below don't apply here — <code>fileperms()</code> can't report anything
                        meaningful about NTFS permissions, and there's no chmod equivalent to "fix" this with.
                        This machine only runs the app for itself; there's nothing to lock down further.
                    </small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div style="text-align: center;">
    <a class="copy" data-clipboard-action="copy" data-clipboard-target="#issuetemplate">Copy Info</a> | <a href="https://github.com/opensourcepos/opensourcepos/issues/new" target="_blank"> <?= lang('Config.report_an_issue') ?></a>
    <script type="text/javascript">
        var clipboard = new ClipboardJS('.copy');

        clipboard.on('success', function(e) {
            document.getSelection().removeAllRanges();
        });

        $(function() {
            $('#timezone').clone().appendTo('#timezoneE');
        });

        if ($('#timezone').html() !== $('#ostimezone').html()) {
            document.getElementById("timezone").innerText = Intl.DateTimeFormat().resolvedOptions().timeZone;
            document.getElementById("TimeError").innerHTML = '<span style="color: red;"><?= lang('Config.timezone_error') ?></span><br><br><?= lang('Config.user_timezone') ?><div id="timezoneE" style="font-weight:600;"></div><br><?= lang('Config.os_timezone') ?><div id="ostimezoneE" style="font-weight:600;"><?= esc($config['timezone']) ?></div><br>';
        }
    </script>
</div>
