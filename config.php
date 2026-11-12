<?php
/**
 * DSC Shifting Inventory Management System
 * Database & Application Configuration (.env Enabled)
 */

// Show errors during development if configured
error_reporting(E_ALL);

// Base directory paths
define('BASE_PATH', __DIR__);
define('UPLOAD_DIR_OLD', BASE_PATH . '/uploads/old_office');
define('UPLOAD_DIR_REACHED', BASE_PATH . '/uploads/reached_office');
define('DATA_DIR', BASE_PATH . '/data');

/**
 * Lightweight .env Parser (Zero-dependency, PHP 7.4+ & 8.x compatible)
 */
function load_env_file($filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return;
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments and empty lines
        if ($line === '' || substr($line, 0, 1) === '#') {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip enclosing quotes if present
            $len = strlen($value);
            if ($len >= 2) {
                $first = substr($value, 0, 1);
                $last = substr($value, -1);
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Load .env from root directory if present
load_env_file(BASE_PATH . '/.env');

/**
 * Helper function to retrieve environment variables with fallback
 */
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        if (isset($_ENV[$key])) {
            $value = $_ENV[$key];
        } elseif (isset($_SERVER[$key])) {
            $value = $_SERVER[$key];
        } else {
            return $default;
        }
    }
    return $value;
}

// Determine Database Driver:
// If DB_DRIVER is set in .env, use that.
// If not specified, but DB_NAME is configured (typical for Hostinger MySQL), default to mysql.
$configuredDriver = strtolower(env('DB_DRIVER', ''));
if (empty($configuredDriver)) {
    $configuredDriver = (env('DB_NAME') && env('DB_NAME') !== 'shifting_dsc') ? 'mysql' : 'sqlite';
}

define('DB_DRIVER', $configuredDriver);
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'shifting_dsc'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// Ensure required upload and data directories exist
foreach ([UPLOAD_DIR_OLD, UPLOAD_DIR_REACHED, DATA_DIR] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

/**
 * Get PDO Database Connection
 */
function get_db() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_DRIVER === 'mysql') {
            // MySQL Connection (Hostinger / cPanel / Remote)
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
            
            // Auto-create tables if they don't exist yet
            initialize_database($pdo, 'mysql');

        } else {
            // SQLite Connection (Default Zero-Config fallback)
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
    } catch (PDOException $e) {
        // Helpful diagnostic display for Hostinger / MySQL configuration errors
        $driver = DB_DRIVER;
        $host = htmlspecialchars(DB_HOST);
        $dbname = htmlspecialchars(DB_NAME);
        $user = htmlspecialchars(DB_USER);
        $errorMsg = htmlspecialchars($e->getMessage());

        die("
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <title>Database Connection Error - ShiftTrack DSC</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #0f172a; color: #f8fafc; padding: 40px 20px; line-height: 1.6; }
                .card { max-width: 650px; margin: 0 auto; background: #1e293b; border-radius: 16px; padding: 32px; border: 1px solid #334155; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); }
                h2 { color: #f87171; margin-top: 0; font-size: 22px; display: flex; items-center; gap: 8px; }
                .msg-box { background: #450a0a; border: 1px solid #7f1d1d; color: #fca5a5; padding: 14px; border-radius: 8px; font-family: monospace; font-size: 13px; margin: 16px 0; word-break: break-all; }
                .config-table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 14px; }
                .config-table td { padding: 8px 12px; border-bottom: 1px solid #334155; }
                .config-table td:first-child { color: #94a3b8; width: 35%; font-weight: 600; }
                .config-table td:last-child { color: #38bdf8; font-family: monospace; }
                .tips { background: #0f172a; padding: 16px; border-radius: 8px; border-left: 4px solid #38bdf8; font-size: 13px; color: #cbd5e1; margin-top: 20px; }
                .tips strong { color: #38bdf8; }
                code { background: #334155; padding: 2px 6px; border-radius: 4px; color: #f1f5f9; }
            </style>
        </head>
        <body>
            <div class='card'>
                <h2>⚠️ Database Connection Error</h2>
                <p>Could not connect to the database using the configuration in your <code>.env</code> file:</p>
                <div class='msg-box'>{$errorMsg}</div>
                
                <table class='config-table'>
                    <tr><td>DB_DRIVER</td><td>{$driver}</td></tr>
                    <tr><td>DB_HOST</td><td>{$host}</td></tr>
                    <tr><td>DB_NAME</td><td>{$dbname}</td></tr>
                    <tr><td>DB_USER</td><td>{$user}</td></tr>
                </table>

                <div class='tips'>
                    <strong>💡 Hostinger Troubleshooting Checklist:</strong>
                    <ol style='margin: 8px 0 0 16px; padding: 0;'>
                        <li>Verify your <code>.env</code> file is created in your website root folder on Hostinger.</li>
                        <li>On Hostinger, both the database name and database username always include your account prefix (e.g., <code>u123456789_shifting</code>).</li>
                        <li>The host on Hostinger is usually <code>localhost</code>.</li>
                        <li>Ensure the database user has been granted <strong>ALL PRIVILEGES</strong> on the database in Hostinger hPanel.</li>
                    </ol>
                </div>
            </div>
        </body>
        </html>
        ");
    }

    return $pdo;
}

/**
 * Initialize Tables
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
                status VARCHAR(50) DEFAULT 'packed',
                old_condition TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                reached_at DATETIME NULL,
                reached_by VARCHAR(100) NULL,
                reached_condition VARCHAR(50) NULL,
                reached_notes TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS item_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT NOT NULL,
                stage VARCHAR(30) NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT NULL,
                action VARCHAR(100) NOT NULL,
                details TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Seed initial items if database is empty
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
                'model' => 'MacBook Pro 16" M2',
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
                'model' => '27" Curved LED Monitor S27E510CS',
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
