<?php
declare(strict_types=1);

/**
 * View: New Pan Card Apply (Sirf Aadhaar Card Se)
 * 15 Input Fields + 4 Upload Cards (Apply Form Only)
 */
require dirname(__DIR__) . '/layouts/header.php';

$currentUser = \App\Services\AuthService::user();
$currentRole = $currentUser['role'] ?? 'USER';
$activeMode = \App\Services\AuthService::activePortalMode();
$isAdmin = in_array($currentRole, ['ADMIN', 'SUPER_ADMIN'], true);
$isOperator = ($currentRole === 'OPERATOR' || $currentRole === 'STAFF' || (\App\Services\AuthService::canAccessOperatorMode((int)($currentUser['id'] ?? 0)) && $activeMode === 'OPERATOR'));
$isStaffOrAdmin = $isAdmin || $isOperator;

$panDtRow = null;
try {
    $panDtRow = \App\Config\Database::getConnection()->query("SELECT fee_amount, notice, announcement, warning_image FROM data_types WHERE code = 'pan_card' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}
$panFee = ($panDtRow && (float)$panDtRow['fee_amount'] > 0) ? (float)$panDtRow['fee_amount'] : 107.00;
// Fetch fresh wallet balance directly from DB for accurate display
$userBal = $currentUser ? (float)\App\Services\WalletService::getBalance((int)$currentUser['id']) : 0.00;
$hasEnoughBalance = $isAdmin || ($userBal >= $panFee);
$csrfToken = \App\Services\AuthService::generateCsrfToken();
?>

<style>
/* Modern Pan Card Service Styles */
:root {
    --pan-primary: #1e40af;
    --pan-primary-hover: #1d4ed8;
    --pan-accent: #f59e0b;
    --pan-success: #059669;
    --pan-danger: #dc2626;
    --pan-card-bg: #ffffff;
    --pan-border: #e2e8f0;
}

.pan-page-header {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 2px 6px -1px rgba(0, 0, 0, 0.04);
}

.pan-title-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #ef4444 0%, #f97316 100%);
    color: #ffffff;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    box-shadow: 0 4px 10px rgba(239, 68, 68, 0.25);
}

.pan-notice-card {
    background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%);
    border: 1.5px solid #fecdd3;
    border-radius: 12px;
    padding: 0.9rem 1.25rem;
    box-shadow: 0 2px 4px rgba(244, 63, 94, 0.05);
}

.pan-form-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 4px 14px -2px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.pan-form-group {
    margin-bottom: 1.15rem;
}

.pan-label {
    display: block;
    font-size: 0.76rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    color: #1e293b;
    margin-bottom: 0.35rem;
    text-transform: uppercase;
}

.pan-label .req-star {
    color: #dc2626;
    font-size: 0.85rem;
    margin-left: 2px;
}

.pan-input, .pan-select {
    width: 100%;
    padding: 0.65rem 0.9rem;
    font-size: 0.88rem;
    font-weight: 500;
    color: #0f172a;
    background-color: #ffffff;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    transition: all 0.2s ease;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);
}

.pan-input:focus, .pan-select:focus {
    border-color: #2563eb;
    outline: none;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    background-color: #fbfdff;
}

.pan-helper {
    font-size: 0.72rem;
    color: #64748b;
    margin-top: 0.25rem;
}

/* Upload Dropzone Styles */
.pan-upload-zone {
    border: 2px dashed #cbd5e1;
    background: #f8fafc;
    border-radius: 10px;
    padding: 1.25rem 1rem;
    text-align: center;
    position: relative;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
}

.pan-upload-zone:hover, .pan-upload-zone.dragover {
    border-color: #2563eb;
    background: #eff6ff;
}

.pan-upload-icon {
    font-size: 1.8rem;
    color: #3b82f6;
    margin-bottom: 0.35rem;
}

.pan-upload-title {
    font-size: 0.82rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.2rem;
}

.pan-upload-subtitle {
    font-size: 0.72rem;
    color: #64748b;
    margin-bottom: 0.6rem;
}

.pan-snap-btn {
    font-size: 0.76rem;
    font-weight: 600;
    padding: 0.35rem 0.85rem;
    border-radius: 6px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.pan-snap-btn:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: #0f172a;
}

.pan-file-preview {
    margin-top: 0.75rem;
    padding: 0.5rem 0.75rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pan-file-preview img {
    width: 44px;
    height: 44px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
}

/* Submit Action Button */
.pan-submit-btn {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    border: none;
    color: #ffffff;
    font-size: 1rem;
    font-weight: 700;
    padding: 0.85rem 1.5rem;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.28);
    transition: all 0.25s ease;
    letter-spacing: 0.02em;
}

.pan-submit-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.38);
    transform: translateY(-1px);
}

.pan-submit-btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

/* Previous Requests List & Cards */
.req-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 2.5rem;
    margin-bottom: 1.25rem;
}

.req-filter-pill {
    padding: 0.4rem 0.85rem;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 600;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    text-decoration: none;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.req-filter-pill:hover, .req-filter-pill.active {
    background: #1e40af;
    color: #ffffff;
    border-color: #1e40af;
}

.req-card {
    background: #ffffff;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    transition: all 0.25s ease;
}

/* Dynamic status-based border colors (state ke hisab se border color) */
.req-card.status-completed, .req-card.status-approved {
    border: 2px solid #22c55e !important;
    border-top: 5px solid #22c55e !important;
}

.req-card.status-pending {
    border: 2px solid #f59e0b !important;
    border-top: 5px solid #f59e0b !important;
}

.req-card.status-in-process, .req-card.status-under-review {
    border: 2px solid #3b82f6 !important;
    border-top: 5px solid #3b82f6 !important;
}

.req-card.status-rejected {
    border: 2px solid #ef4444 !important;
    border-top: 5px solid #ef4444 !important;
}

.req-card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

.req-card-header {
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
    padding: 0.85rem 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.req-card-body {
    padding: 1.15rem 1.25rem;
}

.req-grid-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.45rem 0.75rem;
    height: 100%;
    transition: all 0.2s ease;
}

.req-grid-box:hover {
    background: #ffffff;
    border-color: #cbd5e1;
}

.req-grid-box .req-field-label {
    font-size: 0.66rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 0.15rem;
    letter-spacing: 0.03em;
}

.req-grid-box .req-field-value {
    font-size: 0.84rem;
    font-weight: 700;
    color: #0f172a;
    word-break: break-word;
}

.req-field-row {
    margin-bottom: 0.6rem;
}

.req-field-label {
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 0.1rem;
    letter-spacing: 0.03em;
}

.req-field-value {
    font-size: 0.85rem;
    font-weight: 700;
    color: #0f172a;
    word-break: break-word;
}

.req-thumb-box {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 0.4rem;
    background: #f8fafc;
    text-align: center;
    position: relative;
}

.req-thumb-img {
    width: 100%;
    height: 110px;
    object-fit: cover;
    border-radius: 6px;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.req-thumb-img:hover {
    transform: scale(1.03);
}

.req-output-box {
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border: 1.5px solid #10b981;
    border-radius: 10px;
    padding: 0.9rem 1.25rem;
    margin-top: 1rem;
}

.req-reject-box {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border: 1.5px solid #ef4444;
    border-radius: 10px;
    padding: 0.9rem 1.25rem;
    margin-top: 1rem;
}

/* Blinking Hand & Pulsing Receipt Button */
@keyframes handPointBlink {
    0%, 100% {
        transform: translateX(0);
        opacity: 1;
    }
    50% {
        transform: translateX(-4px);
        opacity: 0.35;
    }
}

@keyframes receiptBlinkGlow {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
        border-color: #16a34a;
    }
    50% {
        box-shadow: 0 0 0 7px rgba(34, 197, 94, 0);
        border-color: #4ade80;
    }
}

.btn-receipt-pulse {
    animation: receiptBlinkGlow 1.8s infinite ease-in-out;
    background: #16a34a !important;
    border: 1.5px solid #16a34a !important;
    color: #ffffff !important;
    transition: all 0.2s ease;
}

.btn-receipt-pulse:hover {
    background: #15803d !important;
    transform: scale(1.04);
}

.hand-blink-icon {
    display: inline-block;
    animation: handPointBlink 0.85s infinite ease-in-out;
    font-size: 1.05rem;
    line-height: 1;
}

/* Mobile-First Camera & Interactive Cropper Styles */
#cameraModal .modal-body {
    background: #0f172a;
    color: #f8fafc;
}

#cameraVideoWrap {
    position: relative;
    width: 100%;
    min-height: 240px;
    max-height: 58vh;
    background: #000000;
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

#cameraVideo {
    width: 100%;
    max-height: 58vh;
    object-fit: contain;
    background: #000000;
    display: block;
}

#cameraGuideOverlay {
    position: absolute;
    inset: 0;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
}

#cameraGuideBox {
    border: 2px dashed rgba(34, 197, 94, 0.9);
    box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.35);
    border-radius: 6px;
    transition: all 0.2s ease;
}

#cropEditorArea {
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
}

#cropStageWrap {
    position: relative;
    display: inline-block;
    max-width: 100%;
    max-height: 52vh;
    background: #000000;
    border-radius: 8px;
    overflow: hidden;
    touch-action: none;
    user-select: none;
    -webkit-user-select: none;
    line-height: 0;
}

#cropPreviewImg {
    display: block;
    max-width: 100%;
    max-height: 52vh;
    width: auto;
    height: auto;
    pointer-events: none;
    user-select: none;
    -webkit-user-drag: none;
}

#cropBox {
    position: absolute;
    border: 2px solid #22c55e;
    box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.58);
    cursor: move;
    touch-action: none;
    box-sizing: border-box;
    z-index: 10;
}

#cropBox::before,
#cropBox::after {
    content: '';
    position: absolute;
    pointer-events: none;
}

/* Subtle rule-of-thirds grid inside crop box */
#cropBox::before {
    left: 33.333%;
    right: 33.333%;
    top: 0;
    bottom: 0;
    border-left: 1px solid rgba(255, 255, 255, 0.35);
    border-right: 1px solid rgba(255, 255, 255, 0.35);
}

#cropBox::after {
    top: 33.333%;
    bottom: 33.333%;
    left: 0;
    right: 0;
    border-top: 1px solid rgba(255, 255, 255, 0.35);
    border-bottom: 1px solid rgba(255, 255, 255, 0.35);
}

.crop-handle {
    position: absolute;
    width: 22px;
    height: 22px;
    background: #ffffff;
    border: 3px solid #16a34a;
    border-radius: 50%;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.45);
    z-index: 20;
    touch-action: none;
}

/* Larger invisible touch hit target for mobile fingers */
.crop-handle::after {
    content: '';
    position: absolute;
    inset: -12px;
}

.crop-handle.nw { top: -11px; left: -11px; cursor: nwse-resize; }
.crop-handle.ne { top: -11px; right: -11px; cursor: nesw-resize; }
.crop-handle.sw { bottom: -11px; left: -11px; cursor: nesw-resize; }
.crop-handle.se { bottom: -11px; right: -11px; cursor: nwse-resize; }

.crop-ratio-btn {
    font-size: 0.74rem;
    font-weight: 600;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    border: 1px solid #475569;
    background: #1e293b;
    color: #e2e8f0;
    cursor: pointer;
    transition: all 0.15s ease;
}

.crop-ratio-btn.active {
    background: #16a34a;
    border-color: #22c55e;
    color: #ffffff;
}

.pan-crop-mini-btn {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.2rem 0.48rem;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #eff6ff;
    color: #1d4ed8;
    white-space: nowrap;
}

.pan-crop-mini-btn:hover {
    background: #dbeafe;
}
</style>

<div class="container-fluid px-2 px-md-4 py-3">

    <?php if (!$isStaffOrAdmin): ?>
    <!-- Main Pan Card Application Form -->
    <div class="pan-form-card mb-5">
        <div class="p-3 p-md-4 border-bottom bg-light d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h5 class="mb-1 fw-bold text-dark"><i class="bi bi-person-lines-fill text-primary me-2"></i> Pan Card Application Details</h5>
                <p class="text-muted small mb-0">Please fill all mandatory fields marked with (<span class="text-danger">*</span>) accurately as per Aadhaar Card.</p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php if ($isAdmin): ?>
                    <a href="/services/pan-processor" class="btn btn-sm btn-primary rounded-pill px-3 py-1.5 fw-semibold shadow-2xs">
                        <i class="bi bi-file-earmark-pdf-fill me-1"></i> PAN Form Processor
                    </a>
                <?php endif; ?>
                <a href="/services/pan-card/list" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1.5 fw-semibold shadow-2xs">
                    <i class="bi bi-card-checklist me-1"></i> New Pan Card List
                </a>
            </div>
        </div>

        <form id="panCardForm" method="POST" action="/services/pan-card/apply" enctype="multipart/form-data" class="p-3 p-md-4">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            
            <!-- Hidden inputs for base64 captured uploads (if camera or drag-drop converted) -->
            <input type="hidden" id="photo_base64" name="photo_base64">
            <input type="hidden" id="signature_base64" name="signature_base64">
            <input type="hidden" id="aadhar_card_base64" name="aadhar_card_base64">
            <input type="hidden" id="pan_form_base64" name="pan_form_base64">

            <!-- 15 Form Input Fields in Clean Layout -->
            <div class="row">
                <!-- 1. FULL NAME -->
                <div class="col-12 col-md-6 col-lg-4 pan-form-group">
                    <label class="pan-label" for="full_name">FULL NAME <span class="req-star">*</span></label>
                    <input type="text" class="pan-input" id="full_name" name="full_name" placeholder="E.g. Ramesh Kumar" required>
                </div>

                <!-- 2. GENDER -->
                <div class="col-12 col-md-6 col-lg-4 pan-form-group">
                    <label class="pan-label" for="gender">GENDER <span class="req-star">*</span></label>
                    <select class="pan-select" id="gender" name="gender" required>
                        <option value="">-- Select Gender --</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Transgender">Transgender</option>
                    </select>
                </div>

                <!-- 3. DATE OF BIRTH -->
                <div class="col-12 col-md-6 col-lg-4 pan-form-group">
                    <label class="pan-label" for="dob">DATE OF BIRTH <span class="req-star">*</span></label>
                    <input type="text" class="pan-input" id="dob" name="dob" placeholder="E.g. 01-01-2003" pattern="\d{2}-\d{2}-\d{4}" maxlength="10" autocomplete="off" required>
                    <div class="pan-helper">Format: DD-MM-YYYY (Auto-hyphen applied, e.g. 01-01-2003)</div>
                    <div id="dobMinorBadge" class="d-none mt-1">
                        <span class="badge bg-warning text-dark fw-bold" style="font-size: 0.72rem;">
                            <i class="bi bi-exclamation-circle-fill me-1"></i> Minor Case (Age &lt; 18) — Guardian Aadhaar No required
                        </span>
                    </div>
                </div>

                <!-- 4. SELECT STATE -->
                <div class="col-12 col-md-6 col-lg-4 pan-form-group">
                    <label class="pan-label" for="state">SELECT STATE <span class="req-star">*</span></label>
                    <select class="pan-select" id="state" name="state" required>
                        <option value="">-- Select State --</option>
                        <option value="Andaman and Nicobar Islands">Andaman and Nicobar Islands</option>
                        <option value="Andhra Pradesh">Andhra Pradesh</option>
                        <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                        <option value="Assam">Assam</option>
                        <option value="Bihar">Bihar</option>
                        <option value="Chandigarh">Chandigarh</option>
                        <option value="Chhattisgarh">Chhattisgarh</option>
                        <option value="Dadra and Nagar Haveli and Daman and Diu">Dadra and Nagar Haveli and Daman and Diu</option>
                        <option value="Delhi">Delhi</option>
                        <option value="Goa">Goa</option>
                        <option value="Gujarat">Gujarat</option>
                        <option value="Haryana">Haryana</option>
                        <option value="Himachal Pradesh">Himachal Pradesh</option>
                        <option value="Jammu and Kashmir">Jammu and Kashmir</option>
                        <option value="Jharkhand">Jharkhand</option>
                        <option value="Karnataka">Karnataka</option>
                        <option value="Kerala">Kerala</option>
                        <option value="Ladakh">Ladakh</option>
                        <option value="Lakshadweep">Lakshadweep</option>
                        <option value="Madhya Pradesh">Madhya Pradesh</option>
                        <option value="Maharashtra">Maharashtra</option>
                        <option value="Manipur">Manipur</option>
                        <option value="Meghalaya">Meghalaya</option>
                        <option value="Mizoram">Mizoram</option>
                        <option value="Nagaland">Nagaland</option>
                        <option value="Odisha">Odisha</option>
                        <option value="Puducherry">Puducherry</option>
                        <option value="Punjab">Punjab</option>
                        <option value="Rajasthan">Rajasthan</option>
                        <option value="Sikkim">Sikkim</option>
                        <option value="Tamil Nadu">Tamil Nadu</option>
                        <option value="Telangana">Telangana</option>
                        <option value="Tripura">Tripura</option>
                        <option value="Uttar Pradesh">Uttar Pradesh</option>
                        <option value="Uttarakhand">Uttarakhand</option>
                        <option value="West Bengal">West Bengal</option>
                    </select>
                </div>

                <!-- 5. AADHAAR NO -->
                <div class="col-12 col-md-6 col-lg-4 pan-form-group">
                    <label class="pan-label" for="aadhaar_no">AADHAAR NO <span class="req-star">*</span></label>
                    <input type="text" class="pan-input" id="aadhaar_no" name="aadhaar_no" placeholder="E.g. 369852147895" maxlength="12" pattern="\d{12}" required>
                </div>

                <!-- 6. MOBILE NO -->
                <div class="col-12 col-md-6 col-lg-4 pan-form-group">
                    <label class="pan-label" for="mobile_no">MOBILE NO <span class="req-star">*</span></label>
                    <input type="tel" class="pan-input" id="mobile_no" name="mobile_no" placeholder="E.g. 8523698520" maxlength="10" pattern="\d{10}" required>
                </div>

                <!-- 7. EMAIL ID -->
                <div class="col-12 col-md-6 col-lg-4 pan-form-group">
                    <label class="pan-label" for="email_id">EMAIL ID <span class="req-star">*</span></label>
                    <input type="email" class="pan-input" id="email_id" name="email_id" placeholder="E.g. rkpm@gmail.com" required>
                </div>

                <!-- 8. FATHER NAME -->
                <div class="col-12 col-md-6 col-lg-4 pan-form-group">
                    <label class="pan-label" for="father_name">FATHER NAME <span class="req-star">*</span></label>
                    <input type="text" class="pan-input" id="father_name" name="father_name" placeholder="Enter Father Name" required>
                </div>

                <!-- 9. MOTHER NAME -->
                <div class="col-12 col-md-6 col-lg-4 pan-form-group">
                    <label class="pan-label" for="mother_name">MOTHER NAME <span class="req-star">*</span></label>
                    <input type="text" class="pan-input" id="mother_name" name="mother_name" placeholder="Enter Mother Name" required>
                </div>

                <!-- 10. HUSBAND NAME -->
                <div class="col-12 col-md-6 col-lg-4 pan-form-group">
                    <label class="pan-label" for="husband_name">HUSBAND NAME</label>
                    <input type="text" class="pan-input" id="husband_name" name="husband_name" placeholder="Enter Husband Name">
                </div>

                <!-- 11. VILLAGE -->
                <div class="col-12 col-md-6 col-lg-4 pan-form-group">
                    <label class="pan-label" for="village">VILLAGE <span class="req-star">*</span></label>
                    <input type="text" class="pan-input" id="village" name="village" placeholder="Enter Village" required>
                </div>

                <!-- 12. POST OFFICE -->
                <div class="col-12 col-md-6 col-lg-4 pan-form-group">
                    <label class="pan-label" for="post_office">POST OFFICE <span class="req-star">*</span></label>
                    <input type="text" class="pan-input" id="post_office" name="post_office" placeholder="Enter Post Office" required>
                </div>

                <!-- 13. BLOCK NAME -->
                <div class="col-12 col-md-6 col-lg-4 pan-form-group">
                    <label class="pan-label" for="block_name">BLOCK NAME <span class="req-star">*</span></label>
                    <input type="text" class="pan-input" id="block_name" name="block_name" placeholder="Enter Block Name" required>
                </div>

                <!-- 14. DISTRICT NAME -->
                <div class="col-12 col-md-6 col-lg-4 pan-form-group">
                    <label class="pan-label" for="district_name">DISTRIC NAME <span class="req-star">*</span></label>
                    <input type="text" class="pan-input" id="district_name" name="district_name" placeholder="Enter District Name" required>
                </div>

                <!-- 15. PINCODE -->
                <div class="col-12 col-md-6 col-lg-4 pan-form-group">
                    <label class="pan-label" for="pincode">PINCODE <span class="req-star">*</span></label>
                    <input type="text" class="pan-input" id="pincode" name="pincode" placeholder="E.g. 852369" maxlength="6" pattern="\d{6}" required>
                </div>

                <!-- MINOR CASE SECTION (Auto-shown only when applicant age < 18 at apply date) -->
                <div class="col-12 d-none" id="minorGuardianSection">
                    <div class="p-3 rounded-3 mb-3" style="background: #fffbeb; border: 1.5px solid #f59e0b;">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                            <div class="fw-bold text-dark" style="font-size: 0.85rem;">
                                <i class="bi bi-shield-exclamation text-warning me-1"></i>
                                Minor Case Detected (<span id="minorAgeLabel">Under 18</span>) — Representative Assessee Aadhaar Required
                            </div>
                            <span class="badge bg-warning text-dark">Age &lt; 18</span>
                        </div>
                        <div class="row">
                            <div class="col-12 col-md-6 pan-form-group mb-md-0">
                                <label class="pan-label" for="minor_guardian_type">REPRESENTATIVE / GUARDIAN <span class="req-star">*</span></label>
                                <select class="pan-select" id="minor_guardian_type" name="minor_guardian_type">
                                    <option value="FATHER" selected>Father (पिता)</option>
                                    <option value="MOTHER">Mother (माता)</option>
                                </select>
                                <div class="pan-helper">Select whose Aadhaar number you are entering (Father by default).</div>
                            </div>
                            <div class="col-12 col-md-6 pan-form-group mb-0">
                                <label class="pan-label" for="father_aadhaar_no" id="fatherAadhaarLabel">FATHER AADHAAR NO <span class="req-star">*</span></label>
                                <input type="text" class="pan-input" id="father_aadhaar_no" name="father_aadhaar_no" placeholder="Enter 12-digit Father Aadhaar No" maxlength="12" pattern="\d{12}" inputmode="numeric" autocomplete="off">
                                <div class="pan-helper">Minor applicant (18 वर्ष से कम) के लिए पिता/माता का 12 अंकों का आधार नंबर अनिवार्य है।</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload Section (4 Cards) -->
            <div class="mt-4 pt-3 border-top">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i> Documents Upload</h6>
                    <span class="text-muted small">Max file size: 2MB each (JPG, PNG, PDF)</span>
                </div>

                <div class="row g-3">
                    <!-- 16. PHOTO -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="pan-label">PHOTO <span class="req-star">*</span></label>
                        <div class="pan-upload-zone" id="drop_photo" onclick="triggerFileInput('file_photo')">
                            <i class="bi bi-person-bounding-box pan-upload-icon"></i>
                            <div class="pan-upload-title">Upload Document / Photo</div>
                            <div class="pan-upload-subtitle">(JPG / PNG max 2MB)</div>
                            <input type="file" id="file_photo" name="photo" accept=".jpg,.jpeg,.png" style="display:none;" onchange="handleFileSelected(this, 'photo')">
                            <button type="button" class="pan-snap-btn" onclick="openCameraModal(event, 'photo')">
                                <i class="bi bi-camera-fill text-danger"></i> Snap Photo / फोटो खींचे
                            </button>
                            <div class="pan-file-preview d-none" id="preview_photo" onclick="event.stopPropagation()">
                                <div class="d-flex align-items-center gap-2 overflow-hidden">
                                    <img src="" id="thumb_photo" alt="Photo" style="cursor:pointer;" onclick="previewUploadedThumb(event, 'photo', 'Photo')">
                                    <div class="text-start overflow-hidden">
                                        <div class="small fw-bold text-truncate" id="name_photo">file.jpg</div>
                                        <div class="text-muted" style="font-size: 0.68rem;" id="size_photo">150 KB</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                    <button type="button" id="crop_btn_photo" class="pan-crop-mini-btn" onclick="openCropperForField(event, 'photo')" title="Crop Photo"><i class="bi bi-crop"></i> Crop</button>
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" onclick="removeFile(event, 'photo')"><i class="bi bi-x-circle-fill fs-5"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 17. SIGNATURE -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="pan-label">SIGNATURE <span class="req-star">*</span></label>
                        <div class="pan-upload-zone" id="drop_signature" onclick="triggerFileInput('file_signature')">
                            <i class="bi bi-pen-fill pan-upload-icon"></i>
                            <div class="pan-upload-title">Upload Document / Signature</div>
                            <div class="pan-upload-subtitle">(JPG / PNG max 2MB)</div>
                            <input type="file" id="file_signature" name="signature" accept=".jpg,.jpeg,.png" style="display:none;" onchange="handleFileSelected(this, 'signature')">
                            <button type="button" class="pan-snap-btn" onclick="openCameraModal(event, 'signature')">
                                <i class="bi bi-camera-fill text-danger"></i> Snap Photo / फोटो खींचे
                            </button>
                            <div class="pan-file-preview d-none" id="preview_signature" onclick="event.stopPropagation()">
                                <div class="d-flex align-items-center gap-2 overflow-hidden">
                                    <img src="" id="thumb_signature" alt="Signature" style="cursor:pointer;" onclick="previewUploadedThumb(event, 'signature', 'Signature')">
                                    <div class="text-start overflow-hidden">
                                        <div class="small fw-bold text-truncate" id="name_signature">sig.jpg</div>
                                        <div class="text-muted" style="font-size: 0.68rem;" id="size_signature">80 KB</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                    <button type="button" id="crop_btn_signature" class="pan-crop-mini-btn" onclick="openCropperForField(event, 'signature')" title="Crop Signature"><i class="bi bi-crop"></i> Crop</button>
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" onclick="removeFile(event, 'signature')"><i class="bi bi-x-circle-fill fs-5"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 18. AADHAR CARD -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="pan-label">AADHAR CARD <span class="req-star">*</span></label>
                        <div class="pan-upload-zone" id="drop_aadhar_card" onclick="triggerFileInput('file_aadhar_card')">
                            <i class="bi bi-file-earmark-person-fill pan-upload-icon"></i>
                            <div class="pan-upload-title">Upload Document / Aadhar Card</div>
                            <div class="pan-upload-subtitle">(PDF / JPG / PNG max 2MB)</div>
                            <input type="file" id="file_aadhar_card" name="aadhar_card" accept=".pdf,.jpg,.jpeg,.png" style="display:none;" onchange="handleFileSelected(this, 'aadhar_card')">
                            <button type="button" class="pan-snap-btn" onclick="openCameraModal(event, 'aadhar_card')">
                                <i class="bi bi-camera-fill text-danger"></i> Snap Photo / फोटो खींचे
                            </button>
                            <div class="pan-file-preview d-none" id="preview_aadhar_card" onclick="event.stopPropagation()">
                                <div class="d-flex align-items-center gap-2 overflow-hidden">
                                    <img src="" id="thumb_aadhar_card" alt="Aadhaar" style="cursor:pointer;" onclick="previewUploadedThumb(event, 'aadhar_card', 'Aadhar Card')">
                                    <div class="text-start overflow-hidden">
                                        <div class="small fw-bold text-truncate" id="name_aadhar_card">aadhar.pdf</div>
                                        <div class="text-muted" style="font-size: 0.68rem;" id="size_aadhar_card">300 KB</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                    <button type="button" id="crop_btn_aadhar_card" class="pan-crop-mini-btn d-none" onclick="openCropperForField(event, 'aadhar_card')" title="Crop Image"><i class="bi bi-crop"></i> Crop</button>
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" onclick="removeFile(event, 'aadhar_card')"><i class="bi bi-x-circle-fill fs-5"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 19. DATE OF BIRTH PROOF (OPTIONAL) -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="pan-label">DATE OF BIRTH PROOF <span class="badge bg-secondary-subtle text-secondary fw-semibold ms-1" style="font-size: 0.68rem; vertical-align: middle;">Optional</span></label>
                        <div class="pan-upload-zone" id="drop_pan_form" onclick="triggerFileInput('file_pan_form')">
                            <i class="bi bi-file-earmark-medical-fill pan-upload-icon"></i>
                            <div class="pan-upload-title">Upload Document / Date Of Birth Proof</div>
                            <div class="pan-upload-subtitle">(Optional • PDF / JPG / PNG max 2MB)</div>
                            <input type="file" id="file_pan_form" name="pan_form" accept=".pdf,.jpg,.jpeg,.png" style="display:none;" onchange="handleFileSelected(this, 'pan_form')">
                            <button type="button" class="pan-snap-btn" onclick="openCameraModal(event, 'pan_form')">
                                <i class="bi bi-camera-fill text-danger"></i> Snap Photo / फोटो खींचे
                            </button>
                            <div class="pan-file-preview d-none" id="preview_pan_form" onclick="event.stopPropagation()">
                                <div class="d-flex align-items-center gap-2 overflow-hidden">
                                    <img src="" id="thumb_pan_form" alt="DOB Proof" style="cursor:pointer;" onclick="previewUploadedThumb(event, 'pan_form', 'Date Of Birth Proof')">
                                    <div class="text-start overflow-hidden">
                                        <div class="small fw-bold text-truncate" id="name_pan_form">dob_proof.pdf</div>
                                        <div class="text-muted" style="font-size: 0.68rem;" id="size_pan_form">450 KB</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                    <button type="button" id="crop_btn_pan_form" class="pan-crop-mini-btn d-none" onclick="openCropperForField(event, 'pan_form')" title="Crop Image"><i class="bi bi-crop"></i> Crop</button>
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" onclick="removeFile(event, 'pan_form')"><i class="bi bi-x-circle-fill fs-5"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button Bar -->
            <div class="mt-4 pt-3 text-center">
                <?php if (!$hasEnoughBalance): ?>
                    <div class="alert alert-warning d-inline-block text-start mb-3 py-2 px-3 small">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Insufficient balance! You have <strong>₹<?= number_format($userBal, 2) ?></strong>, required: <strong>₹<?= number_format($panFee, 2) ?></strong>.
                        <a href="/wallet/topup?amount=<?= $panFee ?>" class="fw-bold ms-1 text-decoration-underline">Recharge now</a>
                    </div>
                <?php endif; ?>

                <div>
                    <button type="submit" id="submitPanBtn" class="pan-submit-btn w-100 py-3" <?= !$hasEnoughBalance ? 'disabled' : '' ?>>
                        <i class="bi bi-check-circle-fill me-2"></i> Submit Request / Apply Now (₹<?= number_format($panFee, 2) ?>)
                    </button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- Lightbox Modal for Image Inspection -->
<div class="modal fade" id="imageViewerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold" id="imageViewerTitle">Image Preview</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 text-center bg-black">
                <img id="imageViewerTarget" src="" class="img-fluid" style="max-height: 80vh;" alt="Preview">
            </div>
            <div class="modal-footer py-2 bg-dark border-0 justify-content-between">
                <a id="imageViewerDownload" href="" download class="btn btn-sm btn-outline-light">
                    <i class="bi bi-download me-1"></i> Download
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden native mobile camera input (works reliably on all Android/iOS phones and opens straight into Cropper) -->
<input type="file" id="nativeMobileCameraInput" accept="image/*" capture="environment" style="display:none;" onchange="handleNativeCameraFile(this)">

<!-- Camera Snap & Interactive Crop Modal (Mobile-First) -->
<div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg bg-dark text-white">
            <div class="modal-header bg-primary text-white py-2 px-3 border-0">
                <h6 class="modal-title fw-bold mb-0 d-flex align-items-center gap-2" id="cameraModalTitle">
                    <i class="bi bi-camera-fill"></i> <span>Camera & Crop</span>
                </h6>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" id="flipCameraBtn" class="btn btn-sm btn-light bg-opacity-25 text-dark fw-semibold py-0.5 px-2" onclick="toggleCameraFacingMode()" title="Switch Front / Back Camera">
                        <i class="bi bi-arrow-repeat"></i> Flip
                    </button>
                    <button type="button" class="btn-close btn-close-white" onclick="closeCameraModal()"></button>
                </div>
            </div>

            <div class="modal-body text-center p-2 p-md-3 d-flex flex-column justify-content-center align-items-center">
                <!-- STAGE 1: Live Camera Preview -->
                <div id="cameraLiveStage" class="w-100">
                    <div id="cameraVideoWrap">
                        <video id="cameraVideo" autoplay playsinline muted></video>
                        <div id="cameraGuideOverlay">
                            <div id="cameraGuideBox"></div>
                        </div>
                        <div id="cameraFallbackPanel" class="d-none p-4 text-center w-100">
                            <i class="bi bi-camera-video-off fs-1 text-warning d-block mb-2"></i>
                            <div id="cameraFallbackMsg" class="small text-light mb-3">Live camera browser me open nahi ho paya. Niche Phone Camera button se seedha photo khinch kar crop karein.</div>
                            <button type="button" class="btn btn-warning fw-bold px-4 py-2 rounded-pill" onclick="triggerNativeMobileCamera()">
                                <i class="bi bi-phone-fill me-1"></i> Open Phone Camera / फोन कैमरा खोलें
                            </button>
                        </div>
                    </div>
                    <div class="mt-2 d-flex align-items-center justify-content-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-light rounded-pill px-3" onclick="triggerNativeMobileCamera()">
                            <i class="bi bi-phone me-1"></i> Phone Camera (HD)
                        </button>
                    </div>
                </div>

                <!-- STAGE 2: Captured Preview + Interactive Touch/Mouse Cropper -->
                <div id="cropEditorArea">
                    <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap mb-2">
                        <button type="button" class="crop-ratio-btn" id="ratioBtn_free" onclick="setCropAspectRatio(0)">Free Crop</button>
                        <button type="button" class="crop-ratio-btn" id="ratioBtn_1_1" onclick="setCropAspectRatio(1)">Passport (1:1)</button>
                        <button type="button" class="crop-ratio-btn" id="ratioBtn_3_4" onclick="setCropAspectRatio(0.75)">Portrait (3:4)</button>
                        <button type="button" class="crop-ratio-btn" id="ratioBtn_2_1" onclick="setCropAspectRatio(2)">Sign (2:1)</button>
                        <button type="button" class="crop-ratio-btn" onclick="rotateCropSource90()"><i class="bi bi-arrow-clockwise"></i> Rotate</button>
                        <button type="button" class="crop-ratio-btn" onclick="resetCropBoxToDefault()"><i class="bi bi-fullscreen"></i> Reset</button>
                    </div>

                    <div id="cropStageWrap">
                        <img id="cropPreviewImg" src="" alt="Crop Preview">
                        <div id="cropBox">
                            <div class="crop-handle nw" data-handle="nw"></div>
                            <div class="crop-handle ne" data-handle="ne"></div>
                            <div class="crop-handle sw" data-handle="sw"></div>
                            <div class="crop-handle se" data-handle="se"></div>
                        </div>
                    </div>

                    <div id="cropDimInfo" class="small text-info mt-2 fw-semibold">Drag box or corners to crop cleanly</div>
                </div>

                <canvas id="cameraCanvas" style="display:none;"></canvas>
                <div id="cameraStatus" class="small text-secondary mt-2">Position photo/document clearly in frame.</div>
            </div>

            <div class="modal-footer py-2 px-3 bg-dark border-top border-secondary justify-content-between flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-light px-3" onclick="closeCameraModal()">Cancel</button>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" id="snapCaptureBtn" class="btn btn-danger px-4 fw-bold rounded-pill" onclick="captureLiveSnapshot()">
                        <i class="bi bi-camera-fill me-1"></i> Capture Photo
                    </button>
                    <button type="button" id="retakeBtn" class="btn btn-warning px-3 fw-bold rounded-pill d-none" onclick="retakeLiveSnapshot()">
                        <i class="bi bi-arrow-repeat me-1"></i> Retake
                    </button>
                    <button type="button" id="useCaptureBtn" class="btn btn-success px-4 fw-bold rounded-pill d-none" onclick="applyCapturedSnapshot()">
                        <i class="bi bi-check-lg me-1"></i> Crop &amp; Use Photo
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Client-side Scripts -->
<script>
let currentTargetField = null;
let cameraStream = null;
let currentFacingMode = 'environment';
let cropSourceDataUrl = null;
let cropAspectRatio = 0; // 0 = free, 1 = 1:1, 0.75 = 3:4, 2 = 2:1
let cropRect = { x: 0, y: 0, w: 100, h: 100 }; // in displayed preview pixels
let cropImgMetrics = { dispW: 0, dispH: 0, natW: 0, natH: 0 };
let cropDragState = null;
let openedCropOnlyMode = false;

const FIELD_CROP_DEFAULTS = {
    photo: { title: 'Passport Photo — Camera & Crop', ratio: 1, guideW: '58%', guideH: '72%' },
    signature: { title: 'Signature — Camera & Crop', ratio: 2, guideW: '82%', guideH: '36%' },
    aadhar_card: { title: 'Aadhaar Card — Camera & Crop', ratio: 0, guideW: '88%', guideH: '70%' },
    pan_form: { title: 'DOB Proof — Camera & Crop', ratio: 0, guideW: '86%', guideH: '78%' }
};

// Trigger hidden file input
function triggerFileInput(inputId) {
    const el = document.getElementById(inputId);
    if (el) el.click();
}

function previewUploadedThumb(event, fieldKey, title) {
    event.stopPropagation();
    const b64 = document.getElementById(fieldKey + '_base64')?.value || '';
    if (b64 && b64.startsWith('data:image/')) {
        openImageModal(b64, title);
    }
}

function estimateBase64Bytes(dataUrl) {
    if (!dataUrl) return 0;
    const idx = dataUrl.indexOf(',');
    const b64 = idx >= 0 ? dataUrl.slice(idx + 1) : dataUrl;
    return Math.round((b64.length * 3) / 4);
}

// Downscale & compress large mobile camera/gallery images automatically so uploads never fail > 2MB
function compressImageDataUrl(rawDataUrl, maxDim = 1600, maxBytes = 1.6 * 1024 * 1024) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = function() {
            let w = img.naturalWidth || img.width;
            let h = img.naturalHeight || img.height;
            if (w > maxDim || h > maxDim) {
                const scale = Math.min(maxDim / w, maxDim / h);
                w = Math.max(1, Math.round(w * scale));
                h = Math.max(1, Math.round(h * scale));
            }
            const c = document.createElement('canvas');
            c.width = w;
            c.height = h;
            const ctx = c.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, w, h);
            ctx.drawImage(img, 0, 0, w, h);

            let q = 0.9;
            let out = c.toDataURL('image/jpeg', q);
            while (estimateBase64Bytes(out) > maxBytes && q > 0.45) {
                q -= 0.1;
                out = c.toDataURL('image/jpeg', q);
            }
            resolve(out);
        };
        img.onerror = function() {
            reject(new Error('Could not decode selected image.'));
        };
        img.src = rawDataUrl;
    });
}

function setFieldPreviewFromDataUrl(fieldKey, dataUrl, displayName) {
    const base64Input = document.getElementById(fieldKey + '_base64');
    const previewBox = document.getElementById('preview_' + fieldKey);
    const thumbImg = document.getElementById('thumb_' + fieldKey);
    const nameEl = document.getElementById('name_' + fieldKey);
    const sizeEl = document.getElementById('size_' + fieldKey);
    const cropBtn = document.getElementById('crop_btn_' + fieldKey);

    if (base64Input) base64Input.value = dataUrl;
    if (thumbImg) {
        thumbImg.src = dataUrl;
        thumbImg.style.display = 'block';
    }
    if (nameEl) nameEl.textContent = displayName || (fieldKey + '_' + Date.now() + '.jpg');
    if (sizeEl) sizeEl.textContent = (estimateBase64Bytes(dataUrl) / 1024).toFixed(1) + ' KB';
    if (cropBtn) cropBtn.classList.remove('d-none');
    if (previewBox) previewBox.classList.remove('d-none');
}

// Handle file selection (Reads file into RAM immediately and detaches disk handle to prevent Chrome ERR_UPLOAD_FILE_CHANGED / "Your file couldn't be accessed")
function handleFileSelected(input, fieldKey) {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];
    const isImage = file.type.startsWith('image/') || /\.(jpe?g|png)$/i.test(file.name || '');

    if ((fieldKey === 'photo' || fieldKey === 'signature') && !isImage) {
        alert('Photo and Signature must be a JPG or PNG image file. PDF is not allowed.');
        input.value = '';
        return;
    }

    // PDFs must strictly be <= 2MB; large mobile images will be auto-compressed below
    if (!isImage && file.size > 2 * 1024 * 1024) {
        alert('PDF file size exceeds the 2MB limit. Please choose a smaller file.');
        input.value = '';
        return;
    }

    const previewBox = document.getElementById('preview_' + fieldKey);
    const thumbImg = document.getElementById('thumb_' + fieldKey);
    const nameEl = document.getElementById('name_' + fieldKey);
    const sizeEl = document.getElementById('size_' + fieldKey);
    const base64Input = document.getElementById(fieldKey + '_base64');
    const cropBtn = document.getElementById('crop_btn_' + fieldKey);

    nameEl.textContent = file.name;
    sizeEl.textContent = (file.size / 1024).toFixed(1) + ' KB';

    if (!isImage) {
        thumbImg.src = 'https://cdn-icons-png.flaticon.com/512/337/337946.png';
        thumbImg.style.display = 'block';
        if (cropBtn) cropBtn.classList.add('d-none');
    }

    const reader = new FileReader();
    reader.onload = async function(e) {
        let dataUrl = e.target.result || '';
        if (isImage) {
            if (file.size > 1.5 * 1024 * 1024) {
                try {
                    dataUrl = await compressImageDataUrl(dataUrl, 1600, 1.5 * 1024 * 1024);
                    sizeEl.textContent = (estimateBase64Bytes(dataUrl) / 1024).toFixed(1) + ' KB';
                } catch (err) {}
            }
            thumbImg.src = dataUrl;
            thumbImg.style.display = 'block';
            if (cropBtn) cropBtn.classList.remove('d-none');
        }
        if (base64Input) {
            base64Input.value = dataUrl;
        }
        // Detach OS disk file handle from <input type="file"> once cached in RAM
        // This 100% prevents Chrome's "Your file couldn't be accessed" (ERR_UPLOAD_FILE_CHANGED)
        try {
            input.value = '';
        } catch (err) {}
    };
    reader.onerror = function() {
        alert('Could not read the selected file. Please re-select the file.');
        try { input.value = ''; } catch (err) {}
    };
    reader.readAsDataURL(file);

    previewBox.classList.remove('d-none');
}

// Remove uploaded file
function removeFile(event, fieldKey) {
    event.stopPropagation();
    const fileInput = document.getElementById('file_' + fieldKey);
    if (fileInput) fileInput.value = '';
    document.getElementById(fieldKey + '_base64').value = '';
    const previewBox = document.getElementById('preview_' + fieldKey);
    if (previewBox) previewBox.classList.add('d-none');
    const cropBtn = document.getElementById('crop_btn_' + fieldKey);
    if (cropBtn) cropBtn.classList.add('d-none');
}

// Drag & Drop Handlers
['photo', 'signature', 'aadhar_card', 'pan_form'].forEach(key => {
    const zone = document.getElementById('drop_' + key);
    if (!zone) return;

    ['dragenter', 'dragover'].forEach(eventName => {
        zone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.add('dragover');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        zone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('dragover');
        }, false);
    });

    zone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            const fileInput = document.getElementById('file_' + key);
            fileInput.files = files;
            handleFileSelected(fileInput, key);
        }
    }, false);
});

// Image Lightbox Viewer
function openImageModal(imgUrl, title) {
    document.getElementById('imageViewerTarget').src = imgUrl;
    document.getElementById('imageViewerTitle').textContent = title || 'Image Viewer';
    document.getElementById('imageViewerDownload').href = imgUrl;
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('imageViewerModal'));
    modal.show();
}

// =========================================================================
// MOBILE-FIRST CAMERA + INTERACTIVE TOUCH/MOUSE CROPPER
// =========================================================================
function stopCameraStream() {
    if (cameraStream) {
        try {
            cameraStream.getTracks().forEach(track => track.stop());
        } catch (e) {}
        cameraStream = null;
    }
    const video = document.getElementById('cameraVideo');
    if (video) {
        try { video.pause(); } catch (e) {}
        video.srcObject = null;
    }
}

function updateGuideBoxForField(fieldKey) {
    const cfg = FIELD_CROP_DEFAULTS[fieldKey] || FIELD_CROP_DEFAULTS.photo;
    const titleSpan = document.querySelector('#cameraModalTitle span');
    if (titleSpan) titleSpan.textContent = cfg.title;
    const guide = document.getElementById('cameraGuideBox');
    if (guide) {
        guide.style.width = cfg.guideW;
        guide.style.height = cfg.guideH;
    }
}

function showLiveCameraUI() {
    document.getElementById('cameraLiveStage').style.display = 'block';
    document.getElementById('cropEditorArea').style.display = 'none';
    document.getElementById('snapCaptureBtn').classList.remove('d-none');
    document.getElementById('useCaptureBtn').classList.add('d-none');
    document.getElementById('retakeBtn').classList.add('d-none');
    document.getElementById('flipCameraBtn').classList.remove('d-none');
    document.getElementById('cameraStatus').textContent = 'Frame me photo/document set karein aur Capture Photo dabayein.';
}

function showCropEditorUI() {
    document.getElementById('cameraLiveStage').style.display = 'none';
    document.getElementById('cropEditorArea').style.display = 'flex';
    document.getElementById('snapCaptureBtn').classList.add('d-none');
    document.getElementById('useCaptureBtn').classList.remove('d-none');
    if (openedCropOnlyMode) {
        document.getElementById('retakeBtn').classList.add('d-none');
    } else {
        document.getElementById('retakeBtn').classList.remove('d-none');
    }
    document.getElementById('flipCameraBtn').classList.add('d-none');
    document.getElementById('cameraStatus').textContent = 'Corners pakad kar halka crop adjust karein, fir "Crop & Use Photo" dabayein.';
}

async function startLiveCameraStream() {
    stopCameraStream();

    const video = document.getElementById('cameraVideo');
    const fallbackPanel = document.getElementById('cameraFallbackPanel');
    const fallbackMsg = document.getElementById('cameraFallbackMsg');
    const guideOverlay = document.getElementById('cameraGuideOverlay');
    const captureBtn = document.getElementById('snapCaptureBtn');

    if (fallbackPanel) fallbackPanel.classList.add('d-none');
    if (video) video.style.display = 'block';
    if (guideOverlay) guideOverlay.style.display = 'flex';
    if (captureBtn) {
        captureBtn.disabled = true;
        captureBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Opening Camera...';
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        if (video) video.style.display = 'none';
        if (guideOverlay) guideOverlay.style.display = 'none';
        if (fallbackPanel) fallbackPanel.classList.remove('d-none');
        if (fallbackMsg) fallbackMsg.textContent = 'Is browser me live camera supported nahi hai. Niche "Open Phone Camera" se seedha photo khinch kar crop karein.';
        if (captureBtn) {
            captureBtn.classList.add('d-none');
            captureBtn.disabled = false;
        }
        return;
    }

    const attempts = [
        { video: { facingMode: { ideal: currentFacingMode }, width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false },
        { video: { facingMode: currentFacingMode }, audio: false },
        { video: true, audio: false }
    ];

    let stream = null;
    let lastErr = null;
    for (const constraints of attempts) {
        try {
            stream = await navigator.mediaDevices.getUserMedia(constraints);
            if (stream) break;
        } catch (err) {
            lastErr = err;
        }
    }

    if (!stream) {
        if (video) video.style.display = 'none';
        if (guideOverlay) guideOverlay.style.display = 'none';
        if (fallbackPanel) fallbackPanel.classList.remove('d-none');
        if (fallbackMsg) {
            fallbackMsg.textContent = 'Live camera open nahi hua (' + (lastErr ? lastErr.message : 'Permission denied') + '). Niche "Open Phone Camera" button use karein!';
        }
        if (captureBtn) {
            captureBtn.classList.add('d-none');
            captureBtn.disabled = false;
        }
        return;
    }

    cameraStream = stream;
    video.srcObject = stream;
    video.muted = true;
    video.setAttribute('playsinline', 'true');
    video.setAttribute('muted', 'true');

    const markReady = () => {
        if (captureBtn) {
            captureBtn.classList.remove('d-none');
            captureBtn.disabled = false;
            captureBtn.innerHTML = '<i class="bi bi-camera-fill me-1"></i> Capture Photo';
        }
    };

    video.onloadedmetadata = () => {
        video.play().then(markReady).catch(markReady);
    };
    setTimeout(markReady, 1200);
}

function openCameraModal(event, fieldKey) {
    event.stopPropagation();
    currentTargetField = fieldKey;
    openedCropOnlyMode = false;
    cropSourceDataUrl = null;
    currentFacingMode = (fieldKey === 'photo') ? 'environment' : 'environment';

    updateGuideBoxForField(fieldKey);
    showLiveCameraUI();

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('cameraModal'));
    modal.show();
    startLiveCameraStream();
}

function openCropperForField(event, fieldKey) {
    event.stopPropagation();
    const existingDataUrl = document.getElementById(fieldKey + '_base64')?.value || '';
    if (!existingDataUrl || !existingDataUrl.startsWith('data:image/')) {
        alert('Only uploaded JPG/PNG images can be cropped.');
        return;
    }
    currentTargetField = fieldKey;
    openedCropOnlyMode = true;
    stopCameraStream();
    updateGuideBoxForField(fieldKey);

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('cameraModal'));
    modal.show();

    setTimeout(() => {
        loadImageIntoCropper(existingDataUrl);
    }, 180);
}

function toggleCameraFacingMode() {
    currentFacingMode = (currentFacingMode === 'environment') ? 'user' : 'environment';
    startLiveCameraStream();
}

function triggerNativeMobileCamera() {
    const nativeInp = document.getElementById('nativeMobileCameraInput');
    if (nativeInp) {
        nativeInp.value = '';
        nativeInp.click();
    }
}

function handleNativeCameraFile(input) {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = async function(e) {
        let dataUrl = e.target.result || '';
        try {
            input.value = '';
        } catch (err) {}
        if (!dataUrl) return;
        try {
            // Normalize orientation & size before cropping on mobile
            dataUrl = await compressImageDataUrl(dataUrl, 1600, 1.8 * 1024 * 1024);
        } catch (err) {}
        stopCameraStream();
        loadImageIntoCropper(dataUrl);
    };
    reader.readAsDataURL(file);
}

function captureLiveSnapshot() {
    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('cameraCanvas');
    if (!video || !video.videoWidth || !video.videoHeight) {
        alert('Camera abhi load ho raha hai, 1 second ruk kar fir Capture dabayein.');
        return;
    }

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const rawSnap = canvas.toDataURL('image/jpeg', 0.92);
    stopCameraStream();
    loadImageIntoCropper(rawSnap);
}

function loadImageIntoCropper(dataUrl) {
    cropSourceDataUrl = dataUrl;
    const cfg = FIELD_CROP_DEFAULTS[currentTargetField] || FIELD_CROP_DEFAULTS.photo;
    cropAspectRatio = cfg.ratio;
    updateRatioButtonStyles();
    showCropEditorUI();

    const imgEl = document.getElementById('cropPreviewImg');
    imgEl.onload = function() {
        // Allow modal layout to paint before measuring displayed image box
        requestAnimationFrame(() => {
            initCropMetricsAndDefaultBox();
        });
    };
    imgEl.src = dataUrl;
}

function updateRatioButtonStyles() {
    const map = {
        0: 'ratioBtn_free',
        1: 'ratioBtn_1_1',
        0.75: 'ratioBtn_3_4',
        2: 'ratioBtn_2_1'
    };
    Object.values(map).forEach((id) => {
        document.getElementById(id)?.classList.remove('active');
    });
    const activeId = map[cropAspectRatio];
    if (activeId) {
        document.getElementById(activeId)?.classList.add('active');
    }
}

function initCropMetricsAndDefaultBox() {
    const imgEl = document.getElementById('cropPreviewImg');
    if (!imgEl || !imgEl.naturalWidth) return;

    const dispW = imgEl.clientWidth || imgEl.offsetWidth;
    const dispH = imgEl.clientHeight || imgEl.offsetHeight;
    if (!dispW || !dispH) {
        setTimeout(initCropMetricsAndDefaultBox, 120);
        return;
    }

    cropImgMetrics = {
        dispW,
        dispH,
        natW: imgEl.naturalWidth,
        natH: imgEl.naturalHeight
    };

    resetCropBoxToDefault();
}

function resetCropBoxToDefault() {
    const { dispW, dispH } = cropImgMetrics;
    if (!dispW || !dispH) return;

    let w, h;
    if (cropAspectRatio > 0) {
        // Fit largest centered crop box with requested aspect ratio (82% of stage)
        if (dispW / dispH > cropAspectRatio) {
            h = dispH * 0.82;
            w = h * cropAspectRatio;
        } else {
            w = dispW * 0.86;
            h = w / cropAspectRatio;
        }
    } else {
        w = dispW * 0.88;
        h = dispH * 0.88;
    }

    w = Math.min(dispW, Math.max(40, w));
    h = Math.min(dispH, Math.max(40, h));

    cropRect = {
        x: Math.round((dispW - w) / 2),
        y: Math.round((dispH - h) / 2),
        w: Math.round(w),
        h: Math.round(h)
    };
    renderCropBox();
}

function setCropAspectRatio(ratio) {
    cropAspectRatio = ratio;
    updateRatioButtonStyles();
    resetCropBoxToDefault();
}

function renderCropBox() {
    const box = document.getElementById('cropBox');
    if (!box) return;
    box.style.left = cropRect.x + 'px';
    box.style.top = cropRect.y + 'px';
    box.style.width = cropRect.w + 'px';
    box.style.height = cropRect.h + 'px';

    const dimEl = document.getElementById('cropDimInfo');
    if (dimEl && cropImgMetrics.dispW > 0) {
        const scaleX = cropImgMetrics.natW / cropImgMetrics.dispW;
        const scaleY = cropImgMetrics.natH / cropImgMetrics.dispH;
        const outW = Math.round(cropRect.w * scaleX);
        const outH = Math.round(cropRect.h * scaleY);
        dimEl.textContent = `Selected Crop: ${outW} × ${outH} px (Move box ya corners kheench kar adjust karein)`;
    }
}

function rotateCropSource90() {
    if (!cropSourceDataUrl) return;
    const img = new Image();
    img.onload = function() {
        const c = document.createElement('canvas');
        c.width = img.naturalHeight;
        c.height = img.naturalWidth;
        const ctx = c.getContext('2d');
        ctx.translate(c.width / 2, c.height / 2);
        ctx.rotate(Math.PI / 2);
        ctx.drawImage(img, -img.naturalWidth / 2, -img.naturalHeight / 2);
        const rotatedUrl = c.toDataURL('image/jpeg', 0.92);
        cropSourceDataUrl = rotatedUrl;
        const imgEl = document.getElementById('cropPreviewImg');
        imgEl.onload = function() {
            requestAnimationFrame(() => initCropMetricsAndDefaultBox());
        };
        imgEl.src = rotatedUrl;
    };
    img.src = cropSourceDataUrl;
}

// Unified Touch + Mouse pointer interactions for Crop Box & Corner Handles
(function initCropPointerEvents() {
    const box = document.getElementById('cropBox');
    if (!box) return;
    const MIN_SIZE = 36;

    box.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const handleEl = e.target.closest('.crop-handle');
        const mode = handleEl ? handleEl.dataset.handle : 'move';
        cropDragState = {
            mode,
            pointerId: e.pointerId,
            startX: e.clientX,
            startY: e.clientY,
            orig: { ...cropRect }
        };
        try { box.setPointerCapture(e.pointerId); } catch (err) {}
    });

    box.addEventListener('pointermove', (e) => {
        if (!cropDragState || e.pointerId !== cropDragState.pointerId) return;
        e.preventDefault();
        const dx = e.clientX - cropDragState.startX;
        const dy = e.clientY - cropDragState.startY;
        const { dispW, dispH } = cropImgMetrics;
        const o = cropDragState.orig;

        if (cropDragState.mode === 'move') {
            const nx = Math.max(0, Math.min(dispW - o.w, o.x + dx));
            const ny = Math.max(0, Math.min(dispH - o.h, o.y + dy));
            cropRect = { x: Math.round(nx), y: Math.round(ny), w: o.w, h: o.h };
            renderCropBox();
            return;
        }

        let left = o.x;
        let top = o.y;
        let right = o.x + o.w;
        let bottom = o.y + o.h;

        if (cropDragState.mode.includes('w')) left = Math.max(0, Math.min(right - MIN_SIZE, o.x + dx));
        if (cropDragState.mode.includes('e')) right = Math.min(dispW, Math.max(left + MIN_SIZE, o.x + o.w + dx));
        if (cropDragState.mode.includes('n')) top = Math.max(0, Math.min(bottom - MIN_SIZE, o.y + dy));
        if (cropDragState.mode.includes('s')) bottom = Math.min(dispH, Math.max(top + MIN_SIZE, o.y + o.h + dy));

        let nw = right - left;
        let nh = bottom - top;

        if (cropAspectRatio > 0) {
            // Preserve aspect ratio anchored on the opposite corner
            nh = nw / cropAspectRatio;
            if (cropDragState.mode.includes('n')) {
                top = bottom - nh;
                if (top < 0) {
                    top = 0;
                    nh = bottom;
                    nw = nh * cropAspectRatio;
                    if (cropDragState.mode.includes('w')) left = right - nw;
                }
            } else {
                if (top + nh > dispH) {
                    nh = dispH - top;
                    nw = nh * cropAspectRatio;
                    if (cropDragState.mode.includes('w')) left = right - nw;
                }
            }
            if (left < 0) {
                left = 0;
                nw = right;
                nh = nw / cropAspectRatio;
                if (cropDragState.mode.includes('n')) top = bottom - nh;
            }
            if (left + nw > dispW) {
                nw = dispW - left;
                nh = nw / cropAspectRatio;
                if (cropDragState.mode.includes('n')) top = bottom - nh;
            }
        }

        if (nw >= MIN_SIZE && nh >= MIN_SIZE) {
            cropRect = {
                x: Math.round(Math.max(0, left)),
                y: Math.round(Math.max(0, top)),
                w: Math.round(Math.min(dispW - Math.max(0, left), nw)),
                h: Math.round(Math.min(dispH - Math.max(0, top), nh))
            };
            renderCropBox();
        }
    });

    const endDrag = (e) => {
        if (!cropDragState || e.pointerId !== cropDragState.pointerId) return;
        try { box.releasePointerCapture(e.pointerId); } catch (err) {}
        cropDragState = null;
    };
    box.addEventListener('pointerup', endDrag);
    box.addEventListener('pointercancel', endDrag);

    window.addEventListener('resize', () => {
        if (document.getElementById('cropEditorArea')?.style.display === 'flex') {
            initCropMetricsAndDefaultBox();
        }
    });
})();

function retakeLiveSnapshot() {
    cropSourceDataUrl = null;
    openedCropOnlyMode = false;
    showLiveCameraUI();
    startLiveCameraStream();
}

function applyCapturedSnapshot() {
    if (!cropSourceDataUrl || !currentTargetField) return;
    const imgEl = document.getElementById('cropPreviewImg');
    if (!imgEl || !cropImgMetrics.dispW || !cropImgMetrics.dispH) return;

    const scaleX = cropImgMetrics.natW / cropImgMetrics.dispW;
    const scaleY = cropImgMetrics.natH / cropImgMetrics.dispH;
    const sx = Math.max(0, Math.round(cropRect.x * scaleX));
    const sy = Math.max(0, Math.round(cropRect.y * scaleY));
    const sw = Math.max(10, Math.min(cropImgMetrics.natW - sx, Math.round(cropRect.w * scaleX)));
    const sh = Math.max(10, Math.min(cropImgMetrics.natH - sy, Math.round(cropRect.h * scaleY)));

    let outW = sw;
    let outH = sh;
    const maxDim = (currentTargetField === 'photo') ? 800 : 1400;
    if (outW > maxDim || outH > maxDim) {
        const s = Math.min(maxDim / outW, maxDim / outH);
        outW = Math.max(1, Math.round(outW * s));
        outH = Math.max(1, Math.round(outH * s));
    }

    const canvas = document.getElementById('cameraCanvas');
    canvas.width = outW;
    canvas.height = outH;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, outW, outH);
    ctx.drawImage(imgEl, sx, sy, sw, sh, 0, 0, outW, outH);

    let q = 0.90;
    let finalDataUrl = canvas.toDataURL('image/jpeg', q);
    while (estimateBase64Bytes(finalDataUrl) > 1.6 * 1024 * 1024 && q > 0.45) {
        q -= 0.1;
        finalDataUrl = canvas.toDataURL('image/jpeg', q);
    }

    setFieldPreviewFromDataUrl(currentTargetField, finalDataUrl, `${currentTargetField}_cropped_${Date.now()}.jpg`);
    closeCameraModal();
}

function closeCameraModal() {
    stopCameraStream();
    cropDragState = null;
    const modalEl = document.getElementById('cameraModal');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();
}

// Ensure camera stream always stops on modal dismiss & crop box re-measures once modal is fully shown
(function initCameraModalLifecycle() {
    const modalEl = document.getElementById('cameraModal');
    if (!modalEl) return;
    modalEl.addEventListener('shown.bs.modal', function() {
        if (document.getElementById('cropEditorArea')?.style.display === 'flex' && cropSourceDataUrl) {
            initCropMetricsAndDefaultBox();
        }
    });
    modalEl.addEventListener('hidden.bs.modal', function() {
        stopCameraStream();
        cropDragState = null;
    });
})();


// Auto-Hyphen (-) Formatter for DATE OF BIRTH (DD-MM-YYYY)
(function initDobAutoMask() {
    const dobInput = document.getElementById('dob');
    if (!dobInput) return;

    let isDeleting = false;

    dobInput.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' || e.key === 'Delete') {
            isDeleting = true;
            if (e.key === 'Backspace') {
                const cursor = this.selectionStart;
                // If cursor is directly after a hyphen (e.g. "01-|" or "01-01-|")
                if ((cursor === 3 || cursor === 6) && this.selectionStart === this.selectionEnd) {
                    e.preventDefault();
                    const val = this.value;
                    this.value = val.slice(0, cursor - 2) + val.slice(cursor);
                    this.setSelectionRange(cursor - 2, cursor - 2);
                }
            }
        } else {
            isDeleting = false;
        }
    });

    dobInput.addEventListener('input', function() {
        if (isDeleting) {
            updateMinorGuardianVisibility();
            return;
        }

        let digits = this.value.replace(/\D/g, '').slice(0, 8);
        let formatted = '';

        if (digits.length <= 2) {
            formatted = digits;
            if (digits.length === 2) formatted += '-';
        } else if (digits.length <= 4) {
            formatted = digits.slice(0, 2) + '-' + digits.slice(2);
            if (digits.length === 4) formatted += '-';
        } else {
            formatted = digits.slice(0, 2) + '-' + digits.slice(2, 4) + '-' + digits.slice(4);
        }

        this.value = formatted;
        updateMinorGuardianVisibility();
    });

    dobInput.addEventListener('paste', function(e) {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text') || '';

        // Handle YYYY-MM-DD or YYYY/MM/DD
        const isoMatch = text.trim().match(/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/);
        if (isoMatch) {
            const y = isoMatch[1];
            const m = isoMatch[2].padStart(2, '0');
            const d = isoMatch[3].padStart(2, '0');
            this.value = `${d}-${m}-${y}`;
            updateMinorGuardianVisibility();
            return;
        }

        // Clean digits
        let digits = text.replace(/\D/g, '').slice(0, 8);
        let formatted = '';
        if (digits.length <= 2) {
            formatted = digits;
            if (digits.length === 2) formatted += '-';
        } else if (digits.length <= 4) {
            formatted = digits.slice(0, 2) + '-' + digits.slice(2);
            if (digits.length === 4) formatted += '-';
        } else {
            formatted = digits.slice(0, 2) + '-' + digits.slice(2, 4) + '-' + digits.slice(4);
        }
        this.value = formatted;
        updateMinorGuardianVisibility();
    });

    dobInput.addEventListener('blur', updateMinorGuardianVisibility);
    dobInput.addEventListener('change', updateMinorGuardianVisibility);
    dobInput.addEventListener('keyup', updateMinorGuardianVisibility);
})();

// Minor Case (< 18 years at apply date): show & require Father/Mother Aadhaar No
function calculateAgeFromDobString(dobStr) {
    const clean = String(dobStr || '').trim().replace(/\//g, '-');
    const m = clean.match(/^(\d{2})-(\d{2})-(\d{4})$/);
    if (!m) return null;
    const day = parseInt(m[1], 10);
    const month = parseInt(m[2], 10);
    const year = parseInt(m[3], 10);
    if (month < 1 || month > 12 || day < 1 || day > 31 || year < 1900 || year > 2100) return null;
    const birthDate = new Date(year, month - 1, day);
    if (birthDate.getFullYear() !== year || birthDate.getMonth() !== month - 1 || birthDate.getDate() !== day) {
        return null;
    }
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const md = today.getMonth() - birthDate.getMonth();
    if (md < 0 || (md === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return age;
}

function updateMinorGuardianLabel() {
    const typeEl = document.getElementById('minor_guardian_type');
    const lblEl = document.getElementById('fatherAadhaarLabel');
    const inpEl = document.getElementById('father_aadhaar_no');
    if (!typeEl || !lblEl || !inpEl) return;
    if (typeEl.value === 'MOTHER') {
        lblEl.innerHTML = 'MOTHER AADHAAR NO <span class="req-star">*</span>';
        inpEl.placeholder = 'Enter 12-digit Mother Aadhaar No';
    } else {
        lblEl.innerHTML = 'FATHER AADHAAR NO <span class="req-star">*</span>';
        inpEl.placeholder = 'Enter 12-digit Father Aadhaar No';
    }
}

function updateMinorGuardianVisibility() {
    const dobInput = document.getElementById('dob');
    const section = document.getElementById('minorGuardianSection');
    const badge = document.getElementById('dobMinorBadge');
    const ageLabel = document.getElementById('minorAgeLabel');
    const aadhaarInput = document.getElementById('father_aadhaar_no');
    if (!dobInput || !section || !aadhaarInput) return;

    const age = calculateAgeFromDobString(dobInput.value);
    const isMinor = (age !== null && age >= 0 && age < 18);

    if (isMinor) {
        section.classList.remove('d-none');
        if (badge) badge.classList.remove('d-none');
        if (ageLabel) ageLabel.textContent = 'Age: ' + age + ' yrs';
        aadhaarInput.required = true;
        updateMinorGuardianLabel();
    } else {
        section.classList.add('d-none');
        if (badge) badge.classList.add('d-none');
        aadhaarInput.required = false;
    }
}

document.getElementById('minor_guardian_type')?.addEventListener('change', updateMinorGuardianLabel);
document.getElementById('father_aadhaar_no')?.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 12);
});
updateMinorGuardianVisibility();

// Instant submit feedback & RAM-only upload safety guard
document.getElementById('panCardForm')?.addEventListener('submit', function(e) {
    ['photo', 'signature', 'aadhar_card', 'pan_form'].forEach(function(key) {
        var b64 = document.getElementById(key + '_base64');
        var fInp = document.getElementById('file_' + key);
        if (b64 && b64.value && fInp) {
            try { fInp.value = ''; } catch (err) {}
        }
    });

    const btn = document.getElementById('submitPanBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Submitting & Opening Pan Card List...';
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
