<?php
// edit-item.php
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
    header("Location: items.php?err=" . urlencode("Item not found"));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $barcodeId = trim($_POST['barcode_id'] ?? '');
    $serialNumber = trim($_POST['serial_number'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    $oldLocation = trim($_POST['old_location'] ?? '');
    $newLocation = trim($_POST['new_location'] ?? '');
    $status = trim($_POST['status'] ?? 'packed');
    $oldCondition = trim($_POST['old_condition'] ?? '');
    $reachedCondition = trim($_POST['reached_condition'] ?? '');
    $reachedBy = trim($_POST['reached_by'] ?? '');
    $reachedNotes = trim($_POST['reached_notes'] ?? '');

    if (empty($brand) || empty($model) || empty($barcodeId)) {
        $error = 'Brand, Model, and Barcode ID are required.';
    } else {
        // Check duplicate barcode ID on another item
        $checkStmt = $db->prepare("SELECT id FROM items WHERE UPPER(barcode_id) = UPPER(:bc) AND id != :id");
        $checkStmt->execute([':bc' => $barcodeId, ':id' => $id]);
        if ($checkStmt->fetch()) {
            $error = "Barcode ID '{$barcodeId}' is already assigned to another item.";
        } else {
            try {
                $upStmt = $db->prepare("
                    UPDATE items SET
                        brand = :brand,
                        model = :model,
                        barcode_id = :barcode_id,
                        serial_number = :serial_number,
                        category = :category,
                        old_location = :old_location,
                        new_location = :new_location,
                        status = :status,
                        old_condition = :old_condition,
                        reached_condition = :reached_condition,
                        reached_by = :reached_by,
                        reached_notes = :reached_notes
                    WHERE id = :id
                ");

                $upStmt->execute([
                    ':brand' => $brand,
                    ':model' => $model,
                    ':barcode_id' => $barcodeId,
                    ':serial_number' => $serialNumber,
                    ':category' => $category,
                    ':old_location' => $oldLocation,
                    ':new_location' => $newLocation,
                    ':status' => $status,
                    ':old_condition' => $oldCondition,
                    ':reached_condition' => $reachedCondition ?: null,
                    ':reached_by' => $reachedBy ?: null,
                    ':reached_notes' => $reachedNotes ?: null,
                    ':id' => $id
                ]);

                log_activity($id, 'Item Updated', "Updated details for {$brand} {$model} (Barcode ID: {$barcodeId}, S/N: {$serialNumber})");
                header("Location: view.php?id={$id}&msg=" . urlencode("Item details updated successfully!"));
                exit;

            } catch (Exception $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<!-- Breadcrumbs -->
<div class="mb-6 flex items-center text-xs text-slate-500 space-x-2">
    <a href="index.php" class="hover:text-sky-600">Dashboard</a>
    <i class="fa-solid fa-chevron-right text-[10px]"></i>
    <a href="items.php" class="hover:text-sky-600">Inventory</a>
    <i class="fa-solid fa-chevron-right text-[10px]"></i>
    <a href="view.php?id=<?= $item['id'] ?>" class="hover:text-sky-600"><?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?></a>
    <i class="fa-solid fa-chevron-right text-[10px]"></i>
    <span class="text-slate-800 font-semibold">Edit Details</span>
</div>

<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-5 bg-slate-900 text-white flex items-center justify-between">
            <h2 class="text-lg font-bold flex items-center">
                <i class="fa-solid fa-pen-to-square mr-2 text-sky-400"></i>
                Edit Item: <?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?>
            </h2>
            <form action="api/delete-item.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this entire item and all its photos?');">
                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                <button type="submit" class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg shadow-sm">
                    <i class="fa-solid fa-trash mr-1"></i> Delete Item
                </button>
            </form>
        </div>

        <?php if (!empty($error)): ?>
            <div class="m-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="edit-item.php?id=<?= $item['id'] ?>" method="POST" class="p-6 sm:p-8 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Brand</label>
                    <input type="text" name="brand" required value="<?= htmlspecialchars($_POST['brand'] ?? $item['brand']) ?>" class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Model</label>
                    <input type="text" name="model" required value="<?= htmlspecialchars($_POST['model'] ?? $item['model']) ?>" class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Barcode ID <span class="text-red-500">*</span></label>
                    <input type="text" name="barcode_id" required value="<?= htmlspecialchars($_POST['barcode_id'] ?? ($item['barcode_id'] ?: $item['serial_number'])) ?>" class="w-full px-3.5 py-2.5 text-sm font-mono border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none uppercase bg-slate-50">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Serial Number (S/N) <span class="text-slate-400 font-normal lowercase">(manual)</span></label>
                    <input type="text" name="serial_number" value="<?= htmlspecialchars($_POST['serial_number'] ?? $item['serial_number']) ?>" placeholder="e.g. SN-882194-X" class="w-full px-3.5 py-2.5 text-sm font-mono border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none uppercase bg-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Category</label>
                    <select name="category" class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none bg-white">
                        <?php foreach (['Workstation', 'Laptops', 'Monitors', 'Printers', 'Networking', 'Server', 'Furniture', 'AV & Display', 'Peripherals', 'General'] as $cat): ?>
                            <option value="<?= $cat ?>" <?= ($item['category'] === $cat) ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Relocation Status</label>
                    <select name="status" class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none bg-white">
                        <option value="packed" <?= ($item['status'] === 'packed') ? 'selected' : '' ?>>Packed at Hamdan Sports Complex</option>
                        <option value="in_transit" <?= ($item['status'] === 'in_transit') ? 'selected' : '' ?>>In Transit</option>
                        <option value="reached" <?= ($item['status'] === 'reached') ? 'selected' : '' ?>>Reached Dubai Design District</option>
                        <option value="damaged" <?= ($item['status'] === 'damaged') ? 'selected' : '' ?>>Damaged</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Hamdan Sports Complex Location</label>
                    <input type="text" name="old_location" value="<?= htmlspecialchars($_POST['old_location'] ?? $item['old_location']) ?>" class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Dubai Design District Destination</label>
                    <input type="text" name="new_location" value="<?= htmlspecialchars($_POST['new_location'] ?? $item['new_location']) ?>" class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Hamdan Sports Complex Packing Notes</label>
                    <textarea name="old_condition" rows="2" class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none"><?= htmlspecialchars($_POST['old_condition'] ?? $item['old_condition']) ?></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Arrival Condition</label>
                    <select name="reached_condition" class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none bg-white">
                        <option value="">Not evaluated yet</option>
                        <option value="perfect" <?= ($item['reached_condition'] === 'perfect') ? 'selected' : '' ?>>✨ Intact / Perfect Condition</option>
                        <option value="minor_damage" <?= ($item['reached_condition'] === 'minor_damage') ? 'selected' : '' ?>>⚠️ Minor Cosmetic Scratches</option>
                        <option value="damaged" <?= ($item['reached_condition'] === 'damaged') ? 'selected' : '' ?>>❌ Damaged</option>
                        <option value="missing_parts" <?= ($item['reached_condition'] === 'missing_parts') ? 'selected' : '' ?>>❓ Missing Parts</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Received By</label>
                    <select name="reached_by" class="w-full px-3.5 py-2.5 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none bg-white font-medium">
                        <?= render_receiver_options($_POST['reached_by'] ?? $item['reached_by']) ?>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase mb-1">Arrival Inspection Remarks</label>
                    <textarea name="reached_notes" rows="2" class="w-full px-3.5 py-2 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none"><?= htmlspecialchars($_POST['reached_notes'] ?? $item['reached_notes']) ?></textarea>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-200 flex justify-end gap-3">
                <a href="view.php?id=<?= $item['id'] ?>" class="px-5 py-2.5 border border-slate-300 rounded-xl text-slate-700 text-xs font-semibold hover:bg-slate-50">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs sm:text-sm font-bold shadow-md">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
