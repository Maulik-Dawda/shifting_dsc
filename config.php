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
// DATABASE CONFIGURATION
// Prioritizes .env values, with direct fallback here if needed
// ====================================================================
define('DB_DRIVER', strtolower(Env::get('DB_DRIVER', 'mysql'))); // 'mysql' (Hostinger) or 'sqlite'
define('DB_HOST', Env::get('DB_HOST', 'localhost'));
define('DB_PORT', Env::get('DB_PORT', '3306'));
define('DB_NAME', Env::get('DB_NAME', ''));
define('DB_USER', Env::get('DB_USER', ''));
define('DB_PASS', Env::get('DB_PASS', ''));

// Ensure storage directories exist
foreach ([UPLOAD_DIR_OLD, UPLOAD_DIR_REACHED, DATA_DIR] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

/**
 * Get PDO Database Connection
 * Strictly respects DB_DRIVER from .env (no silent local file fallback)
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
                throw new Exception("Database Name (DB_NAME) or Username (DB_USER) is empty. Please configure your .env file with your Hostinger database details.");
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
            // SQLite Connection (Only when DB_DRIVER=sqlite in .env)
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
        // Professional Error Display (Shows exact credentials read from .env so user can diagnose immediately)
        $driver = htmlspecialchars(DB_DRIVER);
        $host = htmlspecialchars(DB_HOST);
        $port = htmlspecialchars(DB_PORT);
        $dbname = htmlspecialchars(DB_NAME);
        $user = htmlspecialchars(DB_USER);
        $errorMsg = htmlspecialchars($e->getMessage());
        $envPath = htmlspecialchars(BASE_PATH . '/.env');

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

                <h3 class='text-xs font-bold text-slate-400 uppercase tracking-wider mb-2'>Credentials Loaded from <code>.env</code>:</h3>
                <div class='bg-slate-950 rounded-xl border border-slate-800 divide-y divide-slate-800 text-xs font-mono mb-5 overflow-hidden'>
                    <div class='flex justify-between p-2.5 px-3.5'><span class='text-slate-500 font-sans font-semibold'>DB_DRIVER:</span><span class='text-sky-400 font-bold'>{$driver}</span></div>
                    <div class='flex justify-between p-2.5 px-3.5'><span class='text-slate-500 font-sans font-semibold'>DB_HOST:</span><span class='text-slate-200'>{$host}</span></div>
                    <div class='flex justify-between p-2.5 px-3.5'><span class='text-slate-500 font-sans font-semibold'>DB_NAME:</span><span class='text-emerald-400 font-bold'>{$dbname}</span></div>
                    <div class='flex justify-between p-2.5 px-3.5'><span class='text-slate-500 font-sans font-semibold'>DB_USER:</span><span class='text-amber-400 font-bold'>{$user}</span></div>
                </div>

                <div class='bg-slate-800/60 rounded-xl p-4 text-xs text-slate-300 space-y-2 border border-slate-700/50'>
                    <div class='font-bold text-sky-400'>💡 Quick Hostinger Checklist:</div>
                    <ol class='list-decimal list-inside space-y-1 text-slate-400'>
                        <li>Log in to <strong>Hostinger hPanel</strong> ➔ <strong>Databases</strong> ➔ <strong>MySQL Databases</strong>.</li>
                        <li>Verify that <strong>DB_NAME</strong> matches your exact database name (usually starting with <code>u123456789_</code>).</li>
                        <li>Verify that <strong>DB_USER</strong> matches your database user (also prefixed with <code>u123456789_</code>).</li>
                        <li>Ensure <strong>DB_PASS</strong> is correct and user has <strong>ALL PRIVILEGES</strong> granted to the database.</li>
                        <li>Hostinger MySQL host is typically <code>localhost</code>.</li>
                    </ol>
                </div>

                <div class='mt-6 text-center'>
                    <a href='index.php' class='inline-block px-5 py-2.5 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold transition-all shadow-md'>
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
                serial_number TEXT NOT NULL UNIQUE,
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

        seed_sample_data($pdo);

    } else {
        // MySQL / MariaDB (Hostinger)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                brand VARCHAR(100) NOT NULL,
                model VARCHAR(100) NOT NULL,
                serial_number VARCHAR(150) NOT NULL UNIQUE,
                category VARCHAR(100) DEFAULT 'General',
                old_location VARCHAR(150) DEFAULT '',
                new_location VARCHAR(150) DEFAULT '',
                status ENUM('packed','in_transit','reached','damaged') NOT NULL DEFAULT 'packed',
                old_condition TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reached_at DATETIME DEFAULT NULL,
                reached_by VARCHAR(100) DEFAULT NULL,
                reached_condition ENUM('perfect','minor_damage','damaged','missing_parts') DEFAULT NULL,
                reached_notes TEXT DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS item_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT NOT NULL,
                stage ENUM('old_office','reached_office') NOT NULL DEFAULT 'old_office',
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
                'serial_number' => 'DSC-DT-7090-001',
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
                'serial_number' => 'DSC-PR-428-8821',
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
                'serial_number' => 'DSC-MBP-16-9923',
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
                'serial_number' => 'DSC-SW-2960-5541',
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
                'serial_number' => 'DSC-MON-27-3312',
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
                brand, model, serial_number, category, old_location, new_location, 
                status, old_condition, reached_by, reached_condition, reached_notes, reached_at
            ) VALUES (
                :brand, :model, :serial_number, :category, :old_location, :new_location,
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
