<?php
// api/scan-lookup.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$sn = isset($_GET['sn']) ? trim($_GET['sn']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($sn) && $id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Serial number or ID is required.']);
    exit;
}

try {
    $db = get_db();
    
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM items WHERE id = :id");
        $stmt->execute([':id' => $id]);
    } else {
        $stmt = $db->prepare("SELECT * FROM items WHERE UPPER(serial_number) = UPPER(:sn) LIMIT 1");
        $stmt->execute([':sn' => $sn]);
    }
    
    $item = $stmt->fetch();
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'No item found.']);
        exit;
    }
    
    // Fetch images
    $imgStmt = $db->prepare("SELECT * FROM item_images WHERE item_id = :item_id ORDER BY id ASC");
    $imgStmt->execute([':item_id' => $item['id']]);
    $allImages = $imgStmt->fetchAll();
    
    $oldImages = [];
    $reachedImages = [];
    
    foreach ($allImages as $img) {
        if ($img['stage'] === 'old_office') {
            $oldImages[] = $img['image_path'];
        } else {
            $reachedImages[] = $img['image_path'];
        }
    }
    
    $item['old_images'] = $oldImages;
    $item['reached_images'] = $reachedImages;
    
    echo json_encode([
        'success' => true,
        'item' => $item
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database query failed: ' . $e->getMessage()]);
}
