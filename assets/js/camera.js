/**
 * assets/js/camera.js
 * In-built Camera Capture System for DSC Shifting Inventory
 */

class PhotoCaptureModule {
    constructor(config) {
        this.videoElementId = config.videoElementId || 'cameraVideo';
        this.previewContainerId = config.previewContainerId;
        this.hiddenInputId = config.hiddenInputId;
        this.modalId = config.modalId || 'cameraModal';
        
        this.videoStream = null;
        this.facingMode = 'environment'; // default to back camera for phone/tablet
        this.capturedImages = []; // Array of base64 strings
        
        this.audioCtx = null;
    }

    /**
     * Play shutter click sound effect using Web Audio API
     */
    playShutterSound() {
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
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(800, this.audioCtx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(200, this.audioCtx.currentTime + 0.08);
            gain.gain.setValueAtTime(0.5, this.audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, this.audioCtx.currentTime + 0.08);
            osc.connect(gain);
            gain.connect(this.audioCtx.destination);
            osc.start();
            osc.stop(this.audioCtx.currentTime + 0.08);
        } catch (e) {
            // Audio context failed or blocked by policy
        }
    }

    /**
     * Start the camera feed
     */
    async startCamera() {
        this.stopCamera();
        const video = document.getElementById(this.videoElementId);
        if (!video) return;

        const constraints = {
            video: {
                facingMode: { ideal: this.facingMode },
                width: { ideal: 1920 },
                height: { ideal: 1080 }
            },
            audio: false
        };

        try {
            this.videoStream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = this.videoStream;
            video.play();
        } catch (err) {
            console.warn('FacingMode ideal failed, falling back to basic video', err);
            try {
                this.videoStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                video.srcObject = this.videoStream;
                video.play();
            } catch (fallbackErr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Camera Access Denied',
                    text: 'Please allow camera permission in your browser settings to capture photos directly.'
                });
            }
        }
    }

    /**
     * Stop the camera feed to release device hardware
     */
    stopCamera() {
        if (this.videoStream) {
            this.videoStream.getTracks().forEach(track => track.stop());
            this.videoStream = null;
        }
    }

    /**
     * Switch between Front and Back camera
     */
    switchCamera() {
        this.facingMode = (this.facingMode === 'environment') ? 'user' : 'environment';
        this.startCamera();
    }

    /**
     * Capture snapshot from current video frame
     */
    takeSnapshot() {
        const video = document.getElementById(this.videoElementId);
        if (!video || !this.videoStream) return;

        this.playShutterSound();

        // Shutter flash effect
        const flashOverlay = document.getElementById(this.modalId + '_flash');
        if (flashOverlay) {
            flashOverlay.classList.remove('hidden');
            setTimeout(() => flashOverlay.classList.add('hidden'), 100);
        }

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth || 1280;
        canvas.height = video.videoHeight || 720;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        // Export as high quality JPEG base64
        const base64Data = canvas.toDataURL('image/jpeg', 0.88);
        this.capturedImages.push(base64Data);

        // Update UI
        this.renderCapturedThumbnails();
        this.updateHiddenInput();
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
