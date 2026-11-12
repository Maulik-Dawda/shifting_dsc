<?php
// reached.php - "Product Reached" Checklist Section
require_once __DIR__ . '/config.php';
$db = get_db();

$filter = $_GET['filter'] ?? 'pending'; // 'pending', 'reached', 'all'
$search = trim($_GET['search'] ?? '');
$scanSn = trim($_GET['scan'] ?? '');

// Build query
$where = [];
$params = [];

if ($filter === 'pending') {
    $where[] = "status != 'reached'";
} else if ($filter === 'reached') {
    $where[] = "status = 'reached'";
}

if (!empty($search)) {
    $where[] = "(brand LIKE :s1 OR model LIKE :s2 OR serial_number LIKE :s3 OR new_location LIKE :s4)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
    $params[':s4'] = "%$search%";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$stmt = $db->prepare("SELECT * FROM items $whereSql ORDER BY (status = 'reached') ASC, id DESC");
$stmt->execute($params);
$items = $stmt->fetchAll();

// Pre-load items map and photos map for safe JavaScript rendering (no broken quotes in HTML attributes!)
$itemsMap = [];
$photosMap = [];

foreach ($items as $it) {
    $itemsMap[$it['id']] = $it;
    $pStmt = $db->prepare("SELECT * FROM item_images WHERE item_id = :id AND stage = 'old_office'");
    $pStmt->execute([':id' => $it['id']]);
    $photosMap[$it['id']] = $pStmt->fetchAll();
}

// Counts for filter tabs
$totalCount = $db->query("SELECT COUNT(*) FROM items")->fetchColumn();
$pendingCount = $db->query("SELECT COUNT(*) FROM items WHERE status != 'reached'")->fetchColumn();
$reachedCount = $db->query("SELECT COUNT(*) FROM items WHERE status = 'reached'")->fetchColumn();

require_once __DIR__ . '/header.php';
?>

<!-- Breadcrumbs -->
<div class="mb-6 flex items-center justify-between">
    <div class="flex items-center text-xs text-slate-500 space-x-2">
        <a href="index.php" class="hover:text-sky-600">Dashboard</a>
        <i class="fa-solid fa-chevron-right text-[10px]"></i>
        <span class="text-slate-800 font-semibold">Product Reached Checklist</span>
    </div>
    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
        Stage 2: New Office Arrival Verification
    </span>
</div>

<!-- Notification Banner if any -->
<?php if (isset($_GET['msg'])): ?>
    <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl flex items-center justify-between shadow-sm text-sm">
        <div class="flex items-center space-x-2">
            <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
            <span class="font-medium"><?= htmlspecialchars($_GET['msg']) ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><i class="fa-solid fa-xmark"></i></button>
    </div>
<?php endif; ?>

<!-- Header Card & Barcode Scan Checkoff Bar -->
<div class="bg-gradient-to-r from-emerald-800 via-teal-900 to-slate-900 text-white rounded-2xl p-6 sm:p-7 shadow-lg mb-6 border border-emerald-700/50">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div>
            <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-xs font-semibold uppercase tracking-wider mb-2 border border-emerald-400/30">
                <i class="fa-solid fa-clipboard-check"></i>
                <span>Arrival Inspection & Checklist</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Product Reached Checklist</h1>
            <p class="text-xs sm:text-sm text-emerald-100/80 mt-1 max-w-xl">
                Tick items as they arrive at the new office. Capture fresh arrival photos with your camera to confirm condition and complete the relocation log.
            </p>
        </div>

        <!-- Scan to Checkoff Trigger -->
        <div class="bg-white/10 backdrop-blur-md p-4 rounded-xl border border-white/20 flex flex-col sm:flex-row items-center gap-3">
            <div class="text-center sm:text-left">
                <div class="text-xs font-bold uppercase tracking-wider text-emerald-300">Fast Arrival Check</div>
                <div class="text-xs text-white/80">Scan box barcode to tick off instantly</div>
            </div>
            <button type="button" onclick="openArrivalBarcodeScanner()" class="w-full sm:w-auto px-4 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs sm:text-sm rounded-lg shadow-md transition-all flex items-center justify-center space-x-2">
                <i class="fa-solid fa-barcode text-base"></i>
                <span>Scan Item Barcode</span>
            </button>
        </div>
    </div>
</div>

<!-- Checklist Controls: Filter Tabs & Search -->
<div class="bg-white rounded-2xl border border-slate-200 p-4 mb-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
    <!-- Filter Tabs -->
    <div class="flex items-center space-x-1 sm:space-x-2 bg-slate-100 p-1 rounded-xl">
        <a href="reached.php?filter=pending<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="px-3 sm:px-4 py-1.5 rounded-lg text-xs sm:text-sm font-semibold transition-all flex items-center space-x-1.5 <?= ($filter === 'pending') ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>Pending Arrival</span>
            <span class="ml-1 px-1.5 py-0.2 rounded-full text-[11px] <?= ($filter === 'pending') ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>"><?= $pendingCount ?></span>
        </a>
        <a href="reached.php?filter=reached<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="px-3 sm:px-4 py-1.5 rounded-lg text-xs sm:text-sm font-semibold transition-all flex items-center space-x-1.5 <?= ($filter === 'reached') ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>">
            <i class="fa-solid fa-circle-check"></i>
            <span>Reached / Verified</span>
            <span class="ml-1 px-1.5 py-0.2 rounded-full text-[11px] <?= ($filter === 'reached') ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>"><?= $reachedCount ?></span>
        </a>
        <a href="reached.php?filter=all<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="px-3 sm:px-4 py-1.5 rounded-lg text-xs sm:text-sm font-semibold transition-all flex items-center space-x-1.5 <?= ($filter === 'all') ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-600 hover:text-slate-900' ?>">
            <span>All Items</span>
            <span class="ml-1 px-1.5 py-0.2 rounded-full text-[11px] bg-slate-200 text-slate-600"><?= $totalCount ?></span>
        </a>
    </div>

    <!-- Search Box -->
    <form action="reached.php" method="GET" class="flex gap-2 w-full md:w-80">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <div class="relative flex-grow">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-xs"></i>
            </div>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search S/N, Brand, Model..." class="w-full pl-9 pr-3 py-1.5 text-xs sm:text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <?php if (!empty($search)): ?>
            <a href="reached.php?filter=<?= htmlspecialchars($filter) ?>" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs flex items-center">
                Clear
            </a>
        <?php endif; ?>
        <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold">
            Filter
        </button>
    </form>
</div>

<!-- ============================================== -->
<!-- CHECKLIST ITEMS LIST / CARDS                  -->
<!-- ============================================== -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
        <div class="text-xs font-bold uppercase tracking-wider text-slate-500">
            Checklist (<?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?>)
        </div>
        <div class="text-xs text-slate-500">
            Click checkbox or "Mark Arrived" to verify & capture photos
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div class="text-center py-16 text-slate-400">
            <i class="fa-solid fa-clipboard-check text-5xl mb-3 text-slate-300"></i>
            <p class="text-base font-semibold text-slate-600">No items match this checklist filter.</p>
            <p class="text-xs text-slate-400 mt-1">
                <?= ($filter === 'pending') ? 'All registered items have reached the new office!' : 'Try selecting another tab or packing new items.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="divide-y divide-slate-100">
            <?php foreach ($items as $item): ?>
                <?php
                    $isReached = ($item['status'] === 'reached');
                    $oldPhotos = $photosMap[$item['id']] ?? [];

                    // Fetch Reached Office images if already reached
                    $reachedPhotosStmt = $db->prepare("SELECT * FROM item_images WHERE item_id = :id AND stage = 'reached_office'");
                    $reachedPhotosStmt->execute([':id' => $item['id']]);
                    $reachedPhotos = $reachedPhotosStmt->fetchAll();
                ?>
                <div class="p-4 sm:p-5 hover:bg-slate-50/80 transition-colors flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 <?= $isReached ? 'bg-emerald-50/30' : '' ?>">
                    
                    <!-- Left: Checkbox & Product Details -->
                    <div class="flex items-start space-x-4 min-w-0 flex-grow">
                        <!-- Custom Interactive Checkbox / Arrival Trigger -->
                        <div class="pt-0.5 flex-shrink-0">
                            <?php if ($isReached): ?>
                                <button type="button" onclick="openArrivalModal(<?= $item['id'] ?>)" class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-md shadow-emerald-600/30 hover:scale-105 transition-transform" title="Arrived! Click to view or add more arrival photos">
                                    <i class="fa-solid fa-check text-sm"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" onclick="openArrivalModal(<?= $item['id'] ?>)" class="w-8 h-8 rounded-xl border-2 border-slate-300 hover:border-emerald-500 bg-white hover:bg-emerald-50 flex items-center justify-center text-slate-400 hover:text-emerald-600 shadow-sm transition-all group" title="Click to verify arrival & capture photo">
                                    <i class="fa-solid fa-check text-sm opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Old Office Thumbnail (if available) -->
                        <?php if (!empty($oldPhotos)): ?>
                            <div class="w-14 h-14 rounded-xl overflow-hidden bg-slate-100 border border-slate-200 flex-shrink-0 cursor-pointer group relative shadow-sm" onclick="openLightbox('<?= htmlspecialchars($oldPhotos[0]['image_path']) ?>', '<?= htmlspecialchars($item['brand'] . ' ' . $item['model'] . ' - Old Office Photo') ?>')">
                                <img src="<?= htmlspecialchars($oldPhotos[0]['image_path']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform">
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-xs transition-opacity">
                                    <i class="fa-solid fa-eye"></i>
                                </div>
                                <span class="absolute bottom-0 inset-x-0 bg-black/60 text-[8px] text-white text-center py-0.2">Old</span>
                            </div>
                        <?php else: ?>
                            <div class="w-14 h-14 rounded-xl bg-slate-100 border border-slate-200 flex-shrink-0 flex items-center justify-center text-slate-400 text-lg">
                                <i class="fa-solid fa-box"></i>
                            </div>
                        <?php endif; ?>

                        <!-- Item Metadata -->
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="font-bold text-slate-900 text-sm sm:text-base"><?= htmlspecialchars($item['brand']) ?> <?= htmlspecialchars($item['model']) ?></h4>
                                <?php if ($isReached): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                        <i class="fa-solid fa-check-circle mr-1"></i> Arrived at New Office
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                        <i class="fa-solid fa-clock mr-1"></i> In Transit / Pending
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-x-4 gap-y-1">
                                <span><i class="fa-solid fa-barcode text-slate-400 mr-1"></i> S/N: <strong class="text-slate-700 font-mono"><?= htmlspecialchars($item['serial_number']) ?></strong></span>
                                <span><i class="fa-solid fa-location-dot text-emerald-600 mr-1"></i> Destination: <strong class="text-slate-700"><?= htmlspecialchars($item['new_location'] ?: 'New Office') ?></strong></span>
                                <span><i class="fa-solid fa-layer-group text-slate-400 mr-1"></i> <?= htmlspecialchars($item['category']) ?></span>
                            </div>

                            <?php if ($isReached): ?>
                                <div class="text-[11px] text-emerald-700 mt-1 flex items-center space-x-2">
                                    <span><i class="fa-regular fa-clock mr-1"></i> Reached: <?= date('M j, Y g:i A', strtotime($item['reached_at'])) ?></span>
                                    <span>&bull; Verified by: <strong><?= htmlspecialchars($item['reached_by'] ?: 'Staff') ?></strong></span>
                                    <?php if (!empty($reachedPhotos)): ?>
                                        <span class="font-semibold text-emerald-800"><i class="fa-solid fa-camera mr-0.5"></i> <?= count($reachedPhotos) ?> arrival photo(s)</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right: Action Button -->
                    <div class="flex items-center space-x-2 w-full sm:w-auto justify-end pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                        <?php if (!$isReached): ?>
                            <button type="button" onclick="openArrivalModal(<?= $item['id'] ?>)" class="w-full sm:w-auto px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm shadow-emerald-600/20 transition-all flex items-center justify-center space-x-1.5">
                                <i class="fa-solid fa-camera"></i>
                                <span>Mark Arrived & Snap</span>
                            </button>
                        <?php else: ?>
                            <button type="button" onclick="openArrivalModal(<?= $item['id'] ?>)" class="px-3 py-1.5 text-xs font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 rounded-lg">
                                <i class="fa-solid fa-images mr-1"></i> Add Photos
                            </button>
                        <?php endif; ?>
                        <a href="view.php?id=<?= $item['id'] ?>" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold">
                            Compare
                        </a>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ======================================================= -->
<!-- ARRIVAL VERIFICATION & CAMERA CAPTURE MODAL             -->
<!-- ======================================================= -->
<div id="arrivalModal" class="fixed inset-0 z-50 bg-slate-900/85 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full overflow-hidden border border-slate-200 flex flex-col relative my-8">
        
        <!-- Modal Top Bar -->
        <div class="px-6 py-4 bg-gradient-to-r from-emerald-800 to-teal-900 text-white flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <i class="fa-solid fa-clipboard-check text-emerald-400 text-lg"></i>
                <div>
                    <h3 class="font-bold text-base leading-tight">Product Arrival Verification</h3>
                    <p class="text-[11px] text-emerald-200">Confirm item reached New Office & capture photos</p>
                </div>
            </div>
            <button type="button" onclick="closeArrivalModal()" class="text-emerald-200 hover:text-white p-1 rounded-lg">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form action="api/mark-reached.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
            <!-- Hidden Fields -->
            <input type="hidden" name="item_id" id="modalItemId" value="">
            <input type="hidden" name="serial_number" id="modalItemSerial" value="">
            <input type="hidden" name="camera_images" id="arrivalCameraImages" value="[]">

            <!-- Item Reference Card (Shows Old Office comparison thumbnail) -->
            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl flex items-center gap-3">
                <div id="modalOldPhotoThumb" class="w-14 h-14 rounded-lg bg-slate-200 overflow-hidden flex-shrink-0 flex items-center justify-center text-slate-400 text-sm">
                    <i class="fa-solid fa-box"></i>
                </div>
                <div class="min-w-0 flex-grow">
                    <h4 id="modalItemTitle" class="font-bold text-slate-900 text-sm truncate">Product Brand & Model</h4>
                    <div class="text-xs text-slate-500 mt-0.5 flex flex-wrap gap-x-3">
                        <span>S/N: <strong id="modalItemSerialDisplay" class="font-mono text-slate-800"></strong></span>
                        <span>Destination: <strong id="modalItemDestDisplay" class="text-emerald-700"></strong></span>
                    </div>
                </div>
            </div>

            <!-- Arrival Condition Check -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Arrival Physical Condition</label>
                    <select name="reached_condition" id="modalCondition" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none bg-white">
                        <option value="perfect" selected>✨ Intact / Perfect Condition</option>
                        <option value="minor_damage">⚠️ Minor Scratches / Cosmetic</option>
                        <option value="damaged">❌ Damaged in Transit</option>
                        <option value="missing_parts">❓ Missing Cables / Accessories</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Received & Verified By</label>
                    <input type="text" name="reached_by" id="modalReceivedBy" required value="Maulik Dawda" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                </div>
            </div>

            <!-- New Destination Location Update -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Assigned Desk / Room in New Office</label>
                <input type="text" name="new_location" id="modalNewLocationInput" placeholder="e.g. Floor 4, IT Bay Desk 12" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>

            <!-- Remarks / Arrival Notes -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Arrival Remarks / Inspection Notes</label>
                <textarea name="reached_notes" id="modalNotes" rows="2" placeholder="e.g. Box unpacked, device powered on, all original cables accounted for..." class="w-full px-3 py-2 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
            </div>

            <!-- ============================================== -->
            <!-- CAMERA & PHOTO CAPTURE AT PRODUCT REACHED       -->
            <!-- ============================================== -->
            <div class="border-t border-slate-200 pt-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-bold text-slate-900 uppercase tracking-wider">
                        <i class="fa-solid fa-camera mr-1 text-emerald-600"></i>
                        Capture Arrival Photos at New Office
                    </label>
                    <span class="text-[11px] text-emerald-700 font-semibold">Proof of condition</span>
                </div>
                <p class="text-xs text-slate-500 mb-3">Snap photos directly with the camera, or choose from device gallery.</p>

                <!-- Dual Trigger: Live Camera vs Native Camera / File Upload -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- Live Camera Trigger -->
                    <button type="button" onclick="openArrivalLiveCamera()" class="p-3 bg-emerald-50 hover:bg-emerald-100 border-2 border-dashed border-emerald-300 hover:border-emerald-500 rounded-xl text-center transition-all flex flex-col items-center justify-center">
                        <div class="w-10 h-10 rounded-full bg-emerald-600 text-white flex items-center justify-center text-lg mb-1 shadow-md shadow-emerald-600/30">
                            <i class="fa-solid fa-camera"></i>
                        </div>
                        <span class="font-bold text-xs text-slate-900">Inbuilt Live Camera</span>
                        <span class="text-[11px] text-slate-500">Capture photo directly now</span>
                    </button>

                    <!-- File Upload Input with Native Camera Support -->
                    <div class="p-3 bg-slate-50 hover:bg-slate-100 border-2 border-dashed border-slate-300 rounded-xl text-center relative flex flex-col items-center justify-center">
                        <div class="w-10 h-10 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-lg mb-1">
                            <i class="fa-solid fa-upload"></i>
                        </div>
                        <span class="font-bold text-xs text-slate-900">Device Camera / Gallery</span>
                        <span class="text-[11px] text-slate-500">Upload or snap on mobile</span>
                        <input type="file" name="arrival_photos[]" multiple accept="image/*" capture="environment" onchange="previewArrivalUploadedFiles(this)" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                    </div>
                </div>

                <!-- Preview Strip for Staged Arrival Photos -->
                <div class="mt-3">
                    <div id="arrivalPhotosPreview" class="min-h-[40px] bg-slate-50 border border-slate-200 rounded-xl p-2.5">
                        <div class="text-xs text-slate-400 italic">No arrival photos captured yet.</div>
                    </div>
                    <div id="arrivalUploadedFilesPreview" class="flex flex-wrap gap-2 mt-2"></div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
                <button type="button" onclick="closeArrivalModal()" class="px-4 py-2 border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-semibold">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm rounded-xl shadow-md shadow-emerald-600/30 flex items-center space-x-1.5 transition-all hover:-translate-y-0.5">
                    <i class="fa-solid fa-check"></i>
                    <span>Confirm Product Reached</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================= -->
<!-- INBUILT LIVE CAMERA MODAL FOR PRODUCT REACHED           -->
<!-- ======================================================= -->
<div id="arrivalLiveCameraModal" class="fixed inset-0 z-50 bg-slate-900/90 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 flex flex-col relative max-h-[92vh]">
        <div id="arrivalLiveCameraModal_flash" class="absolute inset-0 bg-white z-30 pointer-events-none hidden transition-opacity"></div>

        <!-- Header -->
        <div class="px-5 py-3.5 bg-emerald-900 text-white flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <i class="fa-solid fa-camera text-emerald-400"></i>
                <h3 class="font-bold text-sm sm:text-base">Camera: Snap New Office Arrival Photo</h3>
            </div>
            <button type="button" onclick="closeArrivalLiveCamera()" class="text-emerald-200 hover:text-white p-1 rounded-lg">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Viewport -->
        <div class="relative bg-black flex items-center justify-center aspect-[4/3] w-full overflow-hidden">
            <video id="arrivalCameraVideo" autoplay playsinline muted class="w-full h-full object-cover"></video>
            <button type="button" onclick="arrivalPhotoModule.switchCamera()" class="absolute top-3 right-3 bg-black/60 hover:bg-black/80 text-white px-3 py-1.5 rounded-full text-xs font-medium backdrop-blur-sm border border-white/20 shadow">
                <i class="fa-solid fa-camera-rotate mr-1"></i> Switch Camera
            </button>
        </div>

        <!-- Shutter Button -->
        <div class="p-4 bg-slate-100 flex items-center justify-center gap-4">
            <button type="button" onclick="arrivalPhotoModule.takeSnapshot()" class="w-16 h-16 rounded-full bg-emerald-600 hover:bg-emerald-500 text-white flex items-center justify-center shadow-lg shadow-emerald-600/40 ring-4 ring-emerald-200 active:scale-95 transition-all">
                <i class="fa-solid fa-camera text-2xl"></i>
            </button>
        </div>

        <!-- Snaps Review -->
        <div class="p-3 bg-white border-t border-slate-200 overflow-y-auto max-h-36">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-slate-500 uppercase">Arrival Snaps Taken:</span>
                <span id="arrivalSessionCount" class="text-xs text-emerald-600 font-bold">0 Snaps</span>
            </div>
            <div id="arrivalCameraThumbnails" class="min-h-[40px]">
                <span class="text-xs text-slate-400 italic">Click the camera button above to take snaps.</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
            <button type="button" onclick="closeArrivalLiveCamera()" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs sm:text-sm font-semibold">
                Done & Attach Snaps
            </button>
        </div>
    </div>
</div>

<!-- ======================================================= -->
<!-- SCANNER MODAL FOR ARRIVAL CHECKLIST                     -->
<!-- ======================================================= -->
<div id="arrivalBarcodeModal" class="fixed inset-0 z-50 bg-slate-900/85 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200 flex flex-col">
        <div class="px-5 py-4 bg-emerald-950 text-white flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <i class="fa-solid fa-barcode text-emerald-400 text-lg"></i>
                <h3 class="font-bold text-base">Scan Barcode to Check Off Item</h3>
            </div>
            <button type="button" onclick="closeArrivalBarcodeScanner()" class="text-emerald-200 hover:text-white p-1">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <div class="p-4 bg-slate-100 flex flex-col items-center">
            <div id="arrivalBarcodeReader" class="w-full max-w-[320px] aspect-square rounded-xl overflow-hidden bg-black shadow-inner flex items-center justify-center">
                <span class="text-slate-400 text-xs">Starting scanner...</span>
            </div>
            <div class="mt-3 w-full flex items-center justify-between text-xs text-slate-600 px-2">
                <span class="text-emerald-700 font-medium">Align barcode with scanner window</span>
                <button type="button" onclick="arrivalBarcodeScanner.switchCamera()" class="px-2.5 py-1 bg-white border border-slate-300 rounded shadow-sm text-slate-700 font-medium">
                    Switch Camera
                </button>
            </div>
        </div>

        <div class="p-4 bg-white border-t border-slate-100 flex justify-end">
            <button type="button" onclick="closeArrivalBarcodeScanner()" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg text-xs font-semibold">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
// Preloaded data from PHP (clean, secure JSON with hex encoding)
const allItemsMap = <?= json_encode($itemsMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const allPhotosMap = <?= json_encode($photosMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

// Arrival Camera Instance
const arrivalPhotoModule = new PhotoCaptureModule({
    videoElementId: 'arrivalCameraVideo',
    previewContainerId: 'arrivalCameraThumbnails',
    hiddenInputId: 'arrivalCameraImages',
    modalId: 'arrivalLiveCameraModal'
});
arrivalPhotoModule.configInstanceName = 'arrivalPhotoModule';

// Mirror snaps into arrival modal preview
const origArrivalUpdate = arrivalPhotoModule.updateHiddenInput.bind(arrivalPhotoModule);
arrivalPhotoModule.updateHiddenInput = function() {
    origArrivalUpdate();
    const modalPreview = document.getElementById('arrivalPhotosPreview');
    if (modalPreview) {
        if (this.capturedImages.length === 0) {
            modalPreview.innerHTML = '<div class="text-xs text-slate-400 italic">No arrival photos captured yet.</div>';
        } else {
            let h = '<div class="flex flex-wrap gap-2">';
            this.capturedImages.forEach((img, idx) => {
                h += `
                    <div class="relative w-16 h-16 rounded-lg overflow-hidden border border-emerald-400 shadow group">
                        <img src="${img}" class="w-full h-full object-cover">
                        <span class="absolute bottom-0 inset-x-0 bg-emerald-900/80 text-[9px] text-white text-center py-0.5">Photo #${idx + 1}</span>
                    </div>
                `;
            });
            h += '</div>';
            modalPreview.innerHTML = h;
        }
    }
    const cnt = document.getElementById('arrivalSessionCount');
    if (cnt) cnt.textContent = `${this.capturedImages.length} Snap${this.capturedImages.length !== 1 ? 's' : ''}`;
};

function openArrivalLiveCamera() {
    arrivalPhotoModule.open();
}
function closeArrivalLiveCamera() {
    arrivalPhotoModule.close();
}

// Arrival Barcode Scanner Instance
const arrivalBarcodeScanner = new BarcodeScannerModule({
    readerElementId: 'arrivalBarcodeReader',
    modalId: 'arrivalBarcodeModal',
    onScanSuccess: async (scannedSerial) => {
        closeArrivalBarcodeScanner();
        // Lookup item and open modal
        try {
            const resp = await fetch(`api/scan-lookup.php?sn=${encodeURIComponent(scannedSerial)}`);
            const data = await resp.json();
            if (data.success && data.item) {
                // If in allItemsMap, use id; otherwise open directly
                openArrivalModalDirect(data.item, data.item.old_images.map(p => ({ image_path: p })));
            } else {
                Swal.fire('Not Found', `Item with S/N ${scannedSerial} was not found.`, 'warning');
            }
        } catch (e) {
            Swal.fire('Error', 'Failed to lookup item: ' + e.message, 'error');
        }
    }
});

function openArrivalBarcodeScanner() {
    arrivalBarcodeScanner.open();
}
function closeArrivalBarcodeScanner() {
    arrivalBarcodeScanner.close();
}

// Open Arrival Modal by Item ID (Reliable, no broken HTML quotes)
function openArrivalModal(itemId) {
    const item = allItemsMap[itemId];
    if (!item) {
        Swal.fire('Error', 'Item data not found.', 'error');
        return;
    }
    const oldPhotos = allPhotosMap[itemId] || [];
    openArrivalModalDirect(item, oldPhotos);
}

// Direct populator
function openArrivalModalDirect(item, oldPhotos = []) {
    document.getElementById('modalItemId').value = item.id;
    document.getElementById('modalItemSerial').value = item.serial_number;
    document.getElementById('modalItemTitle').textContent = `${item.brand} ${item.model}`;
    document.getElementById('modalItemSerialDisplay').textContent = item.serial_number;
    document.getElementById('modalItemDestDisplay').textContent = item.new_location || 'New Office';
    document.getElementById('modalNewLocationInput').value = item.new_location || '';
    
    if (item.reached_condition) {
        document.getElementById('modalCondition').value = item.reached_condition;
    }
    if (item.reached_notes) {
        document.getElementById('modalNotes').value = item.reached_notes;
    }

    // Set old office photo thumbnail for verification reference
    const thumbBox = document.getElementById('modalOldPhotoThumb');
    if (oldPhotos && oldPhotos.length > 0 && oldPhotos[0].image_path) {
        thumbBox.innerHTML = `<img src="${oldPhotos[0].image_path}" class="w-full h-full object-cover rounded-lg" title="Old Office Photo">`;
    } else {
        thumbBox.innerHTML = '<i class="fa-solid fa-box text-slate-400"></i>';
    }

    // Reset captured photos for fresh arrival entry
    arrivalPhotoModule.capturedImages = [];
    arrivalPhotoModule.updateHiddenInput();

    const modal = document.getElementById('arrivalModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeArrivalModal() {
    const modal = document.getElementById('arrivalModal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
}

// File upload preview in arrival modal
function previewArrivalUploadedFiles(input) {
    const preview = document.getElementById('arrivalUploadedFilesPreview');
    preview.innerHTML = '';
    if (input.files) {
        Array.from(input.files).forEach((file) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'relative w-16 h-16 rounded-lg overflow-hidden border border-slate-300 shadow';
                div.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover"><span class="absolute bottom-0 inset-x-0 bg-slate-900/70 text-[9px] text-white text-center py-0.5 truncate px-1">${file.name}</span>`;
                preview.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    }
}

// If scan parameter was passed in URL (e.g. from Dashboard or Quick Scan), open modal automatically!
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const autoScan = urlParams.get('scan');
    if (autoScan) {
        fetch(`api/scan-lookup.php?sn=${encodeURIComponent(autoScan)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.item) {
                    const oldPhotos = data.item.old_images.map(p => ({ image_path: p }));
                    openArrivalModalDirect(data.item, oldPhotos);
                }
            });
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
