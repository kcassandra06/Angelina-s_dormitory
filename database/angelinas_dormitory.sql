-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: angelinas_dormitory
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `announcements`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `expires_on` date DEFAULT NULL,
  `active` tinyint(4) DEFAULT 1,
  `created_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;

--
-- Table structure for table `audit_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `record_type` varchar(80) DEFAULT NULL,
  `record_id` int(10) unsigned DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,NULL,'System initialized',NULL,NULL,'Initial rooms and clearly labeled demonstration tenant created.','2026-10-07 13:39:35'),(2,1,'Logged in','',NULL,'','2026-10-07 13:46:27'),(3,1,'Changed own password','',NULL,'','2026-10-07 13:47:44'),(4,1,'Prepared overdue reminders','',NULL,'','2026-10-07 13:48:48'),(5,1,'Logged out','',NULL,'','2026-10-07 13:49:01'),(6,2,'Logged in','',NULL,'','2026-10-07 13:49:18'),(7,2,'Changed own password','',NULL,'','2026-10-07 13:49:42'),(8,2,'Logged out','',NULL,'','2026-10-07 13:49:51'),(9,3,'Logged in','',NULL,'','2026-10-07 13:50:13'),(10,3,'Changed own password','',NULL,'','2026-10-07 13:50:35'),(11,3,'Logged out','',NULL,'','2026-10-07 13:50:51'),(12,1,'Logged in','',NULL,'','2026-10-07 14:20:36'),(13,1,'Logged out','',NULL,'','2026-10-07 14:23:16'),(14,2,'Logged in','',NULL,'','2026-10-07 14:23:26'),(15,2,'Logged out','',NULL,'','2026-10-07 14:23:43'),(16,3,'Logged in','',NULL,'','2026-10-07 14:23:56'),(17,3,'Logged out','',NULL,'','2026-10-07 14:24:12'),(18,NULL,'Tenant self-registered','tenants',2,'','2026-10-07 14:37:05'),(19,4,'Logged in','',NULL,'','2026-10-07 14:37:12'),(20,4,'Logged out','',NULL,'','2026-10-07 14:37:48'),(21,3,'Logged in','',NULL,'','2026-10-07 14:38:03'),(22,3,'Logged out','',NULL,'','2026-10-07 14:38:24'),(23,1,'Logged in','',NULL,'','2026-10-07 14:38:35'),(24,1,'Logged out','',NULL,'','2026-10-07 14:38:53'),(25,4,'Logged in','',NULL,'','2026-10-07 14:39:03'),(26,4,'Logged out','',NULL,'','2026-10-07 14:39:23'),(27,1,'Logged in','',NULL,'','2026-10-07 14:39:34'),(28,1,'Recorded security deposit','deposits',2,'','2026-10-07 14:40:11'),(29,1,'Assigned room to registered tenant','tenants',2,'','2026-10-07 14:40:28'),(30,1,'Logged out','',NULL,'','2026-10-07 14:40:42'),(31,4,'Logged in','',NULL,'','2026-10-07 14:40:55'),(32,4,'Logged out','',NULL,'','2026-10-07 15:03:49'),(33,1,'Logged in','',NULL,'','2026-10-07 15:04:00'),(34,1,'Created billing statement','billing',2,'Previous balance ₱0.00','2026-10-07 15:04:53'),(35,1,'Generated monthly billing batch','billing',NULL,'1 created; 0 skipped (existing/newer statements).','2026-10-07 15:04:53'),(36,1,'Generated monthly billing batch','billing',NULL,'0 created; 1 skipped (existing/newer statements).','2026-10-07 15:05:09'),(37,1,'Logged out','',NULL,'','2026-10-07 15:05:19'),(38,4,'Logged in','',NULL,'','2026-10-07 15:18:21'),(39,4,'Tenant submitted GCash payment for verification','payment_submissions',1,'','2026-10-07 15:18:51'),(40,4,'Logged out','',NULL,'','2026-10-07 15:19:41'),(41,2,'Logged in','',NULL,'','2026-10-07 15:19:58'),(42,2,'Verified submitted payment and generated receipt','payments',1,'','2026-10-07 15:21:05'),(43,2,'Reviewed tenant payment submission','payment_submissions',1,'approved · ','2026-10-07 15:21:05'),(44,2,'Logged out','',NULL,'','2026-10-07 15:21:18'),(45,1,'Logged in','',NULL,'','2026-10-07 15:21:28'),(46,1,'Logged out','',NULL,'','2026-10-07 15:39:20'),(47,4,'Logged in','',NULL,'','2026-10-07 15:39:33'),(48,4,'Tenant sent inquiry','tenant_inquiries',1,'','2026-10-07 15:40:33'),(49,4,'Logged out','',NULL,'','2026-10-07 15:40:44'),(50,1,'Logged in','',NULL,'','2026-10-07 15:40:57'),(51,1,'Updated tenant payment instructions','',NULL,'','2026-10-07 15:42:29'),(52,1,'Updated dormitory settings','',NULL,'','2026-10-07 15:42:56'),(53,1,'Created billing statement','billing',3,'Previous balance ₱0.00','2026-10-07 16:04:22'),(54,1,'Logged out','',NULL,'','2026-10-07 16:04:49'),(55,4,'Logged in','',NULL,'','2026-10-07 16:05:06'),(56,4,'Logged out','',NULL,'','2026-10-07 16:05:58'),(57,NULL,'Tenant self-registered','tenants',3,'','2026-10-07 16:07:16'),(58,5,'Logged in','',NULL,'','2026-10-07 16:07:22'),(59,5,'Logged out','',NULL,'','2026-10-07 16:07:30'),(60,1,'Logged in','',NULL,'','2026-10-07 16:07:41'),(61,1,'Assigned room to registered tenant','tenants',3,'','2026-10-07 16:07:54'),(62,1,'Logged out','',NULL,'','2026-10-07 16:09:31'),(63,5,'Logged in','',NULL,'','2026-10-07 16:09:40'),(64,5,'Logged out','',NULL,'','2026-10-07 16:10:04'),(65,1,'Logged in','',NULL,'','2026-10-07 16:10:13'),(66,1,'Created billing statement','billing',4,'Previous balance ₱0.00','2026-10-07 16:11:22'),(67,1,'Logged out','',NULL,'','2026-10-07 16:11:48'),(68,5,'Logged in','',NULL,'','2026-10-07 16:12:00'),(69,5,'Tenant submitted GCash payment for verification','payment_submissions',2,'','2026-10-07 16:12:50'),(70,5,'Logged out','',NULL,'','2026-10-07 16:13:01'),(71,1,'Logged in','',NULL,'','2026-10-07 16:13:13'),(72,1,'Verified submitted payment and generated receipt','payments',2,'','2026-10-07 16:13:32'),(73,1,'Reviewed tenant payment submission','payment_submissions',2,'approved · ','2026-10-07 16:13:32');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;

--
-- Table structure for table `bed_spaces`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bed_spaces` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `room_id` int(10) unsigned NOT NULL,
  `label` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_id` (`room_id`,`label`),
  CONSTRAINT `bed_spaces_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bed_spaces`
--

/*!40000 ALTER TABLE `bed_spaces` DISABLE KEYS */;
INSERT INTO `bed_spaces` VALUES (1,1,'Bed 1'),(2,1,'Bed 2'),(3,1,'Bed 3'),(4,1,'Bed 4'),(5,2,'Bed 1'),(6,2,'Bed 2'),(7,2,'Bed 3'),(8,2,'Bed 4'),(9,2,'Bed 5'),(10,2,'Bed 6'),(11,3,'Bed 1'),(12,3,'Bed 2'),(13,3,'Bed 3'),(14,3,'Bed 4'),(15,4,'Bed 1'),(16,4,'Bed 2');
/*!40000 ALTER TABLE `bed_spaces` ENABLE KEYS */;

--
-- Table structure for table `billing`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `billing` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `assignment_id` int(10) unsigned NOT NULL,
  `period` char(7) NOT NULL,
  `cutoff` date NOT NULL,
  `due_date` date NOT NULL,
  `rent` bigint(20) NOT NULL CHECK (`rent` >= 0),
  `electricity` bigint(20) NOT NULL CHECK (`electricity` >= 0),
  `water` bigint(20) NOT NULL CHECK (`water` >= 0),
  `previous_balance` bigint(20) NOT NULL DEFAULT 0 CHECK (`previous_balance` >= 0),
  `total_due` bigint(20) NOT NULL CHECK (`total_due` >= 0),
  `carried_to` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_id` (`tenant_id`,`period`),
  KEY `due_date` (`due_date`),
  KEY `assignment_id` (`assignment_id`),
  KEY `carried_to` (`carried_to`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `billing_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `billing_ibfk_2` FOREIGN KEY (`assignment_id`) REFERENCES `tenant_assignments` (`id`),
  CONSTRAINT `billing_ibfk_3` FOREIGN KEY (`carried_to`) REFERENCES `billing` (`id`),
  CONSTRAINT `billing_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `billing`
--

/*!40000 ALTER TABLE `billing` DISABLE KEYS */;
INSERT INTO `billing` VALUES (1,1,1,'2026-10','2026-10-15','2026-10-31',500000,80000,30000,0,610000,NULL,1,'2026-10-07 13:39:35'),(2,2,2,'2026-10','2026-10-15','2026-10-28',450000,25000,0,0,475000,NULL,1,'2026-10-07 15:04:53'),(3,2,2,'2026-11','2026-11-15','2026-11-28',450000,0,0,0,450000,NULL,1,'2026-10-07 16:04:22'),(4,3,3,'2026-10','2026-10-15','2026-10-28',450000,20000,14993,0,484993,NULL,1,'2026-10-07 16:11:22');
/*!40000 ALTER TABLE `billing` ENABLE KEYS */;

--
-- Table structure for table `billing_adjustments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `billing_adjustments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `billing_id` int(10) unsigned NOT NULL,
  `type` enum('discount','adjustment') NOT NULL,
  `amount` bigint(20) NOT NULL,
  `reason` text NOT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `billing_id` (`billing_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `billing_adjustments_ibfk_1` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`id`),
  CONSTRAINT `billing_adjustments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `billing_adjustments`
--

/*!40000 ALTER TABLE `billing_adjustments` DISABLE KEYS */;
/*!40000 ALTER TABLE `billing_adjustments` ENABLE KEYS */;

--
-- Table structure for table `deposits`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deposits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `amount` bigint(20) NOT NULL CHECK (`amount` > 0),
  `received_date` date NOT NULL,
  `refund_amount` bigint(20) DEFAULT NULL CHECK (`refund_amount` >= 0 and `refund_amount` <= `amount`),
  `deduction_reason` text DEFAULT NULL,
  `refunded_date` date DEFAULT NULL,
  `recorded_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `deposits_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `deposits_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deposits`
--

/*!40000 ALTER TABLE `deposits` DISABLE KEYS */;
INSERT INTO `deposits` VALUES (1,1,500000,'2026-10-01',NULL,NULL,NULL,1,'2026-10-07 13:39:35'),(2,2,500000,'2026-10-07',NULL,NULL,NULL,1,'2026-10-07 14:40:11');
/*!40000 ALTER TABLE `deposits` ENABLE KEYS */;

--
-- Table structure for table `expenses`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category` enum('Utilities','Maintenance','Supplies','Salaries','Other') NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` bigint(20) NOT NULL CHECK (`amount` > 0),
  `expense_date` date NOT NULL,
  `reference` varchar(150) DEFAULT NULL,
  `recorded_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `vendor` varchar(200) NOT NULL DEFAULT '',
  `payment_method` enum('Cash','GCash','Bank transfer') NOT NULL DEFAULT 'Cash',
  `idempotency_key` varchar(100) DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `voided_by` int(10) unsigned DEFAULT NULL,
  `void_reason` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idempotency_key` (`idempotency_key`),
  KEY `recorded_by` (`recorded_by`),
  KEY `fk_expense_voider` (`voided_by`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_expense_voider` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;

--
-- Table structure for table `login_attempts`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `identity` char(64) NOT NULL,
  `attempted_at` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `identity` (`identity`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (2,'b15bc0801ebea329c34a13b8792dff711558fdccf6750a59182c15f34521c6c0',1791389235);
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;

--
-- Table structure for table `maintenance_requests`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maintenance_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `room_id` int(10) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('normal','urgent') DEFAULT 'normal',
  `status` enum('open','in_progress','resolved') DEFAULT 'open',
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `room_id` (`room_id`),
  CONSTRAINT `maintenance_requests_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `maintenance_requests_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maintenance_requests`
--

/*!40000 ALTER TABLE `maintenance_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `maintenance_requests` ENABLE KEYS */;

--
-- Table structure for table `notifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `billing_id` int(10) unsigned DEFAULT NULL,
  `type` varchar(30) NOT NULL,
  `email` varchar(254) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('queued','sent','failed') DEFAULT 'queued',
  `error` text DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `dedup_key` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dedup_key` (`dedup_key`),
  KEY `tenant_id` (`tenant_id`),
  KEY `billing_id` (`billing_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,2,2,'billing','cas@gmail.com','Your billing statement is ready','Billing period: 2026-10. Amount due: ₱4,750.00. Due date: 2026-10-28.','queued',NULL,NULL,'2026-10-07 15:04:53',NULL),(2,2,2,'submission','cas@gmail.com','Payment submission received','Your ₱4,750.00 GCash submission (reference 4555554445) is pending staff verification. Your balance changes after verification.','queued',NULL,NULL,'2026-10-07 15:18:51',NULL),(3,2,2,'receipt','cas@gmail.com','Payment receipt AD-000001','GCash payment verified: ₱4,750.00. Remaining balance: ₱0.00. Reference: 4555554445.','queued',NULL,NULL,'2026-10-07 15:21:05',NULL),(4,2,3,'billing','cas@gmail.com','Your billing statement is ready','Billing period: 2026-11. Amount due: ₱4,500.00. Due date: 2026-11-28.','queued',NULL,NULL,'2026-10-07 16:04:22',NULL),(5,3,4,'billing','cj@gmail.com','Your billing statement is ready','Billing period: 2026-10. Amount due: ₱4,849.93. Due date: 2026-10-28.','queued',NULL,NULL,'2026-10-07 16:11:22',NULL),(6,3,4,'submission','cj@gmail.com','Payment submission received','Your ₱4,849.93 GCash submission (reference 455664555) is pending staff verification. Your balance changes after verification.','queued',NULL,NULL,'2026-10-07 16:12:50',NULL),(7,3,4,'receipt','cj@gmail.com','Payment receipt AD-000002','GCash payment verified: ₱4,849.93. Remaining balance: ₱0.00. Reference: 455664555.','queued',NULL,NULL,'2026-10-07 16:13:32',NULL);
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;

--
-- Table structure for table `occupancy_scans`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `occupancy_scans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `room_id` int(10) unsigned NOT NULL,
  `detected_count` int(11) NOT NULL CHECK (`detected_count` >= 0),
  `registered_count` int(11) NOT NULL,
  `capacity` int(11) NOT NULL,
  `source` enum('manual','camera') NOT NULL,
  `notes` text DEFAULT NULL,
  `scanned_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  KEY `scanned_by` (`scanned_by`),
  CONSTRAINT `occupancy_scans_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`),
  CONSTRAINT `occupancy_scans_ibfk_2` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `occupancy_scans`
--

/*!40000 ALTER TABLE `occupancy_scans` DISABLE KEYS */;
/*!40000 ALTER TABLE `occupancy_scans` ENABLE KEYS */;

--
-- Table structure for table `payment_submissions`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_submissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `billing_id` int(10) unsigned NOT NULL,
  `amount` bigint(20) NOT NULL CHECK (`amount` > 0),
  `payer` varchar(200) NOT NULL,
  `payment_date` date NOT NULL,
  `reference` varchar(150) NOT NULL,
  `proof_file` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `active_reference` varchar(150) GENERATED ALWAYS AS (case when `status` in ('pending','approved') then `reference` else NULL end) STORED,
  `submission_key` varchar(100) NOT NULL,
  `payment_id` int(10) unsigned DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `review_note` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `submission_key` (`submission_key`),
  UNIQUE KEY `active_reference` (`active_reference`),
  UNIQUE KEY `payment_id` (`payment_id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `billing_id` (`billing_id`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `payment_submissions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `payment_submissions_ibfk_2` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`id`),
  CONSTRAINT `payment_submissions_ibfk_3` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`),
  CONSTRAINT `payment_submissions_ibfk_4` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_submissions`
--

/*!40000 ALTER TABLE `payment_submissions` DISABLE KEYS */;
INSERT INTO `payment_submissions` VALUES (1,2,2,475000,'Cassandra','2026-10-07','4555554445',NULL,'','approved','4555554445','efe449bdd88ee1bdecbab0fdacb7864b',1,2,'','2026-10-07 15:21:05','2026-10-07 15:18:51'),(2,3,4,484993,'CJ','2026-10-08','455664555',NULL,'','approved','455664555','469c2c2bf34f8cbb35d08f002569f8ac',2,1,'','2026-10-07 16:13:32','2026-10-07 16:12:50');
/*!40000 ALTER TABLE `payment_submissions` ENABLE KEYS */;

--
-- Table structure for table `payments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `billing_id` int(10) unsigned NOT NULL,
  `tenant_id` int(10) unsigned NOT NULL,
  `amount` bigint(20) NOT NULL CHECK (`amount` > 0),
  `method` enum('GCash','Personal Payment') NOT NULL,
  `payer` varchar(200) NOT NULL,
  `payment_date` date NOT NULL,
  `reference` varchar(150) DEFAULT NULL,
  `received_by` int(10) unsigned NOT NULL,
  `idempotency_key` varchar(100) NOT NULL,
  `balance_after` bigint(20) DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `voided_by` int(10) unsigned DEFAULT NULL,
  `void_reason` text DEFAULT NULL,
  `gcash_reference` varchar(150) GENERATED ALWAYS AS (case when `method` = 'GCash' then `reference` else NULL end) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idempotency_key` (`idempotency_key`),
  UNIQUE KEY `gcash_reference` (`gcash_reference`),
  KEY `payment_date` (`payment_date`),
  KEY `billing_id` (`billing_id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `received_by` (`received_by`),
  KEY `voided_by` (`voided_by`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`id`),
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`),
  CONSTRAINT `payments_ibfk_4` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,2,2,475000,'GCash','Cassandra','2026-10-07','4555554445',2,'submission-1',0,NULL,NULL,NULL,'4555554445','2026-10-07 15:21:05'),(2,4,3,484993,'GCash','CJ','2026-10-08','455664555',1,'submission-2',0,NULL,NULL,NULL,'455664555','2026-10-07 16:13:32');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;

--
-- Table structure for table `rooms`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rooms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(50) NOT NULL,
  `capacity` int(11) NOT NULL CHECK (`capacity` between 1 and 30),
  `rent` bigint(20) NOT NULL CHECK (`rent` >= 0),
  `status` enum('active','maintenance') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `number` (`number`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rooms`
--

/*!40000 ALTER TABLE `rooms` DISABLE KEYS */;
INSERT INTO `rooms` VALUES (1,'101',4,450000,'active'),(2,'102',6,500000,'active'),(3,'103',4,500000,'active'),(4,'104',2,550000,'active');
/*!40000 ALTER TABLE `rooms` ENABLE KEYS */;

--
-- Table structure for table `settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `key` varchar(80) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('business_address','Brgy. Bucana, Nasugbu, Batangas'),('business_contact','09635145864'),('business_name','Angelina’s Dormitory'),('cutoff_day','15'),('due_day','28'),('gcash_name','Angelina'),('gcash_number','09635145864'),('office_hours','8:00-5:00'),('payment_instructions',''),('seeded','1');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;

--
-- Table structure for table `tenant_assignments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `bed_id` int(10) unsigned NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `active_bed` int(10) unsigned GENERATED ALWAYS AS (case when `end_date` is null then `bed_id` else NULL end) STORED,
  `active_tenant` int(10) unsigned GENERATED ALWAYS AS (case when `end_date` is null then `tenant_id` else NULL end) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `active_bed` (`active_bed`),
  UNIQUE KEY `active_tenant` (`active_tenant`),
  KEY `tenant_id` (`tenant_id`),
  KEY `bed_id` (`bed_id`),
  CONSTRAINT `tenant_assignments_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `tenant_assignments_ibfk_2` FOREIGN KEY (`bed_id`) REFERENCES `bed_spaces` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_assignments`
--

/*!40000 ALTER TABLE `tenant_assignments` DISABLE KEYS */;
INSERT INTO `tenant_assignments` VALUES (1,1,5,'2026-10-01',NULL,5,1),(2,2,1,'2026-10-07',NULL,1,2),(3,3,2,'2026-10-08',NULL,2,3);
/*!40000 ALTER TABLE `tenant_assignments` ENABLE KEYS */;

--
-- Table structure for table `tenant_inquiries`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_inquiries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL,
  `category` enum('Billing','Payment','Room assignment','Deposit','Other') NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `status` enum('open','resolved') NOT NULL DEFAULT 'open',
  `reply` text DEFAULT NULL,
  `replied_by` int(10) unsigned DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `replied_by` (`replied_by`),
  CONSTRAINT `tenant_inquiries_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `tenant_inquiries_ibfk_2` FOREIGN KEY (`replied_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenant_inquiries`
--

/*!40000 ALTER TABLE `tenant_inquiries` DISABLE KEYS */;
INSERT INTO `tenant_inquiries` VALUES (1,2,'Other','concerns','walang tubig','open',NULL,NULL,NULL,'2026-10-07 15:40:33');
/*!40000 ALTER TABLE `tenant_inquiries` ENABLE KEYS */;

--
-- Table structure for table `tenants`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenants` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `email` varchar(254) NOT NULL,
  `contact` varchar(80) NOT NULL,
  `status` enum('active','moved_out') NOT NULL DEFAULT 'active',
  `move_in` date NOT NULL,
  `move_out` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tenants`
--

/*!40000 ALTER TABLE `tenants` DISABLE KEYS */;
INSERT INTO `tenants` VALUES (1,'Maria Santos (Demo)','maria@example.com','0917 000 0000','active','2026-10-01',NULL,'2026-10-07 13:39:35'),(2,'Cassandra','cas@gmail.com','098765432442','active','2026-10-07',NULL,'2026-10-07 14:37:04'),(3,'CJ','cj@gmail.com','09456364011','active','2026-10-08',NULL,'2026-10-07 16:07:15');
/*!40000 ALTER TABLE `tenants` ENABLE KEYS */;

--
-- Table structure for table `users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff','tenant') NOT NULL,
  `name` varchar(200) NOT NULL,
  `tenant_id` int(10) unsigned DEFAULT NULL,
  `active` tinyint(4) DEFAULT 1,
  `must_change` tinyint(4) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$12$duCHWxG7edRNOkje1UdCse5At.HdChfJ7kgo3Kc7ImovAFgzXDrSi','admin','Angelina • Administrator',NULL,1,0),(2,'staff','$2y$12$QwoWqND8DOWsCTCPHbwB7.p89ARl1.rdYvoykQmCa6TEj5HBj8eLK','staff','Payment Staff',NULL,1,0),(3,'tenant','$2y$12$p4IfuP35fEN5QDDmM1XOE.XQyiwU2QmTskOVQvP.ZmwMv5ccM52v2','tenant','Maria Santos',1,1,0),(4,'cassandra','$2y$12$VXvzKqhov61HeOtCcwFu8.YlXQXW50iDcL/.VypAhmCYVx35p/WhS','tenant','Cassandra',2,1,0),(5,'cjohn','$2y$12$6Nsb//BdkGLE1EYA1gAhAuyd8noc7jRCP0lSXJGhvIew6dSgU0eh6','tenant','CJ',3,1,0);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;

--
-- Table structure for table `utility_charges`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `utility_charges` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `billing_id` int(10) unsigned NOT NULL,
  `type` enum('water','electricity') NOT NULL,
  `amount` bigint(20) NOT NULL CHECK (`amount` >= 0),
  PRIMARY KEY (`id`),
  UNIQUE KEY `billing_id` (`billing_id`,`type`),
  CONSTRAINT `utility_charges_ibfk_1` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `utility_charges`
--

/*!40000 ALTER TABLE `utility_charges` DISABLE KEYS */;
INSERT INTO `utility_charges` VALUES (1,1,'electricity',80000),(2,1,'water',30000),(3,2,'electricity',25000),(4,2,'water',0),(5,3,'electricity',0),(6,3,'water',0),(7,4,'electricity',20000),(8,4,'water',14993);
/*!40000 ALTER TABLE `utility_charges` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-08  0:26:42
