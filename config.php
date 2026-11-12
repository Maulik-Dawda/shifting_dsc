<?php
/**
 * DSC Shifting Inventory Management System
 * Database & Application Configuration
 */

// Show errors during development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Base Paths
define('BASE_PATH', __DIR__);
define('UPLOAD_DIR_OLD', BASE_PATH . '/uploads/old_office');
define('UPLOAD_DIR_REACHED', BASE_PATH . '/uploads/reached_office');
define('DATA_DIR', BASE_PATH . '/data');

// Database Configuration
// Options: 'sqlite' (zero config, works instantly) or 'mysql'
define('DB_DRIVER', 'sqlite'); 

// MySQL settings (if DB_DRIVER == 'mysql')
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'shifting_dsc');
define('DB_USER', 'root');
define('DB_PASS', '');

// Ensure upload & data directories exist
foreach ([UPLOAD_DIR_OLD, UPLOAD_DIR_REACHED, DATA_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
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
        if (DB_DRIVER === 'sqlite') {
            $dbPath = DATA_DIR . '/inventory.db';
            $isNew = !file_exists($dbPath);
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec("PRAGMA foreign_keys = ON;");

            if ($isNew || filesize($dbPath) == 0) {
                initialize_database($pdo, 'sqlite');
            }
        } else {
            // MySQL
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            initialize_database($pdo, 'mysql');
        }
    } catch (PDOException $e) {
        die("<div style='font-family:sans-serif;padding:20px;background:#fee;border:1px solid #f99;margin:20px;border-radius:8px;'>
            <h3 style='color:#c00;'>Database Connection Error</h3>
            <p>" . htmlspecialchars($e->getMessage()) . "</p>
            <p>If using MySQL, ensure MySQL server is running or switch <code>DB_DRIVER</code> to <code>sqlite</code> in <code>config.php</code>.</p>
        </div>");
    }

    return $pdo;
}

/**
 * Initialize Tables
 */
function initialize_database($pdo, $driver = 'sqlite') {
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
                status TEXT DEFAULT 'packed', -- 'packed', 'in_transit', 'reached', 'damaged'
                old_condition TEXT DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                reached_at DATETIME NULL,
                reached_by TEXT NULL,
                reached_condition TEXT NULL, -- 'perfect', 'minor_damage', 'damaged', 'missing_parts'
                reached_notes TEXT NULL
            );

            CREATE TABLE IF NOT EXISTS item_images (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                item_id INTEGER NOT NULL,
                stage TEXT NOT NULL, -- 'old_office', 'reached_office'
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
        
        // Seed initial sample items if empty
        seed_sample_data($pdo);

    } else {
        // MySQL
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS item_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT NOT NULL,
                stage ENUM('old_office', 'reached_office') NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT NULL,
                action VARCHAR(100) NOT NULL,
                details TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }
}

/**
 * Seed initial sample items for quick demonstration
 */
function seed_sample_data($pdo) {
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

    // Add activity log
    $pdo->exec("INSERT INTO activity_logs (item_id, action, details) VALUES (NULL, 'System Initialized', 'Office shifting inventory system database initialized with default configuration.')");
}

/**
 * Save Base64 Image (Captured directly from Camera)
 */
function save_base64_image($base64_data, $stage = 'old_office') {
    $targetDir = ($stage === 'reached_office') ? UPLOAD_DIR_REACHED : UPLOAD_DIR_OLD;
    
    // Clean base64 header
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
        // silently ignore logging failures
    }
}
