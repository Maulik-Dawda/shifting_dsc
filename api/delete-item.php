<?php
// api/delete-item.php
require_once __DIR__ . '/../config.php';

$id = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
$deleteImageId = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;

if ($id <= 0 && $deleteImageId <= 0) {
    header("Location: ../items.php?err=" . urlencode("Invalid item ID"));
    exit;
}

try {
    $db = get_db();

    // If deleting a specific image
    if ($deleteImageId > 0) {
        $stmt = $db->prepare("SELECT * FROM item_images WHERE id = :id");
        $stmt->execute([':id' => $deleteImageId]);
        $img = $stmt->fetch();
        if ($img) {
            $diskPath = BASE_PATH . '/' . $img['image_path'];
            if (file_exists($diskPath)) {
                @unlink($diskPath);
            }
            $db->prepare("DELETE FROM item_images WHERE id = :id")->execute([':id' => $deleteImageId]);
            log_activity($img['item_id'], 'Deleted Photo', "Deleted photo {$img['image_path']}");
        }
        header("Location: ../view.php?id=" . $img['item_id'] . "&msg=" . urlencode("Photo deleted successfully"));
        exit;
    }

    // Deleting entire item
    $stmt = $db->prepare("SELECT * FROM items WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch();

    if ($item) {
        // Fetch all images and remove from disk
        $imgStmt = $db->prepare("SELECT image_path FROM item_images WHERE item_id = :id");
        $imgStmt->execute([':id' => $id]);
        $images = $imgStmt->fetchAll();
        foreach ($images as $img) {
            $filePath = BASE_PATH . '/' . $img['image_path'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        // Delete from database (foreign key cascade handles item_images)
        $db->prepare("DELETE FROM items WHERE id = :id")->execute([':id' => $id]);
        log_activity(null, 'Item Deleted', "Deleted item {$item['brand']} {$item['model']} (S/N: {$item['serial_number']})");
        
        header("Location: ../items.php?msg=" . urlencode("Item deleted successfully"));
        exit;
    }

} catch (Exception $e) {
    header("Location: ../items.php?err=" . urlencode("Failed to delete item: " . $e->getMessage()));
    exit;
}
