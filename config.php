<?php
/**
 * DSC Shifting Inventory Management System
 * Core Configuration & Database Connection
 * Professional Hostinger MySQL + .env Support
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Base Paths
define('BASE_PATH', __DIR__);
define('UPLOAD_DIR_OLD', BASE_PATH . '/uploads/old_office');
define('UPLOAD_DIR_REACHED', BASE_PATH . '/uploads/reached_office');
define('DATA_DIR', BASE_PATH . '/data');

/**
 * Bulletproof Environment Parser (Does NOT rely on putenv/getenv which may be disabled on Hostinger)
 */
class Env {
    private static $variables = [];
    private static $isLoaded = false;

    public static function load($filePath) {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return;
        }

        $lines = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || substr($line, 0, 1) === '#') {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);

                // Strip outer quotes if present
                $len = strlen($val);
                if ($len >= 2) {
                    $first = substr($val, 0, 1);
                    $last = substr($val, -1);
                    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                        $val = substr($val, 1, -1);
                    }
                }

                self::$variables[$key] = $val;
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
                @putenv("{$key}={$val}");
            }
        }
        self::$isLoaded = true;
    }

    public static function get($key, $default = null) {
        if (!self::$isLoaded) {
            self::load(BASE_PATH . '/.env');
        }

        if (array_key_exists($key, self::$variables) && self::$variables[$key] !== '') {
            return self::$variables[$key];
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        $sys = @getenv($key);
        if ($sys !== false && $sys !== '') {
            return $sys;
        }
        return $default;
    }

    public static function all() {
        if (!self::$isLoaded) {
            self::load(BASE_PATH . '/.env');
        }
        return self::$variables;
    }
}

// Initial load
Env::load(BASE_PATH . '/.env');

// ====================================================================
// DATABASE CONFIGURATION SETTINGS
// 1. Direct configuration: you can enter your Hostinger credentials here
// 2. OR in .env file (if visible / preferred)
// 3. OR interactively test and save credentials at /db-test.php
// ====================================================================
$CONFIG_DB = [
    'driver' => 'mysql',       // 'mysql' (Hostinger) or 'sqlite'
    'host'   => 'localhost',   // Hostinger MySQL host (usually 'localhost')
    'port'   => '3306',
    'name'   => '',            // Hostinger Database Name (e.g. u123456789_inventory)
    'user'   => '',            // Hostinger Database User (e.g. u123456789_user)
    'pass'   => '',            // Hostinger Database Password
];

// Determine effective configuration (.env takes precedence if set with non-placeholder values, otherwise $CONFIG_DB)
$envDriver = Env::get('DB_DRIVER');
$envHost   = Env::get('DB_HOST');
$envPort   = Env::get('DB_PORT');
$envName   = Env::get('DB_NAME');
$envUser   = Env::get('DB_USER');
$envPass   = Env::get('DB_PASS');

// If .env still contains placeholder values, but $CONFIG_DB has real user credentials, prioritize $CONFIG_DB!
$isPlaceholderEnv = ($envName === 'u123456789_shifting_dsc' || $envPass === 'YourDatabasePasswordHere');

if ($isPlaceholderEnv && !empty($CONFIG_DB['name']) && !empty($CONFIG_DB['user'])) {
    $effectiveDriver = $CONFIG_DB['driver'];
    $effectiveHost   = $CONFIG_DB['host'];
    $effectivePort   = $CONFIG_DB['port'];
    $effectiveName   = $CONFIG_DB['name'];
    $effectiveUser   = $CONFIG_DB['user'];
    $effectivePass   = $CONFIG_DB['pass'];
} else {
    $effectiveDriver = (!empty($envDriver)) ? strtolower($envDriver) : $CONFIG_DB['driver'];
    $effectiveHost   = (!empty($envHost)) ? $envHost : ($CONFIG_DB['host'] ?: 'localhost');
    $effectivePort   = (!empty($envPort)) ? $envPort : ($CONFIG_DB['port'] ?: '3306');
    $effectiveName   = (!empty($envName)) ? $envName : $CONFIG_DB['name'];
    $effectiveUser   = (!empty($envUser)) ? $envUser : $CONFIG_DB['user'];
    $effectivePass   = ($envPass !== null && $envPass !== '') ? $envPass : $CONFIG_DB['pass'];
}

define('DB_DRIVER', $effectiveDriver);
define('DB_HOST', $effectiveHost);
define('DB_PORT', $effectivePort);
define('DB_NAME', $effectiveName);
define('DB_USER', $effectiveUser);
define('DB_PASS', $effectivePass);

// Ensure storage directories exist
foreach ([UPLOAD_DIR_OLD, UPLOAD_DIR_REACHED, DATA_DIR] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

/**
 * Get PDO Database Connection
 * Strictly respects DB_DRIVER from .env / config
 */
function get_db() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_DRIVER === 'mysql') {
            // Validate required MySQL parameters
            if (empty(DB_NAME) || empty(DB_USER)) {
                throw new Exception("Database Name (DB_NAME) or Username (DB_USER) is empty. Please configure your credentials in .env, config.php, or use the db-test.php setup tool.");
            }

            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);

            // Ensure tables exist
            initialize_database($pdo, 'mysql');

        } else {
            // SQLite Connection (Only when DB_DRIVER=sqlite)
            $dbPath = DATA_DIR . '/inventory.db';
            $isNew = !file_exists($dbPath);
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec("PRAGMA foreign_keys = ON;");

            if ($isNew || filesize($dbPath) == 0) {
                initialize_database($pdo, 'sqlite');
            }
        }
    } catch (Throwable $e) {
        // If request is AJAX or JSON, return JSON error response
        if ((!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
            (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
            (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }

        // Professional Error Display for regular page views
        $driver = htmlspecialchars(DB_DRIVER);
        $host = htmlspecialchars(DB_HOST);
        $port = htmlspecialchars(DB_PORT);
        $dbname = htmlspecialchars(DB_NAME);
        $user = htmlspecialchars(DB_USER);
        $errorMsg = htmlspecialchars($e->getMessage());

        die("
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Database Error - ShiftTrack DSC</title>
            <script src='https://cdn.tailwindcss.com'></script>
        </head>
        <body class='bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 font-sans'>
            <div class='max-w-xl w-full bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6 sm:p-8'>
                <div class='flex items-center space-x-3 mb-4'>
                    <div class='w-12 h-12 rounded-xl bg-red-500/10 border border-red-500/30 flex items-center justify-center text-red-500 text-xl font-bold'>
                        !
                    </div>
                    <div>
                        <h2 class='text-xl font-bold text-white'>Hostinger MySQL Connection Failed</h2>
                        <p class='text-xs text-slate-400'>The system is configured for MySQL, but could not connect with the provided credentials.</p>
                    </div>
                </div>

                <div class='bg-red-950/40 border border-red-800/50 rounded-xl p-4 text-xs font-mono text-red-300 mb-5 break-words'>
                    {$errorMsg}
                </div>

                <h3 class='text-xs font-bold text-slate-400 uppercase tracking-wider mb-2'>Credentials Loaded:</h3>
                <div class='bg-slate-950 rounded-xl border border-slate-800 divide-y divide-slate-800 text-xs font-mono mb-5 overflow-hidden'>
                    <div class='flex justify-between p-2.5 px-3.5'><span class='text-slate-500 font-sans font-semibold'>DB_DRIVER:</span><span class='text-sky-400 font-bold'>{$driver}</span></div>
                    <div class='flex justify-between p-2.5 px-3.5'><span class='text-slate-500 font-sans font-semibold'>DB_HOST:</span><span class='text-slate-200'>{$host}</span></div>
                    <div class='flex justify-between p-2.5 px-3.5'><span class='text-slate-500 font-sans font-semibold'>DB_NAME:</span><span class='text-emerald-400 font-bold'>{$dbname}</span></div>
                    <div class='flex justify-between p-2.5 px-3.5'><span class='text-slate-500 font-sans font-semibold'>DB_USER:</span><span class='text-amber-400 font-bold'>{$user}</span></div>
                </div>

                <div class='flex gap-3 justify-center'>
                    <a href='db-test.php' class='px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition-all shadow-md'>
                        🛠️ Open Database Setup Tool
                    </a>
                    <a href='index.php' class='px-5 py-2.5 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold transition-all shadow-md'>
                        ↻ Retry Connection
                    </a>
                </div>
            </div>
        </body>
        </html>
        ");
    }

    return $pdo;
}

/**
 * Initialize Tables (Safe CREATE TABLE IF NOT EXISTS)
 */
function initialize_database($pdo, $driver = 'mysql') {
    if ($driver === 'sqlite') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                brand TEXT NOT NULL,
                model TEXT NOT NULL,
                barcode_id TEXT NOT NULL DEFAULT '',
                serial_number TEXT DEFAULT '',
                category TEXT DEFAULT 'General',
                old_location TEXT DEFAULT '',
                new_location TEXT DEFAULT '',
                status TEXT DEFAULT 'packed',
                old_condition TEXT DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                reached_at DATETIME NULL,
                reached_by TEXT NULL,
                reached_condition TEXT NULL,
                reached_notes TEXT NULL
            );

            CREATE TABLE IF NOT EXISTS item_images (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                item_id INTEGER NOT NULL,
                stage TEXT NOT NULL,
                image_path TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS activity_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                item_id INTEGER NULL,
                action TEXT NOT NULL,
                details TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // SQLite migration: ensure barcode_id column exists
        try {
            $cols = $pdo->query("PRAGMA table_info(items)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('barcode_id', $cols)) {
                $pdo->exec("ALTER TABLE items ADD COLUMN barcode_id TEXT DEFAULT ''");
                $pdo->exec("UPDATE items SET barcode_id = serial_number WHERE barcode_id = '' OR barcode_id IS NULL");
            }
        } catch (Throwable $e) {}

        seed_sample_data($pdo);

    } else {
        // MySQL / MariaDB (Hostinger)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                brand VARCHAR(100) NOT NULL,
                model VARCHAR(100) NOT NULL,
                barcode_id VARCHAR(150) NOT NULL,
                serial_number VARCHAR(150) DEFAULT '',
                category VARCHAR(100) DEFAULT 'General',
                old_location VARCHAR(150) DEFAULT '',
                new_location VARCHAR(150) DEFAULT '',
                status VARCHAR(50) NOT NULL DEFAULT 'packed',
                old_condition TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reached_at DATETIME DEFAULT NULL,
                reached_by VARCHAR(100) DEFAULT NULL,
                reached_condition VARCHAR(50) DEFAULT NULL,
                reached_notes TEXT DEFAULT NULL,
                UNIQUE KEY idx_barcode_id (barcode_id),
                KEY idx_serial_number (serial_number),
                KEY idx_status (status),
                KEY idx_category (category)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS item_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT NOT NULL,
                stage VARCHAR(50) NOT NULL DEFAULT 'old_office',
                image_path VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT DEFAULT NULL,
                action VARCHAR(100) NOT NULL,
                details TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Safely migrate existing tables: ensure barcode_id exists & column types are VARCHAR(50)
        try {
            $pdo->exec("ALTER TABLE items ADD COLUMN barcode_id VARCHAR(150) NULL AFTER model");
            $pdo->exec("UPDATE items SET barcode_id = serial_number WHERE barcode_id IS NULL OR barcode_id = ''");
        } catch (Throwable $bcEx) {
            // Already has barcode_id column
        }

        try {
            $pdo->exec("ALTER TABLE items MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'packed'");
            $pdo->exec("ALTER TABLE items MODIFY COLUMN reached_condition VARCHAR(50) DEFAULT NULL");
            $pdo->exec("ALTER TABLE item_images MODIFY COLUMN stage VARCHAR(50) NOT NULL DEFAULT 'old_office'");
        } catch (Throwable $alterEx) {
            // Non-critical if table is fresh or user doesn't have ALTER permissions
        }

        seed_sample_data($pdo);
    }
}

/**
 * Seed initial sample items if table is brand new and empty
 */
function seed_sample_data($pdo) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
        if ($count > 0) return;

        $sampleItems = [
            [
                'brand' => 'Dell',
                'model' => 'OptiPlex 7090 Desktop',
                'barcode_id' => 'BC-DSC-001',
                'serial_number' => 'SN-DELL-7090-XYZ',
                'category' => 'Workstation',
                'old_location' => 'Old Office - IT Dept Desk 04',
                'new_location' => 'New Office - 3rd Floor IT Bay',
                'status' => 'reached',
                'old_condition' => 'Clean condition, power cord and HDMI cable attached in box.',
                'reached_by' => 'Maulik Dawda',
                'reached_condition' => 'perfect',
                'reached_notes' => 'Received intact and booted up successfully at new office.',
                'reached_at' => date('Y-m-d H:i:s')
            ],
            [
                'brand' => 'HP',
                'model' => 'LaserJet Pro MFP M428fdw',
                'barcode_id' => 'BC-DSC-002',
                'serial_number' => 'SN-HP-428-8821',
                'category' => 'Printers',
                'old_location' => 'Old Office - Admin Printing Station',
                'new_location' => 'New Office - Admin Print Hub',
                'status' => 'packed',
                'old_condition' => 'Toner removed and packed in separate anti-static pouch. Unit secured with bubble wrap.',
                'reached_by' => null,
                'reached_condition' => null,
                'reached_notes' => null,
                'reached_at' => null
            ],
            [
                'brand' => 'Apple',
                'model' => 'MacBook Pro 16\" M2',
                'barcode_id' => 'BC-DSC-003',
                'serial_number' => 'C02G89XYMD6R',
                'category' => 'Laptops',
                'old_location' => 'Old Office - Design Studio Desk 01',
                'new_location' => 'New Office - Creative Wing Desk 12',
                'status' => 'packed',
                'old_condition' => 'Supplied with original 140W MagSafe charger and sleeve.',
                'reached_by' => null,
                'reached_condition' => null,
                'reached_notes' => null,
                'reached_at' => null
            ],
            [
                'brand' => 'Cisco',
                'model' => 'Catalyst 2960-X 48-Port Switch',
                'barcode_id' => 'BC-DSC-004',
                'serial_number' => 'FCW1942B02Y',
                'category' => 'Networking',
                'old_location' => 'Old Office - Main Server Rack 1U-12',
                'new_location' => 'New Office - Server Room Rack A-04',
                'status' => 'reached',
                'old_condition' => 'Rack ears attached, console cable included.',
                'reached_by' => 'IT Relocation Team',
                'reached_condition' => 'perfect',
                'reached_notes' => 'Mounted into Rack A at New Office.',
                'reached_at' => date('Y-m-d H:i:s')
            ],
            [
                'brand' => 'Samsung',
                'model' => '27\" Curved LED Monitor S27E510CS',
                'barcode_id' => 'BC-DSC-005',
                'serial_number' => 'ZX82HV1092',
                'category' => 'Monitors',
                'old_location' => 'Old Office - Finance Desk 02',
                'new_location' => 'New Office - Finance Hub Desk 08',
                'status' => 'packed',
                'old_condition' => 'Packed with foam corner protectors and power adapter.',
                'reached_by' => null,
                'reached_condition' => null,
                'reached_notes' => null,
                'reached_at' => null
            ]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO items (
                brand, model, barcode_id, serial_number, category, old_location, new_location, 
                status, old_condition, reached_by, reached_condition, reached_notes, reached_at
            ) VALUES (
                :brand, :model, :barcode_id, :serial_number, :category, :old_location, :new_location,
                :status, :old_condition, :reached_by, :reached_condition, :reached_notes, :reached_at
            )
        ");

        foreach ($sampleItems as $item) {
            $stmt->execute($item);
        }

        $pdo->exec("INSERT INTO activity_logs (item_id, action, details) VALUES (NULL, 'System Initialized', 'Office shifting inventory system database initialized with default configuration.')");
    } catch (Exception $e) {
        // Silently continue if seed already executed
    }
}

/**
 * Save Base64 Image (Captured directly from Camera)
 */
function save_base64_image($base64_data, $stage = 'old_office') {
    $targetDir = ($stage === 'reached_office') ? UPLOAD_DIR_REACHED : UPLOAD_DIR_OLD;
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }
    
    if (preg_match('/^data:image\/(\w+);base64,/', $base64_data, $type)) {
        $base64_data = substr($base64_data, strpos($base64_data, ',') + 1);
        $ext = strtolower($type[1]);
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $ext = 'jpg';
        }
    } else {
        $ext = 'jpg';
    }

    $data = base64_decode($base64_data);
    if ($data === false) {
        return false;
    }

    $fileName = 'cam_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filePath = $targetDir . '/' . $fileName;

    if (file_put_contents($filePath, $data)) {
        return 'uploads/' . (($stage === 'reached_office') ? 'reached_office/' : 'old_office/') . $fileName;
    }
    return false;
}

/**
 * Save standard uploaded file
 */
function save_uploaded_file($fileArray, $stage = 'old_office') {
    $targetDir = ($stage === 'reached_office') ? UPLOAD_DIR_REACHED : UPLOAD_DIR_OLD;
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }
    
    if (!isset($fileArray['tmp_name']) || !is_uploaded_file($fileArray['tmp_name'])) {
        return false;
    }

    $ext = strtolower(pathinfo($fileArray['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
        $ext = 'jpg';
    }

    $fileName = 'up_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = $targetDir . '/' . $fileName;

    if (move_uploaded_file($fileArray['tmp_name'], $destination)) {
        return 'uploads/' . (($stage === 'reached_office') ? 'reached_office/' : 'old_office/') . $fileName;
    }
    return false;
}

/**
 * Log an activity
 */
function log_activity($itemId, $action, $details) {
    try {
        $db = get_db();
        $stmt = $db->prepare("INSERT INTO activity_logs (item_id, action, details) VALUES (:item_id, :action, :details)");
        $stmt->execute([
            ':item_id' => $itemId,
            ':action' => $action,
            ':details' => $details
        ]);
    } catch (Exception $e) {
        // Silently ignore logging failures
    }
}
