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
        $remarks = (string)($app['admin_remarks'] ?? '') . ' ' . (string)($app['remarks'] ?? '');
        $portalStatus = (string)($app['portal_status'] ?? '');
        $lastMsg = (string)($app['last_status_message'] ?? '');
        $hasResult = !empty($app['result_file']);

        $blob = strtolower($status . ' ' . $remarks . ' ' . $portalStatus . ' ' . $lastMsg);

        $step = 1;
        $isRejected = ($status === 'REJECTED' || str_contains($blob, 'rejected'));

        if ($status === 'COMPLETED' || $status === 'APPROVED' || $status === 'ATTACHED' || $hasResult || !empty($app['certificate_path']) || !empty($app['certificate_file']) || (str_contains($blob, 'delivered') && !str_contains($blob, 'not available'))) {
            $step = 4;
        } elseif ($isRejected) {
            $step = 1;
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
            'is_completed' => ($step === 4),
            'is_rejected' => $isRejected,
            'step_names' => $stepNames,
            'summary' => $isRejected ? 'Application Rejected' : ($step === 4 ? '4/4 Steps Completed' : "{$step}/4 Steps Completed")
        ];
    }
}

if (!function_exists('render_status_stepper_dots')) {
    /**
     * Renders 4 round points (circular dots) next to the status badge.
     * Completed steps are vibrant green; remaining steps are gray.
     */
    function render_status_stepper_dots(array $app, bool $showText = true, string $extraClass = ''): string
    {
        $prog = get_application_progress($app);
        $curr = $prog['current_step'];
        $isRej = $prog['is_rejected'];

        $html = '<div class="rtps-step-dots ' . e($extraClass) . '" title="' . e($prog['summary']) . '">';
        for ($i = 1; $i <= 4; $i++) {
            $isDone = ($i <= $curr && !$isRej) || ($isRej && $i === 1);
            $isActive = (!$isDone && $i === ($curr + 1) && !$isRej);
            $dotClass = $isDone ? 'done' : ($isActive ? 'active' : 'pending');
            $lineClass = ($i < $curr && !$isRej) ? 'done' : 'pending';

            $stepTitle = 'Step ' . $i . ': ' . $prog['step_names'][$i]['en'] . ' (' . ($isDone ? 'Completed' : ($isActive ? 'In Progress' : 'Pending')) . ')';
            $html .= '<span class="rtps-dot ' . $dotClass . '" title="' . e($stepTitle) . '"></span>';

            if ($i < 4) {
                $html .= '<span class="rtps-line ' . $lineClass . '"></span>';
            }
        }
        if ($showText) {
            $colorClass = $curr === 4 ? 'text-success' : ($isRej ? 'text-danger' : 'text-muted');
            $html .= '<span class="rtps-step-text ' . $colorClass . '">' . ($isRej ? 'Rej' : ($curr . '/4')) . '</span>';
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

        $progressPercent = ($curr - 1) / 3 * 100;
        if ($curr === 4) $progressPercent = 100;

        $html = '<div class="card border rounded-3 p-3 bg-white shadow-2xs mb-4">';
        $html .= '<div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">';
        $html .= '<div>';
        $html .= '<h6 class="fw-bold mb-0 text-dark"><i class="bi bi-diagram-3-fill text-primary me-2"></i>RTPS ServicePlus 4-Step Tracking</h6>';
        $html .= '<div class="text-muted small">Status: <strong>' . e($prog['summary']) . '</strong></div>';
        $html .= '</div>';
        $html .= '<span class="badge ' . ($curr === 4 ? 'bg-success' : ($isRej ? 'bg-danger' : 'bg-primary')) . ' px-2.5 py-1 rounded-pill">' . e($prog['summary']) . '</span>';
        $html .= '</div>';

        $html .= '<div class="rtps-stepper-lg">';
        $html .= '<div class="rtps-stepper-track"><div class="rtps-stepper-progress" style="width: ' . (int)$progressPercent . '%;"></div></div>';

        for ($i = 1; $i <= 4; $i++) {
            $isDone = ($i <= $curr && !$isRej) || ($isRej && $i === 1);
            $isActive = (!$isDone && $i === ($curr + 1) && !$isRej);
            $circleClass = $isDone ? 'done' : ($isActive ? 'active' : 'pending');
            $icon = $isDone ? '<i class="bi bi-check-lg"></i>' : (string)$i;

            $statusText = $isDone ? '<span class="text-success fw-bold">Completed</span>' : ($isActive ? '<span class="text-primary fw-bold">In Progress</span>' : '<span class="text-muted">Pending</span>');
            if ($isRej && $i === 2) {
                $statusText = '<span class="text-danger fw-bold">Rejected</span>';
            }

            $html .= '<div class="rtps-stepper-item">';
            $html .= '<div class="rtps-stepper-circle ' . $circleClass . '">' . $icon . '</div>';
            $html .= '<div class="fw-bold text-dark small" style="font-size: 0.78rem;">' . e($prog['step_names'][$i]['en']) . '</div>';
            $html .= '<div class="text-muted" style="font-size: 0.7rem;">' . e($prog['step_names'][$i]['hi']) . '</div>';
            $html .= '<div style="font-size: 0.72rem;" class="mt-0.5">' . $statusText . '</div>';
            $html .= '</div>';
        }

        $html .= '</div>'; // .rtps-stepper-lg
        $html .= '</div>'; // .card

        return $html;
    }
}
