<?php
// view.php - Item Detail & Comparison (Old Office vs Product Reached New Office)
require_once __DIR__ . '/config.php';
$db = get_db();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header("Location: items.php?err=" . urlencode("Invalid item ID"));
    exit;
}

$stmt = $db->prepare("SELECT * FROM items WHERE id = :id");
$stmt->execute([':id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    header("Location: items.php?err=" . urlencode("Item not found in inventory"));
    exit;
}

// Fetch images grouped by stage
$oldImagesStmt = $db->prepare("SELECT * FROM item_images WHERE item_id = :id AND stage = 'old_office' ORDER BY id ASC");
$oldImagesStmt->execute([':id' => $id]);
$oldImages = $oldImagesStmt->fetchAll();

$reachedImagesStmt = $db->prepare("SELECT * FROM item_images WHERE item_id = :id AND stage = 'reached_office' ORDER BY id ASC");
$reachedImagesStmt->execute([':id' => $id]);
$reachedImages = $reachedImagesStmt->fetchAll();

// Handle adding more photos to an existing item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_photos') {
    $targetStage = ($_POST['stage'] === 'reached_office') ? 'reached_office' : 'old_office';
    $addedCount = 0;

    // 1. In-built Camera Photos (Base64)
    if (!empty($_POST['camera_images'])) {
        $camPhotos = json_decode($_POST['camera_images'], true);
        if (is_array($camPhotos)) {
            $insStmt = $db->prepare("INSERT INTO item_images (item_id, stage, image_path) VALUES (:item_id, :stage, :image_path)");
            foreach ($camPhotos as $b64) {
                if (!empty($b64)) {
                    $savedPath = save_base64_image($b64, $targetStage);
                    if ($savedPath) {
                        $insStmt->execute([
                            ':item_id' => $id,
                            ':stage' => $targetStage,
                            ':image_path' => $savedPath
                        ]);
                        $addedCount++;
                    }
                }
            }
        }
    }

    // 2. File Uploads
    if (isset($_FILES['extra_photos']) && is_array($_FILES['extra_photos']['name'])) {
        $insStmt = $db->prepare("INSERT INTO item_images (item_id, stage, image_path) VALUES (:item_id, :stage, :image_path)");
        $fileCount = count($_FILES['extra_photos']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if (!empty($_FILES['extra_photos']['tmp_name'][$i])) {
                $fileItem = [
                    'name' => $_FILES['extra_photos']['name'][$i],
                    'type' => $_FILES['extra_photos']['type'][$i],
                    'tmp_name' => $_FILES['extra_photos']['tmp_name'][$i],
                    'error' => $_FILES['extra_photos']['error'][$i],
                    'size' => $_FILES['extra_photos']['size'][$i],
                ];
                $savedPath = save_uploaded_file($fileItem, $targetStage);
                if ($savedPath) {
                    $insStmt->execute([
                        ':item_id' => $id,
                        ':stage' => $targetStage,
                        ':image_path' => $savedPath
                    ]);
                    $addedCount++;
                }
            }
        }
    }

    log_activity($id, 'Photos Added', "Added {$addedCount} photo(s) to stage: {$targetStage}");
    header("Location: view.php?id={$id}&msg=" . urlencode("Added {$addedCount} photo(s) successfully!"));
    exit;
}

require_once __DIR__ . '/header.php';
?>

<!-- Breadcrumbs & Top Actions -->
<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div class="flex items-center text-xs text-slate-500 space-x-2">
        <a href="index.php" class="hover:text-sky-600">Dashboard</a>
        <i class="fa-solid fa-chevron-right text-[10px]"></i>
        <a href="items.php" class="hover:text-sky-600">Inventory</a>
        <i class="fa-solid fa-chevron-right text-[10px]"></i>
        <span class="text-slate-800 font-semibold truncate"><?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?></span>
    </div>

    <!-- Quick Action Bar -->
    <div class="flex items-center space-x-2">
        <button onclick="window.print()" class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-semibold shadow-sm flex items-center space-x-1">
            <i class="fa-solid fa-print"></i>
            <span>Print Relocation Tag</span>
        </button>
        <?php if ($item['status'] !== 'reached'): ?>
            <a href="reached.php?scan=<?= urlencode($item['serial_number']) ?>" class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm flex items-center space-x-1">
                <i class="fa-solid fa-clipboard-check"></i>
                <span>Mark as Reached</span>
            </a>
        <?php endif; ?>
        <a href="edit-item.php?id=<?= $item['id'] ?>" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold">
            <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
        </a>
    </div>
</div>

<!-- Alert notifications -->
<?php if (isset($_GET['msg'])): ?>
    <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl flex items-center justify-between text-sm shadow-sm">
        <div class="flex items-center space-x-2">
            <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
            <span><?= htmlspecialchars($_GET['msg']) ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><i class="fa-solid fa-xmark"></i></button>
    </div>
<?php endif; ?>

<!-- ============================================== -->
<!-- ITEM OVERVIEW & BARCODE BANNER                 -->
<!-- ============================================== -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 sm:p-7 mb-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-slate-100">
        <!-- Item Info -->
        <div class="flex items-start space-x-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-sky-600 to-indigo-600 text-white flex items-center justify-center text-xl font-bold uppercase shadow-md shadow-sky-600/20 flex-shrink-0">
                <?= substr(htmlspecialchars($item['brand']), 0, 2) ?>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900"><?= htmlspecialchars($item['brand']) ?> <?= htmlspecialchars($item['model']) ?></h1>
                    <?php if ($item['status'] === 'reached'): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                            <i class="fa-solid fa-check-circle mr-1"></i> Reached New Office
                        </span>
                    <?php elseif ($item['status'] === 'in_transit'): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800">
                            <i class="fa-solid fa-truck mr-1"></i> In Transit
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                            <i class="fa-solid fa-box mr-1"></i> Packed at Old Office
                        </span>
                    <?php endif; ?>
                </div>

                <div class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-x-4 gap-y-1">
                    <span><i class="fa-solid fa-layer-group text-slate-400 mr-1"></i> Category: <strong class="text-slate-700"><?= htmlspecialchars($item['category']) ?></strong></span>
                    <span><i class="fa-regular fa-calendar text-slate-400 mr-1"></i> Registered: <?= date('M j, Y g:i A', strtotime($item['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Printable Barcode Box -->
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 flex flex-col items-center justify-center text-center shadow-inner">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Serial Number Barcode</span>
            <svg data-barcode="<?= htmlspecialchars($item['serial_number']) ?>" class="max-w-[200px] h-11"></svg>
            <span class="text-xs font-mono font-bold text-slate-800 tracking-wider mt-0.5"><?= htmlspecialchars($item['serial_number']) ?></span>
        </div>
    </div>

    <!-- Metadata Details Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-6">
        <!-- Old Location -->
        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100">
            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Source (Old Office)</span>
            <span class="text-sm font-bold text-slate-800 mt-1 flex items-center">
                <i class="fa-solid fa-building text-slate-400 mr-2 text-xs"></i>
                <?= htmlspecialchars($item['old_location'] ?: 'Old Office Main') ?>
            </span>
        </div>

        <!-- New Location -->
        <div class="p-3.5 bg-emerald-50/60 rounded-xl border border-emerald-100">
            <span class="text-[11px] font-semibold text-emerald-700 uppercase tracking-wider block">Destination (New Office)</span>
            <span class="text-sm font-bold text-emerald-900 mt-1 flex items-center">
                <i class="fa-solid fa-location-dot text-emerald-600 mr-2 text-xs"></i>
                <?= htmlspecialchars($item['new_location'] ?: 'New Office Bay') ?>
            </span>
        </div>

        <!-- Arrival Condition -->
        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100">
            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Arrival Physical Check</span>
            <span class="text-sm font-bold mt-1 block capitalize">
                <?php if ($item['status'] === 'reached'): ?>
                    <?php if ($item['reached_condition'] === 'perfect'): ?>
                        <span class="text-emerald-700"><i class="fa-solid fa-circle-check mr-1"></i> Intact / Perfect</span>
                    <?php elseif ($item['reached_condition'] === 'minor_damage'): ?>
                        <span class="text-amber-600"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Minor Cosmetic</span>
                    <?php else: ?>
                        <span class="text-red-600"><i class="fa-solid fa-circle-xmark mr-1"></i> <?= htmlspecialchars($item['reached_condition']) ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="text-slate-400 italic">Pending Arrival</span>
                <?php endif; ?>
            </span>
        </div>

        <!-- Received By / Date -->
        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100">
            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Received / Verified By</span>
            <span class="text-sm font-bold text-slate-800 mt-1 block">
                <?php if ($item['status'] === 'reached'): ?>
                    <?= htmlspecialchars($item['reached_by'] ?: 'Relocation Staff') ?>
                    <span class="block text-[11px] font-normal text-slate-400"><?= date('M j, Y g:i A', strtotime($item['reached_at'])) ?></span>
                <?php else: ?>
                    <span class="text-slate-400 italic">Not arrived yet</span>
                <?php endif; ?>
            </span>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- SIDE-BY-SIDE VISUAL COMPARISON: OLD OFFICE vs PRODUCT REACHED NEW OFFICE -->
<!-- ====================================================================== -->
<div class="mb-8">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900 flex items-center">
                <i class="fa-solid fa-camera-rotate mr-2 text-sky-600"></i>
                Stage Photo Comparison
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">Compare photos taken at the old office against photos taken on arrival at the new office.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- LEFT COLUMN: OLD OFFICE PACKING PHOTOS -->
        <div class="bg-white rounded-2xl border-2 border-slate-200 shadow-sm overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="px-5 py-4 bg-slate-100 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="w-3 h-3 rounded-full bg-sky-500"></span>
                    <h3 class="font-bold text-slate-800 text-sm sm:text-base">Images at Old Office (Before Shifting)</h3>
                </div>
                <span class="text-xs font-semibold px-2 py-0.5 bg-sky-100 text-sky-800 rounded-full">
                    <?= count($oldImages) ?> photo<?= count($oldImages) !== 1 ? 's' : '' ?>
                </span>
            </div>

            <!-- Notes Section -->
            <div class="p-4 bg-sky-50/40 border-b border-slate-100 text-xs text-slate-600">
                <strong class="text-slate-700 block mb-0.5"><i class="fa-solid fa-clipboard-list mr-1"></i> Packing Notes:</strong>
                <?= nl2br(htmlspecialchars($item['old_condition'] ?: 'No specific packing notes recorded.')) ?>
            </div>

            <!-- Gallery Grid -->
            <div class="p-5 flex-grow">
                <?php if (empty($oldImages)): ?>
                    <div class="text-center py-12 text-slate-400">
                        <i class="fa-solid fa-camera text-4xl mb-2 text-slate-300"></i>
                        <p class="text-xs font-semibold">No photos attached at Old Office stage.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <?php foreach ($oldImages as $img): ?>
                            <div class="relative group rounded-xl overflow-hidden aspect-square border border-slate-200 shadow-sm bg-slate-100 cursor-pointer" onclick="openLightbox('<?= htmlspecialchars($img['image_path']) ?>', '<?= htmlspecialchars($item['brand'] . ' ' . $item['model'] . ' - Old Office Stage') ?>')">
                                <img src="<?= htmlspecialchars($img['image_path']) ?>" class="w-full h-full object-cover img-zoom-hover">
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-sm transition-opacity">
                                    <i class="fa-solid fa-magnifying-glass-plus"></i>
                                </div>
                                <form action="api/delete-item.php" method="POST" class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity" onclick="event.stopPropagation();">
                                    <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                    <button type="submit" onclick="return confirm('Delete this photo?')" class="w-6 h-6 rounded-full bg-red-600/90 hover:bg-red-700 text-white flex items-center justify-center text-[10px] shadow" title="Delete photo">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                                <span class="absolute bottom-0 inset-x-0 bg-slate-900/70 text-[9px] text-white text-center py-0.5"><?= date('M j, g:i A', strtotime($img['created_at'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Add More Old Office Photos Bar -->
            <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button type="button" onclick="openAddPhotosModal('old_office')" class="px-3 py-1.5 bg-white hover:bg-slate-100 text-sky-700 border border-slate-300 rounded-lg text-xs font-semibold shadow-sm flex items-center space-x-1.5">
                    <i class="fa-solid fa-camera"></i>
                    <span>Add Old Office Photos</span>
                </button>
            </div>
        </div>

        <!-- RIGHT COLUMN: PRODUCT REACHED NEW OFFICE PHOTOS -->
        <div class="bg-white rounded-2xl border-2 <?= ($item['status'] === 'reached') ? 'border-emerald-300' : 'border-slate-200' ?> shadow-sm overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="px-5 py-4 <?= ($item['status'] === 'reached') ? 'bg-emerald-50 border-b border-emerald-200' : 'bg-slate-100 border-b border-slate-200' ?> flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="w-3 h-3 rounded-full <?= ($item['status'] === 'reached') ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
                    <h3 class="font-bold text-slate-800 text-sm sm:text-base">Images at Product Reached (New Office)</h3>
                </div>
                <span class="text-xs font-semibold px-2 py-0.5 <?= ($item['status'] === 'reached') ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?> rounded-full">
                    <?= count($reachedImages) ?> photo<?= count($reachedImages) !== 1 ? 's' : '' ?>
                </span>
            </div>

            <!-- Notes Section -->
            <div class="p-4 bg-emerald-50/30 border-b border-slate-100 text-xs text-slate-600">
                <strong class="text-emerald-900 block mb-0.5"><i class="fa-solid fa-clipboard-check mr-1 text-emerald-600"></i> Arrival Remarks:</strong>
                <?php if ($item['status'] === 'reached'): ?>
                    <?= nl2br(htmlspecialchars($item['reached_notes'] ?: 'Item verified and reached in good condition.')) ?>
                <?php else: ?>
                    <span class="italic text-slate-400">Item has not reached yet. Go to <a href="reached.php?scan=<?= urlencode($item['serial_number']) ?>" class="text-emerald-600 font-bold underline">Checklist</a> to mark arrived.</span>
                <?php endif; ?>
            </div>

            <!-- Gallery Grid -->
            <div class="p-5 flex-grow">
                <?php if (empty($reachedImages)): ?>
                    <div class="text-center py-12 text-slate-400">
                        <i class="fa-solid fa-camera text-4xl mb-2 text-slate-300"></i>
                        <p class="text-xs font-semibold">No arrival photos recorded yet.</p>
                        <?php if ($item['status'] !== 'reached'): ?>
                            <a href="reached.php?scan=<?= urlencode($item['serial_number']) ?>" class="inline-block mt-3 px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-semibold">
                                Mark as Reached & Snap Photos
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <?php foreach ($reachedImages as $img): ?>
                            <div class="relative group rounded-xl overflow-hidden aspect-square border border-emerald-200 shadow-sm bg-slate-100 cursor-pointer" onclick="openLightbox('<?= htmlspecialchars($img['image_path']) ?>', '<?= htmlspecialchars($item['brand'] . ' ' . $item['model'] . ' - New Office Arrival Stage') ?>')">
                                <img src="<?= htmlspecialchars($img['image_path']) ?>" class="w-full h-full object-cover img-zoom-hover">
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-sm transition-opacity">
                                    <i class="fa-solid fa-magnifying-glass-plus"></i>
                                </div>
                                <form action="api/delete-item.php" method="POST" class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity" onclick="event.stopPropagation();">
                                    <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                    <button type="submit" onclick="return confirm('Delete this photo?')" class="w-6 h-6 rounded-full bg-red-600/90 hover:bg-red-700 text-white flex items-center justify-center text-[10px] shadow" title="Delete photo">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                                <span class="absolute bottom-0 inset-x-0 bg-emerald-950/70 text-[9px] text-white text-center py-0.5"><?= date('M j, g:i A', strtotime($img['created_at'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Add More Reached Photos Bar -->
            <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button type="button" onclick="openAddPhotosModal('reached_office')" class="px-3 py-1.5 bg-white hover:bg-emerald-50 text-emerald-700 border border-emerald-300 rounded-lg text-xs font-semibold shadow-sm flex items-center space-x-1.5">
                    <i class="fa-solid fa-camera"></i>
                    <span>Add Arrival Photos</span>
                </button>
            </div>
        </div>

    </div>
</div>

<!-- ======================================================= -->
<!-- MODAL: ADD PHOTOS TO EITHER STAGE                       -->
<!-- ======================================================= -->
<div id="addPhotosModal" class="fixed inset-0 z-50 bg-slate-900/85 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 flex flex-col">
        <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
            <h3 id="addPhotosModalTitle" class="font-bold text-base">Add Additional Photos</h3>
            <button type="button" onclick="closeAddPhotosModal()" class="text-slate-400 hover:text-white p-1">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form action="view.php?id=<?= $item['id'] ?>" method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
            <input type="hidden" name="action" value="add_photos">
            <input type="hidden" name="stage" id="targetStageInput" value="old_office">
            <input type="hidden" name="camera_images" id="extraCameraImages" value="[]">

            <p class="text-xs text-slate-500">Capture with camera or choose image files from your computer/device.</p>

            <div class="grid grid-cols-2 gap-3">
                <button type="button" onclick="openExtraCamera()" class="p-3 bg-sky-50 hover:bg-sky-100 border border-sky-300 rounded-xl text-center flex flex-col items-center justify-center">
                    <i class="fa-solid fa-camera text-sky-600 text-xl mb-1"></i>
                    <span class="text-xs font-bold text-slate-800">Inbuilt Camera</span>
                </button>
                <div class="p-3 bg-slate-50 hover:bg-slate-100 border border-slate-300 rounded-xl text-center relative flex flex-col items-center justify-center">
                    <i class="fa-solid fa-upload text-slate-600 text-xl mb-1"></i>
                    <span class="text-xs font-bold text-slate-800">Choose Files</span>
                    <input type="file" name="extra_photos[]" multiple accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer">
                </div>
            </div>

            <!-- Preview -->
            <div id="extraPhotosPreview" class="min-h-[40px] bg-slate-50 rounded-xl p-2 border border-slate-200 text-xs text-slate-400 italic">
                No new photos captured yet.
            </div>

            <div class="pt-3 border-t border-slate-200 flex justify-end gap-2">
                <button type="button" onclick="closeAddPhotosModal()" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg text-xs font-semibold">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-bold">
                    Upload & Save
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Dedicated camera modal for view.php extra photos -->
<div id="extraCameraModal" class="fixed inset-0 z-50 bg-slate-900/90 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 flex flex-col relative max-h-[92vh]">
        <div id="extraCameraModal_flash" class="absolute inset-0 bg-white z-30 pointer-events-none hidden transition-opacity"></div>
        <div class="px-5 py-3.5 bg-slate-900 text-white flex items-center justify-between">
            <h3 class="font-bold text-sm">Camera: Snap Photo</h3>
            <button type="button" onclick="closeExtraCamera()" class="text-slate-400 hover:text-white p-1">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <div class="relative bg-black aspect-[4/3] w-full overflow-hidden flex items-center justify-center">
            <video id="extraCameraVideo" autoplay playsinline muted class="w-full h-full object-cover"></video>
            <button type="button" onclick="extraPhotoModule.switchCamera()" class="absolute top-3 right-3 bg-black/60 text-white px-3 py-1.5 rounded-full text-xs font-medium">
                Switch Camera
            </button>
        </div>
        <div class="p-4 bg-slate-100 flex items-center justify-center">
            <button type="button" onclick="extraPhotoModule.takeSnapshot()" class="w-16 h-16 rounded-full bg-sky-600 hover:bg-sky-500 text-white flex items-center justify-center shadow-lg text-2xl">
                <i class="fa-solid fa-camera"></i>
            </button>
        </div>
        <div class="p-3 bg-white border-t border-slate-200 max-h-32 overflow-y-auto" id="extraCameraThumbnails"></div>
        <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
            <button type="button" onclick="closeExtraCamera()" class="px-5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-semibold">
                Done
            </button>
        </div>
    </div>
</div>

<script>
const extraPhotoModule = new PhotoCaptureModule({
    videoElementId: 'extraCameraVideo',
    previewContainerId: 'extraCameraThumbnails',
    hiddenInputId: 'extraCameraImages',
    modalId: 'extraCameraModal'
});
extraPhotoModule.configInstanceName = 'extraPhotoModule';

const origExtraUpdate = extraPhotoModule.updateHiddenInput.bind(extraPhotoModule);
extraPhotoModule.updateHiddenInput = function() {
    origExtraUpdate();
    const prev = document.getElementById('extraPhotosPreview');
    if (prev) {
        if (this.capturedImages.length === 0) {
            prev.innerHTML = '<div class="text-xs text-slate-400 italic">No photos captured yet.</div>';
        } else {
            let h = '<div class="flex flex-wrap gap-2">';
            this.capturedImages.forEach((img, i) => {
                h += `<div class="w-14 h-14 rounded-lg overflow-hidden border border-sky-400 shadow"><img src="${img}" class="w-full h-full object-cover"></div>`;
            });
            h += '</div>';
            prev.innerHTML = h;
        }
    }
};

function openAddPhotosModal(stage) {
    document.getElementById('targetStageInput').value = stage;
    document.getElementById('addPhotosModalTitle').textContent = (stage === 'reached_office') 
        ? 'Add Photos to Arrival Stage (New Office)' 
        : 'Add Photos to Packing Stage (Old Office)';
    const modal = document.getElementById('addPhotosModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeAddPhotosModal() {
    const modal = document.getElementById('addPhotosModal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
}

function openExtraCamera() {
    extraPhotoModule.open();
}
function closeExtraCamera() {
    extraPhotoModule.close();
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
