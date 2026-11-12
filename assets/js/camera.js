/**
 * assets/js/camera.js
 * In-built Camera Capture System for DSC Shifting Inventory
 * Optimized for mobile, desktop, and all modern browsers
 */

class PhotoCaptureModule {
    constructor(config) {
        this.videoElementId = config.videoElementId || 'cameraVideo';
        this.previewContainerId = config.previewContainerId;
        this.hiddenInputId = config.hiddenInputId;
        this.modalId = config.modalId || 'cameraModal';
        this.statusElementId = config.statusElementId || null;
        
        this.videoStream = null;
        this.facingMode = 'environment'; // default to back camera for phone/tablet
        this.capturedImages = []; // Array of base64 strings
        
        this.audioCtx = null;
        this.availableDevices = [];
        this.currentDeviceIndex = 0;
    }

    /**
     * Play shutter click sound effect using Web Audio API
     */
    playShutterSound() {
        try {
            if (!this.audioCtx) {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (AudioContext) {
                    this.audioCtx = new AudioContext();
                }
            }
            if (this.audioCtx && this.audioCtx.state === 'suspended') {
                this.audioCtx.resume();
            }
            if (this.audioCtx) {
                const osc = this.audioCtx.createOscillator();
                const gain = this.audioCtx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(850, this.audioCtx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(150, this.audioCtx.currentTime + 0.09);
                gain.gain.setValueAtTime(0.4, this.audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, this.audioCtx.currentTime + 0.09);
                osc.connect(gain);
                gain.connect(this.audioCtx.destination);
                osc.start();
                osc.stop(this.audioCtx.currentTime + 0.09);
            }
        } catch (e) {
            // Audio blocked or unsupported, ignore
        }
    }

    /**
     * Check if camera is supported in this browser context
     */
    isSupported() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }

    /**
     * Start the camera feed
     */
    async startCamera() {
        this.stopCamera();
        const video = document.getElementById(this.videoElementId);
        if (!video) return;

        // Ensure video attributes for mobile browsers
        video.setAttribute('autoplay', '');
        video.setAttribute('muted', '');
        video.setAttribute('playsinline', '');
        video.muted = true;

        if (!this.isSupported()) {
            const isHttp = (location.protocol === 'http:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1');
            const reason = isHttp 
                ? 'Camera access in browsers requires a secure connection (HTTPS). Please open this website using https:// or use the "Open Device Camera / Gallery" option.'
                : 'Your browser does not support live camera access. Please use the "Open Device Camera / Gallery" option.';
            
            Swal.fire({
                icon: 'warning',
                title: 'Camera Access Notice',
                text: reason,
                confirmButtonColor: '#0284c7'
            });
            return;
        }

        try {
            // Enumerate devices if possible
            if (navigator.mediaDevices.enumerateDevices) {
                const devices = await navigator.mediaDevices.enumerateDevices();
                this.availableDevices = devices.filter(d => d.kind === 'videoinput');
            }
        } catch (e) {
            // continue
        }

        const constraintsList = [
            // Attempt 1: Facing mode preference
            { video: { facingMode: { ideal: this.facingMode } }, audio: false },
            // Attempt 2: Exact facing mode
            { video: { facingMode: this.facingMode }, audio: false },
            // Attempt 3: Simple video true
            { video: true, audio: false }
        ];

        let streamObtained = false;
        for (const constraint of constraintsList) {
            try {
                this.videoStream = await navigator.mediaDevices.getUserMedia(constraint);
                video.srcObject = this.videoStream;
                video.onloadedmetadata = () => {
                    video.play().catch(err => console.warn('Video play warning:', err));
                };
                streamObtained = true;
                break;
            } catch (err) {
                // try next constraint
            }
        }

        if (!streamObtained) {
            Swal.fire({
                icon: 'error',
                title: 'Camera Unavailable',
                text: 'Could not access the camera. Please check camera permissions in your browser or use the file upload option.',
                confirmButtonColor: '#0284c7'
            });
        }
    }

    /**
     * Stop the camera feed to release device hardware
     */
    stopCamera() {
        if (this.videoStream) {
            this.videoStream.getTracks().forEach(track => {
                try { track.stop(); } catch(e){}
            });
            this.videoStream = null;
        }
    }

    /**
     * Switch between Front and Back camera
     */
    async switchCamera() {
        this.facingMode = (this.facingMode === 'environment') ? 'user' : 'environment';
        await this.startCamera();
    }

    /**
     * Capture snapshot from current video frame
     */
    takeSnapshot() {
        const video = document.getElementById(this.videoElementId);
        if (!video || !this.videoStream) {
            Swal.fire('Camera not ready', 'Please wait for the camera video stream to start.', 'info');
            return;
        }

        this.playShutterSound();

        // Shutter flash animation
        const flashOverlay = document.getElementById(this.modalId + '_flash');
        if (flashOverlay) {
            flashOverlay.classList.remove('hidden');
            setTimeout(() => flashOverlay.classList.add('hidden'), 120);
        }

        const canvas = document.createElement('canvas');
        const width = video.videoWidth > 0 ? video.videoWidth : 1280;
        const height = video.videoHeight > 0 ? video.videoHeight : 720;
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, width, height);

        // High quality JPEG
        const base64Data = canvas.toDataURL('image/jpeg', 0.88);
        this.capturedImages.push(base64Data);

        // Update UI & Hidden Form Input
        this.renderCapturedThumbnails();
        this.updateHiddenInput();

        // Toast feedback
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: `Photo #${this.capturedImages.length} Captured!`,
                showConfirmButton: false,
                timer: 1500
            });
        }
    }

    /**
     * Remove captured photo by index
     */
    removeSnapshot(index) {
        this.capturedImages.splice(index, 1);
        this.renderCapturedThumbnails();
        this.updateHiddenInput();
    }

    /**
     * Render thumbnail strip inside camera modal or preview area
     */
    renderCapturedThumbnails() {
        const previewContainer = document.getElementById(this.previewContainerId);
        if (!previewContainer) return;

        if (this.capturedImages.length === 0) {
            previewContainer.innerHTML = '<div class="text-xs text-slate-400 italic py-2">No photos captured yet.</div>';
            return;
        }

        let html = '<div class="flex flex-wrap gap-2 pt-2">';
        this.capturedImages.forEach((imgSrc, idx) => {
            html += `
                <div class="relative w-20 h-20 rounded-lg overflow-hidden border-2 border-sky-400 shadow group">
                    <img src="${imgSrc}" class="w-full h-full object-cover">
                    <button type="button" onclick="${this.configInstanceName}.removeSnapshot(${idx})" class="absolute top-1 right-1 bg-red-600/90 text-white w-5 h-5 rounded-full flex items-center justify-center text-[10px] hover:bg-red-700 shadow">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <span class="absolute bottom-0 inset-x-0 bg-black/60 text-[9px] text-white text-center py-0.5">#${idx + 1}</span>
                </div>
            `;
        });
        html += '</div>';
        previewContainer.innerHTML = html;
    }

    /**
     * Update hidden form input with JSON array of base64 images
     */
    updateHiddenInput() {
        const hiddenInput = document.getElementById(this.hiddenInputId);
        if (hiddenInput) {
            hiddenInput.value = JSON.stringify(this.capturedImages);
        }
    }

    /**
     * Open Modal and start camera
     */
    open() {
        const modal = document.getElementById(this.modalId);
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            this.startCamera();
        }
    }

    /**
     * Close Modal and stop camera
     */
    close() {
        this.stopCamera();
        const modal = document.getElementById(this.modalId);
        if (modal) {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }
    }
}
