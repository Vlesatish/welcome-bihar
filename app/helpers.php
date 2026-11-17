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

        // Detect Ration Card / SmartPDS
        $appNum = (string)($app['applicant_identifier'] ?? ($app['application_number'] ?? ''));
        $appCode = (string)($app['application_code'] ?? '');
        $isRation = false;
        if (class_exists('\App\Services\SmartPdsService')) {
            $isRation = \App\Services\SmartPdsService::isRationCardApplication($appNum, $appCode);
        } else {
            $isRation = (bool)preg_match('/^RC\d{6,}/i', $appNum) || in_array(strtoupper($appCode), ['EPDS', 'RATION', 'RATION_CARD', 'RATIONCARD', 'RATIONCARDCOREETION', 'RATIONCARDCORRECTION', 'RC', 'SMARTPDS'], true);
        }

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
        $rejectedStep = 3;

        if ($status === 'COMPLETED' || $status === 'APPROVED' || $status === 'ATTACHED' || $hasResult || !empty($app['certificate_path']) || !empty($app['certificate_file']) || (str_contains($blob, 'delivered') && !str_contains($blob, 'not available'))) {
            $step = 4;
        } elseif ($isRejected) {
            if ($isRation) {
                $rejectedStep = (str_contains($blob, 'sdo') || str_contains($blob, 'dfso')) ? 3 : 2;
            } else {
                $rejectedStep = 3;
            }
            $step = $rejectedStep;
        } elseif ($status === 'IN_PROCESS' || $status === 'UNDER_REVIEW' || $status === 'UNAVAILABLE' || $status === 'CHECKING' || !empty($portalStatus)) {
            if ($isRation) {
                $step = (str_contains($blob, 'sdo') || str_contains($blob, 'dfso')) ? 3 : 2;
            } else {
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
                } else {
                    $step = 2;
                }
            }
        } else { // PENDING
            $step = 1;
        }

        if ($isRation) {
            $stepNames = [
                1 => ['en' => 'Submitted', 'hi' => 'जमा'],
                2 => ['en' => 'MO / Inspector', 'hi' => 'निरीक्षक'],
                3 => ['en' => 'SDO', 'hi' => 'अनुमंडल'],
                4 => ['en' => 'Approved', 'hi' => 'स्वीकृत']
            ];
        } else {
            $stepNames = [
                1 => ['en' => 'Submitted', 'hi' => 'जमा'],
                2 => ['en' => 'Verification', 'hi' => 'सत्यापन'],
                3 => ['en' => 'Inquiry', 'hi' => 'जांच'],
                4 => ['en' => 'Approval', 'hi' => 'स्वीकृति']
            ];
        }

        return [
            'is_ration' => $isRation,
            'current_step' => $step,
            'is_completed' => ($step === 4 && !$isRejected),
            'is_rejected' => $isRejected,
            'rejected_step' => $rejectedStep,
            'rejection_reason' => $rejectionReason,
            'step_names' => $stepNames,
            'summary' => $isRejected ? 'Rejected' : ($step === 4 ? 'Completed' : "{$step}/4 In Progress")
        ];
    }
}

if (!function_exists('render_status_stepper_dots')) {
    /**
     * Renders 4 round points (circular dots) next to the status badge.
     * Minimal text and clean status indicator.
     */
    function render_status_stepper_dots(array $app, bool $showText = true, string $extraClass = ''): string
    {
        $prog = get_application_progress($app);
        $curr = $prog['current_step'];
        $isRej = $prog['is_rejected'];
        $rejReason = $prog['rejection_reason'] ?? '';
        $rejStep = $prog['rejected_step'] ?? 3;

        $summaryTitle = $isRej
            ? ('Rejected: ' . $rejReason)
            : $prog['summary'];

        $html = '<div class="rtps-step-dots ' . e($extraClass) . '" title="' . e($summaryTitle) . '">';
        for ($i = 1; $i <= 4; $i++) {
            if ($isRej) {
                if ($i < $rejStep) {
                    $dotClass = 'done';
                    $lineClass = ($i < $rejStep - 1) ? 'done' : 'rejected';
                    $stepTitle = 'Step ' . $i . ': ' . $prog['step_names'][$i]['en'] . ' (Completed)';
                } elseif ($i === $rejStep) {
                    $dotClass = 'rejected';
                    $lineClass = 'rejected';
                    $stepTitle = 'Step ' . $i . ': ' . $prog['step_names'][$i]['en'] . ' (Rejected: ' . $rejReason . ')';
                } else {
                    $dotClass = 'rejected';
                    $lineClass = 'rejected';
                    $stepTitle = 'Step ' . $i . ': ' . $prog['step_names'][$i]['en'] . ' (Rejected)';
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
                $html .= '<span class="rtps-step-text text-danger" title="' . e('Rejected: ' . $rejReason) . '">' . $rejStep . '/4 Rej</span>';
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
     * Renders an expanded 4-step progress stepper card for detailed views with minimal text.
     */
    function render_status_stepper_bar(array $app): string
    {
        $prog = get_application_progress($app);
        $curr = $prog['current_step'];
        $isRej = $prog['is_rejected'];
        $rejReason = $prog['rejection_reason'] ?? '';
        $rejStep = $prog['rejected_step'] ?? 3;
        $isRation = !empty($prog['is_ration']);

        $cardTitle = $isRation ? 'Ration Card Tracking' : 'Application Tracking';
        $iconClass = $isRation ? 'bi-card-checklist' : 'bi-diagram-3-fill';

        $progressPercent = ($curr - 1) / 3 * 100;
        if ($curr === 4 && !$isRej) $progressPercent = 100;
        if ($isRej) {
            $progressPercent = $rejStep === 2 ? 33 : 66;
        }

        $badgeClass = $isRej ? 'bg-danger' : ($curr === 4 ? 'bg-success' : 'bg-primary');
        $badgeText = $isRej ? 'Rejected' : ($curr === 4 ? 'Completed' : ($curr . '/4'));

        $html = '<div class="card border rounded-3 p-3 bg-white shadow-2xs mb-4">';
        $html .= '<div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">';
        $html .= '<div class="d-flex align-items-center gap-2">';
        $html .= '<i class="bi ' . $iconClass . ' text-primary fs-5"></i>';
        $html .= '<h6 class="fw-bold mb-0 text-dark">' . e($cardTitle) . '</h6>';
        $html .= '</div>';
        $html .= '<span class="badge ' . $badgeClass . ' px-2.5 py-1 rounded-pill">' . e($badgeText) . '</span>';
        $html .= '</div>';

        $html .= '<div class="rtps-stepper-lg">';
        $html .= '<div class="rtps-stepper-track"><div class="rtps-stepper-progress ' . ($isRej ? 'bg-danger' : '') . '" style="width: ' . (int)$progressPercent . '%;"></div></div>';

        for ($i = 1; $i <= 4; $i++) {
            if ($isRej) {
                if ($i < $rejStep) {
                    $circleClass = 'done';
                    $icon = '<i class="bi bi-check-lg"></i>';
                    $statusText = '<span class="text-success fw-bold">Completed</span>';
                } elseif ($i === $rejStep) {
                    $circleClass = 'rejected';
                    $icon = '<i class="bi bi-x-lg"></i>';
                    $statusText = '<span class="text-danger fw-bold">Rejected</span>';
                } else {
                    $circleClass = 'rejected';
                    $icon = '<i class="bi bi-x-lg"></i>';
                    $statusText = '<span class="text-danger fw-bold">Rejected</span>';
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
            $html .= '<div class="alert alert-danger border border-danger-subtle d-flex align-items-center gap-2 py-2 px-3 mt-3 mb-0 rounded-3" style="font-size: 0.8rem;">';
            $html .= '<i class="bi bi-x-circle-fill text-danger fs-6 flex-shrink-0"></i>';
            $html .= '<div>';
            $html .= '<span class="fw-bold text-danger">Rejected: </span><span class="text-dark">' . e($rejReason) . '</span>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>'; // .card

        return $html;
    }
}
