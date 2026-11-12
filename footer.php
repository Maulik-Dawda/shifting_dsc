    </main>

    <!-- Global Footer -->
    <footer class="bg-white border-t border-slate-200 mt-auto py-6 text-sm text-slate-500">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center space-x-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>ShiftTrack DSC &bull; Office Relocation Inventory System</span>
            </div>
            <div class="flex items-center space-x-6 text-xs text-slate-400">
                <span><i class="fa-solid fa-camera mr-1"></i> Inbuilt Camera</span>
                <span><i class="fa-solid fa-barcode mr-1"></i> Barcode Scanner</span>
                <span><i class="fa-solid fa-database mr-1"></i> <?= (DB_DRIVER === 'sqlite') ? 'SQLite Storage' : 'MySQL Database: ' . htmlspecialchars(DB_NAME ?: 'Hostinger') ?></span>
            </div>
        </div>
    </footer>

    <!-- ========================================== -->
    <!-- GLOBAL QUICK BARCODE SCANNER MODAL        -->
    <!-- ========================================== -->
    <div id="globalScannerModal" class="fixed inset-0 z-50 bg-slate-900/80 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200 flex flex-col">
            <!-- Modal Header -->
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <i class="fa-solid fa-barcode text-sky-400 text-lg"></i>
                    <h3 class="font-bold text-base">Quick Barcode Scanner</h3>
                </div>
                <button type="button" onclick="closeGlobalScanner()" class="text-slate-400 hover:text-white p-1 rounded-lg">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            
            <!-- Scanner Viewport Area -->
            <div class="p-4 bg-slate-100 flex flex-col items-center">
                <div id="globalScannerReader" class="w-full max-w-[340px] aspect-square rounded-xl overflow-hidden bg-black shadow-inner relative flex items-center justify-center">
                    <span class="text-slate-400 text-xs flex flex-col items-center">
                        <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 text-sky-500"></i>
                        Starting Camera...
                    </span>
                </div>
                
                <!-- Controls -->
                <div class="mt-3 w-full flex items-center justify-between text-xs text-slate-600 px-2">
                    <span id="globalScannerStatus" class="flex items-center text-sky-600 font-medium">
                        <span class="w-2 h-2 rounded-full bg-sky-500 mr-1.5 animate-ping"></span>
                        Scanning for Barcode / QR...
                    </span>
                    <button type="button" onclick="switchGlobalScannerCamera()" class="px-2.5 py-1 bg-white border border-slate-300 rounded shadow-sm hover:bg-slate-50 text-slate-700 font-medium">
                        <i class="fa-solid fa-camera-rotate mr-1"></i> Switch Camera
                    </button>
                </div>
            </div>

            <!-- Manual Search Fallback -->
            <div class="p-4 bg-white border-t border-slate-100">
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Or Enter Serial Number Manually</label>
                <div class="flex gap-2">
                    <input type="text" id="manualScanInput" placeholder="e.g. DSC-DT-7090-001" class="flex-grow px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 focus:outline-none uppercase">
                    <button type="button" onclick="lookupManualSerial()" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-sm font-medium rounded-lg">
                        Find
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- GLOBAL IMAGE LIGHTBOX MODAL                -->
    <!-- ========================================== -->
    <div id="globalLightbox" class="fixed inset-0 z-50 bg-black/90 hidden items-center justify-center p-4" onclick="closeLightbox(event)">
        <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center">
            <button type="button" onclick="closeLightbox()" class="absolute -top-10 right-0 text-white/80 hover:text-white text-2xl p-2">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <img id="lightboxImg" src="" alt="Enlarged Image" class="max-w-full max-h-[80vh] rounded-lg shadow-2xl object-contain">
            <p id="lightboxCaption" class="mt-3 text-slate-300 text-sm font-medium text-center"></p>
        </div>
    </div>

</body>
</html>
