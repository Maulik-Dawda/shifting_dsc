<?php
// api/mark-reached.php - Product Arrival Checkoff Handler
require_once __DIR__ . '/../config.php';

// Detect AJAX request
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
          (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
          (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1');

function respond($success, $message, $redirectUrl = '../reached.php', $extra = []) {
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
        exit;
    }
    // Form submission redirect
    $separator = (strpos($redirectUrl, '?') !== false) ? '&' : '?';
    $param = $success ? 'msg=' . urlencode($message) : 'err=' . urlencode($message);
    header("Location: {$redirectUrl}{$separator}{$param}");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method. POST required.');
}

$itemId = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
$serialNumber = isset($_POST['serial_number']) ? trim($_POST['serial_number']) : '';
$reachedBy = isset($_POST['reached_by']) && trim($_POST['reached_by']) !== '' ? trim($_POST['reached_by']) : 'Staff';
$reachedCondition = isset($_POST['reached_condition']) ? trim($_POST['reached_condition']) : 'perfect';
$reachedNotes = isset($_POST['reached_notes']) ? trim($_POST['reached_notes']) : '';
$newLocation = isset($_POST['new_location']) ? trim($_POST['new_location']) : '';
$reachedAt = !empty($_POST['reached_at']) ? $_POST['reached_at'] : date('Y-m-d H:i:s');
$redirectTo = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : '../reached.php';

// Validate condition enum
$validConditions = ['perfect', 'minor_damage', 'damaged', 'missing_parts'];
if (!in_array($reachedCondition, $validConditions)) {
    $reachedCondition = 'perfect';
}

try {
    $db = get_db();
    
    // Find item
    if ($itemId > 0) {
        $stmt = $db->prepare("SELECT * FROM items WHERE id = :id");
        $stmt->execute([':id' => $itemId]);
    } else if (!empty($serialNumber)) {
        $stmt = $db->prepare("SELECT * FROM items WHERE UPPER(serial_number) = UPPER(:sn)");
        $stmt->execute([':sn' => $serialNumber]);
    } else {
        respond(false, 'Item ID or Serial Number is required to mark as reached.');
    }

    $item = $stmt->fetch();
    if (!$item) {
        respond(false, 'Item not found in inventory. Please verify serial number.');
    }

    $itemId = $item['id'];

    // Update item status to 'reached'
    $updateSql = "
        UPDATE items SET 
            status = 'reached',
            reached_at = :reached_at,
            reached_by = :reached_by,
            reached_condition = :reached_condition,
            reached_notes = :reached_notes";
    
    $params = [
        ':reached_at' => $reachedAt,
        ':reached_by' => $reachedBy,
        ':reached_condition' => $reachedCondition,
        ':reached_notes' => $reachedNotes,
        ':id' => $itemId
    ];

    if (!empty($newLocation)) {
        $updateSql .= ", new_location = :new_location";
        $params[':new_location'] = $newLocation;
    }
    
    $updateSql .= " WHERE id = :id";
    
    $updateStmt = $db->prepare($updateSql);
    $updateStmt->execute($params);

    $savedImageCount = 0;

    // 1. Process Live Camera Photos (JSON array of Base64 strings)
    if (!empty($_POST['camera_images'])) {
        $cameraPhotos = json_decode($_POST['camera_images'], true);
        if (is_array($cameraPhotos)) {
            $imgStmt = $db->prepare("INSERT INTO item_images (item_id, stage, image_path, created_at) VALUES (:item_id, 'reached_office', :image_path, :created_at)");
            foreach ($cameraPhotos as $base64Photo) {
                if (!empty($base64Photo)) {
                    $savedPath = save_base64_image($base64Photo, 'reached_office');
                    if ($savedPath) {
                        $imgStmt->execute([
                            ':item_id' => $itemId,
                            ':image_path' => $savedPath,
                            ':created_at' => date('Y-m-d H:i:s')
                        ]);
                        $savedImageCount++;
                    }
                }
            }
        }
    }

    // 2. Process Uploaded Photos (from device camera or file picker)
    if (isset($_FILES['arrival_photos']) && is_array($_FILES['arrival_photos']['name'])) {
        $imgStmt = $db->prepare("INSERT INTO item_images (item_id, stage, image_path, created_at) VALUES (:item_id, 'reached_office', :image_path, :created_at)");
        $fileCount = count($_FILES['arrival_photos']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if (!empty($_FILES['arrival_photos']['tmp_name'][$i])) {
                $fileItem = [
                    'name' => $_FILES['arrival_photos']['name'][$i],
                    'type' => $_FILES['arrival_photos']['type'][$i],
                    'tmp_name' => $_FILES['arrival_photos']['tmp_name'][$i],
                    'error' => $_FILES['arrival_photos']['error'][$i],
                    'size' => $_FILES['arrival_photos']['size'][$i],
                ];
                $savedPath = save_uploaded_file($fileItem, 'reached_office');
                if ($savedPath) {
                    $imgStmt->execute([
                        ':item_id' => $itemId,
                        ':image_path' => $savedPath,
                        ':created_at' => date('Y-m-d H:i:s')
                    ]);
                    $savedImageCount++;
                }
            }
        }
    }

    // Log Relocation Activity
    $actionDesc = "Item marked as REACHED at New Office by {$reachedBy}. Condition: {$reachedCondition}. Added {$savedImageCount} arrival photo(s).";
    log_activity($itemId, 'Product Reached', $actionDesc);

    $msg = "{$item['brand']} {$item['model']} (S/N: {$item['serial_number']}) marked as Reached!";
    respond(true, $msg, $redirectTo, ['item_id' => $itemId, 'images_added' => $savedImageCount]);

} catch (Throwable $e) {
    respond(false, "Database update error: " . $e->getMessage(), $redirectTo);
}
