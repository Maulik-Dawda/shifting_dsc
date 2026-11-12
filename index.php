<?php
// index.php
require_once __DIR__ . '/config.php';
$db = get_db();

// Fetch summary metrics
$totalItems = $db->query("SELECT COUNT(*) FROM items")->fetchColumn();
$reachedItems = $db->query("SELECT COUNT(*) FROM items WHERE status = 'reached'")->fetchColumn();
$packedItems = $db->query("SELECT COUNT(*) FROM items WHERE status = 'packed'")->fetchColumn();
$inTransitItems = $db->query("SELECT COUNT(*) FROM items WHERE status = 'in_transit'")->fetchColumn();
$damagedItems = $db->query("SELECT COUNT(*) FROM items WHERE status = 'damaged' OR reached_condition IN ('minor_damage', 'damaged')")->fetchColumn();

$reachedPercent = ($totalItems > 0) ? round(($reachedItems / $totalItems) * 100) : 0;

// Fetch recent items
$recentItemsStmt = $db->query("SELECT * FROM items ORDER BY id DESC LIMIT 6");
$recentItems = $recentItemsStmt->fetchAll();

// Fetch recent activity logs
$activityStmt = $db->query("SELECT a.*, i.brand, i.model, i.serial_number FROM activity_logs a LEFT JOIN items i ON a.item_id = i.id ORDER BY a.id DESC LIMIT 5");
$activities = $activityStmt->fetchAll();

require_once __DIR__ . '/header.php';
?>

<!-- Notification Banner if any -->
<?php if (isset($_GET['msg'])): ?>
    <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl flex items-center justify-between shadow-sm">
        <div class="flex items-center space-x-2">
            <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
            <span class="font-medium text-sm"><?= htmlspecialchars($_GET['msg']) ?></span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><i class="fa-solid fa-xmark"></i></button>
    </div>
<?php endif; ?>

<!-- Welcome & Overview Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Office Relocation Dashboard</h1>
        <p class="text-sm text-slate-500 mt-1">Track packing at Hamdan Sports Complex, scan Barcode ID, and verify arrival at Dubai Design District.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="add-item.php" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-semibold text-sm shadow-md shadow-sky-600/20 transition-all hover:-translate-y-0.5">
            <i class="fa-solid fa-plus mr-2"></i> Pack New Item
        </a>
        <a href="reached.php" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm shadow-md shadow-emerald-600/20 transition-all hover:-translate-y-0.5">
            <i class="fa-solid fa-clipboard-check mr-2"></i> Arrival Checklist
        </a>
    </div>
</div>

<!-- Relocation Progress Card -->
<div class="bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 text-white rounded-2xl p-6 mb-8 shadow-xl relative overflow-hidden border border-slate-700">
    <div class="absolute right-0 top-0 bottom-0 w-1/3 opacity-10 flex items-center justify-end pr-8 pointer-events-none">
        <i class="fa-solid fa-truck-moving text-9xl text-white"></i>
    </div>
    
    <div class="relative z-10">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
            <div>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-sky-500/20 text-sky-300 border border-sky-400/30">
                    Relocation Status
                </span>
                <h2 class="text-xl sm:text-2xl font-bold mt-2">Overall Shift Progress: <?= $reachedPercent ?>%</h2>
                <p class="text-xs sm:text-sm text-slate-300 mt-1">
                    <?= $reachedItems ?> of <?= $totalItems ?> items have safely reached and been verified at Dubai Design District.
                </p>
            </div>
            <div class="text-right">
                <span class="text-3xl sm:text-4xl font-extrabold text-sky-400"><?= $reachedPercent ?>%</span>
                <span class="block text-xs text-slate-400">Completed</span>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="w-full bg-slate-700/60 rounded-full h-3.5 overflow-hidden p-0.5 border border-slate-600">
            <div class="bg-gradient-to-r from-sky-500 to-emerald-400 h-full rounded-full transition-all duration-700" style="width: <?= $reachedPercent ?>%"></div>
        </div>
    </div>
</div>

<!-- Metrics Overview Grid -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
    <!-- Total Items -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Inventory</span>
                <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-800 mt-1"><?= $totalItems ?></h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
        </div>
        <div class="mt-3 flex items-center text-xs text-slate-500">
            <span class="text-sky-600 font-semibold mr-1"><?= $packedItems ?></span> Packed at Hamdan Sports Complex
        </div>
    </div>

    <!-- Reached Dubai Design District -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Product Reached</span>
                <h3 class="text-2xl sm:text-3xl font-extrabold text-emerald-600 mt-1"><?= $reachedItems ?></h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>
        <div class="mt-3 flex items-center text-xs text-slate-500">
            <span class="text-emerald-600 font-semibold mr-1"><?= $reachedPercent ?>%</span> Verified at Dubai Design District
        </div>
    </div>

    <!-- In Transit / Pending Arrival -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Pending Arrival</span>
                <h3 class="text-2xl sm:text-3xl font-extrabold text-amber-500 mt-1"><?= ($totalItems - $reachedItems) ?></h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-clock"></i>
            </div>
        </div>
        <div class="mt-3 flex items-center text-xs text-slate-500">
            <a href="reached.php" class="text-amber-600 hover:underline font-medium">Open checklist to verify &rarr;</a>
        </div>
    </div>

    <!-- Condition Alert / Damage Check -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow transition-shadow">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Damage / Attention</span>
                <h3 class="text-2xl sm:text-3xl font-extrabold <?= ($damagedItems > 0) ? 'text-red-500' : 'text-slate-800' ?> mt-1"><?= $damagedItems ?></h3>
            </div>
            <div class="w-12 h-12 rounded-xl <?= ($damagedItems > 0) ? 'bg-red-50 text-red-500' : 'bg-slate-50 text-slate-400' ?> flex items-center justify-center text-xl">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
        </div>
        <div class="mt-3 flex items-center text-xs text-slate-500">
            <?= ($damagedItems > 0) ? '<span class="text-red-500 font-semibold">Requires inspection</span>' : '<span class="text-emerald-600 font-medium">No damaged items reported</span>' ?>
        </div>
    </div>
</div>

<!-- Two Columns: Recent Items & Activity Log -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Left Column (2 Cols): Recent Inventory Items -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <i class="fa-solid fa-box text-sky-600"></i>
                <h3 class="font-bold text-slate-800 text-base">Recently Registered Items</h3>
            </div>
            <a href="items.php" class="text-xs text-sky-600 hover:text-sky-800 font-semibold">View All Items (<?= $totalItems ?>) &rarr;</a>
        </div>

        <div class="divide-y divide-slate-100 overflow-x-auto">
            <?php if (empty($recentItems)): ?>
                <div class="text-center py-12 text-slate-400">
                    <i class="fa-solid fa-box-open text-4xl mb-3 text-slate-300"></i>
                    <p class="text-sm">No items registered yet.</p>
                    <a href="add-item.php" class="inline-block mt-3 text-xs bg-sky-600 text-white px-3 py-1.5 rounded-lg">Add First Item</a>
                </div>
            <?php else: ?>
                <?php foreach ($recentItems as $item): ?>
                    <?php
                        // Check if photos exist
                        $imgCount = $db->query("SELECT COUNT(*) FROM item_images WHERE item_id = " . intval($item['id']))->fetchColumn();
                        $statusBadge = match($item['status']) {
                            'reached' => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800"><i class="fa-solid fa-check mr-1"></i> Reached</span>',
                            'in_transit' => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"><i class="fa-solid fa-truck mr-1"></i> In Transit</span>',
                            'damaged' => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Damaged</span>',
                            default => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800"><i class="fa-solid fa-box mr-1"></i> Packed</span>'
                        };
                    ?>
                    <div class="p-4 sm:px-6 hover:bg-slate-50 transition-colors flex items-center justify-between gap-4">
                        <div class="flex items-center space-x-3 sm:space-x-4 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 flex-shrink-0 flex items-center justify-center text-slate-600 font-bold text-xs uppercase">
                                <?= substr(htmlspecialchars($item['brand']), 0, 2) ?>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h4 class="font-bold text-slate-900 text-sm truncate"><?= htmlspecialchars($item['brand']) ?> <?= htmlspecialchars($item['model']) ?></h4>
                                    <?= $statusBadge ?>
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span><i class="fa-solid fa-barcode mr-1 text-emerald-600"></i> <code class="bg-emerald-50 text-emerald-800 px-1 py-0.5 rounded font-bold"><?= htmlspecialchars($item['barcode_id'] ?: $item['serial_number']) ?></code></span>
                                    <?php if (!empty($item['serial_number'])): ?>
                                        <span><i class="fa-solid fa-hashtag mr-1 text-slate-400"></i> <code class="bg-slate-100 px-1 py-0.5 rounded text-slate-700"><?= htmlspecialchars($item['serial_number']) ?></code></span>
                                    <?php endif; ?>
                                    <span><i class="fa-solid fa-layer-group mr-1 text-slate-400"></i> <?= htmlspecialchars($item['category']) ?></span>
                                    <?php if ($imgCount > 0): ?>
                                        <span class="text-sky-600 font-medium"><i class="fa-solid fa-camera mr-1"></i> <?= $imgCount ?> photo<?= $imgCount > 1 ? 's' : '' ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 flex-shrink-0">
                            <?php if ($item['status'] !== 'reached'): ?>
                                <a href="reached.php?scan=<?= urlencode($item['barcode_id'] ?: $item['serial_number']) ?>" class="p-2 text-emerald-600 hover:bg-emerald-50 rounded-lg text-xs font-medium" title="Mark as Reached">
                                    <i class="fa-solid fa-clipboard-check text-base"></i>
                                </a>
                            <?php endif; ?>
                            <a href="view.php?id=<?= $item['id'] ?>" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-sky-50 text-slate-700 hover:text-sky-700 text-xs font-semibold border border-slate-200 transition-colors">
                                Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Recent Activity Logs & Quick Actions -->
    <div class="space-y-6">
        
        <!-- Quick Action Box -->
        <div class="bg-gradient-to-br from-sky-500 to-indigo-600 text-white rounded-2xl p-6 shadow-md shadow-sky-600/20">
            <h3 class="font-bold text-lg mb-2">Relocation Quick Tools</h3>
            <p class="text-xs text-sky-100 mb-4">Fast capture and verification tools built directly into the software.</p>
            
            <div class="space-y-2.5">
                <button type="button" onclick="openGlobalScanner()" class="w-full bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold flex items-center justify-between transition-colors border border-white/20">
                    <span class="flex items-center"><i class="fa-solid fa-barcode text-base mr-2"></i> Scan S/N Barcode</span>
                    <i class="fa-solid fa-camera"></i>
                </button>
                <a href="add-item.php" class="w-full bg-white text-slate-800 hover:bg-slate-100 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold flex items-center justify-between transition-colors shadow">
                    <span class="flex items-center"><i class="fa-solid fa-plus text-sky-600 mr-2"></i> Pack New Item</span>
                    <i class="fa-solid fa-arrow-right text-xs text-slate-400"></i>
                </a>
                <a href="reached.php" class="w-full bg-emerald-500 hover:bg-emerald-400 text-white px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold flex items-center justify-between transition-colors shadow">
                    <span class="flex items-center"><i class="fa-solid fa-clipboard-check mr-2"></i> Product Reached Checklist</span>
                    <i class="fa-solid fa-arrow-right text-xs text-emerald-200"></i>
                </a>
            </div>
        </div>

        <!-- Activity Feed -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <h3 class="font-bold text-slate-800 text-sm mb-4 flex items-center space-x-2">
                <i class="fa-solid fa-clock-rotate-left text-slate-400"></i>
                <span>Recent Relocation Activity</span>
            </h3>

            <div class="space-y-4">
                <?php if (empty($activities)): ?>
                    <p class="text-xs text-slate-400 italic">No activity logged yet.</p>
                <?php else: ?>
                    <?php foreach ($activities as $act): ?>
                        <div class="flex items-start space-x-3 text-xs">
                            <div class="w-2 h-2 rounded-full bg-sky-500 mt-1.5 flex-shrink-0"></div>
                            <div class="flex-grow">
                                <div class="font-semibold text-slate-800"><?= htmlspecialchars($act['action']) ?></div>
                                <div class="text-slate-500 text-[11px] mt-0.5"><?= htmlspecialchars($act['details']) ?></div>
                                <div class="text-slate-400 text-[10px] mt-1"><?= date('M j, g:i A', strtotime($act['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
