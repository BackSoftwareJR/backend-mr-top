-- =============================================================================
-- Wenando Trust Engine — Master Database Schema
-- MySQL 8+ / MariaDB 10.6+
-- Charset: utf8mb4_unicode_ci
-- Infrastructure: no Redis; queue= database; cache= file|database; Sanctum auth
--
-- DOCUMENTATION: backend/docs/0_INDEX.md
-- GAP ANALYSIS:  backend/docs/10_SQL_REVIEW_&_GAPS.md (read before implement)
--
-- KNOWN GAPS vs frontend analysis (2026-06):
--   [ ] lead_matches.public_ref (ML-####) — column not yet in DDL
--   [ ] audit_logs, impersonation_sessions — admin compliance (deferred)
--   [ ] idempotency_keys — wallet unlock / recharge (deferred)
--   [ ] consent_records — GDPR wizard consent (deferred)
--   [ ] companies consumer_profile JSON — B2C match cards (name, image, pros)
--   [ ] Recommended indexes: see 10_SQL_REVIEW_&_GAPS.md §3
--   [x] activity_feed — migration 000038 (company_id, type, text, metadata, created_at)
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `wenando`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `wenando`;

-- -----------------------------------------------------------------------------
-- 1. USERS
-- -----------------------------------------------------------------------------
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `phone` VARCHAR(32) NULL DEFAULT NULL,
  `user_type` ENUM('consumer', 'b2b', 'superadmin') NOT NULL DEFAULT 'consumer',
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `password` VARCHAR(255) NULL DEFAULT NULL,
  `remember_token` VARCHAR(100) NULL DEFAULT NULL,
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_uuid_unique` (`uuid`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_user_type_index` (`user_type`),
  KEY `users_deleted_at_index` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. ROLES & PERMISSIONS (Spatie-like)
-- -----------------------------------------------------------------------------
CREATE TABLE `permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `guard_name` VARCHAR(255) NOT NULL DEFAULT 'web',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_unique` (`name`, `guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `guard_name` VARCHAR(255) NOT NULL DEFAULT 'web',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_unique` (`name`, `guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_has_permissions` (
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`, `role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign`
    FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign`
    FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `model_has_roles` (
  `role_id` BIGINT UNSIGNED NOT NULL,
  `model_type` VARCHAR(255) NOT NULL,
  `model_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`role_id`, `model_id`, `model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`, `model_type`),
  KEY `model_has_roles_company_id_index` (`company_id`),
  CONSTRAINT `model_has_roles_role_id_foreign`
    FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `model_has_permissions` (
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `model_type` VARCHAR(255) NOT NULL,
  `model_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`, `model_id`, `model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`, `model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign`
    FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. SECTORS
-- -----------------------------------------------------------------------------
CREATE TABLE `sectors` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(64) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `wizard_schema` JSON NULL,
  `operations_schema` JSON NULL,
  `trust_schema` JSON NULL,
  `matching_rules` JSON NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sectors_slug_unique` (`slug`),
  KEY `sectors_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. COMPANIES (B2B partners)
-- -----------------------------------------------------------------------------
CREATE TABLE `companies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `sector_id` BIGINT UNSIGNED NOT NULL,
  `organization_name` VARCHAR(255) NOT NULL,
  `legal_name` VARCHAR(255) NOT NULL,
  `vat_number` VARCHAR(16) NULL DEFAULT NULL,
  `sdi_code` VARCHAR(7) NULL DEFAULT NULL,
  `city` VARCHAR(128) NULL DEFAULT NULL,
  `vetting_status` ENUM(
    'draft',
    'in_progress',
    'pending_review',
    'approved',
    'rejected',
    'suspended'
  ) NOT NULL DEFAULT 'draft',
  `tier` ENUM('starter', 'growth', 'enterprise') NULL DEFAULT NULL,
  `dynamic_attributes` JSON NULL,
  `schedule` JSON NULL,
  `approved_at` TIMESTAMP NULL DEFAULT NULL,
  `rejected_at` TIMESTAMP NULL DEFAULT NULL,
  `rejection_reason` TEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `companies_uuid_unique` (`uuid`),
  KEY `companies_sector_id_index` (`sector_id`),
  KEY `companies_vetting_status_index` (`vetting_status`),
  KEY `companies_vat_number_index` (`vat_number`),
  KEY `companies_sector_vetting_index` (`sector_id`, `vetting_status`),
  KEY `companies_deleted_at_index` (`deleted_at`),
  CONSTRAINT `companies_sector_id_foreign`
    FOREIGN KEY (`sector_id`) REFERENCES `sectors` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `company_user` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role` ENUM('owner', 'staff') NOT NULL DEFAULT 'owner',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_user_company_user_unique` (`company_id`, `user_id`),
  KEY `company_user_user_id_index` (`user_id`),
  CONSTRAINT `company_user_company_id_foreign`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `company_user_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `company_documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('visura', 'identity') NOT NULL,
  `file_path` VARCHAR(512) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(128) NULL DEFAULT NULL,
  `size_bytes` BIGINT UNSIGNED NULL DEFAULT NULL,
  `verified_at` TIMESTAMP NULL DEFAULT NULL,
  `verified_by` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_documents_company_id_index` (`company_id`),
  KEY `company_documents_type_index` (`type`),
  CONSTRAINT `company_documents_company_id_foreign`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `company_documents_verified_by_foreign`
    FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: partners operating in multiple sectors
CREATE TABLE `company_sectors` (
  `company_id` BIGINT UNSIGNED NOT NULL,
  `sector_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`company_id`, `sector_id`),
  CONSTRAINT `company_sectors_company_id_foreign`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `company_sectors_sector_id_foreign`
    FOREIGN KEY (`sector_id`) REFERENCES `sectors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. TRUST TESTS & SCORES
-- -----------------------------------------------------------------------------
CREATE TABLE `trust_tests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `sector_id` BIGINT UNSIGNED NOT NULL,
  `answers` JSON NOT NULL,
  `status` ENUM('draft', 'submitted', 'scored', 'failed') NOT NULL DEFAULT 'draft',
  `submitted_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `trust_tests_company_id_index` (`company_id`),
  KEY `trust_tests_sector_id_index` (`sector_id`),
  KEY `trust_tests_status_index` (`status`),
  CONSTRAINT `trust_tests_company_id_foreign`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `trust_tests_sector_id_foreign`
    FOREIGN KEY (`sector_id`) REFERENCES `sectors` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `company_trust_scores` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `trust_test_id` BIGINT UNSIGNED NOT NULL,
  `score` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
  `breakdown` JSON NULL,
  `scored_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_trust_scores_company_id_index` (`company_id`),
  KEY `company_trust_scores_trust_test_id_index` (`trust_test_id`),
  KEY `company_trust_scores_score_index` (`score`),
  CONSTRAINT `company_trust_scores_company_id_foreign`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `company_trust_scores_trust_test_id_foreign`
    FOREIGN KEY (`trust_test_id`) REFERENCES `trust_tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. LEADS & MATCHES
-- -----------------------------------------------------------------------------
CREATE TABLE `leads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `public_ref` VARCHAR(32) NULL DEFAULT NULL,
  `sector_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `status` ENUM(
    'draft',
    'processing',
    'routed',
    'assigned',
    'closed',
    'cancelled'
  ) NOT NULL DEFAULT 'draft',
  `admin_status` VARCHAR(64) NULL DEFAULT NULL,
  `payload` JSON NOT NULL,
  `contact_name` VARCHAR(255) NULL DEFAULT NULL,
  `contact_phone` VARCHAR(32) NULL DEFAULT NULL,
  `contact_email` VARCHAR(255) NULL DEFAULT NULL,
  `location_label` VARCHAR(255) NULL DEFAULT NULL,
  `budget_min` INT UNSIGNED NULL DEFAULT NULL,
  `budget_max` INT UNSIGNED NULL DEFAULT NULL,
  `need_summary` TEXT NULL DEFAULT NULL,
  `admin_notes` TEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leads_uuid_unique` (`uuid`),
  UNIQUE KEY `leads_public_ref_unique` (`public_ref`),
  KEY `leads_sector_id_index` (`sector_id`),
  KEY `leads_user_id_index` (`user_id`),
  KEY `leads_status_index` (`status`),
  KEY `leads_created_at_index` (`created_at`),
  KEY `leads_sector_status_index` (`sector_id`, `status`),
  KEY `leads_deleted_at_index` (`deleted_at`),
  CONSTRAINT `leads_sector_id_foreign`
    FOREIGN KEY (`sector_id`) REFERENCES `sectors` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `leads_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lead_matches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `match_score` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `rank` SMALLINT UNSIGNED NULL DEFAULT NULL,
  `is_visible_to_consumer` TINYINT(1) NOT NULL DEFAULT 0,
  `is_in_marketplace` TINYINT(1) NOT NULL DEFAULT 0,
  `unlocked_at` TIMESTAMP NULL DEFAULT NULL,
  `unlock_cost_credits` INT UNSIGNED NOT NULL DEFAULT 15,
  `crm_status` ENUM(
    'nuovo',
    'contattato',
    'visita_fissata',
    'perso',
    'chiuso'
  ) NULL DEFAULT NULL,
  `assigned_by` BIGINT UNSIGNED NULL DEFAULT NULL,
  `metadata` JSON NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_matches_lead_company_unique` (`lead_id`, `company_id`),
  KEY `lead_matches_company_id_index` (`company_id`),
  KEY `lead_matches_match_score_index` (`match_score`),
  KEY `lead_matches_unlocked_at_index` (`unlocked_at`),
  KEY `lead_matches_marketplace_index` (`company_id`, `is_in_marketplace`, `match_score`),
  KEY `lead_matches_crm_status_index` (`crm_status`),
  CONSTRAINT `lead_matches_lead_id_foreign`
    FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_matches_company_id_foreign`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_matches_assigned_by_foreign`
    FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. WALLETS & TRANSACTIONS
-- -----------------------------------------------------------------------------
CREATE TABLE `wallets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `balance_credits` INT NOT NULL DEFAULT 0,
  `total_spent_credits` INT UNSIGNED NOT NULL DEFAULT 0,
  `currency` CHAR(3) NOT NULL DEFAULT 'EUR',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wallets_company_id_unique` (`company_id`),
  CONSTRAINT `wallets_company_id_foreign`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `public_ref` VARCHAR(32) NULL DEFAULT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `wallet_id` BIGINT UNSIGNED NOT NULL,
  `lead_match_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `type` ENUM(
    'recharge',
    'lead_unlock',
    'subscription',
    'lead_bundle',
    'commission',
    'refund'
  ) NOT NULL,
  `amount_cents` INT NOT NULL,
  `credits_delta` INT NOT NULL DEFAULT 0,
  `status` ENUM('pending', 'completed', 'failed', 'void') NOT NULL DEFAULT 'pending',
  `payment_method` ENUM('card', 'sepa', 'transfer', 'wallet') NULL DEFAULT NULL,
  `reference` VARCHAR(64) NULL DEFAULT NULL,
  `description` VARCHAR(512) NULL DEFAULT NULL,
  `metadata` JSON NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transactions_uuid_unique` (`uuid`),
  UNIQUE KEY `transactions_public_ref_unique` (`public_ref`),
  KEY `transactions_company_id_index` (`company_id`),
  KEY `transactions_wallet_id_index` (`wallet_id`),
  KEY `transactions_status_index` (`status`),
  KEY `transactions_type_index` (`type`),
  KEY `transactions_created_at_index` (`created_at`),
  KEY `transactions_lead_match_id_index` (`lead_match_id`),
  CONSTRAINT `transactions_company_id_foreign`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `transactions_wallet_id_foreign`
    FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `transactions_lead_match_id_foreign`
    FOREIGN KEY (`lead_match_id`) REFERENCES `lead_matches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. APPOINTMENTS, NOTIFICATIONS, SAVED MATCHES
-- -----------------------------------------------------------------------------
CREATE TABLE `appointments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `lead_match_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `client_name` VARCHAR(255) NOT NULL,
  `scheduled_date` DATE NOT NULL,
  `scheduled_time` TIME NOT NULL,
  `note` TEXT NULL DEFAULT NULL,
  `type` ENUM('visit', 'advisor') NOT NULL DEFAULT 'visit',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appointments_company_id_index` (`company_id`),
  KEY `appointments_lead_match_id_index` (`lead_match_id`),
  KEY `appointments_scheduled_date_index` (`scheduled_date`),
  CONSTRAINT `appointments_company_id_foreign`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_lead_match_id_foreign`
    FOREIGN KEY (`lead_match_id`) REFERENCES `lead_matches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `appointments_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` CHAR(36) NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `notifiable_type` VARCHAR(255) NOT NULL,
  `notifiable_id` BIGINT UNSIGNED NOT NULL,
  `data` JSON NOT NULL,
  `read_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_index` (`notifiable_type`, `notifiable_id`),
  KEY `notifications_read_at_index` (`read_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `saved_matches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `lead_match_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `saved_matches_user_company_unique` (`user_id`, `company_id`),
  KEY `saved_matches_lead_match_id_index` (`lead_match_id`),
  CONSTRAINT `saved_matches_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `saved_matches_company_id_foreign`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `saved_matches_lead_match_id_foreign`
    FOREIGN KEY (`lead_match_id`) REFERENCES `lead_matches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. OTP (passwordless auth)
-- -----------------------------------------------------------------------------
CREATE TABLE `otp_codes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `code_hash` VARCHAR(255) NOT NULL,
  `portal` ENUM('consumer', 'partner', 'admin') NOT NULL DEFAULT 'consumer',
  `expires_at` TIMESTAMP NOT NULL,
  `last_sent_at` TIMESTAMP NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `otp_codes_email_expires_index` (`email`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. LARAVEL SANCTUM, SESSIONS, QUEUE, CACHE
-- -----------------------------------------------------------------------------
CREATE TABLE `personal_access_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` VARCHAR(255) NOT NULL,
  `tokenable_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `abilities` TEXT NULL,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_index` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
  `id` VARCHAR(255) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` TEXT NULL,
  `payload` LONGTEXT NOT NULL,
  `last_activity` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` VARCHAR(255) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL,
  `reserved_at` INT UNSIGNED NULL DEFAULT NULL,
  `available_at` INT UNSIGNED NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `job_batches` (
  `id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `total_jobs` INT NOT NULL,
  `pending_jobs` INT NOT NULL,
  `failed_jobs` INT NOT NULL,
  `failed_job_ids` LONGTEXT NOT NULL,
  `options` MEDIUMTEXT NULL,
  `cancelled_at` INT NULL DEFAULT NULL,
  `created_at` INT NOT NULL,
  `finished_at` INT NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` VARCHAR(255) NOT NULL,
  `connection` TEXT NOT NULL,
  `queue` TEXT NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `exception` LONGTEXT NOT NULL,
  `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache` (
  `key` VARCHAR(255) NOT NULL,
  `value` MEDIUMTEXT NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_locks` (
  `key` VARCHAR(255) NOT NULL,
  `owner` VARCHAR(255) NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. DEFERRED FK: model_has_roles.company_id → companies
-- -----------------------------------------------------------------------------
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_company_id_foreign`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

-- -----------------------------------------------------------------------------
-- 12. SEED DATA (reference — optional run)
-- -----------------------------------------------------------------------------
INSERT INTO `sectors` (`slug`, `name`, `is_active`, `wizard_schema`, `operations_schema`, `trust_schema`, `matching_rules`, `created_at`, `updated_at`)
VALUES
(
  'senior-care',
  'Senior Care',
  1,
  JSON_OBJECT(
    'id', 'wenando-intake-v3',
    'title', 'Analisi gratuita',
    'steps', JSON_ARRAY(
      JSON_OBJECT('id', 'autonomy', 'type', 'cards'),
      JSON_OBJECT('id', 'location', 'type', 'autocomplete'),
      JSON_OBJECT('id', 'budget', 'type', 'range-slider'),
      JSON_OBJECT('id', 'contact', 'type', 'contact-form')
    )
  ),
  JSON_OBJECT(
    'fields', JSON_ARRAY('sector', 'capacity', 'nonSelfSufficient', 'nightStaff', 'notes')
  ),
  JSON_OBJECT(
    'questions', JSON_ARRAY('emergency', 'fall', 'family', 'quality')
  ),
  JSON_OBJECT('default_unlock_cost', 15, 'min_match_score_marketplace', 80),
  NOW(),
  NOW()
),
(
  'home-renovation',
  'Home Renovation',
  0,
  JSON_OBJECT('id', 'wenando-renovation-v1', 'title', 'Preventivo ristrutturazione', 'steps', JSON_ARRAY()),
  JSON_OBJECT('fields', JSON_ARRAY()),
  JSON_OBJECT('questions', JSON_ARRAY()),
  JSON_OBJECT(),
  NOW(),
  NOW()
);

INSERT INTO `roles` (`name`, `guard_name`, `created_at`, `updated_at`)
VALUES
  ('consumer', 'web', NOW(), NOW()),
  ('partner_owner', 'web', NOW(), NOW()),
  ('partner_staff', 'web', NOW(), NOW()),
  ('super_admin', 'web', NOW(), NOW());

INSERT INTO `permissions` (`name`, `guard_name`, `created_at`, `updated_at`)
VALUES
  ('leads.view', 'web', NOW(), NOW()),
  ('leads.unlock', 'web', NOW(), NOW()),
  ('wallet.recharge', 'web', NOW(), NOW()),
  ('crm.manage', 'web', NOW(), NOW()),
  ('partners.approve', 'web', NOW(), NOW()),
  ('admin.access', 'web', NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- End of schema
-- =============================================================================
