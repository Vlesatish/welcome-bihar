<?php
/**
 * UTI PSA Photo & Signature Cropping & Fresh Level Cleaning Modal
 * Matching UTI PSA specifications:
 * - Photo: 213x213 px @ 300 DPI, < 30 KB
 * - Signature: 400x200 px @ 600 DPI, < 60 KB with Fresh Level (Background Whitening & Ink Darkening)
 */
?>
<!-- UTI PSA Cropping & Level Cleaning Modal -->
<div class="modal fade" id="utiCroppingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-2xl rounded-4 overflow-hidden">
            <!-- Modal Header -->
            <div class="modal-header py-2.5 px-4 text-white" style="background: linear-gradient(135deg, #0b1329 0%, #1e3a8a 100%);">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark px-2.5 py-1 fw-bold font-monospace" style="font-size: 0.72rem;">UTI / NSDL PSA</span>
                    <h5 class="modal-title fw-bold fs-6 mb-0">
                        <i class="bi bi-crop text-warning me-1"></i> PAN Photo & Signature Cropping Tools
                    </h5>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-white-50 small d-none d-md-inline" id="utiModalTargetApplicant"></span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-3 p-md-4 bg-light">
                <!-- Top Instructions Pill Banner -->
                <div class="alert alert-primary bg-white border border-primary-subtle py-2 px-3 rounded-3 shadow-2xs mb-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 small">
                        <div>
                            <strong class="text-primary"><i class="bi bi-info-circle-fill me-1"></i> UTI PSA Specifications:</strong>
                            <span class="ms-1">Photo: <strong>213x213 px, 300 DPI (&lt;30 KB)</strong> &bull; Signature: <strong>400x200 px, 600 DPI (&lt;60 KB)</strong></span>
                        </div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                            <i class="bi bi-magic me-1"></i> Auto Fresh Level (कागज साफ & स्याही डार्क)
                        </span>
                    </div>
                </div>

                <div class="row g-3">
                    <!-- LEFT COLUMN: PHOTO CROPPING (213 x 213, 300 DPI) -->
                    <div class="col-12 col-lg-6">
                        <div class="card h-100 border rounded-3 shadow-sm overflow-hidden">
                            <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                <span class="fw-bold text-dark small text-uppercase">
                                    <i class="bi bi-person-bounding-box text-primary me-1"></i> 1. Photo Resize (213x213 @ 300 DPI)
                                </span>
                                <label class="btn btn-2xs btn-outline-secondary py-0.5 px-2 rounded-pill small mb-0" style="font-size: 0.72rem; cursor: pointer;">
                                    <i class="bi bi-upload me-1"></i> Upload Custom
                                    <input type="file" id="utiCustomPhotoInput" accept="image/*" class="d-none" onchange="utiHandleCustomPhoto(this)">
                                </label>
                            </div>
                            <div class="card-body p-3 text-center bg-white d-flex flex-column justify-content-between">
                                <!-- Photo Preview Canvas Container -->
                                <div class="p-3 bg-light rounded-3 border d-flex align-items-center justify-content-center mb-3" style="min-height: 230px;">
                                    <canvas id="utiPhotoCanvas" width="213" height="213" class="rounded shadow-xs border bg-white" style="max-width: 213px; max-height: 213px; object-fit: contain;"></canvas>
                                </div>

                                <!-- Photo Dimension Stats -->
                                <div class="row g-2 mb-3 text-start small">
                                    <div class="col-4">
                                        <label class="text-muted" style="font-size: 0.7rem;">WIDTH (PX)</label>
                                        <input type="number" id="utiPhotoWidth" class="form-control form-control-sm text-center fw-bold" value="213" readonly>
                                    </div>
                                    <div class="col-4">
                                        <label class="text-muted" style="font-size: 0.7rem;">HEIGHT (PX)</label>
                                        <input type="number" id="utiPhotoHeight" class="form-control form-control-sm text-center fw-bold" value="213" readonly>
                                    </div>
                                    <div class="col-4">
                                        <label class="text-muted" style="font-size: 0.7rem;">RESOLUTION (DPI)</label>
                                        <input type="number" id="utiPhotoDpi" class="form-control form-control-sm text-center fw-bold text-primary" value="300" readonly>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center justify-content-between small text-muted mb-3 px-1">
                                    <span>Target Format: <strong>JPEG / JPG</strong></span>
                                    <span id="utiPhotoSizeInfo" class="badge bg-light text-dark border">Calculating...</span>
                                </div>

                                <!-- Download Button -->
                                <button type="button" id="utiDownloadPhotoBtn" class="btn btn-primary w-100 py-2 fw-bold rounded-3 shadow-sm" onclick="utiTriggerPhotoDownload()">
                                    <i class="bi bi-download me-1.5"></i> Download Photo (213x213 @ 300 DPI)
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: SIGNATURE CROPPING & FRESH LEVEL CLEANING (400 x 200, 600 DPI) -->
                    <div class="col-12 col-lg-6">
                        <div class="card h-100 border rounded-3 shadow-sm overflow-hidden">
                            <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                                <span class="fw-bold text-dark small text-uppercase">
                                    <i class="bi bi-pen-fill text-danger me-1"></i> 2. Signature Resize & Clean (400x200 @ 600 DPI)
                                </span>
                                <label class="btn btn-2xs btn-outline-secondary py-0.5 px-2 rounded-pill small mb-0" style="font-size: 0.72rem; cursor: pointer;">
                                    <i class="bi bi-upload me-1"></i> Upload Custom
                                    <input type="file" id="utiCustomSignInput" accept="image/*" class="d-none" onchange="utiHandleCustomSign(this)">
                                </label>
                            </div>
                            <div class="card-body p-3 bg-white d-flex flex-column justify-content-between">
                                <!-- Signature Preview Canvas Container (2:1 aspect ratio) -->
                                <div class="p-3 bg-light rounded-3 border d-flex align-items-center justify-content-center mb-3" style="min-height: 230px;">
                                    <canvas id="utiSignCanvas" width="400" height="200" class="rounded shadow-xs border bg-white" style="max-width: 100%; max-height: 180px; object-fit: contain;"></canvas>
                                </div>

                                <!-- Fresh Levels / Adjustment Controls -->
                                <div class="bg-light p-2.5 rounded-3 border mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                                        <span class="small fw-bold text-dark">
                                            <i class="bi bi-sliders text-primary me-1"></i> Signature Level & Freshness (डार्क कम & ब्राइट)
                                        </span>
                                        <button type="button" class="btn btn-link btn-sm p-0 text-muted small" onclick="utiResetSignSliders()">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                                        </button>
                                    </div>

                                    <!-- Slider 1: Paper Whiteness / Darkness Removal (Brith karo, Darsh kam) -->
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between small text-muted" style="font-size: 0.72rem;">
                                            <span>कागज का बैकग्राउंड साफ करें (Background Whiteness)</span>
                                            <span id="utiWhiteValText" class="fw-bold text-dark">195</span>
                                        </div>
                                        <input type="range" class="form-range" id="utiWhiteSlider" min="130" max="245" value="195" oninput="utiUpdateSignCanvas()">
                                    </div>

                                    <!-- Slider 2: Ink Darkness / Deepness -->
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between small text-muted" style="font-size: 0.72rem;">
                                            <span>हस्ताक्षर स्याही डार्क करें (Ink Darkness & Contrast)</span>
                                            <span id="utiDarkValText" class="fw-bold text-dark">80</span>
                                        </div>
                                        <input type="range" class="form-range" id="utiDarkSlider" min="20" max="140" value="80" oninput="utiUpdateSignCanvas()">
                                    </div>

                                    <!-- Mode Toggle: Pure B&W vs Fresh Blue/Ink -->
                                    <div class="d-flex align-items-center gap-3 mt-1">
                                        <div class="form-check form-check-inline mb-0" style="font-size: 0.76rem;">
                                            <input class="form-check-input" type="radio" name="utiSignMode" id="utiModeBw" value="bw" checked onchange="utiUpdateSignCanvas()">
                                            <label class="form-check-label fw-bold text-dark" for="utiModeBw">डार्क ब्लैक (Pure Black Ink)</label>
                                        </div>
                                        <div class="form-check form-check-inline mb-0" style="font-size: 0.76rem;">
                                            <input class="form-check-input" type="radio" name="utiSignMode" id="utiModeColor" value="color" onchange="utiUpdateSignCanvas()">
                                            <label class="form-check-label fw-bold text-dark" for="utiModeColor">ओरिजिनल इंक (Enhanced Color)</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Signature Dimension Stats -->
                                <div class="row g-2 mb-2 text-start small">
                                    <div class="col-4">
                                        <label class="text-muted" style="font-size: 0.7rem;">WIDTH (PX)</label>
                                        <input type="number" id="utiSignWidth" class="form-control form-control-sm text-center fw-bold" value="400" readonly>
                                    </div>
                                    <div class="col-4">
                                        <label class="text-muted" style="font-size: 0.7rem;">HEIGHT (PX)</label>
                                        <input type="number" id="utiSignHeight" class="form-control form-control-sm text-center fw-bold" value="200" readonly>
                                    </div>
                                    <div class="col-4">
                                        <label class="text-muted" style="font-size: 0.7rem;">RESOLUTION (DPI)</label>
                                        <input type="number" id="utiSignDpi" class="form-control form-control-sm text-center fw-bold text-danger" value="600" readonly>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center justify-content-between small text-muted mb-3 px-1">
                                    <span>Target Format: <strong>JPEG / JPG</strong></span>
                                    <span id="utiSignSizeInfo" class="badge bg-light text-dark border">Calculating...</span>
                                </div>

                                <!-- Download Button -->
                                <button type="button" id="utiDownloadSignBtn" class="btn btn-danger w-100 py-2 fw-bold rounded-3 shadow-sm" onclick="utiTriggerSignDownload()">
                                    <i class="bi bi-download me-1.5"></i> Download Fresh Signature (400x200 @ 600 DPI)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer bg-white py-2.5 px-4 border-top d-flex align-items-center justify-content-between">
                <span class="text-muted small">
                    <i class="bi bi-shield-check text-success me-1"></i> Both files are ready to upload directly to UTI PSA / NSDL portals.
                </span>
                <button type="button" class="btn btn-sm btn-secondary px-4 fw-semibold rounded-pill" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// State for the interactive UTI cropping modal
let utiModalCurrentApplicant = 'Applicant';
let utiRawPhotoImg = null;
let utiRawSignImg = null;
let utiProcessedPhotoBlob = null;
let utiProcessedSignBlob = null;

// Open Interactive Modal
window.openUtiCroppingTool = async function (photoUrl, signatureUrl, applicantName) {
    utiModalCurrentApplicant = applicantName || 'Applicant';
    document.getElementById('utiModalTargetApplicant').textContent = `Applicant: ${utiModalCurrentApplicant}`;

    const modalEl = document.getElementById('utiCroppingModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    // Default sample if none passed
    const defaultPhoto = photoUrl || '/assets/img/sample_photo.jpg';
    const defaultSign = signatureUrl || '/assets/img/sample_signature.jpg';

    if (defaultPhoto) {
        utiLoadPhotoFromUrl(defaultPhoto);
    }
    if (defaultSign) {
        utiLoadSignFromUrl(defaultSign);
    }
};

// Load photo into canvas
async function utiLoadPhotoFromUrl(url) {
    try {
        const res = await window.UtiCropEngine.processPhoto(url, {
            width: 213,
            height: 213,
            dpi: 300,
            maxKb: 30
        });
        utiProcessedPhotoBlob = res.blob;
        document.getElementById('utiPhotoSizeInfo').textContent = `${res.sizeKb} KB (DPI: ${res.dpi})`;

        const canvas = document.getElementById('utiPhotoCanvas');
        const ctx = canvas.getContext('2d');
        const img = new Image();
        img.onload = () => {
            ctx.clearRect(0, 0, 213, 213);
            ctx.drawImage(img, 0, 0, 213, 213);
        };
        img.src = res.dataUrl;
        utiRawPhotoImg = img;
    } catch (err) {
        console.error('Photo load error:', err);
    }
}

// Load signature into canvas with Fresh Level filtering
async function utiLoadSignFromUrl(url) {
    try {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
            utiRawSignImg = img;
            utiUpdateSignCanvas();
        };
        img.onerror = () => {
            fetch(url)
                .then(r => r.blob())
                .then(b => {
                    img.src = URL.createObjectURL(b);
                });
        };
        img.src = url;
    } catch (err) {
        console.error('Sign load error:', err);
    }
}

// Update signature canvas with current sliders
async function utiUpdateSignCanvas() {
    if (!utiRawSignImg) return;

    const whiteVal = Number(document.getElementById('utiWhiteSlider').value);
    const darkVal = Number(document.getElementById('utiDarkSlider').value);
    const mode = document.querySelector('input[name="utiSignMode"]:checked')?.value || 'bw';

    document.getElementById('utiWhiteValText').textContent = whiteVal;
    document.getElementById('utiDarkValText').textContent = darkVal;

    try {
        const res = await window.UtiCropEngine.processSignature(utiRawSignImg.src, {
            width: 400,
            height: 200,
            dpi: 600,
            maxKb: 60,
            whiteLevel: whiteVal,
            darkLevel: darkVal,
            contrast: 1.5,
            mode: mode
        });

        utiProcessedSignBlob = res.blob;
        document.getElementById('utiSignSizeInfo').textContent = `${res.sizeKb} KB (DPI: ${res.dpi})`;

        const canvas = document.getElementById('utiSignCanvas');
        const ctx = canvas.getContext('2d');
        const previewImg = new Image();
        previewImg.onload = () => {
            ctx.clearRect(0, 0, 400, 200);
            ctx.drawImage(previewImg, 0, 0, 400, 200);
        };
        previewImg.src = res.dataUrl;
    } catch (err) {
        console.error('Error updating signature canvas:', err);
    }
}

// Reset signature sliders
function utiResetSignSliders() {
    document.getElementById('utiWhiteSlider').value = 195;
    document.getElementById('utiDarkSlider').value = 80;
    document.getElementById('utiModeBw').checked = true;
    utiUpdateSignCanvas();
}

// Handle custom photo upload
function utiHandleCustomPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => utiLoadPhotoFromUrl(e.target.result);
        reader.readAsDataURL(input.files[0]);
    }
}

// Handle custom signature upload
function utiHandleCustomSign(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => utiLoadSignFromUrl(e.target.result);
        reader.readAsDataURL(input.files[0]);
    }
}

// Trigger Photo Download
function utiTriggerPhotoDownload() {
    if (!utiProcessedPhotoBlob) return;
    const safeName = (utiModalCurrentApplicant || 'Applicant').replace(/[^a-zA-Z0-9_-]/g, '_');
    window.UtiCropEngine.downloadBlob(utiProcessedPhotoBlob, `UTI_Photo_${safeName}_213x213_300dpi.jpg`);
    if (window.showToast) window.showToast('✓ 300 DPI Photo downloaded successfully', 'success');
}

// Trigger Signature Download
function utiTriggerSignDownload() {
    if (!utiProcessedSignBlob) return;
    const safeName = (utiModalCurrentApplicant || 'Applicant').replace(/[^a-zA-Z0-9_-]/g, '_');
    window.UtiCropEngine.downloadBlob(utiProcessedSignBlob, `UTI_Signature_${safeName}_400x200_600dpi_Fresh.jpg`);
    if (window.showToast) window.showToast('✓ Fresh 600 DPI Signature downloaded successfully', 'success');
}
</script>
