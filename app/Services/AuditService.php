<?php
namespace App\Services;

use App\Config\Database;
use PDO;

class AuditService
{
    public static function log(
        string $action,
        string $targetType,
        ?string $targetId = null,
        ?array $details = null,
        ?int $actorUserId = null,
        ?string $actorRole = null
    ): void {
        try {
            $db = Database::getConnection();

            if ($actorUserId === null && isset($_SESSION['user_id'])) {
                $actorUserId = (int)$_SESSION['user_id'];
                $actorRole = $_SESSION['user_role'] ?? null;
            }

            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
            $detailsJson = $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;

            $stmt = $db->prepare("
                INSERT INTO audit_logs (actor_user_id, actor_role, action, target_type, target_id, ip_address, user_agent, details_json, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $actorUserId,
                $actorRole,
                $action,
                $targetType,
                $targetId,
                $ip,
                $userAgent,
                $detailsJson
            ]);
        } catch (\Exception $e) {
            error_log("AuditService Error: " . $e->getMessage());
        }
    }
}
