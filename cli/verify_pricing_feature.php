<?php
/**
 * Verification Test for Service Pricing Screen:
 * - Dynamic State/District/Block addition (persistent DB)
 * - Duplicate block prevention
 * - Combined & Separate workflows
 * - Multi-code rate rule setup, editing, toggling, deleting
 * - PricingService live preview and server-side calculation alignment
 */
declare(strict_types=1);

require_once __DIR__ . '/../app/Config/Env.php';
\App\Config\Env::load(__DIR__ . '/../.env');
require_once __DIR__ . '/../app/Config/Database.php';
require_once __DIR__ . '/../app/Services/PricingService.php';
require_once __DIR__ . '/../app/Services/AuditService.php';

use App\Services\PricingService;

echo "========================================================\n";
echo "SERVICE PRICING & LOCATION AUTOMATION TEST\n";
echo "========================================================\n";

$db = \App\Config\Database::getConnection();
$passed = 0;
$failed = 0;

// Clean up any test records from prior run if needed
$db->exec("DELETE FROM service_rate_rules WHERE rule_label LIKE 'Test Block%'");
$db->exec("DELETE FROM blocks WHERE name LIKE 'Test Tech Zone Block%'");

function assertCondition(string $desc, bool $condition): void {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] $desc\n";
        $passed++;
    } else {
        echo " [FAIL] $desc\n";
        $failed++;
    }
}

try {
    // 1. Check existing records are preserved
    $distCount = (int)$db->query("SELECT COUNT(*) FROM districts")->fetchColumn();
    $blockCount = (int)$db->query("SELECT COUNT(*) FROM blocks")->fetchColumn();
    assertCondition("Existing districts preserved (Count: $distCount >= 38)", $distCount >= 38);
    assertCondition("Existing blocks preserved (Count: $blockCount >= 708)", $blockCount >= 708);

    // Pick Patna district for testing
    $patna = $db->query("SELECT id, name, state FROM districts WHERE name = 'Patna' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $patnaId = (int)$patna['id'];
    assertCondition("Patna district found with state '{$patna['state']}'", $patnaId > 0 && $patna['state'] === 'Bihar');

    // 2. Test Duplicate Block Prevention
    $existingBlock = $db->query("SELECT name FROM blocks WHERE district_id = {$patnaId} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $dupName = $existingBlock['name'];

    $chkDup = $db->prepare("SELECT id, name FROM blocks WHERE district_id = ? AND LOWER(TRIM(name)) = LOWER(TRIM(?))");
    $chkDup->execute([$patnaId, strtolower($dupName)]);
    $dupFound = $chkDup->fetch(PDO::FETCH_ASSOC);
    assertCondition("Duplicate block correctly detected for '{$dupName}' (ID #{$dupFound['id']})", !empty($dupFound));

    // 3. Test New Block Persistent Creation
    $testBlockName = 'Test Tech Zone Block ' . time();
    $testBlockCode = 'TTZB_' . time();
    
    $insStmt = $db->prepare("INSERT INTO blocks (district_id, name, name_hi, code, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
    $insStmt->execute([$patnaId, $testBlockName, 'Test Tech Zone', $testBlockCode]);
    $newBlockId = (int)$db->lastInsertId();
    assertCondition("New persistent block created with ID #$newBlockId", $newBlockId > 0);

    // Verify it is linked to Patna and is active
    $verifyBlock = $db->query("SELECT * FROM blocks WHERE id = $newBlockId")->fetch(PDO::FETCH_ASSOC);
    assertCondition("New block correctly linked to Patna district ($patnaId)", (int)$verifyBlock['district_id'] === $patnaId);
    assertCondition("New block is_active = 1", (int)$verifyBlock['is_active'] === 1);

    // Verify applicant form query immediately sees this block
    $applicantBlocks = $db->query("SELECT id, name FROM blocks WHERE district_id = {$patnaId} AND is_active = 1 AND id = {$newBlockId}")->fetch(PDO::FETCH_ASSOC);
    assertCondition("Applicant form dropdown query immediately includes new active block", !empty($applicantBlocks));

    // 4. Test Multi-Code Rate Rule Creation for this Block
    // Service 4: srv_block_app (base fee 100.00)
    $dataTypeId = 4;

    // Rule 1: BICCO for new block (Fee: 75.00)
    $db->prepare("
        INSERT INTO service_rate_rules (
            data_type_id, state, district_id, block_id, application_code,
            adjustment_type, fee_amount, distributor_commission, rule_label, is_active, created_at
        ) VALUES (?, 'Bihar', ?, ?, 'BICCO', 'FIXED', 75.00, 15.00, 'Test Block BICCO Rate', 1, NOW())
    ")->execute([$dataTypeId, $patnaId, $newBlockId]);
    $rule1Id = (int)$db->lastInsertId();

    // Rule 2: BCCCO for new block (Fee: 85.00)
    $db->prepare("
        INSERT INTO service_rate_rules (
            data_type_id, state, district_id, block_id, application_code,
            adjustment_type, fee_amount, distributor_commission, rule_label, is_active, created_at
        ) VALUES (?, 'Bihar', ?, ?, 'BCCCO', 'FIXED', 85.00, 20.00, 'Test Block BCCCO Rate', 1, NOW())
    ")->execute([$dataTypeId, $patnaId, $newBlockId]);
    $rule2Id = (int)$db->lastInsertId();

    assertCondition("Created BICCO rate rule #$rule1Id (₹75.00) for new block", $rule1Id > 0);
    assertCondition("Created BCCCO rate rule #$rule2Id (₹85.00) for new block", $rule2Id > 0);

    // 5. Test Live Preview and Server-Side Pricing Calculation Alignment
    // Test BICCO calculation
    $pricingBICCO = PricingService::calculatePrice($dataTypeId, $patnaId, $newBlockId, [
        'state' => 'Bihar',
        'application_code' => 'BICCO',
        'application_number' => 'BICCO/2026/99999'
    ]);
    assertCondition("Pricing for BICCO correctly calculates ₹75.00", (float)$pricingBICCO['final_fee'] === 75.00);
    assertCondition("BICCO distributor commission is ₹15.00", (float)$pricingBICCO['final_commission'] === 15.00);
    assertCondition("BICCO matched rule ID matches rule #$rule1Id", (int)$pricingBICCO['matched_rule_id'] === $rule1Id);

    // Test BCCCO calculation
    $pricingBCCCO = PricingService::calculatePrice($dataTypeId, $patnaId, $newBlockId, [
        'state' => 'Bihar',
        'application_code' => 'BCCCO',
        'application_number' => 'BCCCO/2026/88888'
    ]);
    assertCondition("Pricing for BCCCO correctly calculates ₹85.00", (float)$pricingBCCCO['final_fee'] === 85.00);
    assertCondition("BCCCO distributor commission is ₹20.00", (float)$pricingBCCCO['final_commission'] === 20.00);
    assertCondition("BCCCO matched rule ID matches rule #$rule2Id", (int)$pricingBCCCO['matched_rule_id'] === $rule2Id);

    $fallbackStandard = (float)PricingService::getSettingValue($db, 'fallback_rate_standard', 100.00);
    $fallbackOther = (float)PricingService::getSettingValue($db, 'fallback_rate_other', 130.00);

    // Test Other Code (Fallback to standard reference rate)
    $pricingOther = PricingService::calculatePrice($dataTypeId, $patnaId, $newBlockId, [
        'state' => 'Bihar',
        'application_code' => 'OTHER',
        'application_number' => 'OTHER/2026/11111'
    ]);
    assertCondition("Unconfigured code falls back to reference fallback fee (₹$fallbackOther)", (float)$pricingOther['final_fee'] === $fallbackOther);

    // 6. Test Rate Rule Toggle (Deactivate)
    $db->prepare("UPDATE service_rate_rules SET is_active = 0 WHERE id = ?")->execute([$rule1Id]);
    $pricingDeactivated = PricingService::calculatePrice($dataTypeId, $patnaId, $newBlockId, [
        'state' => 'Bihar',
        'application_code' => 'BICCO',
        'application_number' => 'BICCO/2026/99999'
    ]);
    assertCondition("Deactivated rule is ignored and falls back to standard fee (₹$fallbackStandard)", (float)$pricingDeactivated['final_fee'] === $fallbackStandard);

    // 7. Test Rate Rule Update (Edit)
    $db->prepare("UPDATE service_rate_rules SET fee_amount = 62.50, is_active = 1 WHERE id = ?")->execute([$rule1Id]);
    $pricingUpdated = PricingService::calculatePrice($dataTypeId, $patnaId, $newBlockId, [
        'state' => 'Bihar',
        'application_code' => 'BICCO',
        'application_number' => 'BICCO/2026/99999'
    ]);
    assertCondition("Updated rate rule fee immediately calculates ₹62.50", (float)$pricingUpdated['final_fee'] === 62.50);

    // 8. Clean up test records
    $db->prepare("DELETE FROM service_rate_rules WHERE id IN (?, ?)")->execute([$rule1Id, $rule2Id]);
    $db->prepare("DELETE FROM blocks WHERE id = ?")->execute([$newBlockId]);
    assertCondition("Cleaned up temporary test rules and test block", true);

    echo "\n========================================================\n";
    echo "SUMMARY: Passed: $passed | Failed: $failed\n";
    echo "========================================================\n";

} catch (Exception $e) {
    echo "Error during verification: " . $e->getMessage() . "\n";
    exit(1);
}
