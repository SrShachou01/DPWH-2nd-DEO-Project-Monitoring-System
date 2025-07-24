/*
SQLyog Community v13.3.0 (64 bit)
MySQL - 10.4.32-MariaDB : Database - dpwhpms
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`dpwhpms` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `dpwhpms`;

/*Table structure for table `contract-manpower` */

DROP TABLE IF EXISTS `contract-manpower`;

CREATE TABLE `contract-manpower` (
  `cm_mp_ID` int(10) NOT NULL AUTO_INCREMENT,
  `proj_ID` varchar(20) DEFAULT NULL,
  `cm_am_officer` varchar(255) DEFAULT NULL,
  `cm_pm_name` varchar(255) DEFAULT NULL,
  `cm_pm_prc_me_ID` varchar(50) DEFAULT NULL,
  `cm_pe_name` varchar(255) DEFAULT NULL,
  `cm_pe_prc_me_ID` varchar(50) DEFAULT NULL,
  `cm_me_name` varchar(255) DEFAULT NULL,
  `cm_me_prc_me_ID` varchar(50) DEFAULT NULL,
  `cm_const_foreman` varchar(255) DEFAULT NULL,
  `cm_csh_officer` varchar(255) DEFAULT NULL,
  `cm_attachment` varchar(255) DEFAULT NULL,
  `cm_date_created` timestamp NULL DEFAULT current_timestamp(),
  `cm_date_updated` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`cm_mp_ID`),
  KEY `proj_ID` (`proj_ID`),
  CONSTRAINT `contract-manpower_ibfk_1` FOREIGN KEY (`proj_ID`) REFERENCES `projects` (`proj_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `contract-time-extension` */

DROP TABLE IF EXISTS `contract-time-extension`;

CREATE TABLE `contract-time-extension` (
  `cte_code` varchar(255) NOT NULL,
  `proj_ID` varchar(20) DEFAULT NULL,
  `cte_lr_date` date DEFAULT NULL,
  `cte_reason` text DEFAULT NULL,
  `cte_ext_days` int(10) DEFAULT NULL,
  `cte_approved_date` date DEFAULT NULL,
  `cte_attachment` varchar(255) DEFAULT NULL,
  `cte_uploaded_date` datetime NOT NULL DEFAULT current_timestamp(),
  UNIQUE KEY `unique_code_proj` (`cte_code`,`proj_ID`),
  KEY `proj_ID` (`proj_ID`),
  CONSTRAINT `contract-time-extension_ibfk_1` FOREIGN KEY (`proj_ID`) REFERENCES `projects` (`proj_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `contract-work-resumption` */

DROP TABLE IF EXISTS `contract-work-resumption`;

CREATE TABLE `contract-work-resumption` (
  `cwr_code` varchar(255) NOT NULL,
  `proj_ID` varchar(20) DEFAULT NULL,
  `cwr_lr_date` date DEFAULT NULL,
  `cwr_reason` text DEFAULT NULL,
  `cwr_susp_days` int(10) DEFAULT NULL,
  `cwr_approved_date` date DEFAULT NULL,
  `cwr_attachment` varchar(255) DEFAULT NULL,
  `cwr_uploaded_date` datetime NOT NULL DEFAULT current_timestamp(),
  UNIQUE KEY `unique_code_proj` (`cwr_code`,`proj_ID`),
  KEY `proj_ID` (`proj_ID`),
  CONSTRAINT `contract-work-resumption_ibfk_1` FOREIGN KEY (`proj_ID`) REFERENCES `projects` (`proj_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `contract-work-suspension` */

DROP TABLE IF EXISTS `contract-work-suspension`;

CREATE TABLE `contract-work-suspension` (
  `cws_code` varchar(255) NOT NULL,
  `proj_ID` varchar(20) DEFAULT NULL,
  `cws_lr_date` date DEFAULT NULL,
  `cws_reason` text DEFAULT NULL,
  `cws_susp_days` int(10) DEFAULT NULL,
  `cws_approved_date` date DEFAULT NULL,
  `cws_attachment` varchar(255) DEFAULT NULL,
  `cws_uploaded_date` datetime NOT NULL DEFAULT current_timestamp(),
  `cws_ext_days` int(10) DEFAULT NULL,
  `cws_expiry_date` date NOT NULL,
  UNIQUE KEY `unique_code_proj` (`cws_code`,`proj_ID`),
  KEY `proj_ID` (`proj_ID`),
  CONSTRAINT `contract-work-suspension_ibfk_1` FOREIGN KEY (`proj_ID`) REFERENCES `projects` (`proj_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `contractors` */

DROP TABLE IF EXISTS `contractors`;

CREATE TABLE `contractors` (
  `cont_ID` int(15) NOT NULL AUTO_INCREMENT,
  `cont_name` varchar(500) DEFAULT NULL,
  `cont_location` varchar(500) DEFAULT NULL,
  `cont_owner` varchar(200) DEFAULT NULL,
  `cont_phone` varchar(15) DEFAULT NULL,
  `cont_isDeleted` int(1) DEFAULT 0,
  `cont_isBlocklisted` int(1) DEFAULT 0,
  PRIMARY KEY (`cont_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `edit-request` */

DROP TABLE IF EXISTS `edit-request`;

CREATE TABLE `edit-request` (
  `request_id` int(10) NOT NULL AUTO_INCREMENT,
  `proj_ID` varchar(20) NOT NULL,
  `user_ID` int(10) NOT NULL,
  `request_status` enum('Pending','Approved','Denied') DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `request_reason` varchar(255) NOT NULL,
  PRIMARY KEY (`request_id`),
  KEY `proj_ID` (`proj_ID`),
  KEY `user_ID` (`user_ID`),
  CONSTRAINT `edit-request_ibfk_1` FOREIGN KEY (`proj_ID`) REFERENCES `projects` (`proj_ID`) ON DELETE CASCADE,
  CONSTRAINT `edit-request_ibfk_2` FOREIGN KEY (`user_ID`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `final-completion` */

DROP TABLE IF EXISTS `final-completion`;

CREATE TABLE `final-completion` (
  `fc_ID` int(10) NOT NULL AUTO_INCREMENT,
  `proj_ID` varchar(20) DEFAULT NULL,
  `fc_type` enum('Inspection Report','Certificate of Completion','Certificate of Acceptance') DEFAULT NULL,
  `fc_approved_date` datetime DEFAULT NULL,
  `fc_uploaded_date` datetime NOT NULL DEFAULT current_timestamp(),
  `fc_attachment` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`fc_ID`),
  KEY `proj_ID` (`proj_ID`),
  CONSTRAINT `final-completion_ibfk_1` FOREIGN KEY (`proj_ID`) REFERENCES `projects` (`proj_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `implementing-office-manpower` */

DROP TABLE IF EXISTS `implementing-office-manpower`;

CREATE TABLE `implementing-office-manpower` (
  `iom_ID` int(10) NOT NULL AUTO_INCREMENT,
  `proj_ID` varchar(20) DEFAULT NULL,
  `iom_pe_name` varchar(255) DEFAULT NULL,
  `iom_pe_prc_me_ID` varchar(50) DEFAULT NULL,
  `iom_pi_name` varchar(255) DEFAULT NULL,
  `iom_pi_prc_me_ID` varchar(255) DEFAULT NULL,
  `iom_me_name` varchar(255) DEFAULT NULL,
  `iom_me_prc_me_ID` varchar(255) DEFAULT NULL,
  `iom_mic_name` varchar(255) DEFAULT NULL,
  `iom_mic_prc_me_ID` varchar(50) DEFAULT NULL,
  `iom_attachment` varchar(255) DEFAULT NULL,
  `iom_pi_pcma_name` varchar(255) NOT NULL,
  `iom_pi_pcma_prc_me_ID` varchar(255) NOT NULL,
  PRIMARY KEY (`iom_ID`),
  KEY `proj_ID` (`proj_ID`),
  CONSTRAINT `implementing-office-manpower_ibfk_1` FOREIGN KEY (`proj_ID`) REFERENCES `projects` (`proj_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `login-attempts` */

DROP TABLE IF EXISTS `login-attempts`;

CREATE TABLE `login-attempts` (
  `username` varchar(255) NOT NULL,
  `failed_attempts` int(11) DEFAULT 0,
  `last_failed_attempt` datetime DEFAULT NULL,
  `lock_time` datetime DEFAULT NULL,
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `monthly-time-suspension-report` */

DROP TABLE IF EXISTS `monthly-time-suspension-report`;

CREATE TABLE `monthly-time-suspension-report` (
  `mtsr_code` varchar(255) NOT NULL,
  `proj_ID` varchar(20) DEFAULT NULL,
  `mtsr_lr_date` date DEFAULT NULL,
  `mtsr_reason` text DEFAULT NULL,
  `mtsr_susp_days` int(10) DEFAULT NULL,
  `mtsr_approved_date` date DEFAULT NULL,
  `mtsr_attachment` varchar(255) DEFAULT NULL,
  `mtsr_uploaded_date` datetime NOT NULL DEFAULT current_timestamp(),
  UNIQUE KEY `unique_code_proj` (`mtsr_code`,`proj_ID`),
  KEY `proj_ID` (`proj_ID`),
  CONSTRAINT `monthly-time-suspension-report_ibfk_1` FOREIGN KEY (`proj_ID`) REFERENCES `projects` (`proj_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `other-documents` */

DROP TABLE IF EXISTS `other-documents`;

CREATE TABLE `other-documents` (
  `od_ID` int(10) NOT NULL AUTO_INCREMENT,
  `proj_ID` varchar(20) DEFAULT NULL,
  `od_title_name` varchar(255) DEFAULT NULL,
  `od_attachment_type` enum('Document File','Spreadsheet File','Powerpoint File','Image File','Text File','PDF File','Other') DEFAULT NULL,
  `od_attachment` varchar(255) DEFAULT NULL,
  `od_uploaded_date` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`od_ID`),
  KEY `proj_ID` (`proj_ID`),
  CONSTRAINT `other-documents_ibfk_1` FOREIGN KEY (`proj_ID`) REFERENCES `projects` (`proj_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `progress` */

DROP TABLE IF EXISTS `progress`;

CREATE TABLE `progress` (
  `prog_ID` int(10) NOT NULL AUTO_INCREMENT,
  `proj_ID` varchar(20) DEFAULT NULL,
  `prog_date` date DEFAULT NULL,
  `prog_desc` text DEFAULT NULL,
  `prog_percentage` int(6) DEFAULT NULL,
  `prog_issue` text DEFAULT NULL,
  `prog_photos` text DEFAULT NULL,
  `proj_date_uploaded` datetime DEFAULT current_timestamp(),
  `prog_status` enum('Pending','Approved','Denied','') NOT NULL,
  PRIMARY KEY (`prog_ID`),
  KEY `proj_ID` (`proj_ID`),
  CONSTRAINT `progress_ibfk_1` FOREIGN KEY (`proj_ID`) REFERENCES `projects` (`proj_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `project-collaborators` */

DROP TABLE IF EXISTS `project-collaborators`;

CREATE TABLE `project-collaborators` (
  `proj_ID` varchar(20) NOT NULL,
  `user_ID` int(10) NOT NULL,
  PRIMARY KEY (`proj_ID`,`user_ID`),
  KEY `user_ID` (`user_ID`),
  CONSTRAINT `project-collaborators_ibfk_1` FOREIGN KEY (`proj_ID`) REFERENCES `projects` (`proj_ID`) ON DELETE CASCADE,
  CONSTRAINT `project-collaborators_ibfk_2` FOREIGN KEY (`user_ID`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `project_contractors` */

DROP TABLE IF EXISTS `project_contractors`;

CREATE TABLE `project_contractors` (
  `proj_ID` varchar(20) NOT NULL,
  `cont_ID` int(15) NOT NULL,
  PRIMARY KEY (`proj_ID`,`cont_ID`),
  KEY `cont_ID` (`cont_ID`),
  CONSTRAINT `project_contractors_ibfk_1` FOREIGN KEY (`proj_ID`) REFERENCES `projects` (`proj_ID`) ON DELETE CASCADE,
  CONSTRAINT `project_contractors_ibfk_2` FOREIGN KEY (`cont_ID`) REFERENCES `contractors` (`cont_ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `projects` */

DROP TABLE IF EXISTS `projects`;

CREATE TABLE `projects` (
  `proj_ID` varchar(20) NOT NULL,
  `proj_progress` decimal(5,2) DEFAULT NULL,
  `proj_cont_name` text DEFAULT NULL,
  `proj_description` text DEFAULT NULL,
  `proj_comp_ID` varchar(20) DEFAULT NULL,
  `proj_cont_loc` text DEFAULT NULL,
  `proj_cont_amt` decimal(21,2) DEFAULT NULL,
  `proj_cont_duration` int(10) DEFAULT NULL,
  `proj_unwork_days` int(10) DEFAULT 0,
  `proj_NOA` date DEFAULT NULL,
  `proj_NOP` date DEFAULT NULL,
  `proj_effect_date` date DEFAULT NULL,
  `proj_expiry_date` date DEFAULT NULL,
  `proj_status` enum('Ongoing','Not Yet Started','Completed','Suspended') DEFAULT NULL,
  `user_ID` int(10) DEFAULT NULL,
  `proj_isDeleted` int(1) DEFAULT 0,
  `proj_isApproved` int(1) DEFAULT 0,
  `proj_uploaded` datetime DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`proj_ID`),
  KEY `user_ID` (`user_ID`),
  CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`user_ID`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `roles` */

DROP TABLE IF EXISTS `roles`;

CREATE TABLE `roles` (
  `role_ID` int(10) NOT NULL,
  `role_name` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`role_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `users` */

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `user_id` int(10) NOT NULL AUTO_INCREMENT COMMENT 'User ID of a user',
  `user_username` varchar(50) DEFAULT NULL COMMENT 'User''s Username',
  `user_password` varchar(255) DEFAULT NULL COMMENT 'User''s Password',
  `user_first_name` varchar(255) DEFAULT NULL COMMENT 'User''s First Name',
  `user_middle_initial` char(1) DEFAULT NULL,
  `user_last_name` varchar(255) DEFAULT NULL COMMENT 'User''s Last Name',
  `user_email` varchar(255) DEFAULT NULL COMMENT 'User''s Email',
  `role_id` int(10) DEFAULT NULL COMMENT 'User''s Role ID (foreign Key)',
  `user_id_type` enum('PRC ID','ME ID','Others','None','Accreditation Number') DEFAULT NULL,
  `user_id_number` varchar(255) DEFAULT NULL,
  `user_position` enum('Admin','Project Engineer','Project Inspector','Materials Engineer','Others','None','Police','Meow','Horse','Administrative Aide II') NOT NULL,
  `user_photo` varchar(255) DEFAULT NULL COMMENT 'User''s Photo',
  `user_suffix` varchar(10) DEFAULT 'None' COMMENT 'User Suffix',
  PRIMARY KEY (`user_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=12540 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Table structure for table `variation-orders` */

DROP TABLE IF EXISTS `variation-orders`;

CREATE TABLE `variation-orders` (
  `vo_code` varchar(255) NOT NULL,
  `proj_ID` varchar(20) NOT NULL,
  `vo_date` date NOT NULL,
  `vo_add_amt` decimal(10,2) NOT NULL,
  `vo_revised_cost` decimal(15,2) NOT NULL,
  `vo_ext_days` int(10) DEFAULT NULL,
  `vo_expiry_date` date DEFAULT NULL,
  `vo_attachment` varchar(255) DEFAULT NULL,
  `vo_reason` text NOT NULL,
  `vo_approved_date` date DEFAULT NULL,
  `vo_uploaded_date` datetime NOT NULL DEFAULT current_timestamp(),
  UNIQUE KEY `unique_code_proj` (`vo_code`,`proj_ID`),
  UNIQUE KEY `unique_vo_code_proj_ID` (`vo_code`,`proj_ID`),
  KEY `proj_ID` (`proj_ID`),
  CONSTRAINT `variation-orders_ibfk_1` FOREIGN KEY (`proj_ID`) REFERENCES `projects` (`proj_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
