<?php
// items.php - All Inventory Items
require_once __DIR__ . '/config.php';
$db = get_db();

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$status = trim($_GET['status'] ?? '');
$export = isset($_GET['export']) && $_GET['export'] === 'csv';

// Build Query
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(brand LIKE :s1 OR model LIKE :s2 OR barcode_id LIKE :s3 OR serial_number LIKE :s4 OR old_location LIKE :s5 OR new_location LIKE :s6)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
    $params[':s4'] = "%$search%";
    $params[':s5'] = "%$search%";
    $params[':s6'] = "%$search%";
}

if (!empty($category)) {
    $where[] = "category = :cat";
    $params[':cat'] = $category;
}

if (!empty($status)) {
    $where[] = "status = :st";
    $params[':st'] = $status;
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$stmt = $db->prepare("SELECT * FROM items $whereSql ORDER BY id DESC");
$stmt->execute($params);
$items = $stmt->fetchAll();

// CSV Export
if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=dsc_shifting_inventory_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Brand', 'Model', 'Barcode ID', 'Serial Number', 'Category', 'Status', 'Old Location', 'New Location', 'Created At', 'Reached At', 'Reached By', 'Reached Condition', 'Remarks']);
    foreach ($items as $row) {
        fputcsv($output, [
            $row['id'],
            $row['brand'],
            $row['model'],
            $row['barcode_id'] ?: $row['serial_number'],
            $row['serial_number'],
            $row['category'],
            $row['status'],
            $row['old_location'],
            $row['new_location'],
            $row['created_at'],
            $row['reached_at'],
            $row['reached_by'],
            $row['reached_condition'],
            $row['old_condition'] . ' | ' . $row['reached_notes']
        ]);
    }
    fclose($output);
    exit;
}

// Fetch categories for dropdown
$categories = $db->query("SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/header.php';
?>

<!-- Breadcrumb & Title -->
<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Complete Inventory Catalog</h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">Manage and monitor all assets during office relocation.</p>
    </div>
    <div class="flex items-center space-x-2">
        <a href="items.php?export=csv<?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($category) ? '&category='.urlencode($category) : '' ?><?= !empty($status) ? '&status='.urlencode($status) : '' ?>" class="px-3.5 py-2 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-sm flex items-center space-x-1.5">
            <i class="fa-solid fa-file-csv text-emerald-600 text-sm"></i>
            <span>Export CSV</span>
        </a>
        <a href="add-item.php" class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs sm:text-sm font-bold shadow-md shadow-sky-600/20 flex items-center space-x-1.5 transition-all">
            <i class="fa-solid fa-plus"></i>
            <span>Pack New Item</span>
        </a>
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="bg-white rounded-2xl border border-slate-200 p-4 mb-6 shadow-sm">
    <form action="items.php" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- Search Keyword -->
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-xs"></i>
            </div>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search Brand, Model, S/N..." class="w-full pl-9 pr-3 py-2 text-xs sm:text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none">
        </div>

        <!-- Filter by Category -->
        <div>
            <select name="category" class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none bg-white">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= ($category === $cat) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Filter by Status -->
        <div>
            <select name="status" class="w-full px-3 py-2 text-xs sm:text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 focus:outline-none bg-white">
                <option value="">All Statuses</option>
                <option value="packed" <?= ($status === 'packed') ? 'selected' : '' ?>>Packed at Hamdan Sports Complex</option>
                <option value="in_transit" <?= ($status === 'in_transit') ? 'selected' : '' ?>>In Transit</option>
                <option value="reached" <?= ($status === 'reached') ? 'selected' : '' ?>>Reached Dubai Design District</option>
                <option value="damaged" <?= ($status === 'damaged') ? 'selected' : '' ?>>Damaged / Attention</option>
            </select>
        </div>

        <!-- Filter Actions -->
        <div class="flex gap-2">
            <button type="submit" class="flex-grow px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs sm:text-sm font-semibold transition-colors">
                Apply Filters
            </button>
            <?php if (!empty($search) || !empty($category) || !empty($status)): ?>
                <a href="items.php" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs flex items-center justify-center">
                    Reset
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Table Card -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <th class="py-3 px-4 sm:px-6">Product / Brand</th>
                    <th class="py-3 px-4">Barcode ID</th>
                    <th class="py-3 px-4">Serial Number (S/N)</th>
                    <th class="py-3 px-4">Category</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4">Route</th>
                    <th class="py-3 px-4 text-center">Photos</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="8" class="py-12 text-center text-slate-400">
                            <i class="fa-solid fa-box-open text-4xl mb-2 text-slate-300 block"></i>
                            No items found matching the selected criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                            $imgCount = $db->query("SELECT COUNT(*) FROM item_images WHERE item_id = " . intval($item['id']))->fetchColumn();
                        ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <!-- Product -->
                            <td class="py-3.5 px-4 sm:px-6 font-semibold text-slate-900">
                                <a href="view.php?id=<?= $item['id'] ?>" class="hover:text-sky-600">
                                    <?= htmlspecialchars($item['brand']) ?> <?= htmlspecialchars($item['model']) ?>
                                </a>
                            </td>
                            <!-- Barcode ID -->
                            <td class="py-3.5 px-4 font-mono text-xs">
                                <span class="bg-emerald-50 text-emerald-800 px-2 py-1 rounded font-bold border border-emerald-200 flex items-center w-fit space-x-1">
                                    <i class="fa-solid fa-barcode text-[11px] text-emerald-600"></i>
                                    <span><?= htmlspecialchars($item['barcode_id'] ?: $item['serial_number']) ?></span>
                                </span>
                            </td>
                            <!-- S/N (Manual) -->
                            <td class="py-3.5 px-4 font-mono text-xs text-slate-700">
                                <?= !empty($item['serial_number']) ? htmlspecialchars($item['serial_number']) : '<span class="text-slate-400 italic">None</span>' ?>
                            </td>
                            <!-- Category -->
                            <td class="py-3.5 px-4 text-xs text-slate-600">
                                <?= htmlspecialchars($item['category']) ?>
                            </td>
                            <!-- Status -->
                            <td class="py-3.5 px-4 text-xs">
                                <?php if ($item['status'] === 'reached'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full font-semibold bg-emerald-100 text-emerald-800">
                                        <i class="fa-solid fa-circle-check mr-1 text-[10px]"></i> Reached
                                    </span>
                                <?php elseif ($item['status'] === 'in_transit'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full font-medium bg-blue-100 text-blue-800">
                                        <i class="fa-solid fa-truck mr-1 text-[10px]"></i> In Transit
                                    </span>
                                <?php elseif ($item['status'] === 'damaged'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full font-bold bg-red-100 text-red-800">
                                        <i class="fa-solid fa-triangle-exclamation mr-1 text-[10px]"></i> Damaged
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full font-medium bg-amber-100 text-amber-800">
                                        <i class="fa-solid fa-box mr-1 text-[10px]"></i> Packed
                                    </span>
                                <?php endif; ?>
                            </td>
                            <!-- Route -->
                            <td class="py-3.5 px-4 text-xs text-slate-500">
                                <div class="truncate max-w-[180px]">
                                    <span class="text-slate-400">From:</span> <?= htmlspecialchars($item['old_location'] ?: 'Hamdan Sports Complex') ?><br>
                                    <span class="text-emerald-600 font-semibold">To:</span> <?= htmlspecialchars($item['new_location'] ?: 'Dubai Design District') ?>
                                </div>
                            </td>
                            <!-- Photos Count -->
                            <td class="py-3.5 px-4 text-center text-xs">
                                <?php if ($imgCount > 0): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 font-bold border border-sky-200">
                                        <i class="fa-solid fa-camera mr-1"></i> <?= $imgCount ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-300">-</span>
                                <?php endif; ?>
                            </td>
                            <!-- Actions -->
                            <td class="py-3.5 px-4 text-right text-xs">
                                <div class="flex items-center justify-end space-x-1.5">
                                    <?php if ($item['status'] !== 'reached'): ?>
                                        <a href="reached.php?scan=<?= urlencode($item['barcode_id'] ?: $item['serial_number']) ?>" class="p-1.5 text-emerald-600 hover:bg-emerald-50 rounded-lg" title="Mark Reached">
                                            <i class="fa-solid fa-clipboard-check text-base"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="view.php?id=<?= $item['id'] ?>" class="p-1.5 text-sky-600 hover:bg-sky-50 rounded-lg" title="View & Compare">
                                        <i class="fa-solid fa-eye text-base"></i>
                                    </a>
                                    <a href="edit-item.php?id=<?= $item['id'] ?>" class="p-1.5 text-slate-500 hover:bg-slate-100 rounded-lg" title="Edit">
                                        <i class="fa-solid fa-pen-to-square text-sm"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
