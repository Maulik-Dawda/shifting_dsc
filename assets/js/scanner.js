/**
 * assets/js/scanner.js
 * Barcode & QR Code Scanner using Html5Qrcode for DSC Shifting Inventory
 */

class BarcodeScannerModule {
    constructor(config) {
        this.readerElementId = config.readerElementId;
        this.targetInputId = config.targetInputId;
        this.modalId = config.modalId;
        this.onScanSuccessCallback = config.onScanSuccess;
        
        this.html5QrCode = null;
        this.currentCameraId = null;
        this.cameras = [];
        this.isScanning = false;
        
        this.audioCtx = null;
    }

    /**
     * Synthesize clean barcode scanner beep
     */
    playBeep() {
        try {
            if (!this.audioCtx) {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                this.audioCtx = new AudioContext();
            }
            if (this.audioCtx.state === 'suspended') {
                this.audioCtx.resume();
            }
            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(1750, this.audioCtx.currentTime);
            gain.gain.setValueAtTime(0.3, this.audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, this.audioCtx.currentTime + 0.12);
            osc.connect(gain);
            gain.connect(this.audioCtx.destination);
            osc.start();
            osc.stop(this.audioCtx.currentTime + 0.12);
        } catch (e) {
            // Audio context failed
        }
    }

    /**
     * Start Scanner
     */
    async start() {
        if (this.isScanning) return;
        
        const reader = document.getElementById(this.readerElementId);
        if (!reader) return;

        try {
            if (!this.html5QrCode) {
                this.html5QrCode = new Html5Qrcode(this.readerElementId);
            }

            // Get available video input cameras
            const devices = await Html5Qrcode.getCameras();
            if (devices && devices.length > 0) {
                this.cameras = devices;
                // Prefer back camera if available
                const backCam = devices.find(device => /back|rear|environment/i.test(device.label));
                this.currentCameraId = backCam ? backCam.id : devices[0].id;
            } else {
                this.currentCameraId = { facingMode: "environment" };
            }

            const scanConfig = {
                fps: 15,
                qrbox: { width: 260, height: 180 },
                aspectRatio: 1.0,
                formatsToSupport: [
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.EAN_8,
                    Html5QrcodeSupportedFormats.UPC_A,
                    Html5QrcodeSupportedFormats.UPC_E,
                    Html5QrcodeSupportedFormats.QR_CODE,
                    Html5QrcodeSupportedFormats.DATA_MATRIX
                ]
            };

            await this.html5QrCode.start(
                this.currentCameraId,
                scanConfig,
                (decodedText, decodedResult) => {
                    this.onScanSuccess(decodedText, decodedResult);
                },
                (errorMessage) => {
                    // Frame scan failed, normal scanning loop
                }
            );

            this.isScanning = true;

        } catch (err) {
            console.error("Scanner start error:", err);
            // Fallback attempt with environment facingMode constraint
            try {
                await this.html5QrCode.start(
                    { facingMode: "environment" },
                    { fps: 15, qrbox: { width: 250, height: 160 } },
                    (decodedText, decodedResult) => this.onScanSuccess(decodedText, decodedResult),
                    () => {}
                );
                this.isScanning = true;
            } catch (fallbackErr) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Camera Access Needed',
                    text: 'Please allow camera permission to scan barcodes.'
                });
            }
        }
    }

    /**
     * Stop Scanner
     */
    async stop() {
        if (this.html5QrCode && this.isScanning) {
            try {
                await this.html5QrCode.stop();
                this.isScanning = false;
            } catch (err) {
                console.warn("Scanner stop warning:", err);
            }
        }
    }

    /**
     * Switch between available cameras
     */
    async switchCamera() {
        if (this.cameras.length > 1) {
            const currentIdx = this.cameras.findIndex(c => c.id === this.currentCameraId);
            const nextIdx = (currentIdx + 1) % this.cameras.length;
            this.currentCameraId = this.cameras[nextIdx].id;
            await this.stop();
            await this.start();
        } else {
            // Toggle facingMode
            await this.stop();
            this.currentCameraId = (this.currentCameraId && this.currentCameraId.facingMode === 'user') 
                ? { facingMode: 'environment' } 
                : { facingMode: 'user' };
            await this.start();
        }
    }

    /**
     * Handle successfully scanned barcode/QR
     */
    onScanSuccess(decodedText, decodedResult) {
        this.playBeep();
        
        // Populate target input if specified
        if (this.targetInputId) {
            const targetInput = document.getElementById(this.targetInputId);
            if (targetInput) {
                targetInput.value = decodedText.trim();
                // trigger change event
                targetInput.dispatchEvent(new Event('change', { bubbles: true }));
                targetInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        // Close modal
        this.close();

        // Custom callback
        if (typeof this.onScanSuccessCallback === 'function') {
            this.onScanSuccessCallback(decodedText.trim(), decodedResult);
        }
    }

    /**
     * Open Scanner Modal
     */
    open() {
        const modal = document.getElementById(this.modalId);
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            this.start();
        }
    }

    /**
     * Close Scanner Modal
     */
    close() {
        this.stop();
        const modal = document.getElementById(this.modalId);
        if (modal) {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }
    }
}
