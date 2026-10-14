-- ========================================================
-- WELCOME BIHAR - DATABASE SCHEMA
-- Compatible with phpMyAdmin, MySQL 8.0+, MariaDB 10.3+
-- Engine: InnoDB, Charset: utf8mb4, Collation: utf8mb4_unicode_ci
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+05:30";

-- 1. ROLES TABLE
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name_en` VARCHAR(100) NOT NULL,
  `name_hi` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. USERS TABLE
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `uuid` VARCHAR(36) NOT NULL UNIQUE,
  `role` ENUM('ADMIN', 'OPERATOR', 'DISTRIBUTOR', 'USER') NOT NULL DEFAULT 'USER',
  `name` VARCHAR(150) NOT NULL,
  `mobile` VARCHAR(20) NOT NULL UNIQUE,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `status` ENUM('ACTIVE', 'INACTIVE', 'BLOCKED', 'PENDING_APPROVAL') NOT NULL DEFAULT 'ACTIVE',
  `parent_distributor_id` INT NULL,
  `preferred_lang` VARCHAR(10) NOT NULL DEFAULT 'hi',
  `center_name` VARCHAR(150) NULL,
  `profile_state` VARCHAR(100) NOT NULL DEFAULT 'Bihar',
  `profile_district_id` INT NULL,
  `profile_block_id` INT NULL,
  `center_address` TEXT NULL,
  `assigned_all_districts` TINYINT(1) NOT NULL DEFAULT 0,
  `assigned_district_id` INT NULL,
  `assigned_block_id` INT NULL,
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`status`),
  INDEX `idx_users_mobile` (`mobile`),
  INDEX `idx_users_email` (`email`),
  INDEX `idx_users_distributor` (`parent_distributor_id`),
  INDEX `idx_users_profile_district` (`profile_district_id`),
  INDEX `idx_users_profile_block` (`profile_block_id`),
  INDEX `idx_users_assigned_district` (`assigned_district_id`),
  INDEX `idx_users_assigned_block` (`assigned_block_id`),
  CONSTRAINT `fk_users_distributor` FOREIGN KEY (`parent_distributor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. DISTRICTS TABLE
CREATE TABLE IF NOT EXISTS `districts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `state` VARCHAR(100) NOT NULL DEFAULT 'Bihar',
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `name_hi` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_districts_state` (`state`),
  INDEX `idx_districts_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. BLOCKS TABLE
CREATE TABLE IF NOT EXISTS `blocks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `district_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `name_hi` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_blocks_district` (`district_id`),
  INDEX `idx_blocks_active` (`is_active`),
  UNIQUE KEY `uk_district_block` (`district_id`, `name`),
  CONSTRAINT `fk_blocks_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. LOCATION PERMISSIONS (For Users & Distributors)
CREATE TABLE IF NOT EXISTS `location_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `district_id` INT NOT NULL,
  `block_id` INT NULL, -- NULL means all blocks in district
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_loc_perm_user` (`user_id`),
  INDEX `idx_loc_perm_district` (`district_id`),
  INDEX `idx_loc_perm_block` (`block_id`),
  UNIQUE KEY `uk_user_dist_block` (`user_id`, `district_id`, `block_id`),
  CONSTRAINT `fk_loc_perm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_loc_perm_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_loc_perm_block` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. DATA TYPES TABLE (Configurable Citizen Services)
CREATE TABLE IF NOT EXISTS `data_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(64) NOT NULL UNIQUE,
  `name_en` VARCHAR(150) NOT NULL,
  `name_hi` VARCHAR(150) NOT NULL,
  `category` VARCHAR(100) NOT NULL DEFAULT 'General',
  `processing_time` VARCHAR(100) NOT NULL DEFAULT '24-48 Hours',
  `badge` VARCHAR(50) NULL,
  `description_en` TEXT NULL,
  `description_hi` TEXT NULL,
  `notice` TEXT NULL,
  `announcement` TEXT NULL,
  `icon` VARCHAR(50) NOT NULL DEFAULT 'bi-folder2-open',
  `fee_amount` DECIMAL(12,2) NOT NULL DEFAULT 50.00,
  `distributor_commission` DECIMAL(12,2) NOT NULL DEFAULT 10.00,
  `duplicate_check_field` VARCHAR(100) NOT NULL DEFAULT 'aadhaar_no',
  `warning_image` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `enable_chat` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_data_types_active` (`is_active`),
  INDEX `idx_data_types_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. DATA TYPE FIELDS TABLE (Configurable Dynamic Fields)
CREATE TABLE IF NOT EXISTS `data_type_fields` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `data_type_id` INT NOT NULL,
  `field_name` VARCHAR(100) NOT NULL,
  `label_en` VARCHAR(150) NOT NULL,
  `label_hi` VARCHAR(150) NOT NULL,
  `field_type` ENUM('text', 'number', 'date', 'select', 'textarea', 'email', 'tel', 'file') NOT NULL DEFAULT 'text',
  `placeholder_en` VARCHAR(255) NULL,
  `placeholder_hi` VARCHAR(255) NULL,
  `help_text_en` VARCHAR(255) NULL,
  `help_text_hi` VARCHAR(255) NULL,
  `options_json` TEXT NULL, -- JSON array for select options: [{"value":"M","label_en":"Male","label_hi":"Male"}]
  `is_required` TINYINT(1) NOT NULL DEFAULT 1,
  `is_unique` TINYINT(1) NOT NULL DEFAULT 0, -- Duplicate check
  `validation_pattern` VARCHAR(255) NULL, -- Regex pattern or rule
  `file_types` VARCHAR(150) NULL DEFAULT 'pdf,jpg,jpeg,png',
  `max_size_mb` INT NOT NULL DEFAULT 2,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_dt_fields_datatype` (`data_type_id`),
  UNIQUE KEY `uk_datatype_field` (`data_type_id`, `field_name`),
  CONSTRAINT `fk_dt_fields_datatype` FOREIGN KEY (`data_type_id`) REFERENCES `data_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. APPLICATIONS TABLE
CREATE TABLE IF NOT EXISTS `applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `app_ref` VARCHAR(64) NOT NULL UNIQUE,
  `batch_ref` VARCHAR(64) NULL,
  `user_id` INT NOT NULL,
  `distributor_id` INT NULL,
  `assigned_operator_id` INT NULL,
  `claimed_at` DATETIME NULL,
  `data_type_id` INT NOT NULL,
  `district_id` INT NOT NULL,
  `block_id` INT NOT NULL,
  `applicant_name` VARCHAR(150) NOT NULL,
  `applicant_mobile` VARCHAR(20) NOT NULL,
  `applicant_identifier` VARCHAR(100) NULL, -- Primary duplicate detection value (e.g. Aadhaar/Ration card)
  `fee_debited` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `commission_credited` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('PENDING', 'IN_PROCESS', 'UNDER_REVIEW', 'APPROVED', 'REJECTED', 'COMPLETED') NOT NULL DEFAULT 'PENDING',
  `is_locked` TINYINT(1) NOT NULL DEFAULT 1, -- Locked after submission
  `is_refunded` TINYINT(1) NOT NULL DEFAULT 0,
  `is_commission_paid` TINYINT(1) NOT NULL DEFAULT 0,
  `distributor_reviewed_at` DATETIME NULL,
  `distributor_reviewed_by` INT NULL,
  `final_action_at` DATETIME NULL,
  `final_action_by` INT NULL,
  `completed_at` DATETIME NULL,
  `completed_by` INT NULL,
  `rejection_reason` TEXT NULL,
  `remarks` TEXT NULL,
  `admin_remarks` TEXT NULL,
  `result_file` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_apps_user` (`user_id`),
  INDEX `idx_apps_distributor` (`distributor_id`),
  INDEX `idx_apps_assigned_operator` (`assigned_operator_id`),
  INDEX `idx_apps_batch_ref` (`batch_ref`),
  INDEX `idx_apps_data_type` (`data_type_id`),
  INDEX `idx_apps_district` (`district_id`),
  INDEX `idx_apps_block` (`block_id`),
  INDEX `idx_apps_status` (`status`),
  INDEX `idx_apps_ref` (`app_ref`),
  INDEX `idx_apps_mobile` (`applicant_mobile`),
  INDEX `idx_apps_identifier` (`data_type_id`, `applicant_identifier`),
  CONSTRAINT `fk_apps_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_apps_distributor` FOREIGN KEY (`distributor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_apps_datatype` FOREIGN KEY (`data_type_id`) REFERENCES `data_types` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_apps_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_apps_block` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. APPLICATION FIELD VALUES TABLE
CREATE TABLE IF NOT EXISTS `application_field_values` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL,
  `field_id` INT NOT NULL,
  `field_name` VARCHAR(100) NOT NULL,
  `field_value` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_app_fvalues_app` (`application_id`),
  INDEX `idx_app_fvalues_field` (`field_id`),
  CONSTRAINT `fk_app_fvalues_app` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_app_fvalues_field` FOREIGN KEY (`field_id`) REFERENCES `data_type_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. APPLICATION STATUS HISTORY (Immutable Audit Trail)
CREATE TABLE IF NOT EXISTS `application_status_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL,
  `from_status` VARCHAR(50) NOT NULL,
  `to_status` VARCHAR(50) NOT NULL,
  `actor_user_id` INT NOT NULL,
  `actor_name` VARCHAR(150) NOT NULL,
  `actor_role` VARCHAR(50) NOT NULL,
  `remarks` TEXT NULL,
  `rejection_reason` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_app_status_app` (`application_id`),
  CONSTRAINT `fk_app_status_app` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. WALLETS TABLE
CREATE TABLE IF NOT EXISTS `wallets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_credited` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_debited` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_wallets_user` (`user_id`),
  CONSTRAINT `fk_wallets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. WALLET TRANSACTIONS LEDGER (Immutable Double-Entry Style Ledger)
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `txn_ref` VARCHAR(64) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `wallet_id` INT NOT NULL,
  `type` ENUM('TOPUP_CREDIT', 'APPLICATION_FEE_DEBIT', 'COMMISSION_CREDIT', 'REFUND_CREDIT', 'ADMIN_ADJUSTMENT') NOT NULL,
  `direction` ENUM('CREDIT', 'DEBIT') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `balance_before` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `balance_after` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('SUCCESS', 'PENDING', 'FAILED', 'REVERSED') NOT NULL DEFAULT 'SUCCESS',
  `reference_type` VARCHAR(50) NOT NULL, -- 'TOPUP_ORDER', 'APPLICATION', 'ADMIN_MANUAL'
  `reference_id` VARCHAR(100) NOT NULL,
  `idempotency_key` VARCHAR(128) NOT NULL UNIQUE,
  `description_en` VARCHAR(255) NOT NULL,
  `description_hi` VARCHAR(255) NOT NULL,
  `remarks` TEXT NULL,
  `created_by_user_id` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_wtx_user_created` (`user_id`, `created_at`),
  INDEX `idx_wtx_ref` (`reference_type`, `reference_id`),
  INDEX `idx_wtx_type` (`type`),
  INDEX `idx_wtx_idem` (`idempotency_key`),
  CONSTRAINT `fk_wtx_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_wtx_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. TOPUP ORDERS (UPI Scan & Pay via ALL API)
CREATE TABLE IF NOT EXISTS `topup_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` VARCHAR(64) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `status` ENUM('PENDING', 'PAID', 'FAILED', 'EXPIRED', 'PENDING_VERIFICATION') NOT NULL DEFAULT 'PENDING',
  `is_credited` TINYINT(1) NOT NULL DEFAULT 0,
  `provider` VARCHAR(50) NOT NULL DEFAULT 'ALL_API',
  `provider_txn_id` VARCHAR(150) NULL,
  `provider_utr` VARCHAR(150) NULL,
  `qr_image_url` TEXT NULL,
  `payment_url` TEXT NULL,
  `expires_at` DATETIME NULL,
  `credited_at` DATETIME NULL,
  `credited_wallet_txn_id` INT NULL,
  `raw_init_response` LONGTEXT NULL,
  `raw_status_response` LONGTEXT NULL,
  `raw_callback_payload` LONGTEXT NULL,
  `manual_verified_by` INT NULL,
  `manual_verified_at` DATETIME NULL,
  `manual_remarks` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_topup_user` (`user_id`),
  INDEX `idx_topup_status` (`status`),
  INDEX `idx_topup_order` (`order_id`),
  INDEX `idx_topup_provider_txn` (`provider_txn_id`),
  CONSTRAINT `fk_topup_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. PAYMENT PROVIDER EVENTS (Webhook Idempotency & Diagnostics)
CREATE TABLE IF NOT EXISTS `payment_provider_events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `provider` VARCHAR(50) NOT NULL DEFAULT 'ALL_API',
  `event_type` VARCHAR(100) NOT NULL DEFAULT 'WEBHOOK',
  `order_id` VARCHAR(64) NOT NULL,
  `event_id` VARCHAR(150) NOT NULL UNIQUE,
  `payload` LONGTEXT NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `processed` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ppe_order` (`order_id`),
  INDEX `idx_ppe_event` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. SYSTEM SETTINGS
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` LONGTEXT NOT NULL,
  `setting_group` VARCHAR(50) NOT NULL DEFAULT 'GENERAL',
  `description` VARCHAR(255) NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_settings_key` (`setting_key`),
  INDEX `idx_settings_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. AUDIT LOGS
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `actor_user_id` INT NULL,
  `actor_role` VARCHAR(50) NULL,
  `action` VARCHAR(100) NOT NULL,
  `target_type` VARCHAR(50) NOT NULL,
  `target_id` VARCHAR(100) NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `details_json` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit_actor` (`actor_user_id`),
  INDEX `idx_audit_action` (`action`),
  INDEX `idx_audit_target` (`target_type`, `target_id`),
  INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. LOGIN ATTEMPTS (Rate Limiting & Brute Force Defense)
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `username_or_mobile` VARCHAR(100) NOT NULL,
  `attempt_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_successful` TINYINT(1) NOT NULL DEFAULT 0,
  INDEX `idx_login_attempts_ip_time` (`ip_address`, `attempt_time`),
  INDEX `idx_login_attempts_user_time` (`username_or_mobile`, `attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. SERVICE RATE RULES (District / Block / Field Dynamic Pricing)
CREATE TABLE IF NOT EXISTS `service_rate_rules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `data_type_id` INT NOT NULL,
  `state` VARCHAR(100) NOT NULL DEFAULT 'Bihar',
  `district_id` INT NULL,
  `block_id` INT NULL,
  `application_code` VARCHAR(64) NULL,
  `condition_field` VARCHAR(100) NULL,
  `condition_value` VARCHAR(100) NULL,
  `adjustment_type` ENUM('FIXED', 'ADD', 'SUBTRACT') NOT NULL DEFAULT 'FIXED',
  `fee_amount` DECIMAL(12,2) NOT NULL,
  `distributor_commission` DECIMAL(12,2) NULL,
  `rule_label` VARCHAR(150) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_srr_dt` (`data_type_id`),
  INDEX `idx_srr_dist` (`district_id`),
  INDEX `idx_srr_block` (`block_id`),
  INDEX `idx_srr_code` (`application_code`),
  CONSTRAINT `fk_srr_dt` FOREIGN KEY (`data_type_id`) REFERENCES `data_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. NOTIFICATIONS TABLE (User In-App Notifications & Alerts)
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('INFO', 'SUCCESS', 'WARNING', 'DANGER') NOT NULL DEFAULT 'INFO',
  `link` VARCHAR(255) NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_notif_user` (`user_id`, `is_read`, `created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. WHATSAPP NOTIFICATION OUTBOX TABLE (Idempotent Operator WhatsApp Delivery Queue)
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
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `sent_at` DATETIME NULL,
  INDEX `idx_outbox_status_next` (`status`, `next_attempt_at`),
  INDEX `idx_outbox_claim` (`status`, `next_attempt_at`, `locked_at`),
  INDEX `idx_outbox_application_id` (`application_id`),
  INDEX `idx_outbox_recipient_user_id` (`recipient_user_id`),
  INDEX `idx_outbox_lock_token` (`lock_token`),
  INDEX `idx_outbox_created_at` (`created_at`),
  CONSTRAINT `fk_outbox_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_outbox_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. SERVICE BLOCK AVAILABILITY TABLE (Separate Block-Level Service Availability)
CREATE TABLE IF NOT EXISTS `service_block_availability` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `data_type_id` INT NOT NULL,
  `district_id` INT NOT NULL,
  `block_id` INT NOT NULL,
  `status` ENUM('INACTIVE', 'LIVE') NOT NULL DEFAULT 'INACTIVE',
  `activated_at` DATETIME NULL,
  `activated_by` INT NULL,
  `deactivated_at` DATETIME NULL,
  `deactivated_by` INT NULL,
  `internal_note` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_service_district_block` (`data_type_id`, `district_id`, `block_id`),
  INDEX `idx_sba_service_status` (`data_type_id`, `status`),
  INDEX `idx_sba_district` (`district_id`),
  INDEX `idx_sba_block` (`block_id`),
  INDEX `idx_sba_status` (`status`),
  CONSTRAINT `fk_sba_data_type` FOREIGN KEY (`data_type_id`) REFERENCES `data_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sba_district` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sba_block` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sba_activated_by` FOREIGN KEY (`activated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sba_deactivated_by` FOREIGN KEY (`deactivated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

