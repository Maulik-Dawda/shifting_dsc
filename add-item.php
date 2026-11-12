<?php
// add-item.php
require_once __DIR__ . '/config.php';
$db = get_db();

$error = '';
$prefillSn = isset($_GET['sn']) ? trim($_GET['sn']) : '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $serialNumber = trim($_POST['serial_number'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    $oldLocation = trim($_POST['old_location'] ?? '');
    $newLocation = trim($_POST['new_location'] ?? '');
    $status = trim($_POST['status'] ?? 'packed');
    $oldCondition = trim($_POST['old_condition'] ?? '');

    if (empty($brand) || empty($model) || empty($serialNumber)) {
        $error = 'Brand, Model, and Serial Number (S/N) are required.';
    } else {
        // Check if serial number already exists
        $checkStmt = $db->prepare("SELECT id FROM items WHERE UPPER(serial_number) = UPPER(:sn)");
        $checkStmt->execute([':sn' => $serialNumber]);
        if ($checkStmt->fetch()) {
            $error = "An item with Serial Number '{$serialNumber}' is already registered in inventory.";
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO items (
                        brand, model, serial_number, category, old_location, 
                        new_location, status, old_condition, created_at
                    ) VALUES (
                        :brand, :model, :serial_number, :category, :old_location, 
                        :new_location, :status, :old_condition, :created_at
                    )
                ");

                $stmt->execute([
                    ':brand' => $brand,
                    ':model' => $model,
                    ':serial_number' => $serialNumber,
                    ':category' => $category,
                    ':old_location' => $oldLocation,
                    ':new_location' => $newLocation,
                    ':status' => $status,
                    ':old_condition' => $oldCondition,
                    ':created_at' => date('Y-m-d H:i:s')
                ]);

                $itemId = $db->lastInsertId();
                $savedImagesCount = 0;

                // 1. Process Base64 Camera Captures
                if (!empty($_POST['camera_images'])) {
                    $cameraImages = json_decode($_POST['camera_images'], true);
                    if (is_array($cameraImages)) {
                        $imgStmt = $db->prepare("INSERT INTO item_images (item_id, stage, image_path) VALUES (:item_id, 'old_office', :image_path)");
                        foreach ($cameraImages as $base64Img) {
                            if (!empty($base64Img)) {
                                $savedPath = save_base64_image($base64Img, 'old_office');
                                if ($savedPath) {
                                    $imgStmt->execute([
                                        ':item_id' => $itemId,
                                        ':image_path' => $savedPath
                                    ]);
                                    $savedImagesCount++;
                                }
                            }
                        }
                    }
                }

                // 2. Process Standard Uploaded Files
                if (isset($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
                    $imgStmt = $db->prepare("INSERT INTO item_images (item_id, stage, image_path) VALUES (:item_id, 'old_office', :image_path)");
                    $totalFiles = count($_FILES['photos']['name']);
                    for ($i = 0; $i < $totalFiles; $i++) {
                        if (!empty($_FILES['photos']['tmp_name'][$i])) {
                            $fileItem = [
                                'name' => $_FILES['photos']['name'][$i],
                                'type' => $_FILES['photos']['type'][$i],
                                'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                                'error' => $_FILES['photos']['error'][$i],
                                'size' => $_FILES['photos']['size'][$i],
                            ];
                            $savedPath = save_uploaded_file($fileItem, 'old_office');
                            if ($savedPath) {
                                $imgStmt->execute([
                                    ':item_id' => $itemId,
                                    ':image_path' => $savedPath
                                ]);
                                $savedImagesCount++;
                            }
                        }
                    }
                }

                // Log Activity
                log_activity($itemId, 'Item Packed', "Registered item {$brand} {$model} (S/N: {$serialNumber}) at Old Office with {$savedImagesCount} photo(s).");

                header("Location: view.php?id={$itemId}&msg=" . urlencode("Item {$brand} {$model} registered successfully with {$savedImagesCount} photo(s)!"));
                exit;

            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<!-- Breadcrumb -->
<div class="mb-6 flex items-center text-xs text-slate-500 space-x-2">
    <a href="index.php" class="hover:text-sky-600">Dashboard</a>
    <i class="fa-solid fa-chevron-right text-[10px]"></i>
    <a href="items.php" class="hover:text-sky-600">Inventory</a>
    <i class="fa-solid fa-chevron-right text-[10px]"></i>
    <span class="text-slate-800 font-semibold">Pack New Item (Old Office)</span>
</div>

<div class="max-w-4xl mx-auto">
    <!-- Form Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <!-- Card Header -->
        <div class="px-6 py-5 bg-gradient-to-r from-slate-900 to-slate-800 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold flex items-center">
                    <i class="fa-solid fa-boxes-packing mr-2.5 text-sky-400"></i>
                    Register & Pack Item at Old Office
                </h2>
                <p class="text-xs text-slate-300 mt-1">Capture product photos and scan barcode/serial number before shifting.</p>
            </div>
            <div class="flex items-center space-x-2">
                <span class="px-2.5 py-1 bg-amber-500/20 text-amber-300 border border-amber-400/30 rounded-lg text-xs font-semibold uppercase tracking-wider">
                    Stage 1: Old Office
                </span>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="m-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm flex items-center space-x-2">
                <i class="fa-solid fa-circle-exclamation text-red-500 text-lg"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="add-item.php" method="POST" enctype="multipart/form-data" class="p-6 sm:p-8 space-y-6">
            <!-- Hidden Input for In-built Camera Photos (JSON array) -->
            <input type="hidden" name="camera_images" id="cameraImagesInput" value="[]">

            <!-- Section 1: Item Identification -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4 flex items-center">
                    <span class="w-6 h-6 rounded-full bg-sky-100 text-sky-600 inline-flex items-center justify-center text-xs mr-2">1</span>
                    Product Identification
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Brand -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Brand <span class="text-red-500">*</span></label>
                        <input type="text" name="brand" id="brandInput" list="brandList" required placeholder="e.g. Dell, HP, Apple, Cisco" value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>" class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none transition-shadow">
                        <datalist id="brandList">
                            <option value="Dell">
                            <option value="HP">
                            <option value="Apple">
                            <option value="Lenovo">
                            <option value="Cisco">
                            <option value="Samsung">
                            <option value="Logitech">
                            <option value="Canon">
                            <option value="Epson">
                            <option value="LG">
                            <option value="Microsoft">
                            <option value="Herman Miller">
                        </datalist>
                    </div>

                    <!-- Model -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Model Name / Number <span class="text-red-500">*</span></label>
                        <input type="text" name="model" id="modelInput" required placeholder="e.g. Latitude 5420, ProDesk 400" value="<?= htmlspecialchars($_POST['model'] ?? '') ?>" class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none transition-shadow">
                    </div>

                    <!-- Serial Number with Barcode Scanner Integration -->
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">
                            Serial Number (S/N) <span class="text-red-500">*</span>
                        </label>
                        <div class="flex gap-2">
                            <div class="relative flex-grow">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-solid fa-barcode"></i>
                                </div>
                                <input type="text" name="serial_number" id="serialNumberInput" required placeholder="Scan barcode or type S/N" value="<?= htmlspecialchars($_POST['serial_number'] ?? $prefillSn) ?>" class="w-full pl-10 pr-3.5 py-2.5 text-sm font-mono tracking-wider border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none uppercase bg-slate-50 focus:bg-white transition-all">
                            </div>
                            <!-- Barcode Scanner Trigger Button -->
                            <button type="button" onclick="openItemBarcodeScanner()" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-medium text-xs sm:text-sm shadow-sm transition-colors flex-shrink-0">
                                <i class="fa-solid fa-camera mr-2"></i>
                                <span>Scan Barcode</span>
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">Click "Scan Barcode" to use your device camera to scan Code-128, QR Code, or serial barcodes.</p>
                    </div>

                    <!-- Category -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Category</label>
                        <select name="category" class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none bg-white">
                            <option value="Workstation">Desktop / Workstation</option>
                            <option value="Laptops">Laptop / Notebook</option>
                            <option value="Monitors">Monitor / Display</option>
                            <option value="Printers">Printer / Scanner</option>
                            <option value="Networking">Networking (Switch/Router/AP)</option>
                            <option value="Server">Server & Storage</option>
                            <option value="Furniture">Furniture (Desk/Chair/Cabinet)</option>
                            <option value="AV & Display">TV / Projector / Audio</option>
                            <option value="Peripherals">Keyboard, Mouse, Docking Station</option>
                            <option value="General">Other / General Equipment</option>
                        </select>
                    </div>

                    <!-- Initial Status -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Packing Status</label>
                        <select name="status" class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none bg-white">
                            <option value="packed" selected>Packed & Tagged (Ready to Move)</option>
                            <option value="in_transit">In Transit (Loaded in Moving Truck)</option>
                        </select>
                    </div>
                </div>
            </div>

            <hr class="border-slate-100">

            <!-- Section 2: Locations & Condition -->
            <div>
                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4 flex items-center">
                    <span class="w-6 h-6 rounded-full bg-sky-100 text-sky-600 inline-flex items-center justify-center text-xs mr-2">2</span>
                    Relocation Route & Condition
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Old Office Location</label>
                        <input type="text" name="old_location" placeholder="e.g. 2nd Floor, IT Room, Desk 04" value="<?= htmlspecialchars($_POST['old_location'] ?? '') ?>" class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">New Office Destination</label>
                        <input type="text" name="new_location" placeholder="e.g. 4th Floor, Tech Hub, Desk 12" value="<?= htmlspecialchars($_POST['new_location'] ?? '') ?>" class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Condition & Packing Remarks (Old Office)</label>
                        <textarea name="old_condition" rows="2" placeholder="Note physical condition, existing cosmetic marks, included cables, adapters, or accessories in the box..." class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none"><?= htmlspecialchars($_POST['old_condition'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <hr class="border-slate-100">

            <!-- Section 3: Old Office Photos with Inbuilt Camera & Multi-upload -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center">
                        <span class="w-6 h-6 rounded-full bg-sky-100 text-sky-600 inline-flex items-center justify-center text-xs mr-2">3</span>
                        Photos at Old Office (Upload Multiple)
                    </h3>
                    <span class="text-xs text-sky-600 font-semibold flex items-center">
                        <i class="fa-solid fa-camera mr-1"></i> Live Camera Ready
                    </span>
                </div>
                <p class="text-xs text-slate-500 mb-4">Capture photos showing serial number plate, general condition, and packaging before shifting.</p>

                <!-- Dual Option Photo Box -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Option A: Inbuilt Camera Button -->
                    <div class="border-2 border-dashed border-sky-300 hover:border-sky-500 bg-sky-50/50 hover:bg-sky-50 rounded-2xl p-5 text-center transition-all cursor-pointer flex flex-col items-center justify-center" onclick="openItemCamera()">
                        <div class="w-12 h-12 rounded-full bg-sky-600 text-white flex items-center justify-center text-xl mb-2 shadow-md shadow-sky-600/30">
                            <i class="fa-solid fa-camera"></i>
                        </div>
                        <h4 class="font-bold text-sm text-slate-900">Inbuilt Camera Capture</h4>
                        <p class="text-xs text-slate-500 mt-1">Open device camera to snap photos directly into the software.</p>
                        <button type="button" class="mt-3 px-3 py-1.5 bg-sky-600 text-white rounded-lg text-xs font-semibold">
                            Open Camera
                        </button>
                    </div>

                    <!-- Option B: Traditional File Upload -->
                    <div class="border-2 border-dashed border-slate-300 hover:border-slate-400 bg-slate-50 rounded-2xl p-5 text-center transition-all relative flex flex-col items-center justify-center">
                        <div class="w-12 h-12 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-xl mb-2">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                        </div>
                        <h4 class="font-bold text-sm text-slate-900">Upload Image Files</h4>
                        <p class="text-xs text-slate-500 mt-1">Select one or more photos from computer / gallery.</p>
                        <input type="file" name="photos[]" id="filePhotosInput" multiple accept="image/*" onchange="previewSelectedFiles(this)" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                        <span class="mt-3 px-3 py-1.5 bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold pointer-events-none">
                            Browse Files
                        </span>
                    </div>
                </div>

                <!-- Combined Photos Preview Area -->
                <div class="mt-4">
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-2">Photos Staged for Upload:</label>
                    
                    <!-- Camera Photos Preview -->
                    <div id="cameraPhotosPreview" class="min-h-[40px] bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <div class="text-xs text-slate-400 italic">No photos taken or selected yet. Click "Inbuilt Camera Capture" or "Upload Image Files".</div>
                    </div>

                    <!-- File Input Preview -->
                    <div id="fileUploadsPreview" class="flex flex-wrap gap-2 mt-2"></div>
                </div>
            </div>

            <!-- Submit Button Bar -->
            <div class="pt-4 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-end gap-3">
                <a href="items.php" class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 font-semibold text-sm hover:bg-slate-50 text-center transition-colors">
                    Cancel
                </a>
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-sm shadow-md shadow-sky-600/30 transition-all hover:-translate-y-0.5 flex items-center justify-center">
                    <i class="fa-solid fa-check mr-2"></i> Save & Pack Item
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================== -->
<!-- MODAL: DEDICATED INBUILT CAMERA FOR ADD ITEM   -->
<!-- ============================================== -->
<div id="itemCameraModal" class="fixed inset-0 z-50 bg-slate-900/85 backdrop-blur-sm hidden items-center justify-center p-3 sm:p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 flex flex-col relative max-h-[92vh]">
        <!-- Shutter flash animation layer -->
        <div id="itemCameraModal_flash" class="absolute inset-0 bg-white z-30 pointer-events-none hidden transition-opacity"></div>

        <!-- Modal Header -->
        <div class="px-5 py-3.5 bg-slate-900 text-white flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <i class="fa-solid fa-camera text-sky-400"></i>
                <h3 class="font-bold text-sm sm:text-base">Camera: Capture Old Office Photos</h3>
            </div>
            <button type="button" onclick="closeItemCamera()" class="text-slate-400 hover:text-white p-1 rounded-lg">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Video Stream Viewport -->
        <div class="relative bg-black flex items-center justify-center aspect-[4/3] w-full overflow-hidden">
            <video id="itemCameraVideo" autoplay playsinline muted class="w-full h-full object-cover"></video>
            
            <!-- Controls Over Video: Switch Camera -->
            <button type="button" onclick="itemPhotoModule.switchCamera()" class="absolute top-3 right-3 bg-black/60 hover:bg-black/80 text-white px-3 py-1.5 rounded-full text-xs font-medium backdrop-blur-sm border border-white/20 shadow">
                <i class="fa-solid fa-camera-rotate mr-1"></i> Switch Camera
            </button>
        </div>

        <!-- Shutter Action Bar -->
        <div class="p-4 bg-slate-100 flex items-center justify-center gap-4">
            <button type="button" onclick="itemPhotoModule.takeSnapshot()" class="w-16 h-16 rounded-full bg-sky-600 hover:bg-sky-500 text-white flex items-center justify-center shadow-lg shadow-sky-600/40 ring-4 ring-sky-200 active:scale-95 transition-all">
                <i class="fa-solid fa-camera text-2xl"></i>
            </button>
        </div>

        <!-- Captured Snaps Review Strip -->
        <div class="p-3 bg-white border-t border-slate-200 overflow-y-auto max-h-36">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-slate-500 uppercase">Captured Photos in Session:</span>
                <span id="sessionPhotoCount" class="text-xs text-sky-600 font-bold">0 Snaps</span>
            </div>
            <div id="itemCameraThumbnails" class="min-h-[40px]">
                <span class="text-xs text-slate-400 italic">Click the camera button above to take snaps.</span>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
            <button type="button" onclick="closeItemCamera()" class="px-5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs sm:text-sm font-semibold">
                Done & Attach Photos
            </button>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- MODAL: DEDICATED BARCODE SCANNER FOR S/N       -->
<!-- ============================================== -->
<div id="itemBarcodeModal" class="fixed inset-0 z-50 bg-slate-900/85 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200 flex flex-col">
        <!-- Header -->
        <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <i class="fa-solid fa-barcode text-sky-400 text-lg"></i>
                <h3 class="font-bold text-base">Scan Serial Number Barcode</h3>
            </div>
            <button type="button" onclick="closeItemBarcodeScanner()" class="text-slate-400 hover:text-white p-1 rounded-lg">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Viewport -->
        <div class="p-4 bg-slate-100 flex flex-col items-center">
            <div id="itemBarcodeReader" class="w-full max-w-[320px] aspect-square rounded-xl overflow-hidden bg-black shadow-inner flex items-center justify-center">
                <span class="text-slate-400 text-xs">Initializing scanner...</span>
            </div>

            <div class="mt-3 w-full flex items-center justify-between text-xs text-slate-600 px-2">
                <span class="text-sky-600 font-medium">Point camera at serial barcode / QR code</span>
                <button type="button" onclick="itemBarcodeScanner.switchCamera()" class="px-2.5 py-1 bg-white border border-slate-300 rounded shadow-sm text-slate-700 font-medium hover:bg-slate-50">
                    Switch Camera
                </button>
            </div>
        </div>

        <!-- Footer -->
        <div class="p-4 bg-white border-t border-slate-100 flex justify-end">
            <button type="button" onclick="closeItemBarcodeScanner()" class="px-4 py-2 border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-lg text-xs font-semibold">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
// Initialize Camera module instance for Add Item page
const itemPhotoModule = new PhotoCaptureModule({
    videoElementId: 'itemCameraVideo',
    previewContainerId: 'itemCameraThumbnails',
    hiddenInputId: 'cameraImagesInput',
    modalId: 'itemCameraModal'
});
itemPhotoModule.configInstanceName = 'itemPhotoModule';

// Hook up preview container sync when snaps are added
const origUpdateHidden = itemPhotoModule.updateHiddenInput.bind(itemPhotoModule);
itemPhotoModule.updateHiddenInput = function() {
    origUpdateHidden();
    // Also mirror thumbnails to the main form preview
    const formPreview = document.getElementById('cameraPhotosPreview');
    if (formPreview) {
        if (this.capturedImages.length === 0) {
            formPreview.innerHTML = '<div class="text-xs text-slate-400 italic">No camera photos captured.</div>';
        } else {
            let h = '<div class="flex flex-wrap gap-2">';
            this.capturedImages.forEach((img, idx) => {
                h += `
                    <div class="relative w-16 h-16 rounded-lg overflow-hidden border border-sky-400 shadow group">
                        <img src="${img}" class="w-full h-full object-cover">
                        <span class="absolute bottom-0 inset-x-0 bg-sky-900/80 text-[9px] text-white text-center py-0.5">Photo #${idx + 1}</span>
                    </div>
                `;
            });
            h += '</div>';
            formPreview.innerHTML = h;
        }
    }
    const cnt = document.getElementById('sessionPhotoCount');
    if (cnt) cnt.textContent = `${this.capturedImages.length} Snap${this.capturedImages.length !== 1 ? 's' : ''}`;
};

function openItemCamera() {
    itemPhotoModule.open();
}
function closeItemCamera() {
    itemPhotoModule.close();
}

// Initialize Barcode Scanner instance for S/N input
const itemBarcodeScanner = new BarcodeScannerModule({
    readerElementId: 'itemBarcodeReader',
    targetInputId: 'serialNumberInput',
    modalId: 'itemBarcodeModal',
    onScanSuccess: (scannedText) => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: `Barcode Scanned: ${scannedText}`,
            showConfirmButton: false,
            timer: 2500
        });
    }
});

function openItemBarcodeScanner() {
    itemBarcodeScanner.open();
}
function closeItemBarcodeScanner() {
    itemBarcodeScanner.close();
}

// Preview standard file uploads
function previewSelectedFiles(input) {
    const preview = document.getElementById('fileUploadsPreview');
    preview.innerHTML = '';
    if (input.files) {
        Array.from(input.files).forEach((file, i) => {
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
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
