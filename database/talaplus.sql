-- -----------------------------------------------------
-- Schema talaplus
--
-- Datamodel for the "TALA+" platform.
-- == Copyright (c) 2026
-- == Designed by Xanders Samoth (https://team.xsamtech.com/xanderssamoth)
-- -----------------------------------------------------
-- -----------------------------------------------------
-- Table `users`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `firstname` VARCHAR(255) NULL,
  `lastname` VARCHAR(255) NULL,
  `surname` VARCHAR(255) NULL,
  `partner_name` VARCHAR(255) NULL,
  `gender` VARCHAR(45) NULL,
  `birthdate` DATE NULL,
  `country` VARCHAR(255) NULL,
  `city` VARCHAR(255) NULL,
  `address_1` TEXT NULL,
  `address_2` TEXT NULL,
  `p_o_box` VARCHAR(45) NULL,
  `currency` VARCHAR(45) NULL,
  `email` VARCHAR(255) NULL,
  `phone` VARCHAR(45) NULL,
  `email_verified_at` DATETIME NULL,
  `phone_verified_at` DATETIME NULL,
  `username` VARCHAR(255) NULL,
  `password` TEXT NULL,
  `remember_token` VARCHAR(100) NULL,
  `api_token` TEXT NULL,
  `api_key` TEXT NULL,
  `avatar_url` TEXT NULL,
  `cover_url` TEXT NULL,
  `promo_code` VARCHAR(45) NULL,
  `two_factor_secret` TEXT NULL,
  `two_factor_recovery_codes` TEXT NULL,
  `two_factor_email_confirmed_at` TIMESTAMP NULL,
  `two_factor_phone_confirmed_at` TIMESTAMP NULL,
  `tips_at_every_login` TINYINT NOT NULL DEFAULT 1,
  `is_online` TINYINT NOT NULL DEFAULT 1,
  `christian_preference` TINYINT NOT NULL DEFAULT 0,
  `belongs_to` BIGINT NULL,
  `child_lock_code` VARCHAR(45) NULL,
  `status` ENUM('created', 'activated', 'disabled', 'blocked', 'deleted') NOT NULL DEFAULT 'created',
  `type` ENUM('uncertified', 'certified') NOT NULL DEFAULT 'uncertified',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_users_UNIQUE` (`id` ASC),
  UNIQUE INDEX `email_UNIQUE` (`email` ASC),
  UNIQUE INDEX `phone_UNIQUE` (`phone` ASC),
  UNIQUE INDEX `username_UNIQUE` (`username` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `roles`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `role_name` JSON NOT NULL,
  `role_description` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_roles_UNIQUE` (`id` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `role_user`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_user` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `role_id` BIGINT NOT NULL,
  `user_id` BIGINT NOT NULL,
  `is_selected` TINYINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_roleuser_UNIQUE` (`id` ASC),
  INDEX `fk_roleuser_roles_idx` (`role_id` ASC),
  INDEX `fk_roleuser_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_roleuser_roles`
    FOREIGN KEY (`role_id`)
    REFERENCES `roles` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_roleuser_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `password_resets`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NULL,
  `phone` VARCHAR(45) NULL,
  `token` VARCHAR(45) NULL,
  `former_password` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_passwordresets_UNIQUE` (`id` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `personal_access_tokens`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `tokenable_type` VARCHAR(255) NOT NULL,
  `tokenable_id` BIGINT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `abilities` TEXT NULL,
  `last_used_at` TIMESTAMP NULL,
  `expires_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_personalaccesstokens_UNIQUE` (`id` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `payments`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(45) NULL,
  `provider_reference` VARCHAR(45) NULL,
  `order_number` TEXT NULL,
  `amount` DECIMAL(12,2) NULL,
  `amount_customer` DECIMAL(12,2) NULL,
  `phone` VARCHAR(45) NULL,
  `currency` VARCHAR(45) NULL,
  `channel` VARCHAR(45) NULL,
  `type` INT NOT NULL,
  `status` INT NULL,
  `reason` ENUM('media_create', 'media_boost', 'gift', 'product_sale', 'user_certfied', 'ad') NULL,
  `entity` ENUM('media', 'cart', 'user', 'pricing') NULL,
  `entity_id` BIGINT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_payments_UNIQUE` (`id` ASC),
  INDEX `fk_payments_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_payments_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `sessions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `payload` LONGTEXT NULL,
  `last_activity` INT NULL,
  `latitude` DECIMAL(10,7) NULL,
  `longitude` DECIMAL(10,7) NULL,
  `city` VARCHAR(255) NULL,
  `region` VARCHAR(255) NULL,
  `country` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_sessions_UNIQUE` (`id` ASC),
  INDEX `fk_sessions_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_sessions_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `medias`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `medias` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `media_title` TEXT NULL,
  `media_description` LONGTEXT NULL,
  `media_length` INT NULL,
  `media_url` TEXT NULL,
  `cover_url` TEXT NULL,
  `author_names` VARCHAR(255) NULL,
  `is_free` TINYINT NOT NULL DEFAULT 1,
  `price` DECIMAL(12,2) NOT NULL,
  `for_youth` TINYINT NOT NULL DEFAULT 0,
  `belongs_to` BIGINT NULL,
  `type` ENUM('film_series', 'comedy', 'music', 'education', 'business', 'crafts_diy', 'sports', 'documentary') NOT NULL,
  `is_shared` TINYINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `user_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_medias_UNIQUE` (`id` ASC),
  INDEX `fk_medias_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_medias_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `categories`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `category_name` JSON NOT NULL,
  `category_description` JSON NULL,
  `icon` VARCHAR(45) NULL,
  `color` VARCHAR(45) NULL,
  `for_type` ENUM('film_series', 'comedy', 'music', 'education', 'business', 'crafts_diy', 'sports', 'documentary', 'product', 'service') NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_categories_UNIQUE` (`id` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `products`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `product_name` VARCHAR(255) NOT NULL,
  `product_description` TEXT NULL,
  `type` ENUM('product', 'service') NOT NULL DEFAULT 'product',
  `quantity` INT NULL,
  `price` DECIMAL(12,2) NULL,
  `currency` VARCHAR(45) NULL,
  `action` ENUM('sale', 'rental') NOT NULL DEFAULT 'sale',
  `is_shared` TINYINT NOT NULL DEFAULT 0,
  `price_reduction_start` DATETIME NULL,
  `price_reduction_end` DATETIME NULL,
  `reduction_rate` DECIMAL(3,2) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `category_id` BIGINT NULL,
  `user_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_products_UNIQUE` (`id` ASC),
  INDEX `fk_products_categories_idx` (`category_id` ASC),
  INDEX `fk_products_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_products_categories`
    FOREIGN KEY (`category_id`)
    REFERENCES `categories` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_products_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `comments`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `comments` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `comment_content` LONGTEXT NULL,
  `answered_for` BIGINT NULL,
  `type` ENUM('app_info', 'post', 'comment') NOT NULL DEFAULT 'post',
  `for_entity` ENUM('user', 'media', 'product', 'message') NULL COMMENT 'This column is important for “app_info” comments, which provide information about a feature of the application.',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `media_id` BIGINT NULL,
  `product_id` BIGINT NULL,
  `user_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_comments_UNIQUE` (`id` ASC),
  INDEX `fk_comments_medias_idx` (`media_id` ASC),
  INDEX `fk_comments_products_idx` (`product_id` ASC),
  INDEX `fk_comments_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_comments_medias`
    FOREIGN KEY (`media_id`)
    REFERENCES `medias` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_comments_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_comments_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `groups`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `groups` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `group_name` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `user_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_groups_UNIQUE` (`id` ASC),
  INDEX `fk_groups_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_groups_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `messages`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `message_content` LONGTEXT NULL,
  `answered_for` BIGINT NULL,
  `type` ENUM('text', 'voice_note', 'file', 'call_audio', 'call_video') NOT NULL DEFAULT 'text',
  `call_type` ENUM('outgoing', 'incoming', 'missed') NULL COMMENT 'Useful for \"call_audio\" or \"call_video\" type messages',
  `status` ENUM('read', 'unread') NOT NULL DEFAULT 'unread',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `user_id` BIGINT NULL,
  `addressee_user_id` BIGINT NULL,
  `addressee_group_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_messages_UNIQUE` (`id` ASC),
  INDEX `fk_messages_users_1_idx` (`user_id` ASC),
  INDEX `fk_messages_users_2_idx` (`addressee_user_id` ASC),
  INDEX `fk_messages_groups_idx` (`addressee_group_id` ASC),
  CONSTRAINT `fk_messages_users_1`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_messages_users_2`
    FOREIGN KEY (`addressee_user_id`)
    REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_messages_groups`
    FOREIGN KEY (`addressee_group_id`)
    REFERENCES `groups` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `files`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `files` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `file_name` VARCHAR(255) NULL,
  `file_url` TEXT NOT NULL,
  `file_description` LONGTEXT NULL COMMENT 'This might be useful for describing advertisements, for example',
  `file_type` ENUM('video', 'photo', 'audio', 'document', 'id_card', 'ad', 'qr_code') NOT NULL DEFAULT 'photo',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `user_id` BIGINT NULL,
  `comment_id` BIGINT NULL,
  `product_id` BIGINT NULL,
  `message_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_files_UNIQUE` (`id` ASC),
  INDEX `fk_files_users_idx` (`user_id` ASC),
  INDEX `fk_files_comments_idx` (`comment_id` ASC),
  INDEX `fk_files_products_idx` (`product_id` ASC),
  INDEX `fk_files_messages_idx` (`message_id` ASC),
  CONSTRAINT `fk_files_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_files_comments`
    FOREIGN KEY (`comment_id`)
    REFERENCES `comments` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_files_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_files_messages`
    FOREIGN KEY (`message_id`)
    REFERENCES `messages` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `notifications`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `type` ENUM('welcome_new_user', 'media_created', 'media_accepted', 'media_rejected', 'media_published', 'post_sent', 'comment_sent', 'like_sent', 'gift_sent', 'report_sent', 'new_follower', 'mention', 'product_added', 'product_accepted', 'product_rejected', 'product_ordered', 'stock_empty', 'payment_pending', 'payment_successful', 'payment_failed') NULL,
  `is_read` TINYINT NOT NULL DEFAULT 0,
  `from_user_id` BIGINT NULL,
  `to_user_id` BIGINT NULL,
  `media_id` BIGINT NULL,
  `product_id` BIGINT NULL,
  `comment_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_notifications_UNIQUE` (`id` ASC),
  INDEX `fk_notifications_users1_idx` (`from_user_id` ASC),
  INDEX `fk_notifications_users2_idx` (`to_user_id` ASC),
  INDEX `fk_notifications_medias_idx` (`media_id` ASC),
  INDEX `fk_notifications_products_idx` (`product_id` ASC),
  INDEX `fk_notifications_comments_idx` (`comment_id` ASC),
  CONSTRAINT `fk_notifications_users1`
    FOREIGN KEY (`from_user_id`)
    REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_notifications_users2`
    FOREIGN KEY (`to_user_id`)
    REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_notifications_medias`
    FOREIGN KEY (`media_id`)
    REFERENCES `medias` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_notifications_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_notifications_comments`
    FOREIGN KEY (`comment_id`)
    REFERENCES `comments` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `promo_codes`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `promo_codes` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(45) NOT NULL,
  `validity` INT NOT NULL,
  `status` ENUM('active', 'expired') NOT NULL DEFAULT 'expired',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_promocodes_UNIQUE` (`id` ASC),
  INDEX `fk_promocodes_users_idx` (`user_id` ASC),
  UNIQUE INDEX `code_UNIQUE` (`code` ASC),
  CONSTRAINT `fk_promocodes_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `about_subjects`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `about_subjects` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `subject` JSON NULL,
  `subject_description` JSON NOT NULL,
  `status` ENUM('selected', 'rejected') NOT NULL DEFAULT 'rejected',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_aboutsubjects_UNIQUE` (`id` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `about_titles`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `about_titles` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `title` JSON NOT NULL,
  `alias` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `about_subject_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_abouttitles_UNIQUE` (`id` ASC),
  INDEX `fk_abouttitles_aboutsubjects_idx` (`about_subject_id` ASC),
  CONSTRAINT `fk_abouttitles_aboutsubjects`
    FOREIGN KEY (`about_subject_id`)
    REFERENCES `about_subjects` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `blocked_users`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `blocked_users` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `complaint` LONGTEXT NULL,
  `is_unlocked` TINYINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` BIGINT NOT NULL,
  `about_title_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_blockedusers_UNIQUE` (`id` ASC),
  INDEX `fk_blockedusers_users_idx` (`user_id` ASC),
  INDEX `fk_blockedusers_abouttitles_idx` (`about_title_id` ASC),
  CONSTRAINT `fk_blockedusers_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_blockedusers_abouttitles`
    FOREIGN KEY (`about_title_id`)
    REFERENCES `about_titles` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `about_contents`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `about_contents` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `subtitle` JSON NULL,
  `content` JSON NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `about_title_id` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_aboutcontents_UNIQUE` (`id` ASC),
  INDEX `fk_aboutcontents_abouttitles_idx` (`about_title_id` ASC),
  CONSTRAINT `fk_aboutcontents_abouttitles`
    FOREIGN KEY (`about_title_id`)
    REFERENCES `about_titles` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `money_transfers`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `money_transfers` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `has_commission` TINYINT NOT NULL DEFAULT 0,
  `commission_amount` DECIMAL(12,2) NULL,
  `status` ENUM('done', 'failed') NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `payment_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_moneytransfers_UNIQUE` (`id` ASC),
  INDEX `fk_moneytransfers_payments_idx` (`payment_id` ASC),
  CONSTRAINT `fk_moneytransfers_payments`
    FOREIGN KEY (`payment_id`)
    REFERENCES `payments` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `pricings`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pricings` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `pricing_name` JSON NOT NULL,
  `pricing_type` ENUM('money', 'percentage') NOT NULL DEFAULT 'money' COMMENT 'The user must pay directly or pay a commission (percentage) on the payment they receive',
  `reason` ENUM('media_boost', 'ad', 'gift_sent', 'user_certfied') NULL,
  `pricing_cost` DECIMAL(12,2) NULL,
  `currency` VARCHAR(45) NULL,
  `image_url` TEXT NULL,
  `icon` VARCHAR(45) NULL,
  `color` VARCHAR(45) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_pricings_UNIQUE` (`id` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `pricing_descriptions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pricing_descriptions` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `description_title` JSON NOT NULL,
  `description_content` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `pricing_id` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_pricingdescriptions_UNIQUE` (`id` ASC),
  INDEX `fk_pricingdescriptions_pricings_idx` (`pricing_id` ASC),
  CONSTRAINT `fk_pricingdescriptions_pricings`
    FOREIGN KEY (`pricing_id`)
    REFERENCES `pricings` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `histories`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `histories` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `word` TEXT NULL COMMENT 'This refers to a search history of a user',
  `entity` ENUM('media', 'product', 'comment', 'user') NULL,
  `entity_id` BIGINT NULL,
  `action` ENUM('search', 'view', 'play', 'like', 'gift', 'star', 'post', 'comment', 'order', 'report') NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `user_id` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_histories_UNIQUE` (`id` ASC),
  INDEX `fk_histories_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_histories_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `subscriptions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT NOT NULL,
  `follower_id` BIGINT NOT NULL,
  `granted` TINYINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_subscriptions_UNIQUE` (`id` ASC),
  INDEX `fk_subscriptions_users1_idx` (`user_id` ASC),
  INDEX `fk_subscriptions_users2_idx` (`follower_id` ASC),
  CONSTRAINT `fk_subscriptions_users1`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_subscriptions_users2`
    FOREIGN KEY (`follower_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `reasons`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `reasons` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `reason_content` JSON NOT NULL,
  `entity` ENUM('media', 'product', 'user') NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `idreasons_UNIQUE` (`id` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `reports`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `reports` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `entity` ENUM('media', 'product', 'user') NULL,
  `entity_id` BIGINT NULL,
  `report_content` TEXT NULL,
  `muted` TINYINT NOT NULL DEFAULT 0 COMMENT 'This is not a report, just a mute',
  `for_user_id` BIGINT NULL COMMENT 'When a member muted a media, if he does so for a specific user, this column will be useful.',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reason_id` BIGINT NULL,
  `user_id` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_reports_UNIQUE` (`id` ASC),
  INDEX `fk_reports_reasons_idx` (`reason_id` ASC),
  INDEX `fk_reports_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_reports_reasons`
    FOREIGN KEY (`reason_id`)
    REFERENCES `reasons` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_reports_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `hashtags`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `hashtags` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `keyword` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_hashtags_UNIQUE` (`id` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `group_user`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `group_user` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `group_id` BIGINT NOT NULL,
  `user_id` BIGINT NOT NULL,
  `is_admin` TINYINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_groupuser_UNIQUE` (`id` ASC),
  INDEX `fk_groupuser_groups_idx` (`group_id` ASC),
  INDEX `fk_groupuser_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_groupuser_groups`
    FOREIGN KEY (`group_id`)
    REFERENCES `groups` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_groupuser_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `hashtag_media`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `hashtag_media` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `hashtag_id` BIGINT NOT NULL,
  `media_id` BIGINT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_hashtagmedia_UNIQUE` (`id` ASC),
  INDEX `fk_hashtagmedia_hashtags_idx` (`hashtag_id` ASC),
  INDEX `fk_hashtagmedia_medias_idx` (`media_id` ASC),
  CONSTRAINT `fk_hashtagmedia_hashtags`
    FOREIGN KEY (`hashtag_id`)
    REFERENCES `hashtags` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_hashtagmedia_medias`
    FOREIGN KEY (`media_id`)
    REFERENCES `medias` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `category_media`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `category_media` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `category_id` BIGINT NOT NULL,
  `media_id` BIGINT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_categorymedia_UNIQUE` (`id` ASC),
  INDEX `fk_categorymedia_categories_idx` (`category_id` ASC),
  INDEX `fk_categorymedia_medias_idx` (`media_id` ASC),
  CONSTRAINT `fk_categorymedia_categories`
    FOREIGN KEY (`category_id`)
    REFERENCES `categories` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_categorymedia_medias`
    FOREIGN KEY (`media_id`)
    REFERENCES `medias` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `reactions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `reactions` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `type` ENUM('like', 'gift', 'star') NOT NULL,
  `number_of_stars` SMALLINT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `pricing_id` BIGINT NULL,
  `media_id` BIGINT NULL,
  `product_id` BIGINT NULL,
  `comment_id` BIGINT NULL,
  `user_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_reactions_UNIQUE` (`id` ASC),
  INDEX `fk_reactions_pricings_idx` (`pricing_id` ASC),
  INDEX `fk_reactions_medias_idx` (`media_id` ASC),
  INDEX `fk_reactions_products_idx` (`product_id` ASC),
  INDEX `fk_reactions_comments_idx` (`comment_id` ASC),
  INDEX `fk_reactions_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_reactions_pricings`
    FOREIGN KEY (`pricing_id`)
    REFERENCES `pricings` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_reactions_medias`
    FOREIGN KEY (`media_id`)
    REFERENCES `medias` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_reactions_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_reactions_comments`
    FOREIGN KEY (`comment_id`)
    REFERENCES `comments` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_reactions_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `bank_cards`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bank_cards` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `card_name` VARCHAR(255) NULL,
  `card_number` VARCHAR(45) NULL,
  `expiration_date` VARCHAR(45) NULL,
  `cvv_code` VARCHAR(45) NULL,
  `provider` VARCHAR(45) NULL,
  `is_main` TINYINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `user_id` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_bankcards_UNIQUE` (`id` ASC),
  INDEX `fk_bankcards_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_bankcards_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `about_dashes`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `about_dashes` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `dash_content` JSON NOT NULL,
  `belongs_to` BIGINT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `about_content_id` BIGINT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_aboutdashes_UNIQUE` (`id` ASC),
  INDEX `fk_aboutdashes_aboutcontents_idx` (`about_content_id` ASC),
  CONSTRAINT `fk_aboutdashes_aboutcontents`
    FOREIGN KEY (`about_content_id`)
    REFERENCES `about_contents` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `carts`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `carts` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `payment_code` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `user_id` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_carts_UNIQUE` (`id` ASC),
  INDEX `fk_carts_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_carts_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `customer_orders`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `customer_orders` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `price_at_that_time` DECIMAL(12,2) NULL,
  `currency` VARCHAR(45) NULL,
  `quantity` INT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `product_id` BIGINT NOT NULL,
  `cart_id` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_customerorders_UNIQUE` (`id` ASC),
  INDEX `fk_customerorders_products_idx` (`product_id` ASC),
  INDEX `fk_customerorders_carts_idx` (`cart_id` ASC),
  CONSTRAINT `fk_customerorders_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_customerorders_carts`
    FOREIGN KEY (`cart_id`)
    REFERENCES `carts` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `cache`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `cache` (
  `key` VARCHAR(255) NOT NULL,
  `value` MEDIUMTEXT NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`),
  INDEX `cache_expiration_index` (`expiration` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `cache_locks`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` VARCHAR(255) NOT NULL,
  `owner` VARCHAR(255) NOT NULL,
  `expiration` INT NOT NULL,
  PRIMARY KEY (`key`),
  INDEX `cache_locks_expiration_index` (`expiration` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `failed_jobs`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` VARCHAR(255) NOT NULL,
  `connection` TEXT NOT NULL,
  `queue` TEXT NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `exception` LONGTEXT NOT NULL,
  `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `failed_jobs_uuid_unique` (`uuid` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `jobs`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` VARCHAR(255) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `exception` LONGTEXT NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL,
  `reserved_at` INT UNSIGNED NULL,
  `available_at` INT UNSIGNED NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `jobs_queue_index` (`queue` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `job_batches`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `total_jobs` INT NOT NULL,
  `pending_jobs` INT NOT NULL,
  `failed_jobs` INT NOT NULL,
  `failed_job_ids` LONGTEXT NOT NULL,
  `options` MEDIUMTEXT NULL,
  `cancelled_at` INT NULL,
  `created_at` INT NOT NULL,
  `finished_at` INT NULL,
  PRIMARY KEY (`id`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `specifications`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `specifications` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `spec_content` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  `product_id` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_specifications_UNIQUE` (`id` ASC),
  INDEX `fk_specifications_products_idx` (`product_id` ASC),
  CONSTRAINT `fk_specifications_products`
    FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `hashtag_comment`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `hashtag_comment` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `hashtag_id` BIGINT NOT NULL,
  `comment_id` BIGINT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_hashtagcomment_UNIQUE` (`id` ASC),
  INDEX `fk_hashtagcomment_hashtags_idx` (`hashtag_id` ASC),
  INDEX `fk_hashtagcomment_comments_idx` (`comment_id` ASC),
  CONSTRAINT `fk_hashtagcomment_hashtags`
    FOREIGN KEY (`hashtag_id`)
    REFERENCES `hashtags` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_hashtagcomment_comments`
    FOREIGN KEY (`comment_id`)
    REFERENCES `comments` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `media_progresses`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `media_progresses` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `watched_seconds` BIGINT NOT NULL DEFAULT 0,
  `percentage` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `media_id` BIGINT NOT NULL,
  `user_id` BIGINT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_mediaprogresses_UNIQUE` (`id` ASC),
  INDEX `fk_mediaprogresses_medias_idx` (`media_id` ASC),
  INDEX `fk_mediaprogresses_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_mediaprogresses_medias`
    FOREIGN KEY (`media_id`)
    REFERENCES `medias` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_mediaprogresses_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `media_user`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `media_user` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `media_id` BIGINT NOT NULL,
  `user_id` BIGINT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `id_mediauser_UNIQUE` (`id` ASC),
  INDEX `fk_mediauser_medias_idx` (`media_id` ASC),
  INDEX `fk_mediauser_users_idx` (`user_id` ASC),
  CONSTRAINT `fk_mediauser_medias`
    FOREIGN KEY (`media_id`)
    REFERENCES `medias` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_mediauser_users`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;
