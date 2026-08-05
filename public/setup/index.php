<?php
/**
 * BeamLink POS - Desktop Setup Wizard
 * First-run configuration for native installers
 */

$INSTALL_DIR = dirname(dirname(dirname(__DIR__)));

$APP_DIR = $INSTALL_DIR . '/app';
$DATA_DIR = getenv('OSPOS_DATA_DIR') ?: $INSTALL_DIR . '/data';
$STOCK_FILE = $APP_DIR . '/stock-data/STOCK.XLS';
$LOG_DIR = $DATA_DIR . '/logs';

// CodeIgniter .env needs to be in the envDirectory
$ENV_SOURCE = $INSTALL_DIR . '/.env.example';
$ENV_TARGET = getenv('OSPOS_DATA_DIR') ? getenv('OSPOS_DATA_DIR') . '/.env' : $APP_DIR . '/.env';

// Find PHP binary
$phpBinary = null;
if (PHP_OS_FAMILY === 'Windows') {
    $candidates = [
        $INSTALL_DIR . '/php/php.exe',
        $INSTALL_DIR . '/php/php',
    ];
} else {
    $candidates = [
        $INSTALL_DIR . '/php/php',
        '/opt/homebrew/opt/php@8.2/bin/php',
        '/usr/bin/php',
        '/usr/local/bin/php',
    ];
}
foreach ($candidates as $c) {
    if (file_exists($c)) {
        $phpBinary = $c;
        break;
    }
}

// Find MariaDB client
$mariadbBinary = null;
if (PHP_OS_FAMILY === 'Windows') {
    $candidates = [
        $INSTALL_DIR . '/mariadb/bin/mariadb.exe',
    ];
} else {
    $candidates = [
        $INSTALL_DIR . '/mariadb/bin/mariadb',
        $INSTALL_DIR . '/mariadb/bin/mysql',
    ];
}
foreach ($candidates as $c) {
    if (file_exists($c)) {
        $mariadbBinary = $c;
        break;
    }
}

// Already set up?
if (file_exists($DATA_DIR . '/.installed')) {
    header('Location: /');
    exit;
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';
$log = [];

// Helper: run shell command
function runCmd($cmd, &$output = null, &$returnCode = null) {
    exec($cmd . ' 2>&1', $output, $returnCode);
    return $returnCode === 0;
}

// Auto-process Step 2 (Database Connection)
if ($step === 2 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $dbPass = 'pointofsale';
    
    try {
        $dsn = "mysql:host=127.0.0.1;port=3307";
        $pdo = new PDO($dsn, 'admin', $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `ospos` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $pdo->exec("USE `ospos`");
        
        session_start();
        $_SESSION['setup'] = [
            'db_host' => '127.0.0.1',
            'db_port' => 3307,
            'db_user' => 'admin',
            'db_pass' => $dbPass,
            'db_name' => 'ospos',
        ];
        
        $step = 3;
    } catch (PDOException $e) {
        $error = "Auto-Database connection failed: " . $e->getMessage();
        // Fall through to manual step 2 if auto fails
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = (int)($_POST['step'] ?? 1);

    switch ($step) {
        case 2:
            // Test database connection
            $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
            $dbPort = (int)($_POST['db_port'] ?? 3307);
            $dbUser = trim($_POST['db_user'] ?? 'admin');
            $dbPass = trim($_POST['db_pass'] ?? 'pointofsale');
            $dbName = trim($_POST['db_name'] ?? 'ospos');

            try {
                $dsn = "mysql:host={$dbHost};port={$dbPort}";
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);

                // Create database if not exists
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
                $pdo->exec("USE `{$dbName}`");

                // Save DB config for next step
                session_start();
                $_SESSION['setup'] = [
                    'db_host' => $dbHost,
                    'db_port' => $dbPort,
                    'db_user' => $dbUser,
                    'db_pass' => $dbPass,
                    'db_name' => $dbName,
                ];

                $step = 3;
            } catch (PDOException $e) {
                $error = "Database connection failed: " . $e->getMessage();
                $step = 2;
            }
            break;

        case 3:
            // Admin account + company details
            session_start();
            $setup = $_SESSION['setup'] ?? null;
            if (!$setup) {
                $error = 'Session expired. Please go back.';
                $step = 2;
                break;
            }

            $adminUser = trim($_POST['admin_user'] ?? 'admin');
            $adminPass = $_POST['admin_pass'] ?? '';
            $adminPassConfirm = $_POST['admin_pass_confirm'] ?? '';
            $companyName = trim($_POST['company_name'] ?? 'BeamLink');
            $companyAddress = trim($_POST['company_address'] ?? '');
            $companyPhone = trim($_POST['company_phone'] ?? '');
            $companyEmail = trim($_POST['company_email'] ?? '');

            if ($adminPass !== $adminPassConfirm) {
                $error = 'Passwords do not match.';
                $step = 3;
                break;
            }
            if (strlen($adminPass) < 6) {
                $error = 'Password must be at least 6 characters.';
                $step = 3;
                break;
            }
            if (empty($adminUser) || empty($companyName)) {
                $error = 'Username and company name are required.';
                $step = 3;
                break;
            }

            $encryptionKey = bin2hex(random_bytes(32));

            // Write .env file
            $envContent = file_get_contents($ENV_SOURCE);
            $envContent = str_replace('pointofsale', $setup['db_pass'], $envContent);
            $envContent = str_replace(
                "database.default.password = 'pointofsale'",
                "database.default.password = '" . addslashes($setup['db_pass']) . "'",
                $envContent
            );
            $envContent = str_replace(
                "database.default.hostname = '127.0.0.1'",
                "database.default.hostname = '" . addslashes($setup['db_host']) . "'",
                $envContent
            );
            $envContent = str_replace(
                "database.default.database = 'ospos'",
                "database.default.database = '" . addslashes($setup['db_name']) . "'",
                $envContent
            );
            $envContent = str_replace(
                "database.default.username = 'admin'",
                "database.default.username = '" . addslashes($setup['db_user']) . "'",
                $envContent
            );
            $envContent = str_replace(
                "encryption.key = 'e29053f860954962cadb2addb51f474c99fe391845460fdd31bebca265447ae2'",
                "encryption.key = '" . $encryptionKey . "'",
                $envContent
            );

            // Webhook secret is generated and appended if not present
            $webhookSecret = bin2hex(random_bytes(16));
            if (strpos($envContent, 'OSPOS_WEBHOOK_SECRET') !== false) {
                $envContent = preg_replace('/OSPOS_WEBHOOK_SECRET\s*=\s*.*/', "OSPOS_WEBHOOK_SECRET = '" . $webhookSecret . "'", $envContent);
            } else {
                $envContent .= "\nOSPOS_WEBHOOK_SECRET = '" . $webhookSecret . "'\n";
            }
            if (strpos($envContent, 'appTimezone') === false) {
                $envContent .= "appTimezone = 'Africa/Harare'\n";
            }

            file_put_contents($ENV_TARGET, $envContent);

            // Ensure data directories exist
            @mkdir($DATA_DIR, 0755, true);
            @mkdir($LOG_DIR, 0755, true);
            @mkdir($DATA_DIR . '/sessions', 0755, true);

            // Run database migrations
            $migrationLog = [];
            if ($phpBinary) {
                chdir($APP_DIR);
                $cmd = '"' . $phpBinary . '" spark migrate --all 2>&1';
                exec($cmd, $migrationLog, $migrationReturn);
                $log['migrations'] = $migrationReturn === 0 ? 'success' : 'failed';
                $log['migrations_output'] = implode("\n", array_slice($migrationLog, -20));
            } else {
                $log['migrations'] = 'php not found';
            }

            // Seed stock data
            if ($phpBinary && file_exists($STOCK_FILE)) {
                $seedLog = [];
                chdir($APP_DIR);
                $cmd = '"' . $phpBinary . '" spark stock:import 2>&1';
                exec($cmd, $seedLog, $seedReturn);
                $log['stock'] = $seedReturn === 0 ? 'success' : 'failed';
                $log['stock_output'] = implode("\n", array_slice($seedLog, -10));
            } else {
                $log['stock'] = $phpBinary ? 'stock file not found' : 'php not found';
            }

            // Create admin user
            try {
                $pdo = new PDO(
                    "mysql:host={$setup['db_host']};port={$setup['db_port']};dbname={$setup['db_name']}",
                    $setup['db_user'],
                    $setup['db_pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                // Check if employees table exists
                $stmt = $pdo->query("SHOW TABLES LIKE 'ospos_employees'");
                if ($stmt->fetch()) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ospos_employees WHERE deleted = 0");
                    $stmt->execute();
                    $empCount = $stmt->fetchColumn();

                    if ($empCount == 0) {
                        // Insert into ospos_people first
                        $stmt = $pdo->prepare(
                            "INSERT INTO ospos_people (first_name, last_name, phone_number, email, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())"
                        );
                        $stmt->execute([$companyName, 'Admin', $companyPhone, $companyEmail]);
                        $personId = $pdo->lastInsertId();

                        // Hash password
                        $passwordHash = password_hash($adminPass, PASSWORD_DEFAULT);

                        // Insert employee
                        $stmt = $pdo->prepare(
                            "INSERT INTO ospos_employees (person_id, username, password, admin, deleted) VALUES (?, ?, ?, 1, 0)"
                        );
                        $stmt->execute([$personId, $adminUser, $passwordHash]);

                        $log['admin'] = 'created';
                    } else {
                        $log['admin'] = 'already exists';
                    }
                }

                // Update company name in app_config
                $stmt = $pdo->query("SHOW TABLES LIKE 'ospos_app_config'");
                if ($stmt->fetch()) {
                    $pdo->exec("UPDATE ospos_app_config SET value = " . $pdo->quote($companyName) . " WHERE `key` = 'company'");
                }
            } catch (Exception $e) {
                $log['admin_error'] = $e->getMessage();
            }

            // Copy stock file to writable location for future reference
            if (file_exists($STOCK_FILE)) {
                @mkdir($DATA_DIR . '/stock', 0755, true);
                @copy($STOCK_FILE, $DATA_DIR . '/stock/STOCK.XLS');
            }

            // Mark as installed
            file_put_contents($DATA_DIR . '/.installed', date('c'));

            // Cleanup session
            session_destroy();

            $step = 4;
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BeamLink POS - Setup</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container { width: 100%; max-width: 560px; padding: 20px; }
        .card {
            background: #1e293b;
            border-radius: 16px;
            padding: 40px;
            border: 1px solid #334155;
        }
        .logo { text-align: center; margin-bottom: 32px; }
        .logo h1 { font-size: 28px; font-weight: 700; color: #60a5fa; margin-bottom: 4px; }
        .logo p { color: #94a3b8; font-size: 14px; }
        .steps { display: flex; justify-content: center; gap: 8px; margin-bottom: 32px; }
        .step-dot { width: 10px; height: 10px; border-radius: 50%; background: #334155; }
        .step-dot.active { background: #60a5fa; }
        .step-dot.done { background: #34d399; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 500; color: #94a3b8; margin-bottom: 6px; }
        .form-group input {
            width: 100%; padding: 10px 14px; background: #0f172a;
            border: 1px solid #334155; border-radius: 8px; color: #e2e8f0; font-size: 14px;
            outline: none; transition: border-color 0.2s;
        }
        .form-group input:focus { border-color: #60a5fa; }
        .form-group input::placeholder { color: #475569; }
        .form-row { display: flex; gap: 12px; }
        .form-row .form-group { flex: 1; }
        .btn {
            width: 100%; padding: 12px; border: none; border-radius: 8px;
            font-size: 15px; font-weight: 600; cursor: pointer; transition: background 0.2s;
        }
        .btn-primary { background: #2563eb; color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-success { background: #059669; color: white; }
        .btn-success:hover { background: #047857; }
        .error {
            background: #450a0a; border: 1px solid #7f1d1d; color: #fca5a5;
            padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 20px;
        }
        .success-check { text-align: center; font-size: 64px; margin-bottom: 16px; }
        .success-text { text-align: center; margin-bottom: 24px; }
        .success-text h2 { color: #34d399; font-size: 22px; margin-bottom: 8px; }
        .success-text p { color: #94a3b8; font-size: 14px; }
        .credential-box {
            background: #0f172a; border: 1px solid #334155;
            border-radius: 8px; padding: 16px; margin-bottom: 24px;
        }
        .credential-box p { font-size: 13px; color: #94a3b8; margin-bottom: 8px; }
        .credential-box code { color: #60a5fa; font-size: 14px; }
        .welcome-icon { font-size: 48px; text-align: center; margin-bottom: 16px; }
        .welcome-text { text-align: center; color: #94a3b8; font-size: 14px; line-height: 1.6; margin-bottom: 24px; }
        .welcome-text strong { color: #e2e8f0; }
        .footer { text-align: center; margin-top: 24px; color: #475569; font-size: 12px; }
        .log-box {
            background: #0f172a; border: 1px solid #334155; border-radius: 8px;
            padding: 12px; margin-top: 12px; font-family: monospace; font-size: 11px;
            color: #94a3b8; max-height: 120px; overflow-y: auto; white-space: pre-wrap;
        }
        .log-success { color: #34d399; }
        .log-fail { color: #f87171; }
        .info-box {
            background: #1e3a5f; border: 1px solid #2563eb;
            border-radius: 8px; padding: 12px; margin-bottom: 20px; font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">
                <h1>BeamLink POS</h1>
                <p>Desktop Setup Wizard</p>
            </div>

            <div class="steps">
                <div class="step-dot <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>"></div>
                <div class="step-dot <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>"></div>
                <div class="step-dot <?= $step >= 3 ? ($step > 3 ? 'done' : 'active') : '' ?>"></div>
                <div class="step-dot <?= $step >= 4 ? 'done' : '' ?>"></div>
            </div>

            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <div class="welcome-icon">&#128187;</div>
                <div class="welcome-text">
                    <strong>Welcome to BeamLink POS Desktop!</strong><br><br>
                    This wizard will set up your point-of-sale system.<br><br>
                    <strong>What's included:</strong><br>
                    &#10003; MariaDB database (port 3307)<br>
                    &#10003; PHP application server<br>
                    &#10003; WhatsApp integration for customer notifications<br>
                    &#10003; 922+ stock items pre-loaded<br>
                    &#10003; BeamLink ERP sync
                </div>
                <a href="?step=2" class="btn btn-primary" style="text-align: center; text-decoration: none;">Begin Setup</a>

            <?php elseif ($step === 2): ?>
                <h3 style="margin-bottom: 8px; color: #e2e8f0;">Database Configuration</h3>
                <p style="font-size: 13px; color: #94a3b8; margin-bottom: 20px;">
                    MariaDB is pre-configured on port 3307. Default credentials are pre-filled.
                </p>
                <form method="POST">
                    <input type="hidden" name="step" value="2">
                    <div class="form-row">
                        <div class="form-group">
                            <label>DB Host</label>
                            <input type="text" name="db_host" value="127.0.0.1" required>
                        </div>
                        <div class="form-group">
                            <label>DB Port</label>
                            <input type="number" name="db_port" value="3307" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Database Name</label>
                        <input type="text" name="db_name" value="ospos" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>DB Username</label>
                            <input type="text" name="db_user" value="admin" required>
                        </div>
                        <div class="form-group">
                            <label>DB Password</label>
                            <input type="password" name="db_pass" value="pointofsale" placeholder="Enter DB password">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Test Connection & Continue</button>
                </form>

            <?php elseif ($step === 3): ?>
                <h3 style="margin-bottom: 8px; color: #e2e8f0;">Account & Company</h3>
                <p style="font-size: 13px; color: #94a3b8; margin-bottom: 20px;">
                    Create your admin account and set company details.
                </p>
                <form method="POST">
                    <input type="hidden" name="step" value="3">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Admin Username</label>
                            <input type="text" name="admin_user" value="admin" required>
                        </div>
                        <div class="form-group">
                            <label>Admin Password</label>
                            <input type="password" name="admin_pass" required placeholder="Min 6 characters">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="admin_pass_confirm" required>
                    </div>
                    <div class="form-group">
                        <label>Company Name</label>
                        <input type="text" name="company_name" value="BeamLink" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Company Phone</label>
                            <input type="text" name="company_phone" placeholder="+263 ...">
                        </div>
                        <div class="form-group">
                            <label>Company Email</label>
                            <input type="email" name="company_email" placeholder="info@company.co.zw">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Company Address</label>
                        <input type="text" name="company_address" placeholder="Physical address">
                    </div>
                    <button type="submit" class="btn btn-success">Complete Setup & Seed Data</button>
                </form>

            <?php elseif ($step === 4): ?>
                <div class="success-check">&#10004;</div>
                <div class="success-text">
                    <h2>Setup Complete!</h2>
                    <p>BeamLink POS is ready to use.</p>
                </div>

                <div class="credential-box">
                    <p>Login: <code>http://localhost:8080</code></p>
                    <p>Username: <code><?= htmlspecialchars($adminUser ?? 'admin') ?></code></p>
                </div>

                <?php if (!empty($log)): ?>
                    <div style="margin-bottom: 20px;">
                        <p style="font-size: 13px; font-weight: 600; color: #94a3b8; margin-bottom: 8px;">Setup Log:</p>
                        <?php if (isset($log['migrations'])): ?>
                            <p style="font-size: 12px; color: #94a3b8;">
                                Migrations:
                                <span class="<?= $log['migrations'] === 'success' ? 'log-success' : 'log-fail' ?>">
                                    <?= $log['migrations'] === 'success' ? '&#10003; Complete' : '&#10007; Failed' ?>
                                </span>
                            </p>
                        <?php endif; ?>
                        <?php if (isset($log['stock'])): ?>
                            <p style="font-size: 12px; color: #94a3b8;">
                                Stock Import:
                                <span class="<?= $log['stock'] === 'success' ? 'log-success' : 'log-fail' ?>">
                                    <?= $log['stock'] === 'success' ? '&#10003; Seeded' : '&#10007; ' . htmlspecialchars($log['stock']) ?>
                                </span>
                            </p>
                        <?php endif; ?>
                        <?php if (isset($log['admin'])): ?>
                            <p style="font-size: 12px; color: #94a3b8;">
                                Admin User: <span class="log-success"><?= htmlspecialchars($log['admin']) ?></span>
                            </p>
                        <?php endif; ?>
                        <?php if (isset($log['admin_error'])): ?>
                            <p style="font-size: 12px; color: #f87171;">
                                Admin Error: <?= htmlspecialchars($log['admin_error']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <a href="/" class="btn btn-primary" style="text-align: center; text-decoration: none; display: block;">
                    Open BeamLink POS
                </a>

            <?php endif; ?>
        </div>
        <div class="footer">
            Second Spark Intelligence &copy; 2026 &middot; secondspark.co.zw &middot; BeamLink POS v3.4.2
        </div>
    </div>
</body>
</html>
