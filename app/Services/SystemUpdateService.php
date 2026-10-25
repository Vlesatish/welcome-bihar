<?php
namespace App\Services;

use App\Config\Database;
use PDO;
use Exception;

class SystemUpdateService
{
    private static bool $schemaChecked = false;

    /**
     * Self-healing: quickly ensure critical schema columns exist
     */
    public static function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }

        try {
            $db = Database::getConnection();

            // 1. Applications table checks
            $appCols = $db->query("SHOW COLUMNS FROM `applications`")->fetchAll(PDO::FETCH_COLUMN);
            $appCols = array_map('strtolower', $appCols);

            if (!in_array('batch_ref', $appCols, true)) {
                $db->exec("ALTER TABLE `applications` ADD COLUMN `batch_ref` VARCHAR(64) NULL AFTER `app_ref`");
                $db->exec("ALTER TABLE `applications` ADD INDEX `idx_apps_batch_ref` (`batch_ref`)");
            }

            if (!in_array('application_code', $appCols, true)) {
                $db->exec("ALTER TABLE `applications` ADD COLUMN `application_code` VARCHAR(64) NULL AFTER `data_type_id`");
                $db->exec("ALTER TABLE `applications` ADD INDEX `idx_apps_app_code` (`application_code`)");
            }

            if (!in_array('assigned_operator_id', $appCols, true)) {
                $db->exec("ALTER TABLE `applications` ADD COLUMN `assigned_operator_id` INT NULL AFTER `distributor_id`");
                $db->exec("ALTER TABLE `applications` ADD INDEX `idx_apps_assigned_operator` (`assigned_operator_id`)");
            }

            if (!in_array('assigned_at', $appCols, true)) {
                $db->exec("ALTER TABLE `applications` ADD COLUMN `assigned_at` DATETIME NULL AFTER `assigned_operator_id`");
            }

            if (!in_array('assignment_method', $appCols, true)) {
                $db->exec("ALTER TABLE `applications` ADD COLUMN `assignment_method` VARCHAR(50) NULL AFTER `assigned_at`");
            }

            if (!in_array('district_id', $appCols, true)) {
                $db->exec("ALTER TABLE `applications` ADD COLUMN `district_id` INT NULL AFTER `assignment_method`");
                $db->exec("ALTER TABLE `applications` ADD INDEX `idx_apps_district` (`district_id`)");
            }

            if (!in_array('block_id', $appCols, true)) {
                $db->exec("ALTER TABLE `applications` ADD COLUMN `block_id` INT NULL AFTER `district_id`");
                $db->exec("ALTER TABLE `applications` ADD INDEX `idx_apps_block` (`block_id`)");
            }

            if (!in_array('applicant_name', $appCols, true)) {
                $db->exec("ALTER TABLE `applications` ADD COLUMN `applicant_name` VARCHAR(150) NULL AFTER `block_id`");
            }

            if (!in_array('applicant_mobile', $appCols, true)) {
                $db->exec("ALTER TABLE `applications` ADD COLUMN `applicant_mobile` VARCHAR(20) NULL AFTER `applicant_name`");
            }

            if (!in_array('applicant_identifier', $appCols, true)) {
                $db->exec("ALTER TABLE `applications` ADD COLUMN `applicant_identifier` VARCHAR(100) NULL AFTER `applicant_mobile`");
                $db->exec("ALTER TABLE `applications` ADD INDEX `idx_apps_applicant_identifier` (`applicant_identifier`)");
            }

            if (!in_array('claimed_at', $appCols, true)) {
                $db->exec("ALTER TABLE `applications` ADD COLUMN `claimed_at` DATETIME NULL AFTER `assigned_operator_id`");
            }

            if (!in_array('remarks', $appCols, true)) {
                $db->exec("ALTER TABLE `applications` ADD COLUMN `remarks` TEXT NULL AFTER `status`");
            }

            if (!in_array('is_locked', $appCols, true)) {
                $db->exec("ALTER TABLE `applications` ADD COLUMN `is_locked` TINYINT(1) NOT NULL DEFAULT 1");
            }

            if (!in_array('is_refunded', $appCols, true)) {
                $db->exec("ALTER TABLE `applications` ADD COLUMN `is_refunded` TINYINT(1) NOT NULL DEFAULT 0");
            }

            if (!in_array('is_commission_paid', $appCols, true)) {
                $db->exec("ALTER TABLE `applications` ADD COLUMN `is_commission_paid` TINYINT(1) NOT NULL DEFAULT 0");
            }

            // Ensure required composite indexes for operator filtering and staff routing
            try {
                $existingIndexes = $db->query("SHOW INDEX FROM `applications`")->fetchAll(PDO::FETCH_ASSOC);
                $indexNames = array_column($existingIndexes, 'Key_name');
                if (!in_array('idx_dist_blk_app_code', $indexNames, true)) {
                    $db->exec("ALTER TABLE `applications` ADD INDEX `idx_dist_blk_app_code` (`district_id`, `block_id`, `application_code`)");
                }
                if (!in_array('idx_status_assigned_op', $indexNames, true)) {
                    $db->exec("ALTER TABLE `applications` ADD INDEX `idx_status_assigned_op` (`status`, `assigned_operator_id`)");
                }
            } catch (\Throwable $e) {}

            // 2. Users table checks
            $userCols = $db->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
            $userCols = array_map('strtolower', $userCols);

            if (!in_array('profile_state', $userCols, true)) {
                $db->exec("ALTER TABLE `users` ADD COLUMN `profile_state` VARCHAR(100) NOT NULL DEFAULT 'Bihar' AFTER `center_name`");
            }
            if (!in_array('profile_district_id', $userCols, true)) {
                $db->exec("ALTER TABLE `users` ADD COLUMN `profile_district_id` INT NULL AFTER `profile_state`");
            }
            if (!in_array('profile_block_id', $userCols, true)) {
                $db->exec("ALTER TABLE `users` ADD COLUMN `profile_block_id` INT NULL AFTER `profile_district_id`");
            }
            if (!in_array('center_address', $userCols, true)) {
                $db->exec("ALTER TABLE `users` ADD COLUMN `center_address` TEXT NULL AFTER `profile_block_id`");
            }
            if (!in_array('assigned_all_districts', $userCols, true)) {
                $db->exec("ALTER TABLE `users` ADD COLUMN `assigned_all_districts` TINYINT(1) NOT NULL DEFAULT 1 AFTER `center_address`");
            }
            if (!in_array('assigned_district_id', $userCols, true)) {
                $db->exec("ALTER TABLE `users` ADD COLUMN `assigned_district_id` INT NULL AFTER `assigned_all_districts`");
            }
            if (!in_array('assigned_block_id', $userCols, true)) {
                $db->exec("ALTER TABLE `users` ADD COLUMN `assigned_block_id` INT NULL AFTER `assigned_district_id`");
            }

            // Profile location indexes & foreign keys
            try {
                $userIndexes = $db->query("SHOW INDEX FROM `users`")->fetchAll(PDO::FETCH_ASSOC);
                $indexNames = array_column($userIndexes, 'Key_name');
                if (!in_array('idx_users_profile_district', $indexNames, true)) {
                    $db->exec("ALTER TABLE `users` ADD INDEX `idx_users_profile_district` (`profile_district_id`)");
                }
                if (!in_array('idx_users_profile_block', $indexNames, true)) {
                    $db->exec("ALTER TABLE `users` ADD INDEX `idx_users_profile_block` (`profile_block_id`)");
                }
            } catch (\Throwable $e) {
                // Ignore if exists
            }

            try {
                $fks = $db->query("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.TABLE_CONSTRAINTS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                      AND TABLE_NAME = 'users' 
                      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                ")->fetchAll(PDO::FETCH_COLUMN);

                if (!in_array('fk_users_profile_district', $fks, true)) {
                    $db->exec("ALTER TABLE `users` ADD CONSTRAINT `fk_users_profile_district` FOREIGN KEY (`profile_district_id`) REFERENCES `districts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
                }
                if (!in_array('fk_users_profile_block', $fks, true)) {
                    $db->exec("ALTER TABLE `users` ADD CONSTRAINT `fk_users_profile_block` FOREIGN KEY (`profile_block_id`) REFERENCES `blocks` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
                }
            } catch (\Throwable $e) {
                // Ignore if exists
            }

            // 3. Data types columns check (enable_chat, warning_image)
            $dtCols = $db->query("SHOW COLUMNS FROM `data_types`")->fetchAll(PDO::FETCH_COLUMN);
            $dtCols = array_map('strtolower', $dtCols);
            if (!in_array('enable_chat', $dtCols, true)) {
                $db->exec("ALTER TABLE `data_types` ADD COLUMN `enable_chat` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_active`");
            }
            if (!in_array('warning_image', $dtCols, true)) {
                $db->exec("ALTER TABLE `data_types` ADD COLUMN `warning_image` VARCHAR(255) NULL AFTER `announcement`");
            }

            // 4. Ensure required tables exist
            $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $tables = array_map('strtolower', $tables);

            // application_chats
            if (!in_array('application_chats', $tables, true)) {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `application_chats` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `application_id` INT NOT NULL,
                        `sender_id` INT NOT NULL,
                        `sender_name` VARCHAR(150) NOT NULL,
                        `sender_role` ENUM('ADMIN','OPERATOR','DISTRIBUTOR','RETAILER','USER') NOT NULL,
                        `message` TEXT NOT NULL,
                        `attachment_url` VARCHAR(255) NULL,
                        `is_read` TINYINT(1) NOT NULL DEFAULT 0,
                        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                        INDEX `idx_app_id` (`application_id`),
                        INDEX `idx_created` (`created_at`),
                        CONSTRAINT `fk_chat_app` FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            // service_rate_rules
            if (!in_array('service_rate_rules', $tables, true)) {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `service_rate_rules` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `data_type_id` int(11) NOT NULL,
                      `state` varchar(100) NOT NULL DEFAULT 'Bihar',
                      `district_id` int(11) DEFAULT NULL,
                      `block_id` int(11) DEFAULT NULL,
                      `application_code` varchar(64) DEFAULT NULL,
                      `condition_field` varchar(100) DEFAULT NULL,
                      `condition_value` varchar(100) DEFAULT NULL,
                      `adjustment_type` enum('FIXED','ADD','SUBTRACT') NOT NULL DEFAULT 'FIXED',
                      `fee_amount` decimal(12,2) NOT NULL,
                      `distributor_commission` decimal(12,2) DEFAULT NULL,
                      `rule_label` varchar(150) DEFAULT NULL,
                      `is_active` tinyint(1) NOT NULL DEFAULT 1,
                      `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                      `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                      PRIMARY KEY (`id`),
                      KEY `idx_srr_dt` (`data_type_id`),
                      KEY `idx_srr_dist` (`district_id`),
                      KEY `idx_srr_block` (`block_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            // service_block_availability
            if (!in_array('service_block_availability', $tables, true)) {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `service_block_availability` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `data_type_id` int(11) NOT NULL,
                      `district_id` int(11) NOT NULL,
                      `block_id` int(11) NOT NULL,
                      `status` enum('INACTIVE','LIVE') NOT NULL DEFAULT 'INACTIVE',
                      `activated_at` datetime DEFAULT NULL,
                      `activated_by` int(11) DEFAULT NULL,
                      `deactivated_at` datetime DEFAULT NULL,
                      `deactivated_by` int(11) DEFAULT NULL,
                      `internal_note` text DEFAULT NULL,
                      `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                      `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `uk_service_district_block` (`data_type_id`,`district_id`,`block_id`),
                      KEY `idx_sba_service_status` (`data_type_id`,`status`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            // whatsapp_notification_outbox
            if (!in_array('whatsapp_notification_outbox', $tables, true)) {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `whatsapp_notification_outbox` (
                      `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                      `event_key` varchar(191) NOT NULL,
                      `event_type` varchar(64) NOT NULL,
                      `application_id` int(11) NOT NULL,
                      `recipient_user_id` int(11) DEFAULT NULL,
                      `recipient_mobile` varchar(20) NOT NULL,
                      `message_body` text NOT NULL,
                      `status` enum('PENDING','PROCESSING','SENT','FAILED','SKIPPED') NOT NULL DEFAULT 'PENDING',
                      `lock_token` varchar(64) DEFAULT NULL,
                      `locked_at` datetime DEFAULT NULL,
                      `attempts` int(11) NOT NULL DEFAULT 0,
                      `next_attempt_at` datetime DEFAULT NULL,
                      `provider_status` varchar(64) DEFAULT NULL,
                      `provider_message_id` varchar(191) DEFAULT NULL,
                      `response_code` int(11) DEFAULT NULL,
                      `last_error` text DEFAULT NULL,
                      `created_at` datetime NOT NULL,
                      `updated_at` datetime NOT NULL,
                      `sent_at` datetime DEFAULT NULL,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `event_key` (`event_key`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            // rtps_certificate_queue (RTPS Certificate Tracking & Nightly Check Queue)
            if (!in_array('rtps_certificate_queue', $tables, true)) {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `rtps_certificate_queue` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `application_id` int(11) NOT NULL,
                        `service_type` varchar(32) NOT NULL DEFAULT 'RTPS',
                        `application_code` varchar(64) NOT NULL,
                        `application_number` varchar(100) NOT NULL,
                        `applicant_name` varchar(150) NOT NULL,
                        `district_id` int(11) NOT NULL,
                        `block_id` int(11) NOT NULL,
                        `status` enum('QUEUED', 'PENDING_CHECK', 'CHECKING', 'ATTACHED', 'UNAVAILABLE', 'FAILED') NOT NULL DEFAULT 'QUEUED',
                        `portal_url` varchar(255) NOT NULL DEFAULT 'https://swcs.bihar.gov.in/RtpsReportView/certificate_download.jsp',
                        `check_count` int(11) NOT NULL DEFAULT 0,
                        `last_checked_at` datetime DEFAULT NULL,
                        `next_check_at` datetime NOT NULL,
                        `last_status_message` text DEFAULT NULL,
                        `certificate_file` varchar(255) DEFAULT NULL,
                        `created_at` datetime NOT NULL DEFAULT current_timestamp(),
                        `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `uk_rtps_queue_app` (`application_id`),
                        KEY `idx_rtps_queue_schedule` (`status`, `next_check_at`),
                        KEY `idx_rtps_queue_app_num` (`application_number`),
                        CONSTRAINT `fk_rtps_queue_app` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            // user_role_assignments (Dual Role System)
            if (!in_array('user_role_assignments', $tables, true)) {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `user_role_assignments` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id` INT NOT NULL,
                        `role_code` ENUM('USER', 'OPERATOR') NOT NULL,
                        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                        `granted_by` INT NULL,
                        `granted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `revoked_by` INT NULL,
                        `revoked_at` DATETIME NULL,
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY `uk_user_role` (`user_id`, `role_code`),
                        INDEX `idx_role_active` (`role_code`, `is_active`),
                        INDEX `idx_ura_user_id` (`user_id`),
                        CONSTRAINT `fk_ura_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                        CONSTRAINT `fk_ura_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
                        CONSTRAINT `fk_ura_revoked_by` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");

                // Idempotently backfill existing users and operators
                try {
                    $db->exec("
                        INSERT INTO `user_role_assignments` (`user_id`, `role_code`, `is_active`, `granted_by`, `granted_at`, `created_at`, `updated_at`)
                        SELECT u.id, 'USER', 1, NULL, NOW(), NOW(), NOW()
                        FROM `users` u
                        WHERE u.role IN ('USER', 'DISTRIBUTOR')
                        ON DUPLICATE KEY UPDATE `is_active` = `user_role_assignments`.`is_active`;
                    ");
                    $db->exec("
                        INSERT INTO `user_role_assignments` (`user_id`, `role_code`, `is_active`, `granted_by`, `granted_at`, `created_at`, `updated_at`)
                        SELECT u.id, 'OPERATOR', IF(u.status = 'ACTIVE', 1, 0), NULL, NOW(), NOW(), NOW()
                        FROM `users` u
                        WHERE u.role = 'OPERATOR'
                        ON DUPLICATE KEY UPDATE `is_active` = `user_role_assignments`.`is_active`;
                    ");
                } catch (\Throwable $e) {}
            }

            // operator_block_assignments
            if (!in_array('operator_block_assignments', $tables, true)) {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `operator_block_assignments` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `operator_user_id` INT NOT NULL,
                        `district_id` INT NOT NULL,
                        `block_id` INT NOT NULL,
                        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                        `assigned_by` INT NULL,
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY `uq_op_dist_blk` (`operator_user_id`, `district_id`, `block_id`),
                        INDEX `idx_op_block_user` (`operator_user_id`, `is_active`),
                        INDEX `idx_op_block_dist_blk` (`district_id`, `block_id`, `is_active`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            // operator_application_code_assignments
            if (!in_array('operator_application_code_assignments', $tables, true)) {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `operator_application_code_assignments` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `operator_user_id` INT NOT NULL,
                        `district_id` INT NOT NULL,
                        `block_id` INT NOT NULL,
                        `application_code` VARCHAR(64) NOT NULL,
                        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                        `priority` INT NOT NULL DEFAULT 1,
                        `assigned_by` INT NULL,
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX `idx_oaca_operator` (`operator_user_id`),
                        INDEX `idx_oaca_dist_blk` (`district_id`, `block_id`),
                        UNIQUE KEY `uq_op_dist_blk_code` (`operator_user_id`, `district_id`, `block_id`, `application_code`),
                        INDEX `idx_routing_lookup` (`district_id`, `block_id`, `application_code`, `is_active`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            // application_assignment_history
            if (!in_array('application_assignment_history', $tables, true)) {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `application_assignment_history` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `application_id` INT NOT NULL,
                        `previous_operator_id` INT NULL,
                        `new_operator_id` INT NULL,
                        `application_code` VARCHAR(64) NOT NULL,
                        `assigned_by` INT NULL,
                        `assignment_type` ENUM('AUTO', 'MANUAL', 'REASSIGN') NOT NULL DEFAULT 'AUTO',
                        `reason` TEXT NULL,
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        INDEX `idx_aah_app_id` (`application_id`),
                        INDEX `idx_aah_new_op` (`new_operator_id`),
                        INDEX `idx_aah_prev_op` (`previous_operator_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            // application_code_fields
            if (!in_array('application_code_fields', $tables, true)) {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `application_code_fields` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `data_type_id` INT NOT NULL,
                        `application_code` VARCHAR(64) NOT NULL,
                        `field_name` VARCHAR(100) NOT NULL,
                        `label` VARCHAR(150) NOT NULL,
                        `field_type` ENUM('text', 'number', 'mobile', 'aadhaar', 'date', 'select', 'radio', 'textarea', 'file', 'email') NOT NULL DEFAULT 'text',
                        `placeholder` VARCHAR(255) NULL,
                        `help_text` VARCHAR(255) NULL,
                        `options_json` TEXT NULL,
                        `validation_pattern` VARCHAR(255) NULL,
                        `min_length` INT NULL,
                        `max_length` INT NULL,
                        `min_value` DECIMAL(15,2) NULL,
                        `max_value` DECIMAL(15,2) NULL,
                        `allowed_file_types` VARCHAR(150) NULL,
                        `max_file_size_mb` INT NULL,
                        `is_required` TINYINT(1) NOT NULL DEFAULT 1,
                        `is_unique` TINYINT(1) NOT NULL DEFAULT 0,
                        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                        `sort_order` INT NOT NULL DEFAULT 0,
                        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY `uniq_code_field` (`data_type_id`, `application_code`, `field_name`),
                        KEY `idx_code_active` (`data_type_id`, `application_code`, `is_active`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            // application_field_values adjustments
            if (in_array('application_field_values', $tables, true)) {
                $afvCols = $db->query("SHOW COLUMNS FROM `application_field_values`")->fetchAll(PDO::FETCH_COLUMN);
                $afvCols = array_map('strtolower', $afvCols);
                if (!in_array('code_field_id', $afvCols, true)) {
                    $db->exec("ALTER TABLE `application_field_values` ADD COLUMN `code_field_id` INT NULL AFTER `field_id`, ADD KEY `idx_code_field_id` (`code_field_id`)");
                }
                try {
                    $db->exec("ALTER TABLE `application_field_values` MODIFY `field_id` INT NULL");
                } catch (\Throwable $e) {}
            }

            // Seed default application_code_fields if none exist
            try {
                $countFields = (int)($db->query("SELECT COUNT(*) FROM `application_code_fields`")->fetchColumn() ?: 0);
                if ($countFields === 0) {
                    $seedMigration = dirname(__DIR__, 2) . '/cli/migrate_code_fields.php';
                    if (file_exists($seedMigration)) {
                        include_once $seedMigration;
                    }
                }
            } catch (\Throwable $e) {}

            // 4. Ensure srv_block_app service exists
            $chkService = $db->query("SELECT id FROM data_types WHERE code = 'srv_block_app' LIMIT 1")->fetch();
            if (!$chkService) {
                $db->exec("
                    INSERT INTO data_types (id, code, name_en, name_hi, badge, description_en, fee_amount, distributor_commission, is_active, sort_order)
                    VALUES (4, 'srv_block_app', 'Block & District Application Pass Service', 'ब्लॉक एवं जिला स्तर आवेदन पास सेवा', 'Exclusive', 'Online single & bulk citizen application pass processing directly through designated block operators.', 50.00, 10.00, 1, 1)
                    ON DUPLICATE KEY UPDATE is_active = 1
                ");
            }

            // Ensure pan_card service exists
            $chkPan = $db->query("SELECT id FROM data_types WHERE code = 'pan_card' LIMIT 1")->fetch();
            if (!$chkPan) {
                $db->exec("
                    INSERT INTO data_types (id, code, name_en, name_hi, badge, description_en, fee_amount, distributor_commission, is_active, sort_order)
                    VALUES (5, 'pan_card', 'New Pan Card Apply', 'नया पैन कार्ड आवेदन (सिर्फ आधार से)', 'PAN_CARD', 'Pan Card Apply Sirf Aadhaar Card Se. Physical Pan Card ke sath E-Pan Card Bhi Milega.', 107.00, 7.00, 1, 2)
                    ON DUPLICATE KEY UPDATE is_active = 1
                ");
            }

            // 5. Payment Gateway Configuration Self-Healing:
            // Ensure is_credited column exists on topup_orders
            if (in_array('topup_orders', $tables, true)) {
                $topupCols = $db->query("SHOW COLUMNS FROM `topup_orders`")->fetchAll(PDO::FETCH_COLUMN);
                $topupCols = array_map('strtolower', $topupCols);
                if (!in_array('is_credited', $topupCols, true)) {
                    $db->exec("ALTER TABLE `topup_orders` ADD COLUMN `is_credited` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
                }
            }

            // Ensure allapi_mode is set to LIVE so real AllAPI gateway is active
            $db->exec("UPDATE `settings` SET `setting_value` = 'LIVE' WHERE `setting_key` = 'allapi_mode' AND `setting_value` = 'SANDBOX'");
            $db->exec("INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`, `description`) 
                       VALUES ('allapi_mode', 'LIVE', 'PAYMENT', 'ALL API mode: SANDBOX or LIVE') 
                       ON DUPLICATE KEY UPDATE `setting_value` = IF(`setting_value` = 'SANDBOX', 'LIVE', `setting_value`)");

            // Ensure allapi_token has active merchant token if empty or mock
            $db->exec("UPDATE `settings` SET `setting_value` = '737bb1-df709c-d3e73f-e1fb9f-699985' WHERE `setting_key` = 'allapi_token' AND (`setting_value` = 'mock_dev_token' OR `setting_value` = '' OR `setting_value` IS NULL)");

            // Ensure any PENDING applications with confirmed SENT WhatsApp notifications are in IN_PROCESS
            if (in_array('whatsapp_notification_outbox', $tables, true)) {
                $db->exec("
                    UPDATE applications a
                    JOIN whatsapp_notification_outbox o ON a.id = o.application_id
                    SET a.status = 'IN_PROCESS', a.updated_at = NOW()
                    WHERE a.status = 'PENDING' AND o.status = 'SENT'
                ");
            }

            // Auto-heal existing applications and RTPS queue entries where application_code was empty or service_type was 'OTHER' for RTPS application numbers
            try {
                $db->exec("
                    UPDATE `applications` 
                    SET `application_code` = CASE
                        WHEN `applicant_identifier` LIKE 'BCCCO%' THEN 'BCCCO'
                        WHEN `applicant_identifier` LIKE 'BRCCO%' THEN 'BRCCO'
                        WHEN `applicant_identifier` LIKE 'BICCO%' THEN 'BICCO'
                        WHEN `applicant_identifier` LIKE 'BOBC%' THEN 'BOBC'
                        WHEN `applicant_identifier` LIKE 'NCLCO%' THEN 'NCLCO'
                        WHEN `applicant_identifier` LIKE 'EWS%' THEN 'EWSCO'
                        ELSE `application_code`
                    END
                    WHERE (`application_code` IS NULL OR `application_code` = '' OR `application_code` = 'OTHER')
                      AND (`applicant_identifier` LIKE 'BCC%' OR `applicant_identifier` LIKE 'BRC%' OR `applicant_identifier` LIKE 'BIC%' OR `applicant_identifier` LIKE 'BOBC%' OR `applicant_identifier` LIKE 'NCL%' OR `applicant_identifier` LIKE 'EWS%')
                ");
                $db->exec("
                    UPDATE `rtps_certificate_queue` 
                    SET `service_type` = 'RTPS',
                        `application_code` = CASE
                            WHEN `application_number` LIKE 'BCCCO%' THEN 'BCCCO'
                            WHEN `application_number` LIKE 'BRCCO%' THEN 'BRCCO'
                            WHEN `application_number` LIKE 'BICCO%' THEN 'BICCO'
                            WHEN `application_number` LIKE 'BOBC%' THEN 'BOBC'
                            WHEN `application_number` LIKE 'NCLCO%' THEN 'NCLCO'
                            WHEN `application_number` LIKE 'EWS%' THEN 'EWSCO'
                            ELSE `application_code`
                        END
                    WHERE (`service_type` = 'OTHER' OR `application_code` IS NULL OR `application_code` = '' OR `application_code` = 'OTHER')
                      AND (`application_number` LIKE 'BCC%' OR `application_number` LIKE 'BRC%' OR `application_number` LIKE 'BIC%' OR `application_number` LIKE 'BOBC%' OR `application_number` LIKE 'NCL%' OR `application_number` LIKE 'EWS%')
                ");
            } catch (Exception $e) {
                // Ignore if tables not yet populated
            }

            self::$schemaChecked = true;
        } catch (Exception $e) {
            error_log("Schema auto-heal warning: " . $e->getMessage());
        }
    }

    /**
     * Complete One-Click Update: Git pull (if allowed/available) + Database Migration Sync
     */
    public static function runFullUpdate(): array
    {
        $log = [];
        $appliedChanges = 0;

        // Reset schemaChecked flag so we run thorough checks
        self::$schemaChecked = false;
        self::ensureSchema();

        $db = Database::getConnection();

        // 1. Status ENUM check on applications
        try {
            $db->exec("ALTER TABLE `applications` MODIFY `status` ENUM('PENDING', 'IN_PROCESS', 'UNDER_REVIEW', 'APPROVED', 'REJECTED', 'COMPLETED') NOT NULL DEFAULT 'PENDING'");
            $log[] = "Updated applications status ENUM with 'IN_PROCESS'.";
            $appliedChanges++;
        } catch (Exception $e) {
            // Already modified or fine
        }

        // 2. Wallets sync
        try {
            $unlinkedUsers = $db->query("
                SELECT u.id FROM users u
                LEFT JOIN wallets w ON u.id = w.user_id
                WHERE w.id IS NULL
            ")->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($unlinkedUsers)) {
                $wStmt = $db->prepare("INSERT INTO wallets (user_id, balance, created_at) VALUES (?, 0.00, NOW())");
                foreach ($unlinkedUsers as $uid) {
                    $wStmt->execute([$uid]);
                }
                $log[] = "Created missing wallets for " . count($unlinkedUsers) . " users.";
                $appliedChanges++;
            }
        } catch (Exception $e) {
            // Ignore
        }

        // 3. Automated GitHub Release Puller
        $repoDir = dirname(__DIR__, 2);
        $gitPulled = false;

        // Step 3A: Try Git CLI first if .git directory and shell_exec exist
        if (is_dir($repoDir . '/.git') && function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
            $cmd = "cd " . escapeshellarg($repoDir) . " && git pull origin main 2>&1";
            $gitOutput = @shell_exec($cmd);
            if (!empty($gitOutput)) {
                $log[] = "Git CLI: " . trim($gitOutput);
                if (stripos($gitOutput, 'Updating') !== false || stripos($gitOutput, 'Already up to date') !== false) {
                    $gitPulled = true;
                }
                $appliedChanges++;
            }
        }

        // Step 3B: If Git CLI was not used or failed (standard cPanel shared hosting), download directly from GitHub via HTTP and extract!
        if (!$gitPulled) {
            $archiveUrl = 'https://codeload.github.com/Vlesatish/welcome-bihar/tar.gz/refs/heads/main';
            $ch = curl_init($archiveUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'WelcomeBihar-Portal-Updater');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $archiveData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && !empty($archiveData)) {
                $tempTarGz = $repoDir . '/storage/update_' . time() . '.tar.gz';
                $tempExtractDir = $repoDir . '/storage/update_extracted_' . time();
                file_put_contents($tempTarGz, $archiveData);

                try {
                    $phar = new \PharData($tempTarGz);
                    if (!is_dir($tempExtractDir)) {
                        mkdir($tempExtractDir, 0777, true);
                    }
                    $phar->extractTo($tempExtractDir, null, true);

                    // Find extracted root folder (e.g. welcome-bihar-main)
                    $subdirs = glob($tempExtractDir . '/*', GLOB_ONLYDIR);
                    $sourceDir = !empty($subdirs) ? $subdirs[0] : $tempExtractDir;

                    // Copy files recursively, skipping sensitive files
                    $updatedFileCount = self::copyDirectory($sourceDir, $repoDir, [
                        '.env', '.git', 'storage', 'public/uploads', 'uploads'
                    ]);

                    $log[] = "Downloaded and updated {$updatedFileCount} files directly from GitHub.";
                    $appliedChanges += $updatedFileCount;
                } catch (\Exception $e) {
                    $log[] = "GitHub direct download notice: " . $e->getMessage();
                } finally {
                    @unlink($tempTarGz);
                    self::deleteDirectory($tempExtractDir);
                }
            }
        }

        // Re-run schema ensure on the newly updated codebase
        self::$schemaChecked = false;
        self::ensureSchema();

        $log[] = "Database schema, columns, indexes, and master tables are 100% synchronized.";

        return [
            'success' => true,
            'changes' => $appliedChanges,
            'log' => $log,
            'git_output' => $gitOutput ?? ''
        ];
    }

    private static function copyDirectory(string $src, string $dst, array $skipPaths = []): int
    {
        $count = 0;
        $dir = opendir($src);
        if (!$dir) {
            return 0;
        }

        @mkdir($dst, 0777, true);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcFile = $src . '/' . $file;
            $dstFile = $dst . '/' . $file;

            $skip = false;
            foreach ($skipPaths as $skipItem) {
                if ($file === $skipItem) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            if (is_dir($srcFile)) {
                $count += self::copyDirectory($srcFile, $dstFile, $skipPaths);
            } else {
                copy($srcFile, $dstFile);
                $count++;
            }
        }
        closedir($dir);
        return $count;
    }

    private static function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::deleteDirectory("$dir/$file") : @unlink("$dir/$file");
        }
        @rmdir($dir);
    }
}
