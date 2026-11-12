/**
 * assets/js/camera.js
 * In-built Camera Capture System for DSC Shifting Inventory
 * Highly resilient across mobile browsers (Android/iOS) and desktop webcams
 */

class PhotoCaptureModule {
    constructor(config) {
        this.videoElementId = config.videoElementId || 'cameraVideo';
        this.previewContainerId = config.previewContainerId;
        this.hiddenInputId = config.hiddenInputId;
        this.modalId = config.modalId || 'cameraModal';
        
        this.videoStream = null;
        this.facingMode = 'environment'; // default to back camera on mobile
        this.capturedImages = []; // Array of base64 strings
        
        this.audioCtx = null;
    }

    /**
     * Play shutter sound effect via Web Audio API
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
                osc.frequency.setValueAtTime(800, this.audioCtx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(150, this.audioCtx.currentTime + 0.08);
                gain.gain.setValueAtTime(0.4, this.audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, this.audioCtx.currentTime + 0.08);
                osc.connect(gain);
                gain.connect(this.audioCtx.destination);
                osc.start();
                osc.stop(this.audioCtx.currentTime + 0.08);
            }
        } catch (e) {
            // Audio blocked or unsupported, silently continue
        }
    }

    /**
     * Start the camera feed
     */
    async startCamera() {
        this.stopCamera();
        const video = document.getElementById(this.videoElementId);
        if (!video) return;

        video.setAttribute('autoplay', '');
        video.setAttribute('muted', '');
        video.setAttribute('playsinline', '');
        video.muted = true;

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            const isHttp = (location.protocol === 'http:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1');
            const msg = isHttp 
                ? 'Camera access in browsers requires HTTPS. Please access this site over https://, or use the "Device Camera / Gallery" button.'
                : 'Live camera is not supported on this browser. Please use the "Device Camera / Gallery" button.';
            
            Swal.fire({
                icon: 'warning',
                title: 'Camera Access',
                text: msg,
                confirmButtonColor: '#0284c7'
            });
            return;
        }

        const constraintsList = [
            { video: { facingMode: { ideal: this.facingMode } }, audio: false },
            { video: { facingMode: this.facingMode }, audio: false },
            { video: true, audio: false }
        ];

        let streamObtained = false;
        for (const constraint of constraintsList) {
            try {
                this.videoStream = await navigator.mediaDevices.getUserMedia(constraint);
                video.srcObject = this.videoStream;
                video.onloadedmetadata = () => {
                    video.play().catch(e => console.log('Autoplay handled:', e));
                };
                streamObtained = true;
                break;
            } catch (err) {
                // Try next constraint
            }
        }

        if (!streamObtained) {
            Swal.fire({
                icon: 'error',
                title: 'Camera Not Available',
                text: 'Could not access the camera. Check browser permissions or use the "Device Camera / Gallery" option.',
                confirmButtonColor: '#0284c7'
            });
        }
    }

    /**
     * Stop camera feed
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
     * Switch camera (front / rear)
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
        if (!video || !this.videoStream || video.readyState < 2) {
            Swal.fire({
                icon: 'info',
                title: 'Camera Starting...',
                text: 'Please wait for the video feed to appear before capturing.',
                timer: 1500,
                showConfirmButton: false
            });
            return;
        }

        this.playShutterSound();

        // Shutter flash effect
        const flashOverlay = document.getElementById(this.modalId + '_flash');
        if (flashOverlay) {
            flashOverlay.classList.remove('hidden');
            setTimeout(() => flashOverlay.classList.add('hidden'), 100);
        }

        const canvas = document.createElement('canvas');
        const origWidth = video.videoWidth > 0 ? video.videoWidth : 1280;
        const origHeight = video.videoHeight > 0 ? video.videoHeight : 720;
        const maxDim = 1280;
        let width = origWidth;
        let height = origHeight;
        if (width > maxDim || height > maxDim) {
            if (width > height) {
                height = Math.round((height * maxDim) / width);
                width = maxDim;
            } else {
                width = Math.round((width * maxDim) / height);
                height = maxDim;
            }
        }
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, width, height);

        const base64Data = canvas.toDataURL('image/jpeg', 0.82);
        this.capturedImages.push(base64Data);

        this.renderCapturedThumbnails();
        this.updateHiddenInput();

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: `Photo #${this.capturedImages.length} Captured!`,
                showConfirmButton: false,
                timer: 1200
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
     * Render thumbnail strip inside camera modal
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
     * Update hidden form input
     */
    updateHiddenInput() {
        const hiddenInput = document.getElementById(this.hiddenInputId);
        if (hiddenInput) {
            hiddenInput.value = JSON.stringify(this.capturedImages);
        }
    }

    /**
     * Open modal & start camera
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
     * Close modal & stop camera
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

/**
 * Helper to downscale and compress File objects from <input type="file">
 * Returns a Promise resolving to a lightweight JPEG File (~150-250KB)
 */
async function compressImageFile(file, maxDimension = 1280, quality = 0.82) {
    if (!file || !file.type || !file.type.startsWith('image/')) return file;
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                let w = img.width;
                let h = img.height;
                if (w > maxDimension || h > maxDimension) {
                    if (w > h) {
                        h = Math.round((h * maxDimension) / w);
                        w = maxDimension;
                    } else {
                        w = Math.round((w * maxDimension) / h);
                        h = maxDimension;
                    }
                }
                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, w, h);
                canvas.toBlob((blob) => {
                    if (blob) {
                        const safeName = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                        const compressedFile = new File([blob], safeName, {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        });
                        resolve(compressedFile);
                    } else {
                        resolve(file);
                    }
                }, 'image/jpeg', quality);
            };
            img.onerror = () => resolve(file);
            img.src = e.target.result;
        };
        reader.onerror = () => resolve(file);
        reader.readAsDataURL(file);
    });
}
