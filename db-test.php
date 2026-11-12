<?php
/**
 * db-test.php - Database Diagnostics & Interactive Web Configurator
 * Open http://your-domain/db-test.php to test and configure Hostinger MySQL connection
 */
require_once __DIR__ . '/config.php';

$saveMsg = '';
$saveErr = '';

// Handle Interactive Credentials Save & Test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_credentials') {
    $postDriver = strtolower(trim($_POST['db_driver'] ?? 'mysql'));
    $postHost   = trim($_POST['db_host'] ?? 'localhost');
    $postPort   = trim($_POST['db_port'] ?? '3306');
    $postName   = trim($_POST['db_name'] ?? '');
    $postUser   = trim($_POST['db_user'] ?? '');
    $postPass   = trim($_POST['db_pass'] ?? '');

    try {
        if ($postDriver === 'mysql') {
            if (empty($postName) || empty($postUser)) {
                throw new Exception("Database Name and Database Username are required for MySQL.");
            }
            $testDsn = "mysql:host={$postHost};port={$postPort};dbname={$postName};charset=utf8mb4";
            $testPdo = new PDO($testDsn, $postUser, $postPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            $testPdo->query("SELECT 1");
        } else {
            $testDsn = "sqlite:" . DATA_DIR . '/inventory.db';
            $testPdo = new PDO($testDsn);
            $testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $testPdo->query("SELECT 1");
        }

        // Connection tested OK! Save to .env
        $envLines = [
            "# DSC Office Shifting Inventory - Database Configuration",
            "# Updated via db-test.php on " . date('Y-m-d H:i:s'),
            "DB_DRIVER={$postDriver}",
            "DB_HOST={$postHost}",
            "DB_PORT={$postPort}",
            "DB_NAME={$postName}",
            "DB_USER={$postUser}",
            "DB_PASS={$postPass}",
            "APP_ENV=production",
            "APP_DEBUG=true"
        ];

        file_put_contents(BASE_PATH . '/.env', implode("\n", $envLines) . "\n");
        $saveMsg = "Database connection verified and saved to .env successfully!";

        // Reload configuration
        Env::load(BASE_PATH . '/.env');
        header("Location: db-test.php?saved=1");
        exit;

    } catch (Throwable $e) {
        $saveErr = "Connection Test Failed: " . $e->getMessage();
    }
}

$envFileExists = file_exists(BASE_PATH . '/.env');

$pdoError = null;
$pdoSuccess = false;
$tablesFound = [];
$itemCount = 0;
$itemsSample = [];

try {
    $db = get_db();
    $pdoSuccess = true;

    // Check tables
    if (DB_DRIVER === 'mysql') {
        $stmt = $db->query("SHOW TABLES");
        $tablesFound = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tablesFound = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if (in_array('items', $tablesFound)) {
        $itemCount = $db->query("SELECT COUNT(*) FROM items")->fetchColumn();
        $itemsSample = $db->query("SELECT id, brand, model, barcode_id, serial_number, status FROM items ORDER BY id DESC LIMIT 5")->fetchAll();
    }
} catch (Throwable $e) {
    $pdoError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Diagnostics & Setup - ShiftTrack DSC</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen py-10 px-4 font-sans antialiased">
    <div class="max-w-2xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-800 pb-5">
            <div>
                <h1 class="text-2xl font-extrabold text-white flex items-center">
                    <i class="fa-solid fa-database text-sky-400 mr-3"></i>
                    Database Diagnostics & Setup
                </h1>
                <p class="text-xs text-slate-400 mt-1">ShiftTrack DSC Relocation Inventory System</p>
            </div>
            <a href="index.php" class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg border border-slate-700">
                &larr; Go to Dashboard
            </a>
        </div>

        <?php if (isset($_GET['saved'])): ?>
            <div class="bg-emerald-950/60 border border-emerald-500 text-emerald-300 p-4 rounded-xl text-sm flex items-center space-x-2">
                <i class="fa-solid fa-circle-check text-lg"></i>
                <span>Database settings tested and saved successfully!</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($saveErr)): ?>
            <div class="bg-red-950/60 border border-red-500 text-red-300 p-4 rounded-xl text-xs font-mono break-all">
                <?= htmlspecialchars($saveErr) ?>
            </div>
        <?php endif; ?>

        <!-- Connection Status Card -->
        <?php if ($pdoSuccess): ?>
            <div class="bg-emerald-950/40 border border-emerald-500/40 rounded-2xl p-6 shadow-xl">
                <div class="flex items-center space-x-3 mb-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-white">Database Connected Successfully!</h2>
                        <span class="text-xs text-emerald-300 uppercase tracking-wider font-semibold">
                            Active Driver: <?= strtoupper(DB_DRIVER) ?> (<?= htmlspecialchars(DB_NAME ?: 'sqlite') ?>)
                        </span>
                    </div>
                </div>
                <p class="text-xs text-slate-300">
                    The application is actively reading and writing to your <?= (DB_DRIVER === 'mysql') ? 'Hostinger MySQL database' : 'SQLite storage' ?>.
                </p>
            </div>
        <?php else: ?>
            <div class="bg-red-950/40 border border-red-500/40 rounded-2xl p-6 shadow-xl">
                <div class="flex items-center space-x-3 mb-3">
                    <div class="w-10 h-10 rounded-xl bg-red-500/20 text-red-400 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-white">Database Connection Failed</h2>
                        <span class="text-xs text-red-300 uppercase tracking-wider font-semibold">
                            Driver: <?= strtoupper(DB_DRIVER) ?>
                        </span>
                    </div>
                </div>
                <div class="bg-slate-950/80 rounded-xl p-3 font-mono text-xs text-red-300 border border-red-900/60 break-all mb-3">
                    <?= htmlspecialchars($pdoError) ?>
                </div>
                <p class="text-xs text-slate-300">
                    Enter your correct Hostinger MySQL credentials in the form below and click "Test & Save Connection".
                </p>
            </div>
        <?php endif; ?>

        <!-- Active Configuration View -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-lg">
            <div class="px-5 py-3.5 bg-slate-850 border-b border-slate-800 flex items-center justify-between">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">
                    <i class="fa-solid fa-sliders mr-2 text-sky-400"></i> Active Connection Settings
                </h3>
                <span class="text-[11px] <?= $envFileExists ? 'text-emerald-400' : 'text-amber-400' ?>">
                    <i class="fa-solid fa-file-code mr-1"></i> .env: <?= $envFileExists ? 'Found' : 'Missing' ?>
                </span>
            </div>

            <div class="divide-y divide-slate-800 text-xs font-mono">
                <div class="flex justify-between p-3 px-5"><span class="text-slate-400 font-sans">DB_DRIVER:</span><span class="text-sky-400 font-bold"><?= htmlspecialchars(DB_DRIVER) ?></span></div>
                <div class="flex justify-between p-3 px-5"><span class="text-slate-400 font-sans">DB_HOST:</span><span class="text-slate-200"><?= htmlspecialchars(DB_HOST) ?></span></div>
                <div class="flex justify-between p-3 px-5"><span class="text-slate-400 font-sans">DB_PORT:</span><span class="text-slate-200"><?= htmlspecialchars(DB_PORT) ?></span></div>
                <div class="flex justify-between p-3 px-5"><span class="text-slate-400 font-sans">DB_NAME:</span><span class="text-emerald-400 font-bold"><?= htmlspecialchars(DB_NAME ?: '(empty)') ?></span></div>
                <div class="flex justify-between p-3 px-5"><span class="text-slate-400 font-sans">DB_USER:</span><span class="text-amber-400 font-bold"><?= htmlspecialchars(DB_USER ?: '(empty)') ?></span></div>
                <div class="flex justify-between p-3 px-5"><span class="text-slate-400 font-sans">DB_PASS:</span><span class="text-slate-500 font-bold"><?= empty(DB_PASS) ? '(empty)' : '•••••••• (' . strlen(DB_PASS) . ' chars)' ?></span></div>
            </div>
        </div>

        <!-- Interactive Setup Form (Allows User to change DB credentials right from browser) -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-lg p-5">
            <h3 class="text-sm font-bold uppercase tracking-wider text-white mb-2 flex items-center">
                <i class="fa-solid fa-wrench mr-2 text-sky-400"></i> Configure / Update Database Connection
            </h3>
            <p class="text-xs text-slate-400 mb-4">
                Enter your Hostinger MySQL details below. The system will test the connection immediately and save it to <code>.env</code> upon success.
            </p>

            <form action="db-test.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="save_credentials">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Database Driver</label>
                        <select name="db_driver" class="w-full px-3 py-2 text-xs bg-slate-950 border border-slate-700 rounded-lg text-white focus:ring-2 focus:ring-sky-500 focus:outline-none">
                            <option value="mysql" <?= (DB_DRIVER === 'mysql') ? 'selected' : '' ?>>MySQL / MariaDB (Hostinger)</option>
                            <option value="sqlite" <?= (DB_DRIVER === 'sqlite') ? 'selected' : '' ?>>SQLite (Local storage)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Host (usually localhost)</label>
                        <input type="text" name="db_host" value="<?= htmlspecialchars(DB_HOST ?: 'localhost') ?>" class="w-full px-3 py-2 text-xs bg-slate-950 border border-slate-700 rounded-lg text-white font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Port</label>
                        <input type="text" name="db_port" value="<?= htmlspecialchars(DB_PORT ?: '3306') ?>" class="w-full px-3 py-2 text-xs bg-slate-950 border border-slate-700 rounded-lg text-white font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Database Name (DB_NAME)</label>
                        <input type="text" name="db_name" value="<?= htmlspecialchars(DB_NAME) ?>" placeholder="e.g. u123456789_shifting_dsc" class="w-full px-3 py-2 text-xs bg-slate-950 border border-slate-700 rounded-lg text-emerald-400 font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Database User (DB_USER)</label>
                        <input type="text" name="db_user" value="<?= htmlspecialchars(DB_USER) ?>" placeholder="e.g. u123456789_dsc_user" class="w-full px-3 py-2 text-xs bg-slate-950 border border-slate-700 rounded-lg text-amber-400 font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Database Password (DB_PASS)</label>
                        <input type="password" name="db_pass" value="<?= htmlspecialchars(DB_PASS) ?>" placeholder="Your database password" class="w-full px-3 py-2 text-xs bg-slate-950 border border-slate-700 rounded-lg text-white font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none">
                    </div>
                </div>

                <div class="pt-2 flex justify-end">
                    <button type="submit" class="px-5 py-2.5 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold rounded-xl shadow-md transition-all">
                        <i class="fa-solid fa-plug mr-1.5"></i> Test & Save Database Credentials
                    </button>
                </div>
            </form>
        </div>

        <!-- Tables & Data Status -->
        <?php if ($pdoSuccess): ?>
            <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-lg p-5">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">
                    <i class="fa-solid fa-table-list mr-2 text-sky-400"></i> Tables in Database (<?= count($tablesFound) ?>)
                </h3>

                <div class="flex flex-wrap gap-2 mb-4">
                    <?php foreach (['items', 'item_images', 'activity_logs'] as $expectedTable): ?>
                        <?php $found = in_array($expectedTable, $tablesFound); ?>
                        <span class="px-3 py-1 rounded-lg text-xs font-semibold flex items-center space-x-1.5 <?= $found ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-red-950 text-red-400 border border-red-800' ?>">
                            <i class="fa-solid <?= $found ? 'fa-check' : 'fa-xmark' ?>"></i>
                            <span><?= $expectedTable ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>

                <div class="border-t border-slate-800 pt-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs text-slate-400 font-semibold">Total Items in <code>items</code> table:</span>
                        <span class="text-sm font-bold text-sky-400"><?= $itemCount ?> item<?= $itemCount !== 1 ? 's' : '' ?></span>
                    </div>

                    <?php if (!empty($itemsSample)): ?>
                        <div class="bg-slate-950 rounded-xl border border-slate-800 divide-y divide-slate-850 overflow-hidden text-xs">
                            <?php foreach ($itemsSample as $it): ?>
                                <div class="p-2.5 px-3 flex items-center justify-between">
                                    <span class="font-semibold text-slate-200"><?= htmlspecialchars($it['brand']) ?> <?= htmlspecialchars($it['model']) ?></span>
                                    <div class="flex items-center space-x-2">
                                        <span class="font-mono text-[11px] text-emerald-400 font-bold"><?= htmlspecialchars($it['barcode_id'] ?: $it['serial_number']) ?></span>
                                        <?php if (!empty($it['serial_number'])): ?>
                                            <span class="font-mono text-[11px] text-slate-500">S/N: <?= htmlspecialchars($it['serial_number']) ?></span>
                                        <?php endif; ?>
                                        <span class="text-[11px] px-2 py-0.5 rounded <?= ($it['status'] === 'reached') ? 'bg-emerald-950 text-emerald-400' : 'bg-amber-950 text-amber-400' ?>">
                                            <?= htmlspecialchars($it['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>
