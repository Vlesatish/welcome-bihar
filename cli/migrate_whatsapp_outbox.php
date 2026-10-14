<?php
/**
 * Migration: Create WhatsApp Notification Outbox, Lock Fields, and WhatsApp Settings
 * Idempotent: safe to run multiple times without data loss.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Config/Database.php';
require_once dirname(__DIR__) . '/app/Config/Env.php';

$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    \App\Config\Env::load($envFile);
}

use App\Config\Database;

try {
    $db = Database::getConnection();
    echo "[*] Connecting to database...\n";

    // 1. Create table whatsapp_notification_outbox if not exists
    $tableSql = "
        CREATE TABLE IF NOT EXISTS `whatsapp_notification_outbox` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `event_key` VARCHAR(191) NOT NULL UNIQUE,
            `event_type` VARCHAR(64) NOT NULL,
            `application_id` INT NOT NULL,
            `recipient_user_id` INT NULL,
            `recipient_mobile` VARCHAR(20) NOT NULL,
            `message_body` TEXT NOT NULL,
            `status` ENUM('PENDING', 'PROCESSING', 'SENT', 'FAILED', 'SKIPPED') NOT NULL DEFAULT 'PENDING',
            `lock_token` VARCHAR(64) NULL,
            `locked_at` DATETIME NULL,
            `attempts` INT NOT NULL DEFAULT 0,
            `next_attempt_at` DATETIME NULL,
            `provider_status` VARCHAR(64) NULL,
            `provider_message_id` VARCHAR(191) NULL,
            `response_code` INT NULL,
            `last_error` TEXT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            `sent_at` DATETIME NULL,
            INDEX `idx_outbox_status_next` (`status`, `next_attempt_at`),
            INDEX `idx_outbox_claim` (`status`, `next_attempt_at`, `locked_at`),
            INDEX `idx_outbox_application_id` (`application_id`),
            INDEX `idx_outbox_recipient_user_id` (`recipient_user_id`),
            INDEX `idx_outbox_lock_token` (`lock_token`),
            INDEX `idx_outbox_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($tableSql);
    echo "[OK] Table 'whatsapp_notification_outbox' verified / created.\n";

    // Check if lock_token and locked_at columns exist (for existing tables)
    $colStmt = $db->query("SHOW COLUMNS FROM `whatsapp_notification_outbox`");
    $existingCols = $colStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    if (!in_array('lock_token', $existingCols, true)) {
        $db->exec("ALTER TABLE `whatsapp_notification_outbox` ADD COLUMN `lock_token` VARCHAR(64) NULL AFTER `status`");
        echo "[OK] Added column 'lock_token' to whatsapp_notification_outbox.\n";
    }

    if (!in_array('locked_at', $existingCols, true)) {
        $db->exec("ALTER TABLE `whatsapp_notification_outbox` ADD COLUMN `locked_at` DATETIME NULL AFTER `lock_token`");
        echo "[OK] Added column 'locked_at' to whatsapp_notification_outbox.\n";
    }

    // Check and add indexes
    $idxStmt = $db->query("SHOW INDEX FROM `whatsapp_notification_outbox`");
    $existingIndexes = [];
    while ($row = $idxStmt->fetch(PDO::FETCH_ASSOC)) {
        $existingIndexes[] = $row['Key_name'];
    }

    if (!in_array('idx_outbox_claim', $existingIndexes, true)) {
        try {
            $db->exec("ALTER TABLE `whatsapp_notification_outbox` ADD INDEX `idx_outbox_claim` (`status`, `next_attempt_at`, `locked_at`)");
            echo "[OK] Added index idx_outbox_claim.\n";
        } catch (\PDOException $e) {
            echo "[NOTE] idx_outbox_claim: " . $e->getMessage() . "\n";
        }
    }

    if (!in_array('idx_outbox_lock_token', $existingIndexes, true)) {
        try {
            $db->exec("ALTER TABLE `whatsapp_notification_outbox` ADD INDEX `idx_outbox_lock_token` (`lock_token`)");
            echo "[OK] Added index idx_outbox_lock_token.\n";
        } catch (\PDOException $e) {
            echo "[NOTE] idx_outbox_lock_token: " . $e->getMessage() . "\n";
        }
    }

    // Foreign Keys
    $fkCheck = $db->query("
        SELECT CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'whatsapp_notification_outbox'
          AND REFERENCED_TABLE_NAME IS NOT NULL
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('fk_outbox_application', $fkCheck, true)) {
        try {
            $db->exec("
                ALTER TABLE `whatsapp_notification_outbox`
                ADD CONSTRAINT `fk_outbox_application`
                FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`)
                ON DELETE CASCADE
            ");
            echo "[OK] Added foreign key fk_outbox_application.\n";
        } catch (\PDOException $e) {
            echo "[NOTE] fk_outbox_application skipped: " . $e->getMessage() . "\n";
        }
    }

    if (!in_array('fk_outbox_recipient', $fkCheck, true)) {
        try {
            $db->exec("
                ALTER TABLE `whatsapp_notification_outbox`
                ADD CONSTRAINT `fk_outbox_recipient`
                FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`)
                ON DELETE SET NULL
            ");
            echo "[OK] Added foreign key fk_outbox_recipient.\n";
        } catch (\PDOException $e) {
            echo "[NOTE] fk_outbox_recipient skipped: " . $e->getMessage() . "\n";
        }
    }

    // 2. Insert WhatsApp Settings into settings table idempotently
    $defaultSettings = [
        [
            'key' => 'whatsapp_enabled',
            'value' => '1',
            'group' => 'NOTIFICATION',
            'desc' => 'Enable or disable automatic WhatsApp notifications for new applications'
        ],
        [
            'key' => 'whatsapp_api_base_url',
            'value' => 'https://wbapi.in',
            'group' => 'NOTIFICATION',
            'desc' => 'Base URL of WBAPI WhatsApp provider'
        ],
        [
            'key' => 'whatsapp_api_token',
            'value' => '',
            'group' => 'NOTIFICATION',
            'desc' => 'Secure API token for WhatsApp sending'
        ],
        [
            'key' => 'whatsapp_session_id',
            'value' => '',
            'group' => 'NOTIFICATION',
            'desc' => 'Configured connected WhatsApp session ID'
        ],
        [
            'key' => 'whatsapp_default_country_code',
            'value' => '91',
            'group' => 'NOTIFICATION',
            'desc' => 'Default country code for mobile number normalization (e.g. 91)'
        ],
        [
            'key' => 'whatsapp_provider_blocked',
            'value' => '0',
            'group' => 'NOTIFICATION',
            'desc' => 'Runtime health flag: 1 if sending is blocked due to provider auth error (HTTP 401)'
        ],
        [
            'key' => 'whatsapp_provider_blocked_reason',
            'value' => '',
            'group' => 'NOTIFICATION',
            'desc' => 'Reason why WhatsApp provider sending was blocked'
        ],
        [
            'key' => 'whatsapp_last_status',
            'value' => 'Not Configured',
            'group' => 'NOTIFICATION',
            'desc' => 'Last tested session connection status (Connected, Disconnected, Not Configured, API Error)'
        ],
        [
            'key' => 'whatsapp_last_status_checked_at',
            'value' => '',
            'group' => 'NOTIFICATION',
            'desc' => 'Timestamp when WBAPI session status was last verified'
        ]
    ];

    $checkStmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $insertStmt = $db->prepare("
        INSERT INTO settings (setting_key, setting_value, setting_group, description, updated_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    foreach ($defaultSettings as $s) {
        $checkStmt->execute([$s['key']]);
        if ($checkStmt->fetchColumn() === false) {
            $insertStmt->execute([$s['key'], $s['value'], $s['group'], $s['desc']]);
            echo "[OK] Initialized setting: {$s['key']}\n";
        } else {
            echo "[SKIP] Setting already exists: {$s['key']}\n";
        }
    }

    echo "\n=== WhatsApp Migration Completed Successfully ===\n";
} catch (\Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
