<?php
declare(strict_types=1);

/**
 * View: New Pan Card Apply (Sirf Aadhaar Card Se)
 * 15 Input Fields + 4 Upload Cards + Previous Requests List
 */
require dirname(__DIR__) . '/layouts/header.php';

$panFee = 107.00;
$userBal = (float)($walletBalance ?? 0.00);
$hasEnoughBalance = $userBal >= $panFee;
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
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 1.25rem;
    box-shadow: 0 2px 8px -2px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    transition: all 0.2s ease;
}

.req-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 6px 16px -2px rgba(0, 0, 0, 0.08);
}

.req-card-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 0.75rem 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.req-card-body {
    padding: 1.15rem 1.25rem;
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

    <!-- Top Header Card -->
    <div class="pan-page-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div class="d-flex align-items-center gap-3">
            <div class="pan-title-icon">
                <i class="bi bi-credit-card-2-front"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h4 class="mb-0 fw-bold text-dark">New Pan Card Apply</h4>
                    <span class="badge bg-secondary px-2.5 py-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">PAN_CARD</span>
                </div>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <span class="badge bg-warning text-dark px-2.5 py-1 fw-bold" style="font-size: 0.75rem;">
                        <i class="bi bi-wallet2 me-1"></i> Wallet Se Charge Katega Rs: <?= number_format($panFee, 2) ?>
                    </span>
                    <span class="text-muted small d-none d-sm-inline">• Sirf Aadhaar Card Se Pan Card Banaye</span>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <div class="text-end">
                <div class="text-muted small fw-semibold" style="font-size: 0.72rem;">WALLET BALANCE</div>
                <div class="fs-5 fw-bold text-success">₹<?= number_format($userBal, 2) ?></div>
            </div>
            <a href="/wallet/topup?amount=<?= $panFee ?>" class="btn btn-sm btn-primary rounded-pill px-3 py-1.5 fw-semibold shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Recharge / Add Fund
            </a>
        </div>
    </div>

    <!-- Notice Banner -->
    <div class="pan-notice-card d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
        <div class="d-flex align-items-center gap-2 text-danger fw-bold">
            <i class="bi bi-patch-check-fill fs-5"></i>
            <span>Physical Pan Card ke sath E-Pan Card Bhi Milega</span>
        </div>
        <div>
            <a href="/wallet/topup?amount=<?= $panFee ?>" class="btn btn-sm btn-danger rounded-pill px-3 py-1 fw-semibold text-white">
                <i class="bi bi-wallet-fill me-1"></i> Recharge ₹<?= number_format($panFee, 2) ?>
            </a>
        </div>
    </div>

    <!-- Main Pan Card Application Form -->
    <div class="pan-form-card mb-5">
        <div class="p-3 p-md-4 border-bottom bg-light">
            <h5 class="mb-1 fw-bold text-dark"><i class="bi bi-person-lines-fill text-primary me-2"></i> Pan Card Application Details</h5>
            <p class="text-muted small mb-0">Please fill all mandatory fields marked with (<span class="text-danger">*</span>) accurately as per Aadhaar Card.</p>
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
                        <option value="Bihar" selected>Bihar</option>
                        <option value="Jharkhand">Jharkhand</option>
                        <option value="Uttar Pradesh">Uttar Pradesh</option>
                        <option value="West Bengal">West Bengal</option>
                        <option value="Delhi">Delhi</option>
                        <option value="Maharashtra">Maharashtra</option>
                        <option value="Madhya Pradesh">Madhya Pradesh</option>
                        <option value="Rajasthan">Rajasthan</option>
                        <option value="Punjab">Punjab</option>
                        <option value="Haryana">Haryana</option>
                        <option value="Gujarat">Gujarat</option>
                        <option value="Assam">Assam</option>
                        <option value="Odisha">Odisha</option>
                        <option value="Chhattisgarh">Chhattisgarh</option>
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

    <!-- ========================================================
         PREVIOUS REQUESTS SECTION
         ======================================================== -->
    <div class="req-section-header">
        <div class="d-flex align-items-center gap-2">
            <h5 class="mb-0 fw-bold text-dark">Previous Requests</h5>
            <span class="badge bg-primary rounded-pill px-2.5 py-1" style="font-size: 0.78rem;"><?= $totalRequests ?></span>
        </div>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-calendar3 me-1"></i> All Date
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><a class="dropdown-item" href="?status=<?= urlencode($statusFilter) ?>">All Time</a></li>
                <li><a class="dropdown-item" href="?status=<?= urlencode($statusFilter) ?>&date=today">Today</a></li>
                <li><a class="dropdown-item" href="?status=<?= urlencode($statusFilter) ?>&date=this_month">This Month</a></li>
            </ul>
        </div>
    </div>

    <!-- Search Box and Status Pills -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <!-- Live Search Input -->
        <div class="position-relative flex-grow-1" style="max-width: 480px;">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
            <input type="text" id="liveSearchInput" class="form-control rounded-pill ps-5 py-2 shadow-2xs" 
                   placeholder="Search Name & Aadhar number, PAN number..." 
                   value="<?= e($search) ?>" onkeyup="handleFilterSearch(this.value)">
        </div>

        <!-- Filter Tabs -->
        <div class="d-flex flex-wrap gap-2">
            <a href="?status=ALL<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" 
               class="req-filter-pill <?= ($statusFilter === 'ALL' || empty($statusFilter)) ? 'active' : '' ?>">
                <span>All Requests</span>
                <span class="badge bg-light text-dark rounded-pill"><?= $totalRequests ?></span>
            </a>
            <a href="?status=PENDING<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" 
               class="req-filter-pill <?= $statusFilter === 'PENDING' ? 'active' : '' ?>">
                <span>Pending</span>
                <span class="badge bg-warning text-dark rounded-pill"><?= $pendingCount ?></span>
            </a>
            <a href="?status=COMPLETED<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" 
               class="req-filter-pill <?= $statusFilter === 'COMPLETED' ? 'active' : '' ?>">
                <span>Completed</span>
                <span class="badge bg-success text-white rounded-pill"><?= $completedCount ?></span>
            </a>
            <a href="?status=REJECTED<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" 
               class="req-filter-pill <?= $statusFilter === 'REJECTED' ? 'active' : '' ?>">
                <span>Rejected</span>
                <span class="badge bg-danger text-white rounded-pill"><?= $rejectedCount ?></span>
            </a>
        </div>
    </div>

    <!-- Request Cards Container -->
    <div id="requestCardsContainer">
        <?php if (empty($requests)): ?>
            <div class="pan-form-card p-5 text-center text-muted">
                <i class="bi bi-folder-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                <h6 class="fw-bold">No Pan Card requests found</h6>
                <p class="small mb-0">Submit a new request above using Aadhaar card details to see it listed here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($requests as $req): ?>
                <?php
                    $fields = $req['fields'] ?? [];
                    $st = strtoupper($req['status']);
                    $badgeClass = 'bg-warning text-dark';
                    $badgeIcon = 'bi-hourglass-split';
                    if ($st === 'COMPLETED' || $st === 'APPROVED') {
                        $badgeClass = 'bg-success text-white';
                        $badgeIcon = 'bi-check-circle-fill';
                    } elseif ($st === 'REJECTED') {
                        $badgeClass = 'bg-danger text-white';
                        $badgeIcon = 'bi-x-circle-fill';
                    }

                    $reqDate = date('d-M-Y, h:i:s a', strtotime($req['created_at']));
                    $isToday = date('Y-m-d', strtotime($req['created_at'])) === date('Y-m-d');
                    $datePrefix = $isToday ? 'Today | ' : '';

                    $photoUrl = $fields['photo'] ?? null;
                    $sigUrl = $fields['signature'] ?? null;
                    $aadharUrl = $fields['aadhar_card'] ?? null;
                    $panFormUrl = $fields['pan_form'] ?? null;
                    $resultFile = $req['result_file'] ?? null;
                ?>
                <div class="req-card" data-ref="<?= e($req['app_ref']) ?>" data-name="<?= e(strtolower($req['applicant_name'] ?? '')) ?>" data-aadhar="<?= e($req['applicant_identifier'] ?? '') ?>">
                    <!-- Card Header -->
                    <div class="req-card-header">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-dark text-white fw-bold px-2.5 py-1" style="font-size: 0.76rem;">
                                #<?= e($req['app_ref']) ?>
                            </span>
                            <span class="text-muted small">
                                <i class="bi bi-clock me-1"></i> <?= $datePrefix . $reqDate ?>
                            </span>
                        </div>
                        <div>
                            <span class="badge <?= $badgeClass ?> px-2.5 py-1.5 fw-bold" style="font-size: 0.75rem;">
                                <i class="bi <?= $badgeIcon ?> me-1"></i> <?= e($st) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="req-card-body">
                        <!-- 2-Column Info Grid matching the screenshot -->
                        <div class="row g-3">
                            <!-- Left Column -->
                            <div class="col-12 col-md-6 border-end-md">
                                <div class="req-field-row">
                                    <div class="req-field-label">FULL NAME</div>
                                    <div class="req-field-value text-uppercase"><?= e($req['applicant_name'] ?: ($fields['full_name'] ?? '-')) ?></div>
                                </div>
                                <div class="req-field-row">
                                    <div class="req-field-label">DATE OF BIRTH</div>
                                    <div class="req-field-value"><?= e($fields['dob'] ?? '-') ?></div>
                                </div>
                                <div class="req-field-row">
                                    <div class="req-field-label">AADHAAR NO</div>
                                    <div class="req-field-value text-primary font-monospace"><?= e($req['applicant_identifier'] ?: ($fields['aadhaar_no'] ?? '-')) ?></div>
                                </div>
                                <div class="req-field-row">
                                    <div class="req-field-label">MOBILE NO</div>
                                    <div class="req-field-value"><?= e($req['applicant_mobile'] ?: ($fields['mobile_no'] ?? '-')) ?></div>
                                </div>
                                <div class="req-field-row">
                                    <div class="req-field-label">EMAIL ID</div>
                                    <div class="req-field-value text-lowercase"><?= e($fields['email_id'] ?? '-') ?></div>
                                </div>
                                <div class="req-field-row">
                                    <div class="req-field-label">FATHER NAME</div>
                                    <div class="req-field-value text-uppercase"><?= e($fields['father_name'] ?? '-') ?></div>
                                </div>
                                <div class="req-field-row">
                                    <div class="req-field-label">VILLAGE</div>
                                    <div class="req-field-value text-uppercase"><?= e($fields['village'] ?? '-') ?></div>
                                </div>
                                <div class="req-field-row">
                                    <div class="req-field-label">PINCODE</div>
                                    <div class="req-field-value font-monospace"><?= e($fields['pincode'] ?? '-') ?></div>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="col-12 col-md-6">
                                <div class="req-field-row">
                                    <div class="req-field-label">GENDER</div>
                                    <div class="req-field-value"><?= e($fields['gender'] ?? '-') ?></div>
                                </div>
                                <div class="req-field-row">
                                    <div class="req-field-label">SELECT STATE</div>
                                    <div class="req-field-value"><?= e($fields['state'] ?? 'Bihar') ?></div>
                                </div>
                                <div class="req-field-row">
                                    <div class="req-field-label">MOTHER NAME</div>
                                    <div class="req-field-value text-uppercase"><?= e($fields['mother_name'] ?? '-') ?></div>
                                </div>
                                <div class="req-field-row">
                                    <div class="req-field-label">HUSBAND NAME</div>
                                    <div class="req-field-value text-uppercase"><?= e(!empty($fields['husband_name']) ? $fields['husband_name'] : '-') ?></div>
                                </div>
                                <div class="req-field-row">
                                    <div class="req-field-label">POST OFFICE</div>
                                    <div class="req-field-value text-uppercase"><?= e($fields['post_office'] ?? '-') ?></div>
                                </div>
                                <div class="req-field-row">
                                    <div class="req-field-label">BLOCK NAME</div>
                                    <div class="req-field-value text-uppercase"><?= e($fields['block_name'] ?? '-') ?></div>
                                </div>
                                <div class="req-field-row">
                                    <div class="req-field-label">DISTRIC NAME</div>
                                    <div class="req-field-value text-uppercase"><?= e($fields['district_name'] ?? '-') ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Documents Preview & Actions Row -->
                        <div class="mt-3 pt-3 border-top">
                            <div class="row g-2">
                                <!-- Photo Preview -->
                                <div class="col-6 col-sm-3">
                                    <div class="req-thumb-box">
                                        <div class="req-field-label text-center mb-1">PHOTO</div>
                                        <?php if ($photoUrl): ?>
                                            <img src="<?= e($photoUrl) ?>" class="req-thumb-img" alt="Photo" onclick="openImageModal('<?= e($photoUrl) ?>', 'Photo Preview')">
                                            <button type="button" class="btn btn-sm btn-outline-primary w-100 mt-1 py-0.5" style="font-size: 0.7rem;" onclick="openImageModal('<?= e($photoUrl) ?>', 'Photo Preview')">
                                                <i class="bi bi-eye"></i> View Image
                                            </button>
                                        <?php else: ?>
                                            <div class="text-muted small py-4">No Photo</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Signature Preview -->
                                <div class="col-6 col-sm-3">
                                    <div class="req-thumb-box">
                                        <div class="req-field-label text-center mb-1">SIGNATURE</div>
                                        <?php if ($sigUrl): ?>
                                            <img src="<?= e($sigUrl) ?>" class="req-thumb-img" alt="Signature" onclick="openImageModal('<?= e($sigUrl) ?>', 'Signature Preview')">
                                            <button type="button" class="btn btn-sm btn-outline-primary w-100 mt-1 py-0.5" style="font-size: 0.7rem;" onclick="openImageModal('<?= e($sigUrl) ?>', 'Signature Preview')">
                                                <i class="bi bi-eye"></i> View Image
                                            </button>
                                        <?php else: ?>
                                            <div class="text-muted small py-4">No Signature</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Aadhar Card Preview -->
                                <div class="col-6 col-sm-3">
                                    <div class="req-thumb-box h-100 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="req-field-label text-center mb-1">AADHAR CARD</div>
                                            <div class="py-2 text-danger">
                                                <i class="bi bi-file-earmark-pdf-fill fs-1"></i>
                                            </div>
                                        </div>
                                        <?php if ($aadharUrl): ?>
                                            <a href="<?= e($aadharUrl) ?>" target="_blank" class="btn btn-sm btn-outline-dark w-100 py-1" style="font-size: 0.72rem;">
                                                <i class="bi bi-box-arrow-up-right me-1"></i> View PDF / New Tab
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">Not Uploaded</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Pan Form Preview -->
                                <div class="col-6 col-sm-3">
                                    <div class="req-thumb-box h-100 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="req-field-label text-center mb-1">PAN FORM</div>
                                            <div class="py-2 text-primary">
                                                <i class="bi bi-file-earmark-text-fill fs-1"></i>
                                            </div>
                                        </div>
                                        <?php if ($panFormUrl): ?>
                                            <a href="<?= e($panFormUrl) ?>" target="_blank" class="btn btn-sm btn-outline-dark w-100 py-1" style="font-size: 0.72rem;">
                                                <i class="bi bi-box-arrow-up-right me-1"></i> View PDF / New Tab
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">Not Uploaded</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Green Output File Box (If Completed or Output Attached) -->
                        <?php if ($st === 'COMPLETED' || $st === 'APPROVED' || !empty($resultFile)): ?>
                            <div class="req-output-box">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                    <div class="d-flex align-items-center gap-2 text-success fw-bold">
                                        <i class="bi bi-check-circle-fill fs-5"></i>
                                        <span>Receiving / Output File (यहाँ से रसीद मिला है)</span>
                                    </div>
                                    <?php if (!empty($resultFile)): ?>
                                        <a href="<?= e($resultFile) ?>" target="_blank" class="btn btn-sm btn-outline-success bg-white fw-semibold">
                                            <i class="bi bi-eye me-1"></i> View
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div class="text-dark small mb-2">
                                    <strong>Operator Remark:</strong> <?= e($req['admin_remarks'] ?: ($req['remarks'] ?: 'Ack slip / Pan card processed successfully.')) ?>
                                </div>

                                <?php if (!empty($resultFile)): ?>
                                    <a href="<?= e($resultFile) ?>" download class="btn btn-sm btn-success rounded-pill px-3 py-1.5 fw-bold text-white shadow-2xs">
                                        <i class="bi bi-download me-1"></i> Download PDF File (यहाँ क्लिक करके डाउनलोड करें)
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($st === 'REJECTED'): ?>
                            <div class="req-reject-box">
                                <div class="d-flex align-items-center gap-2 text-danger fw-bold mb-1">
                                    <i class="bi bi-x-circle-fill fs-5"></i>
                                    <span>Application Rejected (आवेदन अस्वीकृत कर दिया गया है)</span>
                                </div>
                                <div class="text-danger small">
                                    <strong>Rejection Reason:</strong> <?= e($req['rejection_reason'] ?: 'Aadhaar data mismatch or unclear document upload.') ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- ========================================================
     MODALS: Image Viewer & Camera Photo Capture
     ======================================================== -->

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

// Live Filter Search in Previous Requests
function handleFilterSearch(query) {
    query = (query || '').toLowerCase().trim();
    const cards = document.querySelectorAll('.req-card');
    cards.forEach(card => {
        const ref = (card.getAttribute('data-ref') || '').toLowerCase();
        const name = (card.getAttribute('data-name') || '').toLowerCase();
        const aadhar = (card.getAttribute('data-aadhar') || '').toLowerCase();
        if (query === '' || ref.includes(query) || name.includes(query) || aadhar.includes(query)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Form Submission Spinner
document.getElementById('panCardForm')?.addEventListener('submit', function(e) {
    const btn = document.getElementById('submitPanBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing & Debiting ₹107.00...';
    }
});
</script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
