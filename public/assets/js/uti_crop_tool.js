/**
 * UTI / NSDL PAN PSA Photo & Signature Cropping & Level Cleaning Engine
 * - Photo: 213 x 213 px @ 300 DPI, JPEG, < 30 KB
 * - Signature: 400 x 200 px @ 600 DPI, JPEG, < 60 KB
 *   With Automatic Background Whitening (Darsh kam, Bright karo) & Ink Darkening
 */

(function (window) {
    'use strict';

    // Helper: Convert base64 dataURL to Blob with injected JFIF DPI
    function dataUrlToJfifBlob(dataUrl, dpi) {
        const parts = dataUrl.split(',');
        const mime = parts[0].match(/:(.*?);/)[1];
        const binaryStr = atob(parts[1]);
        const len = binaryStr.length;
        const bytes = new Uint8Array(len);
        for (let i = 0; i < len; i++) {
            bytes[i] = binaryStr.charCodeAt(i);
        }

        // Check if JFIF APP0 (0xFF 0xE0) exists
        if (bytes[0] === 0xFF && bytes[1] === 0xD8 && bytes[2] === 0xFF && bytes[3] === 0xE0) {
            bytes[13] = 1; // Units: 1 = dots per inch (DPI)
            bytes[14] = (dpi >> 8) & 0xFF;
            bytes[15] = dpi & 0xFF;
            bytes[16] = (dpi >> 8) & 0xFF;
            bytes[17] = dpi & 0xFF;
            return new Blob([bytes], { type: mime });
        }

        // Otherwise insert standard 18-byte JFIF APP0 header right after SOI (FF D8)
        const app0 = new Uint8Array([
            0xFF, 0xE0, // APP0 marker
            0x00, 0x10, // Length = 16
            0x4A, 0x46, 0x49, 0x46, 0x00, // 'JFIF\0'
            0x01, 0x02, // Version 1.2
            0x01,       // Units: 1 = DPI
            (dpi >> 8) & 0xFF, dpi & 0xFF, // Xdensity
            (dpi >> 8) & 0xFF, dpi & 0xFF, // Ydensity
            0x00, 0x00  // Thumbnail size 0x0
        ]);

        const combined = new Uint8Array(bytes.length + app0.length);
        combined.set(bytes.subarray(0, 2), 0);          // SOI
        combined.set(app0, 2);                          // APP0
        combined.set(bytes.subarray(2), 2 + app0.length); // Remainder
        return new Blob([combined], { type: mime });
    }

    // Helper: Load image from URL or DataURI safely with CORS
    function loadImage(src) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => resolve(img);
            img.onerror = () => {
                // If CORS fails, try fetching as blob
                fetch(src)
                    .then(res => res.blob())
                    .then(blob => {
                        const blobUrl = URL.createObjectURL(blob);
                        const bImg = new Image();
                        bImg.onload = () => resolve(bImg);
                        bImg.onerror = (e) => reject(new Error('Failed to load image: ' + src));
                        bImg.src = blobUrl;
                    })
                    .catch(err => reject(err));
            };
            img.src = src;
        });
    }

    // Process Photo: Exact 213x213 px, 300 DPI, < 30 KB
    async function processPhoto(imageSrc, options = {}) {
        const targetW = options.width || 213;
        const targetH = options.height || 213;
        const targetDpi = options.dpi || 300;
        const maxKb = options.maxKb || 30;

        const img = await loadImage(imageSrc);
        const canvas = document.createElement('canvas');
        canvas.width = targetW;
        canvas.height = targetH;
        const ctx = canvas.getContext('2d');

        // White background
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, targetW, targetH);

        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';

        // Direct Stretch: stretch entire uploaded photo to exact target dimensions (213x213)
        // No cropping of people, background or borders so 100% of uploaded photo remains intact
        const srcW = img.naturalWidth || img.width;
        const srcH = img.naturalHeight || img.height;
        ctx.drawImage(img, 0, 0, srcW, srcH, 0, 0, targetW, targetH);

        // Compress and enforce < 30 KB limit
        let quality = 0.92;
        let blob = null;
        let dataUrl = '';

        while (quality >= 0.25) {
            dataUrl = canvas.toDataURL('image/jpeg', quality);
            blob = dataUrlToJfifBlob(dataUrl, targetDpi);
            if (blob.size / 1024 < maxKb) {
                break;
            }
            quality -= 0.08;
        }

        return {
            blob: blob,
            dataUrl: dataUrl,
            width: targetW,
            height: targetH,
            dpi: targetDpi,
            sizeKb: (blob.size / 1024).toFixed(1)
        };
    }

    // Process Signature: Exact 400x200 px, 600 DPI, < 60 KB
    // With Level Enhancement: Remove background darkness (darsh kam), brighten paper (brith karo), sharpen dark ink
    async function processSignature(imageSrc, options = {}) {
        const targetW = options.width || 400;
        const targetH = options.height || 200;
        const targetDpi = options.dpi || 600;
        const maxKb = options.maxKb || 60;

        // Level parameters:
        // whiteLevel: any luminance >= this becomes pure white (#FFFFFF)
        // darkLevel: any luminance <= this becomes deep dark ink (#000000)
        // contrast: slope steepness
        const whiteLevel = options.whiteLevel !== undefined ? Number(options.whiteLevel) : 195;
        const darkLevel = options.darkLevel !== undefined ? Number(options.darkLevel) : 80;
        const contrast = options.contrast !== undefined ? Number(options.contrast) : 1.45;
        const isPureBw = options.mode !== 'color'; // default pure clean black/white ink

        const img = await loadImage(imageSrc);

        // First step: clean and level-adjust in native resolution
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = img.width;
        tempCanvas.height = img.height;
        const tempCtx = tempCanvas.getContext('2d');
        tempCtx.drawImage(img, 0, 0);

        const imgData = tempCtx.getImageData(0, 0, img.width, img.height);
        const data = imgData.data;

        // Auto-detect ink color if colored (blue/black)
        for (let i = 0; i < data.length; i += 4) {
            const r = data[i];
            const g = data[i + 1];
            const b = data[i + 2];

            // Standard perceptual luminance
            const lum = 0.299 * r + 0.587 * g + 0.114 * b;

            if (lum >= whiteLevel) {
                // Paper background: 100% pure white, eliminating all shadows, yellow tint and paper grey
                data[i] = 255;
                data[i + 1] = 255;
                data[i + 2] = 255;
            } else if (lum <= darkLevel) {
                // Pen ink: deep, sharp dark ink
                if (isPureBw) {
                    data[i] = 0;
                    data[i + 1] = 0;
                    data[i + 2] = 0;
                } else {
                    // Deepen existing ink color
                    data[i] = Math.max(0, Math.round(r * 0.5));
                    data[i + 1] = Math.max(0, Math.round(g * 0.5));
                    data[i + 2] = Math.max(0, Math.round(b * 0.7));
                }
            } else {
                // Midtones: smooth transition
                const t = (lum - darkLevel) / (whiteLevel - darkLevel);
                const curved = Math.pow(t, contrast) * 255;
                if (isPureBw) {
                    const cVal = Math.round(Math.min(255, Math.max(0, curved)));
                    data[i] = cVal;
                    data[i + 1] = cVal;
                    data[i + 2] = cVal;
                } else {
                    const ratio = curved / lum;
                    data[i] = Math.round(Math.min(255, r * ratio));
                    data[i + 1] = Math.round(Math.min(255, g * ratio));
                    data[i + 2] = Math.round(Math.min(255, b * ratio));
                }
            }
        }
        tempCtx.putImageData(imgData, 0, 0);

        // Second step: scale into exact 400 x 200 canvas with white background
        const finalCanvas = document.createElement('canvas');
        finalCanvas.width = targetW;
        finalCanvas.height = targetH;
        const fCtx = finalCanvas.getContext('2d');

        // Pure white background
        fCtx.fillStyle = '#ffffff';
        fCtx.fillRect(0, 0, targetW, targetH);

        fCtx.imageSmoothingEnabled = true;
        fCtx.imageSmoothingQuality = 'high';

        // Direct Stretch: stretch full signature image to exact target dimensions (400x200)
        // No cropping of signatures, initials or edges so 100% of uploaded signature remains intact
        fCtx.drawImage(tempCanvas, 0, 0, tempCanvas.width, tempCanvas.height, 0, 0, targetW, targetH);

        // Compress and enforce < 60 KB limit
        let quality = 0.90;
        let blob = null;
        let dataUrl = '';

        while (quality >= 0.25) {
            dataUrl = finalCanvas.toDataURL('image/jpeg', quality);
            blob = dataUrlToJfifBlob(dataUrl, targetDpi);
            if (blob.size / 1024 < maxKb) {
                break;
            }
            quality -= 0.08;
        }

        return {
            blob: blob,
            dataUrl: dataUrl,
            width: targetW,
            height: targetH,
            dpi: targetDpi,
            sizeKb: (blob.size / 1024).toFixed(1)
        };
    }

    // Trigger browser file download
    function downloadBlob(blob, filename) {
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        setTimeout(() => {
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);
        }, 300);
    }

    // Direct 1-Click Download: Photo (213x213 @ 300 DPI)
    window.downloadUtiPhoto = async function (imgUrl, applicantName, btnElement) {
        if (!imgUrl) {
            alert('Photo file URL not available.');
            return;
        }
        let originalText = '';
        if (btnElement) {
            originalText = btnElement.innerHTML;
            btnElement.disabled = true;
            btnElement.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Cropping...';
        }

        try {
            const res = await processPhoto(imgUrl);
            const safeName = (applicantName || 'Applicant').replace(/[^a-zA-Z0-9_-]/g, '_');
            const filename = `UTI_Photo_${safeName}_213x213_300dpi.jpg`;
            downloadBlob(res.blob, filename);

            if (window.showToast) {
                window.showToast(`✓ UTI Photo downloaded (${res.width}x${res.height} @ ${res.dpi} DPI, ${res.sizeKb} KB)`, 'success');
            }
        } catch (err) {
            console.warn('Client-side crop fallback to server:', err);
            const serverUrl = `/api/uti-crop?type=photo&url=${encodeURIComponent(imgUrl)}&applicant=${encodeURIComponent(applicantName || 'Applicant')}`;
            const link = document.createElement('a');
            link.href = serverUrl;
            link.download = `UTI_Photo_${(applicantName || 'Applicant').replace(/[^a-zA-Z0-9_-]/g, '_')}_213x213_300dpi.jpg`;
            document.body.appendChild(link);
            link.click();
            setTimeout(() => document.body.removeChild(link), 500);
            if (window.showToast) {
                window.showToast('✓ UTI Photo generated from server', 'info');
            }
        } finally {
            if (btnElement) {
                btnElement.disabled = false;
                btnElement.innerHTML = originalText;
            }
        }
    };

    // Direct 1-Click Download: Signature (Fresh & Clean 400x200 @ 600 DPI)
    window.downloadUtiSignature = async function (imgUrl, applicantName, btnElement) {
        if (!imgUrl) {
            alert('Signature file URL not available.');
            return;
        }
        let originalText = '';
        if (btnElement) {
            originalText = btnElement.innerHTML;
            btnElement.disabled = true;
            btnElement.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Cleaning...';
        }

        try {
            const res = await processSignature(imgUrl, {
                whiteLevel: 195,
                darkLevel: 80,
                contrast: 1.5,
                mode: 'bw'
            });
            const safeName = (applicantName || 'Applicant').replace(/[^a-zA-Z0-9_-]/g, '_');
            const filename = `UTI_Signature_${safeName}_400x200_600dpi_Fresh.jpg`;
            downloadBlob(res.blob, filename);

            if (window.showToast) {
                window.showToast(`✓ UTI Signature downloaded (${res.width}x${res.height} @ ${res.dpi} DPI, Fresh & Clean, ${res.sizeKb} KB)`, 'success');
            }
        } catch (err) {
            console.warn('Client-side signature clean fallback to server:', err);
            const serverUrl = `/api/uti-crop?type=signature&url=${encodeURIComponent(imgUrl)}&applicant=${encodeURIComponent(applicantName || 'Applicant')}`;
            const link = document.createElement('a');
            link.href = serverUrl;
            link.download = `UTI_Signature_${(applicantName || 'Applicant').replace(/[^a-zA-Z0-9_-]/g, '_')}_400x200_600dpi_Fresh.jpg`;
            document.body.appendChild(link);
            link.click();
            setTimeout(() => document.body.removeChild(link), 500);
            if (window.showToast) {
                window.showToast('✓ UTI Signature generated from server', 'info');
            }
        } finally {
            if (btnElement) {
                btnElement.disabled = false;
                btnElement.innerHTML = originalText;
            }
        }
    };

    // Export module
    window.UtiCropEngine = {
        processPhoto,
        processSignature,
        dataUrlToJfifBlob,
        downloadBlob
    };

})(window);
