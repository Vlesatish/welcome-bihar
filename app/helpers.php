<?php
/**
 * Global Helper Functions for Welcome Bihar
 */

if (!function_exists('__')) {
    function __(string $key, ?string $fallback = null): string
    {
        return \App\Services\TranslationService::trans($key, $fallback);
    }
}

if (!function_exists('e')) {
    function e(?string $string): string
    {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return \App\Services\AuthService::generateCsrfToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('get_application_progress')) {
    /**
     * Calculates 4-step Bihar RTPS / ServicePlus workflow progress:
     * Step 1: Application Submission (आवेदन जमा)
     * Step 2: Form Details Verification (प्रपत्र सत्यापन)
     * Step 3: Field Verification (क्षेत्रीय जांच - Field Inquiry / Forwarded to RO/CO)
     * Step 4: Approval & Certificate Delivery (स्वीकृति एवं प्रमाण पत्र / RO-CO Sign)
     */
    function get_application_progress(array $app): array
    {
        $status = strtoupper((string)($app['status'] ?? 'PENDING'));
        $rawRejectionReason = trim((string)($app['rejection_reason'] ?? ''));
        $remarks = (string)($app['admin_remarks'] ?? '') . ' ' . (string)($app['remarks'] ?? '');
        $portalStatus = (string)($app['portal_status'] ?? '');
        $lastMsg = (string)($app['last_status_message'] ?? '');
        $hasResult = !empty($app['result_file']);

        $blob = strtolower($status . ' ' . $rawRejectionReason . ' ' . $remarks . ' ' . $portalStatus . ' ' . $lastMsg);

        $isRejected = ($status === 'REJECTED' || str_contains($blob, 'reject') || str_contains($blob, 'अस्वीकृत'));

        // Extract clean rejection reason
        $rejectionReason = '';
        if ($isRejected) {
            $candidates = [
                $rawRejectionReason,
                (string)($app['remarks'] ?? ''),
                (string)($app['admin_remarks'] ?? ''),
                $lastMsg
            ];
            foreach ($candidates as $c) {
                $cClean = trim($c);
                if (empty($cClean)) continue;
                $cClean = preg_replace('/^(?:rejected on portal:\s*|rejected on official portal:\s*|rejected:\s*)/i', '', $cClean);
                $cClean = trim($cClean);
                if (!empty($cClean) && strtolower($cClean) !== 'view' && strtolower($cClean) !== 'na' && strtolower($cClean) !== 'application has been rejected on official portal.') {
                    $rejectionReason = $cClean;
                    break;
                }
            }
            if (empty($rejectionReason)) {
                $rejectionReason = 'Official portal par rejected; reason abhi load nahi hua';
            }
        }

        $step = 1;
        $rejectedStep = 3; // Standard Bihar RTPS: Step 1 (Submission: Done), Step 2 (Form Details: Done), Step 3 (Field Verification: Rejected), Step 4 (Approval: Rejected/Not Issued)

        if ($status === 'COMPLETED' || $status === 'APPROVED' || $status === 'ATTACHED' || $hasResult || !empty($app['certificate_path']) || !empty($app['certificate_file']) || (str_contains($blob, 'delivered') && !str_contains($blob, 'not available'))) {
            $step = 4;
        } elseif ($isRejected) {
            $step = 3;
            $rejectedStep = 3;
        } elseif ($status === 'IN_PROCESS' || $status === 'UNDER_REVIEW' || $status === 'UNAVAILABLE' || $status === 'CHECKING' || !empty($portalStatus)) {
            if (
                str_contains($blob, 'sign pending') ||
                str_contains($blob, 'forwarded to ro/co') ||
                str_contains($blob, 'forwarded to ro') ||
                str_contains($blob, 'forwarded to co') ||
                str_contains($blob, 'field verification: forwarded') ||
                str_contains($blob, 'field verification') ||
                str_contains($blob, 'forwarded') ||
                str_contains($blob, 'digital signature pending') ||
                str_contains($blob, 'sign baki hai')
            ) {
                $step = 3;
            } elseif (
                str_contains($blob, 'verification') ||
                str_contains($blob, 'inquiry') ||
                str_contains($blob, 'under process') ||
                str_contains($blob, 'processing')
            ) {
                $step = 2;
            } else {
                $step = 2;
            }
        } else { // PENDING
            $step = 1;
        }

        $stepNames = [
            1 => [
                'en' => 'Application Submission',
                'hi' => 'आवेदन जमा',
                'desc' => 'Online application submitted'
            ],
            2 => [
                'en' => 'Form Details Verification',
                'hi' => 'प्रपत्र सत्यापन',
                'desc' => 'Online form verified & forwarded'
            ],
            3 => [
                'en' => 'Field Verification',
                'hi' => 'क्षेत्रीय जांच (Field Inquiry)',
                'desc' => 'RI / CI inquiry & verification'
            ],
            4 => [
                'en' => 'Approval & Certificate',
                'hi' => 'स्वीकृति एवं प्रमाण पत्र',
                'desc' => 'RO/CO digital signature & delivery'
            ]
        ];

        return [
            'current_step' => $step,
            'is_completed' => ($step === 4 && !$isRejected),
            'is_rejected' => $isRejected,
            'rejected_step' => $rejectedStep,
            'rejection_reason' => $rejectionReason,
            'step_names' => $stepNames,
            'summary' => $isRejected ? 'Application Rejected' : ($step === 4 ? '4/4 Steps Completed' : "{$step}/4 Steps Completed")
        ];
    }
}

if (!function_exists('render_status_stepper_dots')) {
    /**
     * Renders 4 round points (circular dots) next to the status badge.
     * Completed steps are vibrant green; rejected steps are red; pending steps are gray.
     */
    function render_status_stepper_dots(array $app, bool $showText = true, string $extraClass = ''): string
    {
        $prog = get_application_progress($app);
        $curr = $prog['current_step'];
        $isRej = $prog['is_rejected'];
        $rejReason = $prog['rejection_reason'] ?? '';
        $rejStep = $prog['rejected_step'] ?? 3;

        $summaryTitle = $isRej
            ? ('Application Rejected: ' . $rejReason)
            : $prog['summary'];

        $html = '<div class="rtps-step-dots ' . e($extraClass) . '" title="' . e($summaryTitle) . '">';
        for ($i = 1; $i <= 4; $i++) {
            if ($isRej) {
                if ($i === 1) {
                    $dotClass = 'done';
                    $lineClass = 'done';
                    $stepTitle = 'Step 1: Application Submission (Completed)';
                } elseif ($i === 2) {
                    $dotClass = 'done';
                    $lineClass = 'rejected';
                    $stepTitle = 'Step 2: Form Details Verification (Completed)';
                } elseif ($i === 3) {
                    $dotClass = 'rejected';
                    $lineClass = 'rejected';
                    $stepTitle = 'Step 3: Field Verification (REJECTED: ' . $rejReason . ')';
                } else {
                    $dotClass = 'rejected';
                    $lineClass = 'rejected';
                    $stepTitle = 'Step 4: Approval & Certificate (Rejected - Certificate Not Generated)';
                }
            } else {
                $isDone = ($i <= $curr);
                $isActive = (!$isDone && $i === ($curr + 1));
                $dotClass = $isDone ? 'done' : ($isActive ? 'active' : 'pending');
                $lineClass = ($i < $curr) ? 'done' : 'pending';
                $stepTitle = 'Step ' . $i . ': ' . $prog['step_names'][$i]['en'] . ' (' . ($isDone ? 'Completed' : ($isActive ? 'In Progress' : 'Pending')) . ')';
            }

            $html .= '<span class="rtps-dot ' . $dotClass . '" title="' . e($stepTitle) . '"></span>';

            if ($i < 4) {
                $html .= '<span class="rtps-line ' . $lineClass . '"></span>';
            }
        }
        if ($showText) {
            if ($isRej) {
                $html .= '<span class="rtps-step-text text-danger" title="' . e('Rejected: ' . $rejReason) . '">3/4 Rej</span>';
            } else {
                $colorClass = $curr === 4 ? 'text-success' : 'text-muted';
                $html .= '<span class="rtps-step-text ' . $colorClass . '">' . ($curr . '/4') . '</span>';
            }
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('render_status_stepper_bar')) {
    /**
     * Renders an expanded 4-step progress stepper card for detailed views.
     */
    function render_status_stepper_bar(array $app): string
    {
        $prog = get_application_progress($app);
        $curr = $prog['current_step'];
        $isRej = $prog['is_rejected'];
        $rejReason = $prog['rejection_reason'] ?? '';

        $progressPercent = ($curr - 1) / 3 * 100;
        if ($curr === 4 && !$isRej) $progressPercent = 100;
        if ($isRej) $progressPercent = 66; // reaches step 3

        $badgeClass = $isRej ? 'bg-danger' : ($curr === 4 ? 'bg-success' : 'bg-primary');
        $badgeText = $isRej ? 'Rejected (3/4 Rej)' : ($curr === 4 ? '4/4 Completed' : ($curr . '/4 In Progress'));

        $html = '<div class="card border rounded-3 p-3 bg-white shadow-2xs mb-4">';
        $html .= '<div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">';
        $html .= '<div>';
        $html .= '<h6 class="fw-bold mb-0 text-dark"><i class="bi bi-diagram-3-fill text-primary me-2"></i>RTPS ServicePlus 4-Step Tracking</h6>';
        $html .= '<div class="text-muted small">Status: <strong class="' . ($isRej ? 'text-danger' : '') . '">' . e($isRej ? 'Application Rejected on Portal' : $prog['summary']) . '</strong></div>';
        $html .= '</div>';
        $html .= '<span class="badge ' . $badgeClass . ' px-2.5 py-1 rounded-pill">' . e($badgeText) . '</span>';
        $html .= '</div>';

        $html .= '<div class="rtps-stepper-lg">';
        $html .= '<div class="rtps-stepper-track"><div class="rtps-stepper-progress ' . ($isRej ? 'bg-danger' : '') . '" style="width: ' . (int)$progressPercent . '%;"></div></div>';

        for ($i = 1; $i <= 4; $i++) {
            if ($isRej) {
                if ($i === 1) {
                    $circleClass = 'done';
                    $icon = '<i class="bi bi-check-lg"></i>';
                    $statusText = '<span class="text-success fw-bold">Completed</span>';
                } elseif ($i === 2) {
                    $circleClass = 'done';
                    $icon = '<i class="bi bi-check-lg"></i>';
                    $statusText = '<span class="text-success fw-bold">Completed</span>';
                } elseif ($i === 3) {
                    $circleClass = 'rejected';
                    $icon = '<i class="bi bi-x-lg"></i>';
                    $statusText = '<span class="text-danger fw-bold">Rejected</span>';
                } else {
                    $circleClass = 'rejected';
                    $icon = '<i class="bi bi-x-lg"></i>';
                    $statusText = '<span class="text-danger fw-bold">Not Issued</span>';
                }
            } else {
                $isDone = ($i <= $curr);
                $isActive = (!$isDone && $i === ($curr + 1));
                $circleClass = $isDone ? 'done' : ($isActive ? 'active' : 'pending');
                $icon = $isDone ? '<i class="bi bi-check-lg"></i>' : (string)$i;
                $statusText = $isDone ? '<span class="text-success fw-bold">Completed</span>' : ($isActive ? '<span class="text-primary fw-bold">In Progress</span>' : '<span class="text-muted">Pending</span>');
            }

            $html .= '<div class="rtps-stepper-item">';
            $html .= '<div class="rtps-stepper-circle ' . $circleClass . '">' . $icon . '</div>';
            $html .= '<div class="fw-bold text-dark small" style="font-size: 0.78rem;">' . e($prog['step_names'][$i]['en']) . '</div>';
            $html .= '<div class="text-muted" style="font-size: 0.7rem;">' . e($prog['step_names'][$i]['hi']) . '</div>';
            $html .= '<div style="font-size: 0.72rem;" class="mt-0.5">' . $statusText . '</div>';
            $html .= '</div>';
        }

        $html .= '</div>'; // .rtps-stepper-lg

        if ($isRej && !empty($rejReason)) {
            $html .= '<div class="alert alert-danger border border-danger-subtle d-flex align-items-start gap-2 py-2 px-3 mt-3 mb-0 rounded-3" style="font-size: 0.8rem;">';
            $html .= '<i class="bi bi-exclamation-octagon-fill text-danger fs-5 mt-0.5 flex-shrink-0"></i>';
            $html .= '<div>';
            $html .= '<div class="fw-bold text-danger">Rejection Reason (अस्वीकृति का कारण):</div>';
            $html .= '<div class="text-dark mt-0.5">' . e($rejReason) . '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>'; // .card

        return $html;
    }
}
