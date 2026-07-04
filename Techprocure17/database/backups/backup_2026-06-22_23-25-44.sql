-- TechProcure17 Database Backup
-- Generated: 2026-06-22 23:25:44

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS activity_logs;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO activity_logs VALUES ('1','2','User Registered','buyer',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 10:53:08');
INSERT INTO activity_logs VALUES ('2','2','User Logged In','auth',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 11:03:15');
INSERT INTO activity_logs VALUES ('3','2','Requested Quotation','quotation','1',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 11:33:28');
INSERT INTO activity_logs VALUES ('4','2','User Logged Out','auth',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 11:43:15');
INSERT INTO activity_logs VALUES ('5','2','User Logged In','auth',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 11:43:24');
INSERT INTO activity_logs VALUES ('6','2','User Logged Out','auth',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 11:43:48');
INSERT INTO activity_logs VALUES ('7','2','User Logged In','auth',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 11:44:00');
INSERT INTO activity_logs VALUES ('8','2','User Logged Out','auth',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 11:44:24');
INSERT INTO activity_logs VALUES ('9','5','Admin Registered','user',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 12:23:56');
INSERT INTO activity_logs VALUES ('10','5','User Logged Out','auth',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 19:32:03');
INSERT INTO activity_logs VALUES ('11','5','User Logged Out','auth',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 19:49:04');
INSERT INTO activity_logs VALUES ('12','5','Approved Supplier','supplier','2',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 19:57:14');
INSERT INTO activity_logs VALUES ('13','5','Added Product','product','1',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 20:00:20');
INSERT INTO activity_logs VALUES ('14','5','Approved Product','product','1',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 20:05:10');
INSERT INTO activity_logs VALUES ('15','5','Updated Product','product','1',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 21:44:15');
INSERT INTO activity_logs VALUES ('16','5','Added Product','product','2',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 22:08:44');
INSERT INTO activity_logs VALUES ('17','5','Approved Product','product','2',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 22:09:14');
INSERT INTO activity_logs VALUES ('18','5','User Logged Out','auth',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 22:10:43');
INSERT INTO activity_logs VALUES ('19','5','User Logged In','auth',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 22:10:56');
INSERT INTO activity_logs VALUES ('20','5','Updated Product','product','2',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 22:12:06');
INSERT INTO activity_logs VALUES ('21','5','User Logged Out','auth',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 22:15:09');
INSERT INTO activity_logs VALUES ('22','8','User Registered','buyer',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 22:16:23');
INSERT INTO activity_logs VALUES ('23','9','User Registered','buyer',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 22:19:00');
INSERT INTO activity_logs VALUES ('24','9','User Logged In','auth',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 22:19:16');
INSERT INTO activity_logs VALUES ('25','9','User Logged Out','auth',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-06-17 22:29:49');
INSERT INTO activity_logs VALUES ('26','9','User Logged In','auth',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-18 07:50:31');
INSERT INTO activity_logs VALUES ('27','9','Placed Order','order','1',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-18 09:19:53');
INSERT INTO activity_logs VALUES ('28','9','User Logged Out','auth',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-18 09:38:24');
INSERT INTO activity_logs VALUES ('29','9','User Logged In','auth',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-22 22:06:16');
INSERT INTO activity_logs VALUES ('30','9','Payment Made','payment','1',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-22 22:27:26');
INSERT INTO activity_logs VALUES ('31','9','User Logged Out','auth',NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-22 22:31:24');
INSERT INTO activity_logs VALUES ('32','5','Approved Supplier','supplier','1',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-22 22:34:34');
INSERT INTO activity_logs VALUES ('33','5','Updated Order Status','order','1',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-22 22:42:45');
INSERT INTO activity_logs VALUES ('34','5','Refunded Payment','payment','1',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-22 22:47:05');
INSERT INTO activity_logs VALUES ('35','5','Updated Order Status','order','1',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-22 22:48:02');
INSERT INTO activity_logs VALUES ('36','5','Updated Order Status','order','1',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-22 22:48:27');
INSERT INTO activity_logs VALUES ('37','5','Updated Order Status','order','1',NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-22 23:16:14');

DROP TABLE IF EXISTS api_logs;
CREATE TABLE `api_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_key` varchar(100) DEFAULT NULL,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_data`)),
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_data`)),
  `status_code` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `response_time_ms` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_api_key` (`api_key`),
  KEY `idx_endpoint` (`endpoint`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS blocked_entities;
CREATE TABLE `blocked_entities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` enum('ip','user','email') NOT NULL,
  `entity_value` varchar(255) NOT NULL,
  `reason` text DEFAULT NULL,
  `blocked_by` int(11) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `blocked_by` (`blocked_by`),
  KEY `idx_entity` (`entity_type`,`entity_value`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `blocked_entities_ibfk_1` FOREIGN KEY (`blocked_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS blog_posts;
CREATE TABLE `blog_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext DEFAULT NULL,
  `excerpt` text DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `author_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `views` int(11) DEFAULT 0,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `author_id` (`author_id`),
  KEY `category_id` (`category_id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_published` (`published_at`),
  CONSTRAINT `blog_posts_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blog_posts_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS bulk_discounts;
CREATE TABLE `bulk_discounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `min_quantity` int(11) NOT NULL,
  `max_quantity` int(11) DEFAULT NULL,
  `discount_percent` decimal(5,2) NOT NULL,
  `fixed_price` decimal(15,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tier` (`product_id`,`min_quantity`),
  KEY `idx_product` (`product_id`),
  KEY `idx_quantity` (`min_quantity`,`max_quantity`),
  CONSTRAINT `bulk_discounts_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS cart_items;
CREATE TABLE `cart_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cart_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(15,2) NOT NULL,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `added_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `variant_id` (`variant_id`),
  KEY `idx_cart` (`cart_id`),
  CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `cart_items_ibfk_3` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS carts;
CREATE TABLE `carts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_session` (`session_id`),
  CONSTRAINT `carts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS categories;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon_class` varchar(50) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `parent_id` (`parent_id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_status` (`status`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO categories VALUES ('1','Computers','computers','Desktop computers and workstations','fa-desktop',NULL,'1','active','2026-06-16 12:08:44',NULL);
INSERT INTO categories VALUES ('2','Laptops','laptops','Laptops and notebooks','fa-laptop',NULL,'2','active','2026-06-16 12:08:44',NULL);
INSERT INTO categories VALUES ('3','Servers','servers','Enterprise servers and racks','fa-server',NULL,'3','active','2026-06-16 12:08:44',NULL);
INSERT INTO categories VALUES ('4','Networking','networking','Switches, routers, and networking equipment','fa-network-wired',NULL,'4','active','2026-06-16 12:08:44',NULL);
INSERT INTO categories VALUES ('5','Software','software','Software licenses and solutions','fa-code',NULL,'5','active','2026-06-16 12:08:44',NULL);
INSERT INTO categories VALUES ('6','Storage','storage','Storage devices and solutions','fa-database',NULL,'6','active','2026-06-16 12:08:44',NULL);
INSERT INTO categories VALUES ('7','Accessories','accessories','Computer accessories and peripherals','fa-keyboard',NULL,'7','active','2026-06-16 12:08:44',NULL);
INSERT INTO categories VALUES ('8','Printers','printers','Printers, scanners, and copiers','fa-print',NULL,'8','active','2026-06-16 12:08:44',NULL);

DROP TABLE IF EXISTS contact_messages;
CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `replied` tinyint(1) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS daily_sales_summary;
CREATE TABLE `daily_sales_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `summary_date` date NOT NULL,
  `total_orders` int(11) DEFAULT 0,
  `total_revenue` decimal(15,2) DEFAULT 0.00,
  `total_discounts` decimal(15,2) DEFAULT 0.00,
  `total_savings` decimal(15,2) DEFAULT 0.00,
  `unique_customers` int(11) DEFAULT 0,
  `unique_suppliers` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `summary_date` (`summary_date`),
  KEY `idx_date` (`summary_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS dispute_messages;
CREATE TABLE `dispute_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dispute_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_dispute` (`dispute_id`),
  CONSTRAINT `dispute_messages_ibfk_1` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dispute_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS disputes;
CREATE TABLE `disputes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dispute_number` varchar(50) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `type` enum('item_not_delivered','damaged_item','wrong_item','quality_issue','payment_issue','other') NOT NULL,
  `description` text NOT NULL,
  `amount_claimed` decimal(15,2) DEFAULT NULL,
  `evidence_files` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`evidence_files`)),
  `status` enum('open','investigating','resolved','closed','refunded') DEFAULT 'open',
  `resolution_notes` text DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `dispute_number` (`dispute_number`),
  KEY `user_id` (`user_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `resolved_by` (`resolved_by`),
  KEY `idx_dispute_number` (`dispute_number`),
  KEY `idx_order` (`order_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `disputes_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `disputes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `disputes_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `disputes_ibfk_4` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS email_queue;
CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(200) DEFAULT NULL,
  `subject` varchar(500) NOT NULL,
  `body` text NOT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `priority` int(11) DEFAULT 0,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS email_verifications;
CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `idx_token` (`token`),
  CONSTRAINT `email_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS escrow_payments;
CREATE TABLE `escrow_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` enum('pending','released','refunded','disputed') DEFAULT 'pending',
  `release_date` datetime DEFAULT NULL,
  `refund_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `escrow_payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO escrow_payments VALUES ('1','1','24534560.00','released','2026-06-22 23:16:14','2026-06-22 22:47:05','2026-06-18 09:19:52','2026-06-22 23:16:14');

DROP TABLE IF EXISTS escrow_transactions;
CREATE TABLE `escrow_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `escrow_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `performed_by` (`performed_by`),
  KEY `idx_escrow` (`escrow_id`),
  CONSTRAINT `escrow_transactions_ibfk_1` FOREIGN KEY (`escrow_id`) REFERENCES `escrow_payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `escrow_transactions_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS global_discount_tiers;
CREATE TABLE `global_discount_tiers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `min_quantity` int(11) NOT NULL,
  `max_quantity` int(11) DEFAULT NULL,
  `discount_percent` decimal(5,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_quantity` (`min_quantity`,`max_quantity`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO global_discount_tiers VALUES ('1','5','9','3.00','1','2026-06-16 12:08:50');
INSERT INTO global_discount_tiers VALUES ('2','10','49','8.00','1','2026-06-16 12:08:50');
INSERT INTO global_discount_tiers VALUES ('3','50','199','15.00','1','2026-06-16 12:08:50');
INSERT INTO global_discount_tiers VALUES ('4','200',NULL,'25.00','1','2026-06-16 12:08:50');

DROP TABLE IF EXISTS invoices;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
  `pdf_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `user_id` (`user_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_order` (`order_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `invoices_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO invoices VALUES ('1','INV-20260618-0135','1','9','2','2026-06-18','2026-07-18','22600000.00','3742560.00','0.00','24534560.00','0.00','draft',NULL,NULL,'9','2026-06-18 09:19:52',NULL);

DROP TABLE IF EXISTS login_attempts;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime DEFAULT current_timestamp(),
  `was_successful` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_email` (`email`),
  KEY `idx_time` (`attempt_time`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO login_attempts VALUES ('1','zeno1@gmail.com','::1','2026-06-17 09:23:13','0');
INSERT INTO login_attempts VALUES ('2','zeno1@gmail.com','::1','2026-06-17 09:23:21','0');
INSERT INTO login_attempts VALUES ('3','zeno2','::1','2026-06-17 10:14:43','0');
INSERT INTO login_attempts VALUES ('4','heroku001@gmail.com','::1','2026-06-17 10:46:45','0');
INSERT INTO login_attempts VALUES ('5','heroku001@gmail.com','::1','2026-06-17 10:51:08','0');
INSERT INTO login_attempts VALUES ('6','zeno2','::1','2026-06-17 10:53:21','0');
INSERT INTO login_attempts VALUES ('7','zeno2','::1','2026-06-17 11:01:09','0');
INSERT INTO login_attempts VALUES ('8','zeno2','::1','2026-06-17 11:01:37','0');
INSERT INTO login_attempts VALUES ('9','heroku1','::1','2026-06-17 11:02:03','0');
INSERT INTO login_attempts VALUES ('10','heroku','::1','2026-06-17 11:47:44','0');
INSERT INTO login_attempts VALUES ('11','heroku','::1','2026-06-17 11:48:21','0');
INSERT INTO login_attempts VALUES ('12','admin@techprocure.co.tz','::1','2026-06-17 11:53:18','0');
INSERT INTO login_attempts VALUES ('13','admin@techprocure.co.tz','::1','2026-06-17 11:53:46','0');
INSERT INTO login_attempts VALUES ('14','admin@techprocure.co.tz','::1','2026-06-17 11:55:52','0');
INSERT INTO login_attempts VALUES ('15','admin@techprocure.co.tz','::1','2026-06-17 11:56:24','0');
INSERT INTO login_attempts VALUES ('16','admin','::1','2026-06-17 11:57:30','0');
INSERT INTO login_attempts VALUES ('17','admin@techprocure.co.tz','::1','2026-06-17 12:02:23','0');
INSERT INTO login_attempts VALUES ('18','admin@techprocure.co.tz','::1','2026-06-17 12:12:32','0');
INSERT INTO login_attempts VALUES ('19','heroku01@gmail.com','::1','2026-06-17 19:53:28','0');
INSERT INTO login_attempts VALUES ('20','customer@techprocure.com','::1','2026-06-17 22:15:25','0');
INSERT INTO login_attempts VALUES ('21','heroku12@gmail.com','::1','2026-06-17 22:16:53','0');
INSERT INTO login_attempts VALUES ('22','heroku12@gmail.com','::1','2026-06-17 22:17:16','0');

DROP TABLE IF EXISTS monthly_sales_summary;
CREATE TABLE `monthly_sales_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `summary_month` date NOT NULL,
  `total_orders` int(11) DEFAULT 0,
  `total_revenue` decimal(15,2) DEFAULT 0.00,
  `total_discounts` decimal(15,2) DEFAULT 0.00,
  `total_savings` decimal(15,2) DEFAULT 0.00,
  `unique_customers` int(11) DEFAULT 0,
  `unique_suppliers` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `summary_month` (`summary_month`),
  KEY `idx_month` (`summary_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS notifications;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_read` (`is_read`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO notifications VALUES ('1','9','order','Order Placed','Your order ORD-20260618-1096 has been placed successfully.','customer/orders/order-details.php?id=1','0',NULL,'2026-06-18 09:19:53');
INSERT INTO notifications VALUES ('2','9','payment','Payment Successful','Your payment of TSh 24,534,560.00 for order ORD-20260618-1096 has been processed successfully.','../customer/orders/order-details.php?id=1','0',NULL,'2026-06-22 22:27:26');
INSERT INTO notifications VALUES ('3','9','order','Order Status Updated','Your order ORD-20260618-1096 has been updated to Completed','../customer/orders/order-details.php?id=1','0',NULL,'2026-06-22 23:16:14');

DROP TABLE IF EXISTS order_items;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `total_price` decimal(15,2) NOT NULL,
  `product_name_snapshot` varchar(255) DEFAULT NULL,
  `specifications_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specifications_snapshot`)),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `variant_id` (`variant_id`),
  KEY `idx_order` (`order_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO order_items VALUES ('1','1','1',NULL,'18','1200000.00','0.00','21600000.00',NULL,NULL,'2026-06-18 09:19:52');
INSERT INTO order_items VALUES ('2','1','2',NULL,'2','500000.00','0.00','1000000.00',NULL,NULL,'2026-06-18 09:19:52');

DROP TABLE IF EXISTS order_tracking;
CREATE TABLE `order_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_order` (`order_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `order_tracking_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_tracking_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO order_tracking VALUES ('1','1','pending',NULL,'Order placed successfully',NULL,'2026-06-18 09:19:52');
INSERT INTO order_tracking VALUES ('2','1','completed',NULL,'Order status updated to Completed',NULL,'2026-06-22 22:42:45');
INSERT INTO order_tracking VALUES ('3','1','pending',NULL,'Order status updated to Pending',NULL,'2026-06-22 22:48:02');
INSERT INTO order_tracking VALUES ('4','1','completed',NULL,'Order status updated to Completed',NULL,'2026-06-22 22:48:27');
INSERT INTO order_tracking VALUES ('5','1','completed',NULL,'Order status updated to Completed',NULL,'2026-06-22 23:16:14');

DROP TABLE IF EXISTS orders;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `quotation_id` int(11) DEFAULT NULL,
  `quotation_response_id` int(11) DEFAULT NULL,
  `po_number` varchar(100) DEFAULT NULL,
  `order_status` enum('pending','confirmed','processing','shipped','delivered','completed','cancelled') DEFAULT 'pending',
  `payment_status` enum('pending','paid','failed','refunded','partial') DEFAULT 'pending',
  `subtotal` decimal(15,2) NOT NULL,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `shipping_cost` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `total_savings` decimal(15,2) DEFAULT 0.00,
  `currency` varchar(3) DEFAULT 'TSh',
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `estimated_delivery` date DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `quotation_id` (`quotation_id`),
  KEY `quotation_response_id` (`quotation_response_id`),
  KEY `idx_order_number` (`order_number`),
  KEY `idx_user` (`user_id`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_status` (`order_status`,`payment_status`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_ibfk_4` FOREIGN KEY (`quotation_response_id`) REFERENCES `quotation_responses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO orders VALUES ('1','ORD-20260618-1096','9',NULL,NULL,NULL,NULL,'completed','refunded','22600000.00','1808000.00','3742560.00','0.00','24534560.00','0.00','TSh','mpesa','TXN-20260622-2342','kk','kk','001',NULL,NULL,NULL,NULL,'','','2026-06-18 09:19:52','2026-06-22 23:16:14');

DROP TABLE IF EXISTS password_resets;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_token` (`token`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS payment_logs;
CREATE TABLE `payment_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `status` enum('pending','success','failed','refunded') DEFAULT 'pending',
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_data`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_reference` (`reference`),
  KEY `idx_status` (`status`),
  CONSTRAINT `payment_logs_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS payment_methods;
CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `method_name` varchar(50) NOT NULL,
  `method_code` varchar(20) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `additional_fee_percent` decimal(5,2) DEFAULT 0.00,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `method_name` (`method_name`),
  UNIQUE KEY `method_code` (`method_code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO payment_methods VALUES ('1','M-Pesa','MPESA','1','0.00',NULL,'2026-06-16 12:08:56');
INSERT INTO payment_methods VALUES ('2','Airtel Money','AIRTEL','1','0.00',NULL,'2026-06-16 12:08:56');
INSERT INTO payment_methods VALUES ('3','Tigo Pesa','TIGO','1','0.00',NULL,'2026-06-16 12:08:56');
INSERT INTO payment_methods VALUES ('4','Halopesa','HALOPESA','1','0.00',NULL,'2026-06-16 12:08:56');
INSERT INTO payment_methods VALUES ('5','Bank Transfer','BANK','1','0.00',NULL,'2026-06-16 12:08:56');
INSERT INTO payment_methods VALUES ('6','Credit/Debit Card','CARD','1','0.00',NULL,'2026-06-16 12:08:56');

DROP TABLE IF EXISTS payments;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(50) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `payment_proof` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `refund_amount` decimal(15,2) DEFAULT 0.00,
  `refund_reason` text DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_number` (`payment_number`),
  KEY `user_id` (`user_id`),
  KEY `payment_method_id` (`payment_method_id`),
  KEY `processed_by` (`processed_by`),
  KEY `idx_payment_number` (`payment_number`),
  KEY `idx_order` (`order_id`),
  KEY `idx_status` (`payment_status`),
  KEY `idx_transaction` (`transaction_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`),
  CONSTRAINT `payments_ibfk_4` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO payments VALUES ('1','PAY-20260622-8595','1','9','1','24534560.00','TXN-20260622-2342','refunded',NULL,NULL,NULL,'2026-06-22 22:27:26','24534560.00',NULL,'2026-06-22 22:47:05','2026-06-22 22:27:26');

DROP TABLE IF EXISTS product_images;
CREATE TABLE `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_primary` (`is_primary`),
  CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO product_images VALUES ('1','1','uploads/products/1/product_1781715620_0.png',NULL,'1','0','2026-06-17 20:00:20');
INSERT INTO product_images VALUES ('2','2','uploads/products/2/product_1781723324_0.png',NULL,'1','0','2026-06-17 22:08:44');

DROP TABLE IF EXISTS product_specifications;
CREATE TABLE `product_specifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `spec_name` varchar(100) NOT NULL,
  `spec_value` text NOT NULL,
  `spec_unit` varchar(20) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `product_specifications_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS product_variants;
CREATE TABLE `product_variants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `variant_sku` varchar(50) NOT NULL,
  `variant_name` varchar(200) DEFAULT NULL,
  `specifications` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specifications`)),
  `price_adjustment` decimal(10,2) DEFAULT 0.00,
  `stock_quantity` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `variant_sku` (`variant_sku`),
  KEY `idx_product` (`product_id`),
  KEY `idx_sku` (`variant_sku`),
  CONSTRAINT `product_variants_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS products;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `sku` varchar(50) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `price_tsh` decimal(15,2) NOT NULL,
  `compare_price_tsh` decimal(15,2) DEFAULT NULL,
  `bulk_price_tsh` decimal(15,2) DEFAULT NULL,
  `bulk_min_quantity` int(11) DEFAULT 10,
  `min_order_quantity` int(11) DEFAULT 1,
  `stock_quantity` int(11) DEFAULT 0,
  `stock_status` enum('in_stock','out_of_stock','pre_order','discontinued') DEFAULT 'out_of_stock',
  `weight` decimal(10,2) DEFAULT NULL,
  `dimensions` varchar(100) DEFAULT NULL,
  `warranty_months` int(11) DEFAULT 12,
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `views` int(11) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `status` enum('active','inactive','draft') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `sku` (`sku`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`status`,`approval_status`),
  KEY `idx_featured` (`is_featured`),
  KEY `idx_price` (`price_tsh`),
  FULLTEXT KEY `idx_search` (`product_name`,`description`,`brand`,`sku`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO products VALUES ('1','2','1','hp elitebook','hp-elitebook','for heavy duties like gaming','high quality','001','HP','1200000.00','0.00','0.00','11','1','-16','out_of_stock','2.00','2x2x5','12','0.00','0','15','1','approved','active','5','2026-06-17 20:00:20','2026-06-18 09:19:52','5','2026-06-17 20:05:10');
INSERT INTO products VALUES ('2','2','4','Router','router','very strong and fiderity','high quality','002','Cisco','500000.00','0.00','550000.00','10','5','18','out_of_stock','1.50','2x2x5','6','0.00','0','2','1','approved','active','5','2026-06-17 22:08:44','2026-06-18 09:19:52','5','2026-06-17 22:09:13');

DROP TABLE IF EXISTS quotation_responses;
CREATE TABLE `quotation_responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `response_number` varchar(50) NOT NULL,
  `quote_message` text DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `shipping_cost` decimal(15,2) DEFAULT 0.00,
  `delivery_timeframe` varchar(100) DEFAULT NULL,
  `validity_days` int(11) DEFAULT 30,
  `status` enum('draft','submitted','accepted','rejected','expired') DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `response_number` (`response_number`),
  KEY `reviewed_by` (`reviewed_by`),
  KEY `idx_quotation` (`quotation_id`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `quotation_responses_ibfk_1` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quotation_responses_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `quotation_responses_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS quotations;
CREATE TABLE `quotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_number` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `products` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`products`)),
  `delivery_location` text DEFAULT NULL,
  `delivery_deadline` date DEFAULT NULL,
  `budget_min` decimal(15,2) DEFAULT NULL,
  `budget_max` decimal(15,2) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `special_requirements` text DEFAULT NULL,
  `status` enum('draft','open','closed','cancelled','awarded') DEFAULT 'draft',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `quotation_number` (`quotation_number`),
  KEY `idx_quotation_number` (`quotation_number`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `quotations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO quotations VALUES ('1','QTN-20260617-3356','2','laptops','heavy','{\"name\":\"laptop\",\"quantity\":5,\"category_id\":2}','mwanza','2026-07-02','1200000.00','0.00','On Delivery','ssd','open','2026-06-17 11:33:28',NULL);

DROP TABLE IF EXISTS roles;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `role_description` text DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO roles VALUES ('1','admin','Full system administrator with all permissions','{\"all\": true}','1','2026-06-16 12:08:41',NULL);
INSERT INTO roles VALUES ('2','supplier','Supplier who sells products on the platform','{\"manage_products\": true, \"view_orders\": true, \"send_quotations\": true}','1','2026-06-16 12:08:41',NULL);
INSERT INTO roles VALUES ('3','customer','Customer who buys products on the platform','{\"purchase_products\": true, \"view_orders\": true, \"request_quotations\": true}','1','2026-06-16 12:08:41',NULL);

DROP TABLE IF EXISTS sms_queue;
CREATE TABLE `sms_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_phone` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS supplier_bank_accounts;
CREATE TABLE `supplier_bank_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `account_name` varchar(200) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_supplier` (`supplier_id`),
  CONSTRAINT `supplier_bank_accounts_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS supplier_reviews;
CREATE TABLE `supplier_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `review_text` text DEFAULT NULL,
  `response_text` text DEFAULT NULL,
  `response_by` int(11) DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_rating` (`rating`),
  CONSTRAINT `supplier_reviews_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `supplier_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS suppliers;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(200) NOT NULL,
  `company_logo` varchar(255) DEFAULT NULL,
  `registration_number` varchar(100) DEFAULT NULL,
  `tax_id` varchar(100) DEFAULT NULL,
  `contact_person` varchar(200) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `business_description` text DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected','suspended') DEFAULT 'pending',
  `verification_badge` tinyint(1) DEFAULT 0,
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_sales` int(11) DEFAULT 0,
  `total_reviews` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_company` (`company_name`),
  KEY `idx_approval` (`approval_status`),
  KEY `idx_status` (`status`),
  KEY `idx_rating` (`rating`),
  CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO suppliers VALUES ('1','6','vicking',NULL,'122','0.18','yenu','zeno1@gmail.com','+255760211221','kk','Dar es Salaam','Dar es Salaam','good service','approved','1','0.00','0','0','active','2026-06-17 19:50:59','2026-06-22 22:34:33');
INSERT INTO suppliers VALUES ('2','7','vicking',NULL,'122','0.18','heroku ic','heroku11@gmail.com','+255760211221','kk','Dar es Salaam','Dar es Salaam','good products','approved','1','0.00','0','0','active','2026-06-17 19:55:34','2026-06-17 19:57:14');

DROP TABLE IF EXISTS system_settings;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','json','file') DEFAULT 'text',
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_key` (`setting_key`),
  CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO system_settings VALUES ('1','site_name','TechProcure Tanzania','text','Platform name',NULL,NULL,'2026-06-16 12:09:05');
INSERT INTO system_settings VALUES ('2','site_email','info@techprocure.co.tz','text','Default support email',NULL,NULL,'2026-06-16 12:09:05');
INSERT INTO system_settings VALUES ('3','site_phone','+255 123 456 789','text','Default contact phone',NULL,NULL,'2026-06-16 12:09:05');
INSERT INTO system_settings VALUES ('4','tax_rate','18.00','number','Default tax rate percentage',NULL,NULL,'2026-06-16 12:09:05');
INSERT INTO system_settings VALUES ('5','currency','TSh','text','Default currency',NULL,NULL,'2026-06-16 12:09:05');
INSERT INTO system_settings VALUES ('6','currency_symbol','TSh','text','Currency symbol',NULL,NULL,'2026-06-16 12:09:05');
INSERT INTO system_settings VALUES ('7','commission_rate','2.50','number','Commission rate for suppliers',NULL,NULL,'2026-06-16 12:09:05');
INSERT INTO system_settings VALUES ('8','escrow_enabled','true','boolean','Enable escrow payment system',NULL,NULL,'2026-06-16 12:09:05');
INSERT INTO system_settings VALUES ('9','bulk_discount_enabled','true','boolean','Enable bulk discount system',NULL,NULL,'2026-06-16 12:09:05');
INSERT INTO system_settings VALUES ('10','rfq_enabled','true','boolean','Enable RFQ system',NULL,NULL,'2026-06-16 12:09:05');
INSERT INTO system_settings VALUES ('11','maintenance_mode','false','boolean','Put site in maintenance mode',NULL,NULL,'2026-06-16 12:09:05');
INSERT INTO system_settings VALUES ('12','allow_registration','true','boolean','Allow new user registration',NULL,NULL,'2026-06-16 12:09:05');
INSERT INTO system_settings VALUES ('13','email_verification_required','false','boolean','Require email verification',NULL,NULL,'2026-06-16 12:09:05');
INSERT INTO system_settings VALUES ('14','max_cart_items','100','number','Maximum items per cart',NULL,NULL,'2026-06-16 12:09:05');
INSERT INTO system_settings VALUES ('15','min_order_amount','0','number','Minimum order amount',NULL,NULL,'2026-06-16 12:09:05');
INSERT INTO system_settings VALUES ('16','free_shipping_threshold','100000','number','Free shipping threshold',NULL,NULL,'2026-06-16 12:09:05');
INSERT INTO system_settings VALUES ('17','delivery_days','7','number','Default delivery days',NULL,NULL,'2026-06-16 12:09:05');

DROP TABLE IF EXISTS user_sessions;
CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `user_id` (`user_id`),
  KEY `idx_token` (`session_token`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO user_sessions VALUES ('1','2','d4dd3fd69c9da42895c29fe3b37206f5e59675fa83689c15219d7dd95041d34c',NULL,NULL,'2026-06-24 11:43:59','2026-06-17 11:43:59');
INSERT INTO user_sessions VALUES ('2','9','ae839d382da11e8a80a16658c013f8dab0cdfa8af05229379e30faa70dff6975',NULL,NULL,'2026-06-25 07:50:29','2026-06-18 07:50:29');

DROP TABLE IF EXISTS user_settings;
CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `sms_notifications` tinyint(1) DEFAULT 0,
  `order_updates` tinyint(1) DEFAULT 1,
  `promotional_emails` tinyint(1) DEFAULT 0,
  `language` varchar(10) DEFAULT 'en',
  `currency` varchar(10) DEFAULT 'TSh',
  `timezone` varchar(50) DEFAULT 'Africa/Dar_es_Salaam',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS users;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(200) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `company_name` varchar(200) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `tin_number` varchar(50) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `user_type` enum('customer','supplier','admin') DEFAULT 'customer',
  `status` enum('active','inactive','pending','suspended') DEFAULT 'active',
  `is_active` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) DEFAULT 0,
  `phone_verified` tinyint(1) DEFAULT 0,
  `profile_image` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_user_type` (`user_type`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO users VALUES ('2','heroku','heroku001@gmail.com','$2y$10$v.F54agSDtidns6hVigPDueiEjrAzAdiNe4nkcBxK4tUUfiPC7G3K','heroku ic',NULL,NULL,'+255760211221','vicking','kk','Dar es Salaam',NULL,NULL,'3','customer','active','1','0','0',NULL,'2026-06-17 11:43:59','::1','2026-06-17 10:53:08','2026-06-17 11:43:59');
INSERT INTO users VALUES ('4','admin','admin@techprocure.co.tz','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','System Administrator','System','Administrator','+255760211221','TechProcure Tanzania',NULL,NULL,NULL,NULL,'1','admin','active','1','1','0',NULL,NULL,NULL,'2026-06-17 12:11:00','2026-06-17 12:11:56');
INSERT INTO users VALUES ('5','yeye','heroku01@gmail.com','$2y$10$A.cZkta9vCZnIrUiKOo69euJF5.0g.RsyXCHEfmhweJm3TtnDX//2','yenu',NULL,NULL,'+255760211221',NULL,NULL,NULL,NULL,NULL,'1','admin','active','1','1','0',NULL,'2026-06-22 22:34:17','::1','2026-06-17 12:23:56','2026-06-22 22:34:17');
INSERT INTO users VALUES ('6','zeno1971','zeno1@gmail.com','$2y$10$VN4isZehjt01p9HZ7IuYbO6ibNWYMqpjMnLRj.9L1fpFN7XMpsRKm','yenu',NULL,NULL,'+255760211221','vicking',NULL,NULL,NULL,NULL,'2','supplier','active','1','0','0',NULL,NULL,NULL,'2026-06-17 19:50:59','2026-06-22 22:34:33');
INSERT INTO users VALUES ('7','heroku11854','heroku11@gmail.com','$2y$10$dUrw5n8yC1pOxZX2HZT.GelWY91DTkEZ4lSDkvpbKZf7Ax8e.mhbC','heroku ic',NULL,NULL,'+255760211221','vicking',NULL,NULL,NULL,NULL,'2','supplier','active','1','0','0',NULL,NULL,NULL,'2026-06-17 19:55:34','2026-06-17 19:57:14');
INSERT INTO users VALUES ('8','heroku01@gmail.com','zeno12@gmail.com','$2y$10$ideDozGZJIiHcbb6npzDUu7gK6C8g8JtXzEM9Ffr7LvEYwlQ7oTtC','yenu',NULL,NULL,'+255760211221','vicking','kk','Dar es Salaam',NULL,NULL,'3','customer','active','1','0','0',NULL,NULL,NULL,'2026-06-17 22:16:23',NULL);
INSERT INTO users VALUES ('9','heroku00','heroku0001@gmail.com','$2y$10$KgEzbsABCTi47l9dlsS2i.xDw16oocu0uRnnDh5mSrrDAFWzNkrpK','herok',NULL,NULL,'+255760211221','vicking','kk','Dar es Salaam',NULL,NULL,'3','customer','active','1','0','0',NULL,'2026-06-22 22:06:16','::1','2026-06-17 22:19:00','2026-06-22 22:06:16');

DROP TABLE IF EXISTS v_active_products;
;

INSERT INTO v_active_products VALUES ('1','001','hp elitebook','hp-elitebook','high quality','1200000.00','0.00','-16','1','0.00','0','Computers','computers','vicking','1','0.00','uploads/products/1/product_1781715620_0.png');
INSERT INTO v_active_products VALUES ('2','002','Router','router','high quality','500000.00','550000.00','18','5','0.00','0','Networking','networking','vicking','1','0.00','uploads/products/2/product_1781723324_0.png');

DROP TABLE IF EXISTS v_order_summary;
;

INSERT INTO v_order_summary VALUES ('1','ORD-20260618-1096','completed','refunded','24534560.00','2026-06-18 09:19:52','herok','heroku0001@gmail.com','vicking',NULL,'2','20');

DROP TABLE IF EXISTS v_pending_suppliers;
;

DROP TABLE IF EXISTS wishlist;
CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_wishlist` (`user_id`,`product_id`),
  KEY `product_id` (`product_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS=1;
