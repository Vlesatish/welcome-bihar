<?php
namespace App\Services;

use App\Config\Database;
use Exception;
use PDO;

class NotificationService
{
    /**
     * Create an in-app notification for a user
     */
    public static function create(
        int $userId,
        string $title,
        string $message,
        string $type = 'INFO',
        ?string $link = null
    ): int {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, link, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");
            $allowedTypes = ['INFO', 'SUCCESS', 'WARNING', 'DANGER'];
            $notifType = in_array(strtoupper($type), $allowedTypes, true) ? strtoupper($type) : 'INFO';
            $stmt->execute([$userId, $title, $message, $notifType, $link]);
            return (int)$db->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to insert notification: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get count of unread notifications for a user
     */
    public static function getUnreadCount(int $userId): int
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$userId]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get recent notifications
     */
    public static function getRecent(int $userId, int $limit = 5): array
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT * FROM notifications
                WHERE user_id = ?
                ORDER BY id DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Mark a notification as read
     */
    public static function markAsRead(int $notificationId, int $userId): bool
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            return $stmt->execute([$notificationId, $userId]);
        } catch (Exception $e) {
            return false;
        }
    }
}
