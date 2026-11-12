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
async function handleGlobalBarcodeDetected(serialNumber) {
    closeGlobalScanner();
    
    Swal.fire({
        title: 'Looking up Item...',
        text: 'Searching for ' + serialNumber,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const response = await fetch(`api/scan-lookup.php?sn=${encodeURIComponent(serialNumber)}`);
        const result = await response.json();

        if (result.success && result.item) {
            Swal.close();
            // Prompt user where they want to go: View details or Mark Reached
            if (result.item.status !== 'reached') {
                Swal.fire({
                    icon: 'success',
                    title: `${result.item.brand} ${result.item.model}`,
                    html: `
                        <div class="text-left text-sm bg-slate-50 p-3 rounded-lg border border-slate-200 mb-3">
                            <div><strong>S/N:</strong> <code class="bg-slate-200 px-1 py-0.5 rounded">${result.item.serial_number}</code></div>
                            <div><strong>Current Status:</strong> <span class="capitalize font-semibold text-amber-600">${result.item.status}</span></div>
                            <div><strong>Source:</strong> ${result.item.old_location || 'Old Office'}</div>
                            <div><strong>Destination:</strong> ${result.item.new_location || 'New Office'}</div>
                        </div>
                        <p class="text-xs text-slate-500">Would you like to mark this item as reached at the new office or view full details?</p>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-clipboard-check mr-1"></i> Mark as Arrived',
                    confirmButtonColor: '#059669',
                    cancelButtonText: '<i class="fa-solid fa-eye mr-1"></i> View Details',
                    cancelButtonColor: '#0284c7'
                }).then((action) => {
                    if (action.isConfirmed) {
                        window.location.href = `reached.php?scan=${encodeURIComponent(result.item.serial_number)}`;
                    } else if (action.dismiss === Swal.DismissReason.cancel) {
                        window.location.href = `view.php?id=${result.item.id}`;
                    }
                });
            } else {
                // Already reached
                Swal.fire({
                    icon: 'info',
                    title: `${result.item.brand} ${result.item.model}`,
                    text: `This item has already reached the new office (Status: Reached).`,
                    confirmButtonText: 'View Item Details',
                    confirmButtonColor: '#0284c7'
                }).then(() => {
                    window.location.href = `view.php?id=${result.item.id}`;
                });
            }
        } else {
            Swal.fire({
                icon: 'question',
                title: 'Item Not Found',
                text: `No registered item found with Serial Number "${serialNumber}".`,
                showCancelButton: true,
                confirmButtonText: 'Register as New Item',
                confirmButtonColor: '#0284c7',
                cancelButtonText: 'Cancel'
            }).then((action) => {
                if (action.isConfirmed) {
                    window.location.href = `add-item.php?sn=${encodeURIComponent(serialNumber)}`;
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
