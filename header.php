<?php
// header.php
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/config.php';
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ShiftTrack DSC - Office Relocation Inventory</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0f7ff',
                            100: '#e0effe',
                            500: '#0284c7',
                            600: '#0369a1',
                            700: '#075985',
                            800: '#0c4a6e',
                            900: '#082f49',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- HTML5-QRCode Scanner -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <!-- JsBarcode for rendering barcodes -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/custom.css">
    <script>
    // Helpful advisory if loaded on live domain over unencrypted HTTP (which blocks browser camera)
    if (location.protocol === 'http:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
        document.addEventListener('DOMContentLoaded', () => {
            const bar = document.createElement('div');
            bar.className = 'bg-amber-400 text-slate-900 px-4 py-2 text-xs font-medium flex items-center justify-between text-center sticky top-0 z-50 shadow';
            bar.innerHTML = '<span>🔒 <strong>Notice:</strong> Mobile browsers block live camera access on non-secure HTTP. Please use <strong>HTTPS (https://...)</strong> or use the "Device Camera / Gallery" button to capture photos.</span><button onclick="this.parentElement.remove()" class="ml-3 font-bold text-base px-2">&times;</button>';
            document.body.prepend(bar);
        });
    }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col font-sans antialiased">

    <!-- Top Navigation Bar -->
    <nav class="bg-slate-900 text-white sticky top-0 z-40 shadow-lg border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Brand / Logo -->
                <div class="flex items-center space-x-3">
                    <a href="index.php" class="flex items-center space-x-3 group">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-600 flex items-center justify-center text-white shadow-md shadow-sky-500/20 group-hover:scale-105 transition-transform">
                            <i class="fa-solid fa-truck-ramp-box text-lg"></i>
                        </div>
                        <div>
                            <span class="text-lg font-bold tracking-tight bg-gradient-to-r from-white via-sky-200 to-sky-400 bg-clip-text text-transparent">ShiftTrack DSC</span>
                            <span class="block text-[10px] text-sky-400 font-medium tracking-wider uppercase">Office Relocation Inventory</span>
                        </div>
                    </a>
                </div>

                <!-- Desktop Navigation Links -->
                <div class="hidden md:flex items-center space-x-1 lg:space-x-2">
                    <a href="index.php" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors <?= ($currentPage == 'index.php') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?>">
                        <i class="fa-solid fa-chart-pie mr-1.5"></i> Dashboard
                    </a>
                    <a href="items.php" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors <?= ($currentPage == 'items.php') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?>">
                        <i class="fa-solid fa-boxes-stacked mr-1.5"></i> All Items
                    </a>
                    <a href="add-item.php" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors <?= ($currentPage == 'add-item.php') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?>">
                        <i class="fa-solid fa-plus mr-1.5"></i> Pack Item
                    </a>
                    <a href="reached.php" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors <?= ($currentPage == 'reached.php') ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/30' : 'text-emerald-400 hover:text-white hover:bg-slate-800' ?>">
                        <i class="fa-solid fa-clipboard-check mr-1.5"></i> Product Reached (Checklist)
                    </a>
                </div>

                <!-- Action Button: Global Quick Barcode Scanner -->
                <div class="flex items-center space-x-2">
                    <button type="button" onclick="openGlobalScanner()" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-sky-500/10 hover:bg-sky-500/20 text-sky-400 hover:text-sky-300 border border-sky-500/30 text-xs sm:text-sm font-medium transition-all shadow-sm">
                        <i class="fa-solid fa-barcode mr-1.5 text-base"></i>
                        <span class="hidden sm:inline">Quick Scan</span>
                    </button>
                    <!-- Mobile Hamburger -->
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 focus:outline-none">
                        <i class="fa-solid fa-bars text-xl" id="menuIcon"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div id="mobileMenu" class="hidden md:hidden px-4 pt-2 pb-4 space-y-1 bg-slate-900 border-t border-slate-800">
            <a href="index.php" class="block px-3 py-2.5 rounded-lg text-base font-medium <?= ($currentPage == 'index.php') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-chart-pie mr-2 text-sky-400"></i> Dashboard
            </a>
            <a href="items.php" class="block px-3 py-2.5 rounded-lg text-base font-medium <?= ($currentPage == 'items.php') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-boxes-stacked mr-2 text-sky-400"></i> All Items
            </a>
            <a href="add-item.php" class="block px-3 py-2.5 rounded-lg text-base font-medium <?= ($currentPage == 'add-item.php') ? 'bg-sky-600 text-white' : 'text-slate-300 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-plus mr-2 text-sky-400"></i> Pack Item (Old Office)
            </a>
            <a href="reached.php" class="block px-3 py-2.5 rounded-lg text-base font-medium <?= ($currentPage == 'reached.php') ? 'bg-emerald-600 text-white' : 'text-emerald-400 hover:bg-slate-800' ?>">
                <i class="fa-solid fa-clipboard-check mr-2"></i> Product Reached (Checklist)
            </a>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
