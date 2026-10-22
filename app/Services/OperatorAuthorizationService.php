<?php
namespace App\Services;

use App\Config\Database;
use PDO;

class OperatorAuthorizationService
{
    /**
     * Get the strict authorized SQL condition for an operator.
     * Uses safe integer casting on $operatorId, ensuring 0 SQL injection risk
     * and avoiding PDO named-parameter collision issues when combined in complex queries.
     *
     * @param int $operatorId
     * @param string $tableAlias Alias of the applications table (default: 'a')
     * @return string
     */
    public static function getAuthorizedSqlCondition(int $operatorId, string $tableAlias = 'a'): string
    {
        $opId = (int)$operatorId;
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $tableAlias);

        return "{$alias}.assigned_operator_id = {$opId}
            AND {$alias}.user_id != {$opId}
            AND EXISTS (
                SELECT 1
                FROM operator_block_assignments oba
                WHERE oba.operator_user_id = {$opId}
                  AND oba.district_id = {$alias}.district_id
                  AND oba.block_id = {$alias}.block_id
                  AND oba.is_active = 1
            )
            AND EXISTS (
                SELECT 1
                FROM operator_application_code_assignments oaca
                WHERE oaca.operator_user_id = {$opId}
                  AND oaca.district_id = {$alias}.district_id
                  AND oaca.block_id = {$alias}.block_id
                  AND UPPER(TRIM(oaca.application_code)) = UPPER(TRIM({$alias}.application_code))
                  AND oaca.is_active = 1
            )";
    }

    /**
     * Parameterized version of the authorized condition for prepared statements.
     *
     * @param int $operatorId
     * @param string $tableAlias
     * @param string $prefix
     * @return array ['sql' => string, 'params' => array]
     */
    public static function getAuthorizedParameterizedCondition(int $operatorId, string $tableAlias = 'a', string $prefix = 'op_auth_'): array
    {
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $tableAlias);
        $p1 = ":{$prefix}op1";
        $p2 = ":{$prefix}op2";
        $p3 = ":{$prefix}op3";
        $p4 = ":{$prefix}op4";

        $sql = "{$alias}.assigned_operator_id = {$p1}
            AND {$alias}.user_id != {$p2}
            AND EXISTS (
                SELECT 1
                FROM operator_block_assignments oba
                WHERE oba.operator_user_id = {$p3}
                  AND oba.district_id = {$alias}.district_id
                  AND oba.block_id = {$alias}.block_id
                  AND oba.is_active = 1
            )
            AND EXISTS (
                SELECT 1
                FROM operator_application_code_assignments oaca
                WHERE oaca.operator_user_id = {$p4}
                  AND oaca.district_id = {$alias}.district_id
                  AND oaca.block_id = {$alias}.block_id
                  AND UPPER(TRIM(oaca.application_code)) = UPPER(TRIM({$alias}.application_code))
                  AND oaca.is_active = 1
            )";

        return [
            'sql' => $sql,
            'params' => [
                $p1 => $operatorId,
                $p2 => $operatorId,
                $p3 => $operatorId,
                $p4 => $operatorId,
            ]
        ];
    }

    /**
     * Check if a specific operator can access/process a given application.
     * Validates:
     * 1. Operator account is ACTIVE and has OPERATOR capability.
     * 2. Application explicitly assigned to this operator.
     * 3. Operator is not the creator of the application.
     * 4. Active District + Block assignment exists.
     * 5. Active Application Code assignment exists for that exact District + Block.
     *
     * @param int $operatorId
     * @param int $applicationId
     * @return bool
     */
    public static function canAccessApplication(int $operatorId, int $applicationId): bool
    {
        if ($operatorId <= 0 || $applicationId <= 0) {
            return false;
        }

        $db = Database::getConnection();

        // Verify operator is active and has operator capability
        $stmt = $db->prepare("SELECT id, role, status FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$operatorId]);
        $op = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$op || $op['status'] !== 'ACTIVE' || !AuthService::canAccessOperatorMode($operatorId)) {
            return false;
        }

        $cond = self::getAuthorizedSqlCondition($operatorId, 'a');
        $sql = "SELECT 1 FROM applications a WHERE a.id = ? AND {$cond} LIMIT 1";

        $chk = $db->prepare($sql);
        $chk->execute([$applicationId]);
        return (bool)$chk->fetchColumn();
    }

    /**
     * Get aggregate status counters for an operator in a single high-performance query.
     * Computes ALL, PENDING, IN_PROCESS, APPROVED, REJECTED, fees, and commissions.
     *
     * @param int $operatorId
     * @param int|null $dataTypeId Optional data_type_id filter
     * @return array
     */
    public static function getStatusCounts(int $operatorId, ?int $dataTypeId = null): array
    {
        $db = Database::getConnection();
        $authCond = self::getAuthorizedSqlCondition($operatorId, 'a');

        $dtSql = ($dataTypeId !== null && $dataTypeId > 0) ? " AND a.data_type_id = " . (int)$dataTypeId : "";

        $sql = "
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN a.status = 'PENDING' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN a.status IN ('IN_PROCESS', 'UNDER_REVIEW') THEN 1 ELSE 0 END) as in_process,
                SUM(CASE WHEN a.status IN ('APPROVED', 'COMPLETED') THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN a.status = 'REJECTED' THEN 1 ELSE 0 END) as rejected,
                COALESCE(SUM(a.fee_debited), 0) as total_fees,
                COALESCE(SUM(a.commission_credited), 0) as total_commissions
            FROM applications a
            WHERE {$authCond} {$dtSql}
        ";

        $row = $db->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'ALL' => (int)($row['total'] ?? 0),
            'PENDING' => (int)($row['pending'] ?? 0),
            'IN_PROCESS' => (int)($row['in_process'] ?? 0),
            'APPROVED' => (int)($row['approved'] ?? 0),
            'REJECTED' => (int)($row['rejected'] ?? 0),
            'total' => (int)($row['total'] ?? 0),
            'pending' => (int)($row['pending'] ?? 0),
            'in_process' => (int)($row['in_process'] ?? 0),
            'completed' => (int)($row['approved'] ?? 0),
            'approved' => (int)($row['approved'] ?? 0),
            'rejected' => (int)($row['rejected'] ?? 0),
            'total_fees' => (float)($row['total_fees'] ?? 0),
            'total_commissions' => (float)($row['total_commissions'] ?? 0),
        ];
    }

    /**
     * Build unified WHERE conditions and parameters for applications listing.
     *
     * @param array $filters Filters from GET request
     * @param bool $isOperator Whether current context is operator
     * @param int|null $operatorId Operator user ID
     * @param string $tableAlias Default 'a'
     * @return array ['where' => array of strings, 'params' => array]
     */
    public static function buildFilterConditions(array $filters, bool $isOperator = false, ?int $operatorId = null, string $tableAlias = 'a'): array
    {
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $tableAlias);
        $where = [];
        $params = [];

        // 1. Operator strict authorization scoping
        if ($isOperator && $operatorId > 0) {
            $where[] = self::getAuthorizedSqlCondition($operatorId, $alias);
        }

        // 2. Status Tab Filter
        $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
        if ($status === 'PENDING') {
            $where[] = "{$alias}.status = 'PENDING'";
        } elseif ($status === 'IN_PROCESS') {
            $where[] = "{$alias}.status IN ('IN_PROCESS', 'UNDER_REVIEW')";
        } elseif ($status === 'APPROVED') {
            $where[] = "{$alias}.status IN ('APPROVED', 'COMPLETED')";
        } elseif ($status === 'REJECTED') {
            $where[] = "{$alias}.status = 'REJECTED'";
        }

        // 3. District Filter
        $districtId = !empty($filters['district_id']) ? (int)$filters['district_id'] : 0;
        if ($districtId > 0) {
            $where[] = "{$alias}.district_id = :flt_district_id";
            $params[':flt_district_id'] = $districtId;
        }

        // 4. Block Filter
        $blockId = !empty($filters['block_id']) ? (int)$filters['block_id'] : 0;
        if ($blockId > 0) {
            $where[] = "{$alias}.block_id = :flt_block_id";
            $params[':flt_block_id'] = $blockId;
        }

        // 5. Application Code Filter (Robust: checks direct column, identifier prefix, and submitted field values)
        $appCode = strtoupper(trim((string)($filters['app_code'] ?? '')));
        if (!empty($appCode)) {
            $where[] = "(
                UPPER(TRIM({$alias}.application_code)) = :flt_app_code_exact
                OR {$alias}.applicant_identifier LIKE :flt_app_code_like
                OR EXISTS (
                    SELECT 1 FROM application_field_values afv
                    WHERE afv.application_id = {$alias}.id
                      AND afv.field_name = 'application_code'
                      AND UPPER(TRIM(afv.field_value)) = :flt_app_code_val
                )
            )";
            $params[':flt_app_code_exact'] = $appCode;
            $params[':flt_app_code_like'] = $appCode . '%';
            $params[':flt_app_code_val'] = $appCode;
        }

        // 6. Retailer Filter
        $retailerId = !empty($filters['retailer_id']) ? (int)$filters['retailer_id'] : 0;
        if ($retailerId > 0) {
            $where[] = "{$alias}.user_id = :flt_retailer_id";
            $params[':flt_retailer_id'] = $retailerId;
        }

        // 7. Date Filter
        $dateFilter = strtoupper(trim((string)($filters['date_filter'] ?? 'ALL')));
        if ($dateFilter === 'TODAY') {
            $where[] = "DATE({$alias}.created_at) = CURDATE()";
        } elseif ($dateFilter === 'WEEK') {
            $where[] = "{$alias}.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($dateFilter === 'MONTH') {
            $where[] = "{$alias}.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }

        // 8. Staff Routing Filter (Admin only)
        if (!$isOperator) {
            $assignmentStatus = strtoupper(trim((string)($filters['assignment_status'] ?? 'ALL')));
            if ($assignmentStatus === 'UNASSIGNED') {
                $where[] = "{$alias}.assigned_operator_id IS NULL";
            } elseif ($assignmentStatus === 'ASSIGNED') {
                $where[] = "{$alias}.assigned_operator_id IS NOT NULL";
            }
        }

        // 9. Search Text (App No, Name, Mobile, Ref, Retailer Name, Mobile)
        $search = trim((string)($filters['search'] ?? ''));
        if (!empty($search)) {
            $where[] = "(
                {$alias}.applicant_identifier LIKE :s_id
                OR {$alias}.applicant_name LIKE :s_name
                OR {$alias}.applicant_mobile LIKE :s_mob
                OR {$alias}.app_ref LIKE :s_ref
                OR {$alias}.batch_ref LIKE :s_bref
                OR u.name LIKE :s_uname
                OR u.mobile LIKE :s_umob
            )";
            $sVal = "%{$search}%";
            $params[':s_id'] = $sVal;
            $params[':s_name'] = $sVal;
            $params[':s_mob'] = $sVal;
            $params[':s_ref'] = $sVal;
            $params[':s_bref'] = $sVal;
            $params[':s_uname'] = $sVal;
            $params[':s_umob'] = $sVal;
        }

        return [
            'where' => $where,
            'params' => $params
        ];
    }
}
