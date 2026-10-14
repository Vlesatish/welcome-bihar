<?php
namespace App\Services;

use App\Config\Database;
use Exception;
use PDO;

class PricingService
{
    /**
     * Calculate final fee and distributor commission server-side with strict deterministic precedence
     *
     * Precedence:
     * 1. Exact District + Exact Block + Exact Application Code (Specificity: 35)
     * 2. Exact District + Exact Block + Wildcard/Any Code (Specificity: 30)
     * 3. Exact District + Any Block + Exact Application Code (Specificity: 25)
     * 4. Exact District + Any Block + Wildcard/Any Code (Specificity: 20)
     * 5. Any District + Any Block + Exact Application Code (Specificity: 15)
     * 6. Any District + Any Block + Any Code (General Rule: Specificity: 10)
     * 7. Fallback: Base Fee from data_types table (Standard Default)
     *
     * @param int $dataTypeId
     * @param int $districtId
     * @param int $blockId
     * @param array $submittedValues
     * @return array
     */
    public static function calculatePrice(
        int $dataTypeId,
        int $districtId,
        int $blockId,
        array $submittedValues = []
    ): array {
        $db = Database::getConnection();

        // 1. Fetch base service data
        $stmt = $db->prepare("SELECT id, code, fee_amount, distributor_commission, name_en, name_hi FROM data_types WHERE id = ?");
        $stmt->execute([$dataTypeId]);
        $dt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dt) {
            throw new Exception("Service not found.");
        }

        $baseFee = (float)$dt['fee_amount'];
        $baseCommission = (float)$dt['distributor_commission'];

        $appCode = strtoupper(trim((string)($submittedValues['application_code'] ?? '')));
        $appNumber = trim((string)($submittedValues['application_number'] ?? ''));
        $submittedState = trim((string)($submittedValues['state'] ?? ''));

        if (empty($submittedState)) {
            if ($districtId > 0) {
                $distStateStmt = $db->prepare("SELECT state FROM districts WHERE id = ?");
                $distStateStmt->execute([$districtId]);
                $submittedState = $distStateStmt->fetchColumn() ?: 'Bihar';
            } else {
                $submittedState = 'Bihar';
            }
        }

        $isBlockAppService = ($dt['code'] === 'srv_block_app');

        // Block & District Application Pass Service (srv_block_app) MUST have exact block rate and LIVE status
        if ($isBlockAppService) {
            // If inputs are incomplete during user selection, return incomplete state without calculating price
            if ($districtId <= 0 || $blockId <= 0 || empty($appCode)) {
                return [
                    'base_fee' => $baseFee,
                    'final_fee' => 0.00,
                    'final_commission' => 0.00,
                    'applied_rules' => [],
                    'is_complete' => false,
                    'rate_status' => 'INCOMPLETE',
                    'rate_note' => 'Select block and application code to view configured rate'
                ];
            }

            // 1. Verify District exists and is active
            $dChk = $db->prepare("SELECT id FROM districts WHERE id = ? AND is_active = 1 LIMIT 1");
            $dChk->execute([$districtId]);
            if (!$dChk->fetch()) {
                throw new Exception("Selected district does not exist or is inactive.");
            }

            // 2. Verify Block exists, is active, and belongs to that district
            $bChk = $db->prepare("SELECT id, name FROM blocks WHERE id = ? AND district_id = ? AND is_active = 1 LIMIT 1");
            $bChk->execute([$blockId, $districtId]);
            if (!$bChk->fetch()) {
                throw new Exception("Selected block does not exist, is inactive, or does not belong to the selected district.");
            }

            // 3. Verify Block Service Availability is LIVE
            $sbaStmt = $db->prepare("
                SELECT status FROM service_block_availability
                WHERE data_type_id = ? AND district_id = ? AND block_id = ?
                LIMIT 1
            ");
            $sbaStmt->execute([$dataTypeId, $districtId, $blockId]);
            $sbaStatus = $sbaStmt->fetchColumn();

            if ($sbaStatus !== 'LIVE') {
                throw new Exception("Service is temporarily unavailable for the selected block.");
            }

            // 4. Exact active rate match for (data_type_id, district_id, block_id, application_code)
            $exactStmt = $db->prepare("
                SELECT id, fee_amount, distributor_commission, rule_label
                FROM service_rate_rules
                WHERE data_type_id = ?
                  AND district_id = ?
                  AND block_id = ?
                  AND application_code = ?
                  AND is_active = 1
                  AND fee_amount > 0
                LIMIT 1
            ");
            $exactStmt->execute([$dataTypeId, $districtId, $blockId, $appCode]);
            $matchedRule = $exactStmt->fetch(PDO::FETCH_ASSOC);

            if (!$matchedRule) {
                throw new Exception("No active rate is configured for the selected block and application code.");
            }

            $ruleFee = (float)$matchedRule['fee_amount'];
            if ($ruleFee <= 0) {
                throw new Exception("No active rate is configured for the selected block and application code.");
            }

            $finalFee = round($ruleFee, 2);
            $finalCommission = $matchedRule['distributor_commission'] !== null ? round((float)$matchedRule['distributor_commission'], 2) : 0.00;
            $rateNote = $matchedRule['rule_label'] ?: "Configured Rate: {$appCode} — ₹" . number_format($finalFee, 2);

            return [
                'base_fee' => $baseFee,
                'final_fee' => $finalFee,
                'final_commission' => $finalCommission,
                'applied_rules' => [
                    [
                        'rule_id' => $matchedRule['id'],
                        'label' => $rateNote,
                        'adjustment_type' => 'FIXED',
                        'amount' => $finalFee,
                        'specificity_score' => 45
                    ]
                ],
                'is_complete' => true,
                'rate_status' => 'EXACT_RATE_APPLIED',
                'rate_note' => $rateNote
            ];
        }

        // Generic fallback for other services
        $isInputComplete = ($districtId > 0);
        $finalFee = $baseFee;
        $finalCommission = $baseCommission;
        $appliedRules = [];
        $matchedRule = null;

        // Fetch active rate rules ordered by specificity score
        $rStmt = $db->prepare("
            SELECT *,
                (
                    (CASE WHEN state IS NOT NULL AND state != '' AND state != '*' THEN 10 ELSE 0 END) +
                    (CASE WHEN district_id IS NOT NULL AND district_id > 0 THEN 20 ELSE 0 END) +
                    (CASE WHEN block_id IS NOT NULL AND block_id > 0 THEN 10 ELSE 0 END) +
                    (CASE WHEN application_code IS NOT NULL AND application_code != '' AND application_code != '*' THEN 5 ELSE 0 END) +
                    (CASE WHEN condition_field IS NOT NULL AND condition_field != '' THEN 2 ELSE 0 END)
                ) AS specificity_score
            FROM service_rate_rules
            WHERE data_type_id = ? AND is_active = 1
            ORDER BY specificity_score DESC, id ASC
        ");
        $rStmt->execute([$dataTypeId]);
        $rules = $rStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rules as $rule) {
            if (!empty($rule['state']) && $rule['state'] !== '*') {
                if (strcasecmp($rule['state'], $submittedState) !== 0) continue;
            }
            if ($rule['district_id'] !== null && (int)$rule['district_id'] > 0) {
                if ((int)$rule['district_id'] !== $districtId) continue;
            }
            if ($rule['block_id'] !== null && (int)$rule['block_id'] > 0) {
                if ((int)$rule['block_id'] !== $blockId) continue;
            }
            if (!empty($rule['application_code']) && $rule['application_code'] !== '*') {
                if (strcasecmp($rule['application_code'], $appCode) !== 0) continue;
            }

            $matchedRule = $rule;
            $ruleFee = (float)$rule['fee_amount'];
            if ($rule['adjustment_type'] === 'FIXED') {
                $finalFee = $ruleFee;
                break;
            } elseif ($rule['adjustment_type'] === 'ADD') {
                $finalFee += $ruleFee;
            } elseif ($rule['adjustment_type'] === 'SUBTRACT') {
                $finalFee = max(0.00, $finalFee - $ruleFee);
            }
            if ($rule['distributor_commission'] !== null) {
                $finalCommission = (float)$rule['distributor_commission'];
            }
            $appliedRules[] = [
                'rule_id' => $rule['id'],
                'label' => $rule['rule_label'] ?: 'Location Rate',
                'adjustment_type' => $rule['adjustment_type'],
                'amount' => $ruleFee,
                'specificity_score' => (int)$rule['specificity_score']
            ];
        }

        $rateStatus = !empty($appliedRules) ? 'RULE_APPLIED' : 'DEFAULT_BASE_FEE';
        $rateNote = !empty($appliedRules) ? ($appliedRules[0]['label'] ?? 'Custom rate applied') : 'Standard base fee';

        $finalFee = round(max(0.00, $finalFee), 2);
        $finalCommission = round(max(0.00, $finalCommission), 2);

        if (!$isInputComplete) {
            $rateStatus = 'PENDING_SELECTION';
            $rateNote = $isBlockAppService
                ? 'Select District, Block and Application Code to view exact applicable rate'
                : 'Select location to view exact applicable rate';
        }

        return [
            'base_fee' => $baseFee,
            'base_commission' => $baseCommission,
            'final_fee' => $finalFee,
            'final_commission' => $finalCommission,
            'is_complete' => $isInputComplete,
            'rate_status' => $rateStatus,
            'rate_note' => $rateNote,
            'applied_rules' => $appliedRules,
            'matched_rule_id' => $matchedRule['id'] ?? null
        ];
    }

    /**
     * Helper to read dynamic float setting with fallback
     */
    public static function getSettingValue(PDO $db, string $key, float $default): float
    {
        try {
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            if ($val !== false && $val !== null && is_numeric($val)) {
                return (float)$val;
            }
        } catch (Exception $e) {
            // fallback
        }
        return $default;
    }
}
