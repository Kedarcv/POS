<?php
/**
 * @var object $user_info
 * @var array $allowed_modules
 * @var CodeIgniter\HTTP\IncomingRequest $request
 * @var array $config
 */

use Config\Services;

$request = Services::request();
?>

<!doctype html>
<html lang="<?= current_language_code() ?>">

<head>
    <meta charset="utf-8">
    <base href="<?= base_url() ?>">
    <title><?= esc($config['company']) . ' | ' . lang('Common.powered_by') . ' OSPOS ' . esc(config('App')->application_version) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="shortcut icon" type="image/svg+xml" href="images/favicon.svg">
    <?php $theme = (empty($config['theme']) ? 'flatly' : esc($config['theme'])); ?>
    <link rel="stylesheet" href="resources/bootswatch/<?= "$theme" ?>/bootstrap.min.css">

    <?php if (ENVIRONMENT == 'development' || get_cookie('debug') == 'true' || $request->getGet('debug') == 'true') : ?>
        <!-- inject:debug:css -->
        <link rel="stylesheet" href="resources/css/jquery-ui-fe010342cb.css">
        <link rel="stylesheet" href="resources/css/bootstrap-dialog-1716ef6e7c.css">
        <link rel="stylesheet" href="resources/css/jasny-bootstrap-40bf85f3ed.css">
        <link rel="stylesheet" href="resources/css/bootstrap-datetimepicker-66374fba71.css">
        <link rel="stylesheet" href="resources/css/bootstrap-select-66d5473b84.css">
        <link rel="stylesheet" href="resources/css/bootstrap-table-ed9d1a3360.css">
        <link rel="stylesheet" href="resources/css/bootstrap-table-sticky-header-07d65e7533.css">
        <link rel="stylesheet" href="resources/css/daterangepicker-85523b7dfe.css">
        <link rel="stylesheet" href="resources/css/chartist-c19aedb81a.css">
        <link rel="stylesheet" href="resources/css/chartist-plugin-tooltip-2e0ec92e60.css">
        <link rel="stylesheet" href="resources/css/bootstrap-tagsinput-5a6d46a06c.css">
        <link rel="stylesheet" href="resources/css/bootstrap-toggle-e12db6c1f3.css">
        <link rel="stylesheet" href="resources/css/bootstrap-4875cf7b0d.autocomplete.css">
        <link rel="stylesheet" href="resources/css/invoice-a99a4dfac3.css">
        <link rel="stylesheet" href="resources/css/ospos_print-bf10c1438b.css">
        <link rel="stylesheet" href="resources/css/ospos-d0b91fdf8f.css">
        <link rel="stylesheet" href="resources/css/popupbox-57d45cb822.css">
        <link rel="stylesheet" href="resources/css/receipt-0606f1c54e.css">
        <link rel="stylesheet" href="resources/css/register-e88ac141ce.css">
        <link rel="stylesheet" href="resources/css/reports-ace7faf688.css">
        <!-- endinject -->
        <!-- inject:debug:js -->
        <script src="resources/js/jquery-12e87d2f3a.js"></script>
        <script src="resources/js/jquery-4fa896f615.form.js"></script>
        <script src="resources/js/jquery-a0350e8820.validate.js"></script>
        <script src="resources/js/jquery-ui-cbc65ff85e.js"></script>
        <script src="resources/js/bootstrap-894d79839f.js"></script>
        <script src="resources/js/bootstrap-dialog-27123abb65.js"></script>
        <script src="resources/js/jasny-bootstrap-7c6d7b8adf.js"></script>
        <script src="resources/js/bootstrap-datetimepicker-25e39b7ef8.js"></script>
        <script src="resources/js/bootstrap-select-b01896a67b.js"></script>
        <script src="resources/js/bootstrap-table-bdb06552ea.js"></script>
        <script src="resources/js/bootstrap-table-export-6389dc2aa5.js"></script>
        <script src="resources/js/bootstrap-table-mobile-fc655b68ab.js"></script>
        <script src="resources/js/bootstrap-table-sticky-header-cb4d83d172.js"></script>
        <script src="resources/js/moment-d65dc6d2e6.min.js"></script>
        <script src="resources/js/daterangepicker-048c56a690.js"></script>
        <script src="resources/js/es6-promise-855125e6f5.js"></script>
        <script src="resources/js/FileSaver-e73b1946e8.js"></script>
        <script src="resources/js/html2canvas-e1d3a8d7cd.js"></script>
        <script src="resources/js/jspdf-bbbebb610c.umd.js"></script>
        <script src="resources/js/purify-6f534925a6.js"></script>
        <script src="resources/js/jspdf-92d87e47e8.plugin.autotable.js"></script>
        <script src="resources/js/tableExport-3d506dfa61.min.js"></script>
        <script src="resources/js/chartist-8a7ecb4445.js"></script>
        <script src="resources/js/chartist-plugin-pointlabels-0a1ab6aa4e.js"></script>
        <script src="resources/js/chartist-plugin-tooltip-116cb48831.js"></script>
        <script src="resources/js/chartist-plugin-axistitle-80a1198058.js"></script>
        <script src="resources/js/chartist-plugin-barlabels-4165273742.js"></script>
        <script src="resources/js/bootstrap-notify-376bc6eb87.js"></script>
        <script src="resources/js/bootstrap-tagsinput-855a7c7670.js"></script>
        <script src="resources/js/bootstrap-toggle-1c7a19a049.js"></script>
        <script src="resources/js/clipboard-908af414ab.js"></script>
        <script src="resources/js/imgpreview-1db063409f.full.jquery.js"></script>
        <script src="resources/js/manage_tables-e5dae00ba1.js"></script>
        <script src="resources/js/nominatim-89be77a11a.autocomplete.js"></script>
        <!-- endinject -->
    <?php else : ?>
        <!--inject:prod:css -->
        <link rel="stylesheet" href="resources/opensourcepos-c3c51fd7e7.min.css">
        <!-- endinject -->

        <!-- Tweaks to the UI for a particular theme should drop here  -->
        <?php if ($config['theme'] != 'flatly' && file_exists($_SERVER['DOCUMENT_ROOT'] . '/public/css/' . esc($config['theme']) . '.css')) { ?>
            <link rel="stylesheet" href="<?= 'css/' . esc($config['theme']) . '.css' ?>">
        <?php } ?>
        <!-- inject:prod:js -->
        <script src="resources/jquery-2c872dbe60.min.js"></script>
        <script src="resources/opensourcepos-c0154d3618.min.js"></script>
        <!-- endinject -->
    <?php endif; ?>

    <?= view('partial/header_js') ?>
    <?= view('partial/lang_lines') ?>

    <style>
        html {
            overflow: auto;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="topbar">
            <div class="container">
                <div class="navbar-left">
                    <div id="liveclock"><?= date($config['dateformat'] . ' ' . $config['timeformat']) ?></div>
                </div>

                <div class="navbar-right" style="margin: 0;">
                    <span id="notification-bell" style="position:relative;cursor:pointer;margin-right:8px;display:inline-block;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <span id="notification-badge" style="display:none;position:absolute;top:-6px;right:-8px;background:#e74c3c;color:#fff;font-size:10px;font-weight:700;border-radius:50%;min-width:16px;height:16px;text-align:center;line-height:16px;padding:0 4px;">0</span>
                    </span>
                    <div id="notification-dropdown" style="display:none;position:absolute;top:36px;right:10px;width:340px;max-height:400px;overflow-y:auto;background:#fff;border:1px solid #ddd;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:9999;font-size:13px;">
                        <div style="padding:10px 12px;border-bottom:1px solid #eee;font-weight:600;display:flex;justify-content:space-between;align-items:center;">
                            <span>Notifications</span>
                            <a href="#" id="mark-all-read" style="font-size:11px;color:#3498db;text-decoration:none;">Mark all read</a>
                        </div>
                        <div id="notification-list"></div>
                        <div id="notification-empty" style="padding:20px;text-align:center;color:#999;">No new notifications</div>
                    </div>
                    <?= anchor("home/changePassword/$user_info->person_id", "$user_info->first_name $user_info->last_name", ['class' => 'modal-dlg', 'data-btn-submit' => lang('Common.submit'), 'title' => lang('Employees.change_password')]) ?>
                    <span>&nbsp;|&nbsp;</span>
                    <?= anchor('home/logout', lang('Login.logout')) ?>
                </div>

                <div class="navbar-center" style="text-align: center;">
                    <strong><?= esc($config['company']) ?></strong>
                </div>
            </div>
        </div>

        <!-- CENTER POPUP MODAL for new job notifications -->
        <div id="job-popup-overlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:99998;" onclick="return false;"></div>
        <div id="job-popup" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.3);z-index:99999;width:420px;max-width:90vw;overflow:hidden;">
            <div id="job-popup-header" style="background:#3498db;color:#fff;padding:16px 20px;display:flex;align-items:center;gap:10px;">
                <span style="font-size:24px;">&#128196;</span>
                <div>
                    <div id="job-popup-title" style="font-weight:700;font-size:16px;">New Print Job</div>
                    <div id="job-popup-subtitle" style="font-size:12px;opacity:0.85;">A new job has been created</div>
                </div>
            </div>
            <div id="job-popup-body" style="padding:20px;">
                <div id="job-popup-message" style="font-size:14px;color:#333;margin-bottom:16px;"></div>
                <div id="job-popup-meta" style="font-size:12px;color:#666;margin-bottom:16px;"></div>
                <div style="display:flex;gap:8px;justify-content:flex-end;">
                    <button type="button" id="job-popup-assign-btn" class="btn btn-primary btn-sm" onclick="handleJobPopupAssign()">
                        <i class="glyphicon glyphicon-user"></i> Assign & Start
                    </button>
                    <button type="button" id="job-popup-view-btn" class="btn btn-success btn-sm" onclick="handleJobPopupView()">
                        <i class="glyphicon glyphicon-eye-open"></i> View Job
                    </button>
                    <button type="button" class="btn btn-default btn-sm" onclick="closeJobPopup()">Dismiss</button>
                </div>
            </div>
        </div>

        <div class="navbar navbar-default" role="navigation">
            <div class="container">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target=".navbar-collapse">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>

                    <a class="navbar-brand hidden-sm" href="<?= base_url('dashboard') ?>">BeamLink POS</a>
                </div>

                <div class="navbar-collapse collapse">
                    <ul class="nav navbar-nav navbar-right">
                        <?php foreach ($allowed_modules as $module): ?>
                            <li class="<?= $module->module_id == $request->getUri()->getSegment(1) ? 'active' : '' ?>">
                                <a href="<?= base_url($module->module_id) ?>" title="<?= lang("Module.$module->module_id") ?>" class="menu-icon">
                                    <img src="<?= base_url("images/menubar/$module->module_id.svg") ?>" style="border: none;" alt="Module Icon"><br>
                                    <?= lang('Module.' . $module->module_id) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="row">

    <script>
    (function() {
        var bell = document.getElementById('notification-bell');
        var dropdown = document.getElementById('notification-dropdown');
        var badge = document.getElementById('notification-badge');
        var list = document.getElementById('notification-list');
        var empty = document.getElementById('notification-empty');
        var markAllBtn = document.getElementById('mark-all-read');
        var isOpen = false;
        var lastTime = new Date().toISOString();
        var seenNotifIds = {};
        var popupQueue = [];
        var popupShowing = false;

        // Audio notification (Web Audio API beep)
        var audioCtx = null;
        function playNotifSound() {
            try {
                if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                // Beep 1
                var osc = audioCtx.createOscillator();
                var gain = audioCtx.createGain();
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.type = 'sine';
                osc.frequency.value = 880;
                gain.gain.value = 0.3;
                osc.start(audioCtx.currentTime);
                osc.stop(audioCtx.currentTime + 0.15);
                // Beep 2 (higher)
                var osc2 = audioCtx.createOscillator();
                var gain2 = audioCtx.createGain();
                osc2.connect(gain2);
                gain2.connect(audioCtx.destination);
                osc2.type = 'sine';
                osc2.frequency.value = 1320;
                gain2.gain.value = 0.25;
                osc2.start(audioCtx.currentTime + 0.18);
                osc2.stop(audioCtx.currentTime + 0.33);
            } catch(e) {}
        }

        bell.addEventListener('click', function(e) {
            e.stopPropagation();
            isOpen = !isOpen;
            dropdown.style.display = isOpen ? 'block' : 'none';
            if (isOpen) loadNotifications();
        });

        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && e.target !== bell) {
                dropdown.style.display = 'none';
                isOpen = false;
            }
        });

        if (markAllBtn) {
            markAllBtn.addEventListener('click', function(e) {
                e.preventDefault();
                fetch('notifications/read_all', {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}});
                var items = list.querySelectorAll('.notif-item');
                items.forEach(function(item) { item.style.background = '#fff'; });
                badge.style.display = 'none';
                badge.textContent = '0';
            });
        }

        function loadNotifications() {
            fetch('notifications', {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    renderNotifications(data.notifications, data.unread_count);
                })
                .catch(function() {
                    // Retry with credentials
                    fetch('notifications', {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
                        .then(function(r) { return r.json(); })
                        .then(function(data) { renderNotifications(data.notifications, data.unread_count); });
                });
        }

        function renderNotifications(notifications, count) {
            updateBadge(count);
            list.innerHTML = '';
            if (!notifications || notifications.length === 0) {
                empty.style.display = 'block';
                return;
            }
            empty.style.display = 'none';
            notifications.forEach(function(n) {
                var div = document.createElement('div');
                div.className = 'notif-item';
                div.style.cssText = 'padding:10px 12px;border-bottom:1px solid #f0f0f0;cursor:pointer;' + (n.is_read == 0 ? 'background:#eef6ff;' : '');
                var icon = n.type === 'job_new' ? '&#128196;' : n.type === 'job_status' ? '&#128260;' : n.type === 'job_assigned' ? '&#128101;' : '&#128276;';
                div.innerHTML = '<div style="display:flex;gap:8px;align-items:flex-start;">' +
                    '<span style="font-size:16px;">' + icon + '</span>' +
                    '<div style="flex:1;"><strong style="font-size:12px;">' + escHtml(n.title) + '</strong>' +
                    (n.message ? '<div style="font-size:11px;color:#666;margin-top:2px;">' + escHtml(n.message) + '</div>' : '') +
                    '<div style="font-size:10px;color:#999;margin-top:2px;">' + timeAgo(n.created_at) + '</div></div></div>';
                if (n.link) {
                    div.addEventListener('click', function() { window.location.href = n.link; });
                } else {
                    div.addEventListener('click', function() {
                        fetch('notifications/read/' + n.id, {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}});
                        div.style.background = '#fff';
                        updateBadge(Math.max(0, (parseInt(badge.textContent) || 1) - 1));
                    });
                }
                list.appendChild(div);
            });
        }

        function escHtml(s) {
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(s || ''));
            return d.innerHTML;
        }

        function timeAgo(dt) {
            var diff = (Date.now() - new Date(dt).getTime()) / 1000;
            if (diff < 60) return 'just now';
            if (diff < 3600) return Math.floor(diff/60) + 'm ago';
            if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
            return Math.floor(diff/86400) + 'd ago';
        }

        function showToast(title, message, link) {
            var toast = document.createElement('div');
            toast.style.cssText = 'position:fixed;top:60px;right:20px;z-index:99997;background:#fff;border-left:4px solid #3498db;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,0.2);padding:14px 18px;max-width:360px;font-size:13px;animation:slideIn 0.3s ease;cursor:pointer;';
            toast.innerHTML = '<div style="font-weight:600;margin-bottom:3px;">' + escHtml(title) + '</div>' +
                '<div style="color:#555;">' + escHtml(message) + '</div>';
            toast.addEventListener('click', function() {
                if (link) window.location.href = link;
                toast.remove();
            });
            document.body.appendChild(toast);
            setTimeout(function() { if (toast.parentNode) toast.remove(); }, 8000);
        }

        function showJobPopup(n) {
            popupQueue.push(n);
            if (!popupShowing) processPopupQueue();
        }

        function processPopupQueue() {
            if (popupQueue.length === 0) { popupShowing = false; return; }
            popupShowing = true;
            var n = popupQueue.shift();
            var overlay = document.getElementById('job-popup-overlay');
            var popup = document.getElementById('job-popup');
            var titleEl = document.getElementById('job-popup-title');
            var subtitleEl = document.getElementById('job-popup-subtitle');
            var msgEl = document.getElementById('job-popup-message');
            var metaEl = document.getElementById('job-popup-meta');
            var viewBtn = document.getElementById('job-popup-view-btn');
            var assignBtn = document.getElementById('job-popup-assign-btn');

            titleEl.textContent = n.title || 'New Notification';
            subtitleEl.textContent = n.type === 'job_new' ? 'A new print job needs attention' : n.type === 'job_assigned' ? 'Job has been assigned' : 'Status has changed';
            msgEl.textContent = n.message || '';
            metaEl.innerHTML = '<span style="color:#999;">' + timeAgo(n.created_at) + '</span>';

            if (n.link) {
                viewBtn.style.display = 'inline-block';
                viewBtn.onclick = function() { window.location.href = n.link; };
            } else {
                viewBtn.style.display = 'none';
            }

            // Show assign button only for new unassigned jobs
            if (n.type === 'job_new' && n.link) {
                assignBtn.style.display = 'inline-block';
                assignBtn.onclick = function() { window.location.href = n.link; };
            } else {
                assignBtn.style.display = 'none';
            }

            overlay.style.display = 'block';
            popup.style.display = 'block';
            popup.style.animation = 'popupBounceIn 0.4s ease';
        }

        window.closeJobPopup = function() {
            document.getElementById('job-popup-overlay').style.display = 'none';
            document.getElementById('job-popup').style.display = 'none';
            setTimeout(processPopupQueue, 200);
        };

        window.handleJobPopupView = function() {
            var viewBtn = document.getElementById('job-popup-view-btn');
            if (viewBtn.onclick) viewBtn.onclick();
        };

        window.handleJobPopupAssign = function() {
            var assignBtn = document.getElementById('job-popup-assign-btn');
            if (assignBtn.onclick) assignBtn.onclick();
        };

        function updateBadge(count) {
            if (count > 0) {
                badge.style.display = 'inline-block';
                badge.textContent = count > 99 ? '99+' : count;
            } else {
                badge.style.display = 'none';
            }
        }

        var lastUnreadCount = 0;

        function pollNotifications() {
            fetch('notifications/unread_count', {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var count = data.count || 0;
                    updateBadge(count);
                    if (count > lastUnreadCount && lastUnreadCount > 0) {
                        // New notifications arrived — fetch details
                        fetch('notifications', {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
                            .then(function(r) { return r.json(); })
                            .then(function(nd) {
                                (nd.notifications || []).forEach(function(n) {
                                    if (!seenNotifIds[n.id] && n.is_read == 0) {
                                        seenNotifIds[n.id] = true;
                                        playNotifSound();
                                        showToast(n.title, n.message, n.link);
                                        if (n.type === 'job_new' || n.type === 'job_assigned') {
                                            showJobPopup(n);
                                        }
                                    }
                                });
                                if (isOpen) loadNotifications();
                            });
                    }
                    lastUnreadCount = count;
                })
                .catch(function() {});
        }

        // Initial load + poll every 5 seconds
        pollNotifications();
        setInterval(pollNotifications, 5000);
    })();
    </script>
    <style>
    @keyframes slideIn { from { transform: translateX(100%); opacity:0; } to { transform: translateX(0); opacity:1; } }
    @keyframes popupBounceIn {
        0% { transform: translate(-50%,-50%) scale(0.7); opacity:0; }
        60% { transform: translate(-50%,-50%) scale(1.05); opacity:1; }
        100% { transform: translate(-50%,-50%) scale(1); opacity:1; }
    }
    .notif-item:hover { background: #f5f5f5 !important; }
    #job-popup-overlay { transition: opacity 0.2s; }
    #job-popup { transition: transform 0.2s; }
    </style>
