/**
 * assets/js/app.js
 * Core application routines & global barcode lookup
 */

// Mobile navigation menu toggle
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    if (menu) {
        menu.classList.toggle('hidden');
    }
}

// Global Quick Scanner Instance
let globalScanner = null;

function initGlobalScanner() {
    if (!globalScanner && document.getElementById('globalScannerReader')) {
        globalScanner = new BarcodeScannerModule({
            readerElementId: 'globalScannerReader',
            targetInputId: 'manualScanInput',
            modalId: 'globalScannerModal',
            onScanSuccess: (barcode) => {
                handleGlobalBarcodeDetected(barcode);
            }
        });
    }
}

function openGlobalScanner() {
    initGlobalScanner();
    if (globalScanner) {
        globalScanner.open();
    }
}

function closeGlobalScanner() {
    if (globalScanner) {
        globalScanner.close();
    }
}

function switchGlobalScannerCamera() {
    if (globalScanner) {
        globalScanner.switchCamera();
    }
}

function lookupManualSerial() {
    const input = document.getElementById('manualScanInput');
    if (!input || !input.value.trim()) {
        Swal.fire('Empty Serial', 'Please enter a valid serial number.', 'info');
        return;
    }
    handleGlobalBarcodeDetected(input.value.trim());
}

/**
 * When a barcode is detected by the global scanner
 */
async function handleGlobalBarcodeDetected(barcode) {
    closeGlobalScanner();
    
    Swal.fire({
        title: 'Looking up Item...',
        text: 'Searching Barcode: ' + barcode,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const response = await fetch(`api/scan-lookup.php?barcode=${encodeURIComponent(barcode)}`);
        const result = await response.json();

        if (result.success && result.item) {
            Swal.close();
            const itemBc = result.item.barcode_id || result.item.serial_number;
            const itemSn = result.item.serial_number || 'None';

            // Prompt user: Mark Reached or View details
            if (result.item.status !== 'reached') {
                Swal.fire({
                    icon: 'success',
                    title: `${result.item.brand} ${result.item.model}`,
                    html: `
                        <div class="text-left text-sm bg-slate-50 p-3 rounded-lg border border-slate-200 mb-3 space-y-1">
                            <div><strong>Barcode ID:</strong> <code class="bg-emerald-100 text-emerald-800 px-1 py-0.5 rounded font-bold">${itemBc}</code></div>
                            <div><strong>Serial Number (S/N):</strong> <span class="font-mono text-slate-700">${itemSn}</span></div>
                            <div><strong>Status:</strong> <span class="capitalize font-semibold text-amber-600">${result.item.status}</span></div>
                            <div><strong>Destination:</strong> ${result.item.new_location || 'Dubai Design District'}</div>
                        </div>
                        <p class="text-xs text-slate-500">Would you like to mark this item as reached at Dubai Design District or view full details?</p>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-clipboard-check mr-1"></i> Mark as Arrived',
                    confirmButtonColor: '#059669',
                    cancelButtonText: '<i class="fa-solid fa-eye mr-1"></i> View Details',
                    cancelButtonColor: '#0284c7'
                }).then((action) => {
                    if (action.isConfirmed) {
                        window.location.href = `reached.php?scan=${encodeURIComponent(itemBc)}`;
                    } else if (action.dismiss === Swal.DismissReason.cancel) {
                        window.location.href = `view.php?id=${result.item.id}`;
                    }
                });
            } else {
                // Already reached
                Swal.fire({
                    icon: 'info',
                    title: `${result.item.brand} ${result.item.model}`,
                    html: `
                        <div class="text-left text-sm bg-slate-50 p-3 rounded-lg border border-slate-200 mb-3 space-y-1">
                            <div><strong>Barcode ID:</strong> <code class="bg-emerald-100 text-emerald-800 px-1 py-0.5 rounded font-bold">${itemBc}</code></div>
                            <div><strong>S/N:</strong> <span class="font-mono">${itemSn}</span></div>
                            <div class="text-emerald-700 font-semibold"><i class="fa-solid fa-circle-check mr-1"></i> Arrived at Dubai Design District</div>
                        </div>
                    `,
                    confirmButtonText: 'View Item Details',
                    confirmButtonColor: '#0284c7'
                }).then(() => {
                    window.location.href = `view.php?id=${result.item.id}`;
                });
            }
        } else {
            Swal.fire({
                icon: 'question',
                title: 'Barcode Not Found',
                text: `No registered item found with Barcode ID "${barcode}".`,
                showCancelButton: true,
                confirmButtonText: 'Register as New Item',
                confirmButtonColor: '#0284c7',
                cancelButtonText: 'Cancel'
            }).then((action) => {
                if (action.isConfirmed) {
                    window.location.href = `add-item.php?bc=${encodeURIComponent(barcode)}`;
                }
            });
        }
    } catch (e) {
        Swal.fire('Error', 'Failed to communicate with inventory server.', 'error');
    }
}

/**
 * Image Lightbox Viewer
 */
function openLightbox(imgSrc, caption = '') {
    const lightbox = document.getElementById('globalLightbox');
    const lightboxImg = document.getElementById('lightboxImg');
    const lightboxCaption = document.getElementById('lightboxCaption');
    if (lightbox && lightboxImg) {
        lightboxImg.src = imgSrc;
        lightboxCaption.textContent = caption;
        lightbox.classList.remove('hidden');
        lightbox.classList.add('flex');
    }
}

function closeLightbox(event) {
    if (event && event.target && event.target.id === 'lightboxImg') {
        return; // don't close if clicking the image itself
    }
    const lightbox = document.getElementById('globalLightbox');
    if (lightbox) {
        lightbox.classList.remove('flex');
        lightbox.classList.add('hidden');
    }
}

// Render barcodes on page load if elements with class .barcode-svg exist
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('svg[data-barcode]').forEach((svg) => {
        const val = svg.getAttribute('data-barcode');
        if (val && typeof JsBarcode !== 'undefined') {
            try {
                JsBarcode(svg, val, {
                    format: "CODE128",
                    lineColor: "#1e293b",
                    width: 1.6,
                    height: 40,
                    displayValue: true,
                    fontSize: 12,
                    margin: 5
                });
            } catch (err) {
                console.warn('Barcode render error for', val, err);
            }
        }
    });
});
