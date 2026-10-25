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

$panFee = 107.00;
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

/* Webcam Modal Styles */
#cameraVideo {
    width: 100%;
    max-height: 380px;
    object-fit: cover;
    border-radius: 8px;
    background: #000;
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
            <div>
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
                    <input type="text" class="pan-input" id="dob" name="dob" placeholder="E.g. 25-01-1995" pattern="\d{2}-\d{2}-\d{4}" required>
                    <div class="pan-helper">Format: DD-MM-YYYY, (Eight digits, only numbers/hyphen, valid calendar date)</div>
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
                        <option value="Bihar" selected>Bihar</option>
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
            </div>

            <!-- Upload Section (4 Cards) -->
            <div class="mt-4 pt-3 border-top">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i> Mandatory Documents Upload</h6>
                    <span class="text-muted small">Max file size: 2MB each (JPG, PNG, PDF)</span>
                </div>

                <div class="row g-3">
                    <!-- 16. PHOTO -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="pan-label">PHOTO <span class="req-star">*</span></label>
                        <div class="pan-upload-zone" id="drop_photo" onclick="triggerFileInput('file_photo')">
                            <i class="bi bi-person-bounding-box pan-upload-icon"></i>
                            <div class="pan-upload-title">Upload Document / Photo</div>
                            <div class="pan-upload-subtitle">(PDF / JPG / PNG max 2MB)</div>
                            <input type="file" id="file_photo" name="photo" accept=".jpg,.jpeg,.png,.pdf" style="display:none;" onchange="handleFileSelected(this, 'photo')">
                            <button type="button" class="pan-snap-btn" onclick="openCameraModal(event, 'photo')">
                                <i class="bi bi-camera-fill text-danger"></i> Snap Photo / फोटो खींचे
                            </button>
                            <div class="pan-file-preview d-none" id="preview_photo">
                                <div class="d-flex align-items-center gap-2 overflow-hidden">
                                    <img src="" id="thumb_photo" alt="Photo">
                                    <div class="text-start overflow-hidden">
                                        <div class="small fw-bold text-truncate" id="name_photo">file.jpg</div>
                                        <div class="text-muted" style="font-size: 0.68rem;" id="size_photo">150 KB</div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeFile(event, 'photo')"><i class="bi bi-x-circle-fill fs-5"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- 17. SIGNATURE -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="pan-label">SIGNATURE <span class="req-star">*</span></label>
                        <div class="pan-upload-zone" id="drop_signature" onclick="triggerFileInput('file_signature')">
                            <i class="bi bi-pen-fill pan-upload-icon"></i>
                            <div class="pan-upload-title">Upload Document / Signature</div>
                            <div class="pan-upload-subtitle">(PDF / JPG / PNG max 2MB)</div>
                            <input type="file" id="file_signature" name="signature" accept=".jpg,.jpeg,.png,.pdf" style="display:none;" onchange="handleFileSelected(this, 'signature')">
                            <button type="button" class="pan-snap-btn" onclick="openCameraModal(event, 'signature')">
                                <i class="bi bi-camera-fill text-danger"></i> Snap Photo / फोटो खींचे
                            </button>
                            <div class="pan-file-preview d-none" id="preview_signature">
                                <div class="d-flex align-items-center gap-2 overflow-hidden">
                                    <img src="" id="thumb_signature" alt="Signature">
                                    <div class="text-start overflow-hidden">
                                        <div class="small fw-bold text-truncate" id="name_signature">sig.jpg</div>
                                        <div class="text-muted" style="font-size: 0.68rem;" id="size_signature">80 KB</div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeFile(event, 'signature')"><i class="bi bi-x-circle-fill fs-5"></i></button>
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
                            <div class="pan-file-preview d-none" id="preview_aadhar_card">
                                <div class="d-flex align-items-center gap-2 overflow-hidden">
                                    <img src="" id="thumb_aadhar_card" alt="Aadhaar">
                                    <div class="text-start overflow-hidden">
                                        <div class="small fw-bold text-truncate" id="name_aadhar_card">aadhar.pdf</div>
                                        <div class="text-muted" style="font-size: 0.68rem;" id="size_aadhar_card">300 KB</div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeFile(event, 'aadhar_card')"><i class="bi bi-x-circle-fill fs-5"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- 19. PAN FORM -->
                    <div class="col-12 col-md-6 col-lg-3">
                        <label class="pan-label">PAN FORM <span class="req-star">*</span></label>
                        <div class="pan-upload-zone" id="drop_pan_form" onclick="triggerFileInput('file_pan_form')">
                            <i class="bi bi-file-earmark-medical-fill pan-upload-icon"></i>
                            <div class="pan-upload-title">Upload Document / Pan Form</div>
                            <div class="pan-upload-subtitle">(PDF / JPG / PNG max 2MB)</div>
                            <input type="file" id="file_pan_form" name="pan_form" accept=".pdf,.jpg,.jpeg,.png" style="display:none;" onchange="handleFileSelected(this, 'pan_form')">
                            <button type="button" class="pan-snap-btn" onclick="openCameraModal(event, 'pan_form')">
                                <i class="bi bi-camera-fill text-danger"></i> Snap Photo / फोटो खींचे
                            </button>
                            <div class="pan-file-preview d-none" id="preview_pan_form">
                                <div class="d-flex align-items-center gap-2 overflow-hidden">
                                    <img src="" id="thumb_pan_form" alt="Pan Form">
                                    <div class="text-start overflow-hidden">
                                        <div class="small fw-bold text-truncate" id="name_pan_form">pan_form.pdf</div>
                                        <div class="text-muted" style="font-size: 0.68rem;" id="size_pan_form">450 KB</div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeFile(event, 'pan_form')"><i class="bi bi-x-circle-fill fs-5"></i></button>
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

<!-- Camera Snap Modal (Webcam Capture) -->
<div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-camera me-1"></i> Live Camera Photo Capture</h6>
                <button type="button" class="btn-close btn-close-white" onclick="closeCameraModal()"></button>
            </div>
            <div class="modal-body text-center p-3">
                <div class="position-relative">
                    <video id="cameraVideo" autoplay playsinline></video>
                    <canvas id="cameraCanvas" style="display:none;"></canvas>
                </div>
                <div id="cameraStatus" class="small text-muted mt-2">Position face/document clearly in camera frame.</div>
            </div>
            <div class="modal-footer py-2 justify-content-between">
                <button type="button" class="btn btn-sm btn-secondary" onclick="closeCameraModal()">Cancel</button>
                <div class="d-flex gap-2">
                    <button type="button" id="snapCaptureBtn" class="btn btn-sm btn-danger px-3 fw-bold" onclick="captureLiveSnapshot()">
                        <i class="bi bi-record-circle me-1"></i> Capture Photo
                    </button>
                    <button type="button" id="useCaptureBtn" class="btn btn-sm btn-success px-3 fw-bold d-none" onclick="applyCapturedSnapshot()">
                        <i class="bi bi-check-lg me-1"></i> Use This Photo
                    </button>
                    <button type="button" id="retakeBtn" class="btn btn-sm btn-warning px-3 fw-bold d-none" onclick="retakeLiveSnapshot()">
                        <i class="bi bi-arrow-repeat me-1"></i> Retake
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
let capturedDataUrl = null;

// Trigger hidden file input
function triggerFileInput(inputId) {
    const el = document.getElementById(inputId);
    if (el) el.click();
}

// Handle file selection
function handleFileSelected(input, fieldKey) {
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];
    
    // Validate size (2MB max)
    if (file.size > 2 * 1024 * 1024) {
        alert('File size exceeds the 2MB limit. Please choose a smaller file.');
        input.value = '';
        return;
    }

    const previewBox = document.getElementById('preview_' + fieldKey);
    const thumbImg = document.getElementById('thumb_' + fieldKey);
    const nameEl = document.getElementById('name_' + fieldKey);
    const sizeEl = document.getElementById('size_' + fieldKey);

    nameEl.textContent = file.name;
    sizeEl.textContent = (file.size / 1024).toFixed(1) + ' KB';

    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(e) {
            thumbImg.src = e.target.result;
            thumbImg.style.display = 'block';
            document.getElementById(fieldKey + '_base64').value = e.target.result;
        };
        reader.readAsDataURL(file);
    } else {
        // PDF fallback icon
        thumbImg.src = 'https://cdn-icons-png.flaticon.com/512/337/337946.png';
        thumbImg.style.display = 'block';
        document.getElementById(fieldKey + '_base64').value = '';
    }

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
    const modal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
    modal.show();
}

// Camera Capture Modal
function openCameraModal(event, fieldKey) {
    event.stopPropagation();
    currentTargetField = fieldKey;
    capturedDataUrl = null;

    document.getElementById('snapCaptureBtn').classList.remove('d-none');
    document.getElementById('useCaptureBtn').classList.add('d-none');
    document.getElementById('retakeBtn').classList.add('d-none');
    document.getElementById('cameraVideo').style.display = 'block';
    document.getElementById('cameraCanvas').style.display = 'none';

    const modal = new bootstrap.Modal(document.getElementById('cameraModal'));
    modal.show();

    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } } })
            .then(stream => {
                cameraStream = stream;
                const video = document.getElementById('cameraVideo');
                video.srcObject = stream;
                video.play();
            })
            .catch(err => {
                alert('Unable to access camera: ' + err.message + '. You can upload file directly.');
                closeCameraModal();
            });
    } else {
        alert('Camera is not supported on this browser. Please use standard file upload.');
        closeCameraModal();
    }
}

function captureLiveSnapshot() {
    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('cameraCanvas');
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    capturedDataUrl = canvas.toDataURL('image/jpeg', 0.88);

    video.style.display = 'none';
    canvas.style.display = 'block';

    document.getElementById('snapCaptureBtn').classList.add('d-none');
    document.getElementById('useCaptureBtn').classList.remove('d-none');
    document.getElementById('retakeBtn').classList.remove('d-none');
}

function retakeLiveSnapshot() {
    capturedDataUrl = null;
    document.getElementById('cameraVideo').style.display = 'block';
    document.getElementById('cameraCanvas').style.display = 'none';

    document.getElementById('snapCaptureBtn').classList.remove('d-none');
    document.getElementById('useCaptureBtn').classList.add('d-none');
    document.getElementById('retakeBtn').classList.add('d-none');
}

function applyCapturedSnapshot() {
    if (!capturedDataUrl || !currentTargetField) return;

    // Set base64 value in hidden input
    document.getElementById(currentTargetField + '_base64').value = capturedDataUrl;

    // Display preview
    const previewBox = document.getElementById('preview_' + currentTargetField);
    const thumbImg = document.getElementById('thumb_' + currentTargetField);
    const nameEl = document.getElementById('name_' + currentTargetField);
    const sizeEl = document.getElementById('size_' + currentTargetField);

    thumbImg.src = capturedDataUrl;
    nameEl.textContent = 'camera_capture_' + Date.now() + '.jpg';
    sizeEl.textContent = 'Captured Photo';
    previewBox.classList.remove('d-none');

    closeCameraModal();
}

function closeCameraModal() {
    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
    }
    const modalEl = document.getElementById('cameraModal');
    const modalInstance = bootstrap.Modal.getInstance(modalEl);
    if (modalInstance) modalInstance.hide();
}


// Instant submit feedback - disables button and gives feedback while redirecting to list
document.getElementById('panCardForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitPanBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Submitting & Opening Pan Card List...';
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
