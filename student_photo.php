-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: internhub
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
-- Table structure for table `application`
--

DROP TABLE IF EXISTS `application`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `application` (
  `application_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `resume_id` int(11) NOT NULL,
  `application_status` enum('Pending','Shortlisted','Interview','Accepted','Rejected','Review','Accept','Reject') DEFAULT 'Pending',
  `application_student_response` enum('Pending','Accepted','Rejected') DEFAULT 'Pending',
  `application_applied_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`application_id`),
  KEY `fk_application_resume` (`resume_id`),
  KEY `idx_application_student` (`student_id`),
  KEY `idx_application_job` (`job_id`),
  CONSTRAINT `fk_application_job` FOREIGN KEY (`job_id`) REFERENCES `jobposting` (`job_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_application_resume` FOREIGN KEY (`resume_id`) REFERENCES `resume` (`resume_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_application_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application`
--

LOCK TABLES `application` WRITE;
/*!40000 ALTER TABLE `application` DISABLE KEYS */;
INSERT INTO `application` VALUES (1,1,1,1,'Accepted','Pending','2026-05-01 09:00:00'),(2,2,3,2,'Accepted','Pending','2026-05-02 10:30:00'),(3,3,2,3,'Pending','Pending','2026-05-10 12:00:00'),(4,5,3,5,'Accepted','Pending','2026-05-12 14:15:00'),(5,4,3,4,'Shortlisted','Pending','2026-05-13 11:00:00'),(6,7,3,6,'Pending','Pending','2026-06-09 11:01:23'),(7,7,4,6,'Accepted','Accepted','2026-06-09 18:26:22'),(8,7,5,6,'Pending','Pending','2026-06-10 17:03:43'),(9,6,5,7,'Pending','Pending','2026-06-10 17:18:02'),(10,8,6,8,'Accepted','Accepted','2026-05-07 12:52:51'),(11,9,7,9,'Accepted','Accepted','2026-05-08 12:52:51'),(12,10,8,10,'Accepted','Accepted','2026-05-09 12:52:51'),(13,11,9,11,'Accepted','Accepted','2026-05-10 12:52:51'),(14,12,10,12,'Shortlisted','Pending','2026-05-11 12:52:51'),(15,13,11,13,'Accepted','Accepted','2026-05-12 12:52:52'),(16,14,12,14,'Accepted','Accepted','2026-05-13 12:52:52'),(17,15,13,15,'Accepted','Accepted','2026-05-14 12:52:52'),(18,16,14,16,'Accepted','Accepted','2026-05-15 12:52:52'),(19,17,15,17,'Shortlisted','Pending','2026-05-16 12:52:52'),(20,18,16,18,'Accepted','Accepted','2026-05-17 12:52:52'),(21,19,17,19,'Accepted','Accepted','2026-05-18 12:52:52'),(22,20,6,20,'Accepted','Accepted','2026-05-19 12:52:52'),(23,21,7,21,'Accepted','Accepted','2026-05-20 12:52:52'),(24,22,8,22,'Shortlisted','Pending','2026-05-21 12:52:52'),(25,23,9,23,'Accepted','Accepted','2026-05-22 12:52:52'),(26,24,10,24,'Accepted','Accepted','2026-05-23 12:52:52'),(27,25,11,25,'Accepted','Accepted','2026-05-24 12:52:52'),(28,26,12,26,'Accepted','Accepted','2026-05-25 12:52:52'),(29,27,13,27,'Shortlisted','Pending','2026-05-26 12:52:52'),(30,28,14,28,'Accepted','Accepted','2026-05-27 12:52:53'),(31,29,15,29,'Accepted','Accepted','2026-05-28 12:52:53'),(32,30,16,30,'Accepted','Accepted','2026-05-29 12:52:53'),(33,31,17,31,'Accepted','Accepted','2026-05-30 12:52:53');
/*!40000 ALTER TABLE `application` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `audit_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `user_role` varchar(30) DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `ip_address` varchar(60) DEFAULT NULL,
  `audit_detail` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`audit_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
INSERT INTO `audit_log` VALUES (1,12,'student','Login failed','::1','Generic failed login response used.','2026-06-09 16:13:42'),(2,1,'coordinator','Login success','::1','Role coordinator','2026-06-09 16:13:48'),(3,1,'coordinator','Logout','::1','Session invalidated.','2026-06-09 16:14:40'),(4,2,'lecturer','Login success','::1','Role lecturer','2026-06-09 16:14:46'),(5,2,'lecturer','Logout','::1','Session invalidated.','2026-06-09 16:14:59'),(6,9,'company','Login success','::1','Role company','2026-06-09 16:15:08'),(7,9,'company','Logout','::1','Session invalidated.','2026-06-09 16:15:25'),(8,13,'company','Company registration','::1','Pending email verification and coordinator approval.','2026-06-09 16:30:27');
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `company`
--

DROP TABLE IF EXISTS `company`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company` (
  `company_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(200) NOT NULL,
  `company_email` varchar(150) NOT NULL,
  `company_registration_no` varchar(80) DEFAULT NULL,
  `company_phone` varchar(20) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `company_address_line` varchar(255) DEFAULT NULL,
  `company_state` varchar(80) DEFAULT NULL,
  `company_postcode` varchar(10) DEFAULT NULL,
  `company_description` text DEFAULT NULL,
  `company_type` varchar(100) DEFAULT NULL,
  `company_registration_date` date DEFAULT NULL,
  `company_registration_expiry_date` date DEFAULT NULL,
  `company_business_status` varchar(30) DEFAULT 'Active',
  `company_owner_name` varchar(150) DEFAULT NULL,
  `company_approval_status` varchar(30) DEFAULT 'Pending',
  `company_contact_person` varchar(150) DEFAULT NULL,
  `company_allowance_range` varchar(40) DEFAULT NULL,
  `company_capacity_programme` varchar(40) DEFAULT 'Both Programs',
  `company_capacity_ais` int(11) DEFAULT 0,
  `company_capacity_accounting` int(11) DEFAULT 0,
  `business_registration_no` varchar(80) DEFAULT NULL,
  `business_registration_date` date DEFAULT NULL,
  `business_expiry_date` date DEFAULT NULL,
  `business_owner_name` varchar(150) DEFAULT NULL,
  `open_slots` int(11) DEFAULT 0,
  PRIMARY KEY (`company_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `company_email` (`company_email`),
  KEY `idx_company_user` (`user_id`),
  CONSTRAINT `fk_company_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `company`
--

LOCK TABLES `company` WRITE;
/*!40000 ALTER TABLE `company` DISABLE KEYS */;
INSERT INTO `company` VALUES (1,8,'TechNova Sdn Bhd','hr@technova.test',NULL,'03-7788 1000','Cyberjaya, Selangor',NULL,NULL,NULL,'Software and accounting systems consulting firm.','Technology',NULL,NULL,'Active',NULL,'Approved',NULL,NULL,'Both Programs',0,0,NULL,NULL,NULL,NULL,0),(2,9,'FinEdge Analytics','hr@finedge.test',NULL,'03-8899 2000','Kuala Lumpur',NULL,NULL,NULL,'Financial analytics and audit support provider.','Finance',NULL,NULL,'Active',NULL,'Approved',NULL,NULL,'Both Programs',0,0,NULL,NULL,NULL,NULL,0),(3,13,'ju comel','gkdfnkds@gmail.com',NULL,'018-8347132','jfhsfhlfkk','jfhsfhlfkk','Melaka','08000',NULL,'Accounting',NULL,NULL,'Active',NULL,'Approved','ndkndk',NULL,'Both Programs',0,0,'ngeh','2019-05-06','2035-06-05','ndkndk',0),(4,14,'ju comel','nejd@gmail.com','726371','017-53537273','fkjekfe',NULL,NULL,NULL,NULL,'Business Analytics','2019-06-12','2035-06-19','Active','fejbfe','Approved','fejbfe',NULL,'Both Programs',0,0,NULL,NULL,NULL,NULL,0),(5,15,'amirul tomyam','rosni@gmail.com','123444','01567834567','jalan sintok',NULL,NULL,NULL,NULL,'Education','2026-01-16','2026-06-27','Active','rosni','Approved','rosni',NULL,'Both Programs',0,0,NULL,NULL,NULL,NULL,0),(6,16,'AuditPro Selangor','demodash.company1@internhub.test',NULL,'03-7000 0001','Selangor, Malaysia','DemoDash Business Centre','Selangor','50001','Demo dashboard company for coordinator analytics.','Audit',NULL,NULL,'Active',NULL,'Approved','Demo HR 1','RM601 - RM900','Both Programs',8,5,NULL,NULL,NULL,NULL,0),(7,17,'KL Tax Advisory','demodash.company2@internhub.test',NULL,'03-7000 0002','Kuala Lumpur, Malaysia','DemoDash Business Centre','Kuala Lumpur','50002','Demo dashboard company for coordinator analytics.','Taxation',NULL,NULL,'Active',NULL,'Approved','Demo HR 2','RM901 - RM1,200','Both Programs',4,4,NULL,NULL,NULL,NULL,0),(8,18,'Johor Ledger Hub','demodash.company3@internhub.test',NULL,'03-7000 0003','Johor, Malaysia','DemoDash Business Centre','Johor','50003','Demo dashboard company for coordinator analytics.','Accounting',NULL,NULL,'Active',NULL,'Approved','Demo HR 3','RM301 - RM600','Both Programs',4,2,NULL,NULL,NULL,NULL,0),(9,19,'Penang FinTech Accounts','demodash.company4@internhub.test',NULL,'03-7000 0004','Pulau Pinang, Malaysia','DemoDash Business Centre','Pulau Pinang','50004','Demo dashboard company for coordinator analytics.','Technology',NULL,NULL,'Active',NULL,'Approved','Demo HR 4','Above RM1,200','Both Programs',5,6,NULL,NULL,NULL,NULL,0),(10,20,'Perak Assurance Group','demodash.company5@internhub.test',NULL,'03-7000 0005','Perak, Malaysia','DemoDash Business Centre','Perak','50005','Demo dashboard company for coordinator analytics.','Audit',NULL,NULL,'Active',NULL,'Approved','Demo HR 5','RM301 - RM600','Both Programs',5,8,NULL,NULL,NULL,NULL,0),(11,21,'Kedah Agro Accounts','demodash.company6@internhub.test',NULL,'03-7000 0006','Kedah, Malaysia','DemoDash Business Centre','Kedah','50006','Demo dashboard company for coordinator analytics.','Finance',NULL,NULL,'Active',NULL,'Approved','Demo HR 6','RM0 - RM300','Both Programs',3,7,NULL,NULL,NULL,NULL,0),(12,22,'Pahang Corporate Services','demodash.company7@internhub.test',NULL,'03-7000 0007','Pahang, Malaysia','DemoDash Business Centre','Pahang','50007','Demo dashboard company for coordinator analytics.','Consulting',NULL,NULL,'Active',NULL,'Approved','Demo HR 7','RM601 - RM900','Both Programs',7,6,NULL,NULL,NULL,NULL,0),(13,23,'Sabah Cloud Finance','demodash.company8@internhub.test',NULL,'03-7000 0008','Sabah, Malaysia','DemoDash Business Centre','Sabah','50008','Demo dashboard company for coordinator analytics.','Technology',NULL,NULL,'Active',NULL,'Approved','Demo HR 8','RM901 - RM1,200','Both Programs',5,5,NULL,NULL,NULL,NULL,0),(14,24,'Sarawak Management Accounting','demodash.company9@internhub.test',NULL,'03-7000 0009','Sarawak, Malaysia','DemoDash Business Centre','Sarawak','50009','Demo dashboard company for coordinator analytics.','Management Accounting',NULL,NULL,'Active',NULL,'Approved','Demo HR 9','RM601 - RM900','Both Programs',4,7,NULL,NULL,NULL,NULL,0),(15,25,'Melaka Compliance Centre','demodash.company10@internhub.test',NULL,'03-7000 0010','Melaka, Malaysia','DemoDash Business Centre','Melaka','50010','Demo dashboard company for coordinator analytics.','Taxation',NULL,NULL,'Active',NULL,'Approved','Demo HR 10','RM301 - RM600','Both Programs',8,2,NULL,NULL,NULL,NULL,0),(16,26,'Terengganu Zakat Finance','demodash.company11@internhub.test',NULL,'03-7000 0011','Terengganu, Malaysia','DemoDash Business Centre','Terengganu','50011','Demo dashboard company for coordinator analytics.','Finance',NULL,NULL,'Active',NULL,'Approved','Demo HR 11','RM0 - RM300','Both Programs',7,3,NULL,NULL,NULL,NULL,0),(17,27,'Kelantan SME Advisory','demodash.company12@internhub.test',NULL,'03-7000 0012','Kelantan, Malaysia','DemoDash Business Centre','Kelantan','50012','Demo dashboard company for coordinator analytics.','Consulting',NULL,NULL,'Active',NULL,'Approved','Demo HR 12','RM301 - RM600','Both Programs',7,7,NULL,NULL,NULL,NULL,0);
/*!40000 ALTER TABLE `company` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `companyevaluation`
--

DROP TABLE IF EXISTS `companyevaluation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `companyevaluation` (
  `ce_id` int(11) NOT NULL AUTO_INCREMENT,
  `internship_id` int(11) NOT NULL,
  `ce_understand_organization_governance` int(11) DEFAULT NULL,
  `ce_knowledge_business_principles_practices` int(11) DEFAULT NULL,
  `ce_apply_knowledge_practices` int(11) DEFAULT NULL,
  `ce_problem_identification_supporting_evidence` int(11) DEFAULT NULL,
  `ce_proposed_solutions` int(11) DEFAULT NULL,
  `ce_application_it` int(11) DEFAULT NULL,
  `ce_attitude_towards_team_members` int(11) DEFAULT NULL,
  `ce_contribution_to_team` int(11) DEFAULT NULL,
  `ce_leadership_skills` int(11) DEFAULT NULL,
  `ce_attentiveness` int(11) DEFAULT NULL,
  `ce_answering_questions` int(11) DEFAULT NULL,
  `ce_questioning` int(11) DEFAULT NULL,
  `ce_seeking_information` int(11) DEFAULT NULL,
  `ce_being_resourceful` int(11) DEFAULT NULL,
  `ce_logbook` int(11) DEFAULT NULL,
  `ce_respect_for_others` int(11) DEFAULT NULL,
  `ce_punctuality` int(11) DEFAULT NULL,
  `ce_meeting_deadlines` int(11) DEFAULT NULL,
  `ce_personal_apperance` int(11) DEFAULT NULL,
  `ce_knowledge_of_ethics` int(11) DEFAULT NULL,
  `ce_ethical_behaviour` int(11) DEFAULT NULL,
  `ce_total_score` int(11) DEFAULT NULL,
  PRIMARY KEY (`ce_id`),
  UNIQUE KEY `internship_id` (`internship_id`),
  KEY `idx_ce_internship` (`internship_id`),
  CONSTRAINT `fk_ce_internship` FOREIGN KEY (`internship_id`) REFERENCES `internship` (`internship_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `companyevaluation`
--

LOCK TABLES `companyevaluation` WRITE;
/*!40000 ALTER TABLE `companyevaluation` DISABLE KEYS */;
INSERT INTO `companyevaluation` VALUES (1,1,4,4,3,3,3,4,4,4,3,4,3,3,4,4,4,4,4,3,4,4,4,77),(2,2,3,3,3,3,3,3,4,3,3,4,3,3,3,3,4,4,4,3,4,3,4,70);
/*!40000 ALTER TABLE `companyevaluation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coordinator`
--

DROP TABLE IF EXISTS `coordinator`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coordinator` (
  `coordinator_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `coordinator_name` varchar(100) NOT NULL,
  `coordinator_gender` enum('Male','Female','Other') DEFAULT NULL,
  `coordinator_email` varchar(150) NOT NULL,
  `coordinator_phone` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`coordinator_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `coordinator_email` (`coordinator_email`),
  KEY `idx_coordinator_user` (`user_id`),
  CONSTRAINT `fk_coordinator_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coordinator`
--

LOCK TABLES `coordinator` WRITE;
/*!40000 ALTER TABLE `coordinator` DISABLE KEYS */;
INSERT INTO `coordinator` VALUES (1,1,'Dr Hazrami','Male','coordinator@internhub.test','03-5544 1000');
/*!40000 ALTER TABLE `coordinator` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `internship`
--

DROP TABLE IF EXISTS `internship`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internship` (
  `internship_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `lecturer_id` int(11) NOT NULL,
  `job_id` int(11) DEFAULT NULL,
  `internship_field` varchar(150) DEFAULT NULL,
  `internship_start_date` date DEFAULT NULL,
  `internship_end_date` date DEFAULT NULL,
  `internship_status` enum('Pending','Applied','Accepted','Rejected','Active','Completed') DEFAULT 'Pending',
  PRIMARY KEY (`internship_id`),
  KEY `idx_internship_student` (`student_id`),
  KEY `idx_internship_company` (`company_id`),
  KEY `idx_internship_lecturer` (`lecturer_id`),
  KEY `idx_internship_job` (`job_id`),
  CONSTRAINT `fk_internship_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_internship_job` FOREIGN KEY (`job_id`) REFERENCES `jobposting` (`job_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_internship_lecturer` FOREIGN KEY (`lecturer_id`) REFERENCES `lecturer` (`lecturer_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_internship_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `internship`
--

LOCK TABLES `internship` WRITE;
/*!40000 ALTER TABLE `internship` DISABLE KEYS */;
INSERT INTO `internship` VALUES (1,1,1,1,1,'Accounting Systems','2026-06-01','2026-09-30','Active'),(2,2,2,2,3,'Audit Analytics','2026-06-01','2026-09-30','Active'),(3,5,2,2,3,'Audit Analytics','2026-07-01','2026-10-31','Active'),(4,7,2,1,4,'Audit Analytics','2026-06-12','2026-06-22','Active'),(5,8,6,2,6,'Accounting System','2026-06-09','2026-08-30','Active'),(6,9,7,1,7,'Financial Reporting','2026-06-08','2026-08-31','Active'),(7,10,8,2,8,'Auditing','2026-06-07','2026-09-01','Active'),(8,11,9,1,9,'Tax Compliance','2026-06-06','2026-09-02','Active'),(9,13,11,1,11,'Management Accounting','2026-06-04','2026-09-04','Active'),(10,14,12,2,12,'Accounting System','2026-06-03','2026-09-05','Completed'),(11,15,13,1,13,'Financial Reporting','2026-06-02','2026-09-06','Active'),(12,16,14,2,14,'Auditing','2026-06-01','2026-09-07','Active'),(13,18,16,2,16,'Data Analytics','2026-05-30','2026-09-09','Active'),(14,19,17,1,17,'Management Accounting','2026-05-29','2026-09-10','Active'),(15,20,6,2,6,'Accounting System','2026-05-28','2026-09-11','Active'),(16,21,7,1,7,'Financial Reporting','2026-05-27','2026-09-12','Completed'),(17,23,9,1,9,'Tax Compliance','2026-05-25','2026-09-14','Active'),(18,24,10,2,10,'Data Analytics','2026-05-24','2026-09-15','Active'),(19,25,11,1,11,'Management Accounting','2026-05-23','2026-09-16','Active'),(20,26,12,2,12,'Accounting System','2026-05-22','2026-09-17','Active'),(21,28,14,2,14,'Auditing','2026-05-20','2026-09-19','Completed'),(22,29,15,1,15,'Tax Compliance','2026-05-19','2026-09-20','Active'),(23,30,16,2,16,'Data Analytics','2026-05-18','2026-09-21','Active'),(24,31,17,1,17,'Management Accounting','2026-05-17','2026-09-22','Active');
/*!40000 ALTER TABLE `internship` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `internshiplogbookscore`
--

DROP TABLE IF EXISTS `internshiplogbookscore`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internshiplogbookscore` (
  `ls_id` int(11) NOT NULL AUTO_INCREMENT,
  `internship_id` int(11) NOT NULL,
  `ls_information` int(11) DEFAULT NULL,
  `ls_impact_task` int(11) DEFAULT NULL,
  PRIMARY KEY (`ls_id`),
  UNIQUE KEY `internship_id` (`internship_id`),
  KEY `idx_ls_internship` (`internship_id`),
  CONSTRAINT `fk_ls_internship` FOREIGN KEY (`internship_id`) REFERENCES `internship` (`internship_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `internshiplogbookscore`
--

LOCK TABLES `internshiplogbookscore` WRITE;
/*!40000 ALTER TABLE `internshiplogbookscore` DISABLE KEYS */;
INSERT INTO `internshiplogbookscore` VALUES (1,1,50,10);
/*!40000 ALTER TABLE `internshiplogbookscore` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `internshiporalpresentationscore`
--

DROP TABLE IF EXISTS `internshiporalpresentationscore`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internshiporalpresentationscore` (
  `ops_id` int(11) NOT NULL AUTO_INCREMENT,
  `internship_id` int(11) NOT NULL,
  `ops_organization` int(11) DEFAULT NULL,
  `ops_idea_delivery` int(11) DEFAULT NULL,
  `ops_multimedia_support` int(11) DEFAULT NULL,
  `ops_non_verbal_skills` int(11) DEFAULT NULL,
  `ops_verbal_skills` int(11) DEFAULT NULL,
  PRIMARY KEY (`ops_id`),
  UNIQUE KEY `internship_id` (`internship_id`),
  KEY `idx_ops_internship` (`internship_id`),
  CONSTRAINT `fk_ops_internship` FOREIGN KEY (`internship_id`) REFERENCES `internship` (`internship_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `internshiporalpresentationscore`
--

LOCK TABLES `internshiporalpresentationscore` WRITE;
/*!40000 ALTER TABLE `internshiporalpresentationscore` DISABLE KEYS */;
INSERT INTO `internshiporalpresentationscore` VALUES (1,1,10,10,9,9,10);
/*!40000 ALTER TABLE `internshiporalpresentationscore` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `internshipreportwritingscore`
--

DROP TABLE IF EXISTS `internshipreportwritingscore`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internshipreportwritingscore` (
  `rws_id` int(11) NOT NULL AUTO_INCREMENT,
  `internship_id` int(11) NOT NULL,
  `rws_coherence` int(11) DEFAULT NULL,
  `rws_information` int(11) DEFAULT NULL,
  `rws_analysis` int(11) DEFAULT NULL,
  `rws_grammar_spelling` int(11) DEFAULT NULL,
  `rws_appearance` int(11) DEFAULT NULL,
  `rws_sources_references` int(11) DEFAULT NULL,
  PRIMARY KEY (`rws_id`),
  UNIQUE KEY `internship_id` (`internship_id`),
  KEY `idx_rws_internship` (`internship_id`),
  CONSTRAINT `fk_rws_internship` FOREIGN KEY (`internship_id`) REFERENCES `internship` (`internship_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `internshipreportwritingscore`
--

LOCK TABLES `internshipreportwritingscore` WRITE;
/*!40000 ALTER TABLE `internshipreportwritingscore` DISABLE KEYS */;
INSERT INTO `internshipreportwritingscore` VALUES (1,1,10,10,11,9,10,10);
/*!40000 ALTER TABLE `internshipreportwritingscore` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `internshipsystemscore`
--

DROP TABLE IF EXISTS `internshipsystemscore`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internshipsystemscore` (
  `ss_id` int(11) NOT NULL AUTO_INCREMENT,
  `internship_id` int(11) NOT NULL,
  `ss_data_structure` int(11) DEFAULT NULL,
  `ss_coding_standard` int(11) DEFAULT NULL,
  `ss_system_control` int(11) DEFAULT NULL,
  `ss_user_interface` int(11) DEFAULT NULL,
  `ss_data_maintenance` int(11) DEFAULT NULL,
  `ss_output` int(11) DEFAULT NULL,
  `ss_ability_solve_problem` int(11) DEFAULT NULL,
  PRIMARY KEY (`ss_id`),
  UNIQUE KEY `internship_id` (`internship_id`),
  KEY `idx_ss_internship` (`internship_id`),
  CONSTRAINT `fk_ss_internship` FOREIGN KEY (`internship_id`) REFERENCES `internship` (`internship_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `internshipsystemscore`
--

LOCK TABLES `internshipsystemscore` WRITE;
/*!40000 ALTER TABLE `internshipsystemscore` DISABLE KEYS */;
INSERT INTO `internshipsystemscore` VALUES (1,1,10,9,10,10,9,10,10);
/*!40000 ALTER TABLE `internshipsystemscore` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobposting`
--

DROP TABLE IF EXISTS `jobposting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobposting` (
  `job_id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `job_title` varchar(200) NOT NULL,
  `job_description` text DEFAULT NULL,
  `job_location` varchar(255) DEFAULT NULL,
  `job_status` enum('Active','Expiring') DEFAULT 'Active',
  `job_requirement` text DEFAULT NULL,
  `job_allowance_range` varchar(40) DEFAULT NULL,
  `job_posted_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`job_id`),
  KEY `fk_job_company` (`company_id`),
  CONSTRAINT `fk_job_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobposting`
--

LOCK TABLES `jobposting` WRITE;
/*!40000 ALTER TABLE `jobposting` DISABLE KEYS */;
INSERT INTO `jobposting` VALUES (1,1,'Accounting Information System Intern','Demo dashboard internship posting with realistic analytics data.','Cyberjaya','Active','Accounting IS student, SQL basics, documentation skills.',NULL,'2026-06-09 18:35:23'),(2,1,'Accounting Information System Intern','Demo dashboard internship posting with realistic analytics data.','Hybrid','Active','Attention to detail and basic web testing.',NULL,'2026-06-09 18:35:23'),(3,2,'Accounting Information System Intern','Demo dashboard internship posting with realistic analytics data.','Kuala Lumpur','Active','Accounting student, spreadsheet and analytical skills.',NULL,'2026-06-09 18:35:23'),(4,2,'Accounting Information System Intern','Demo dashboard internship posting with realistic analytics data.','Johor','Active','ndsjcs','RM901 - RM1,200','2026-06-09 18:35:23'),(5,2,'Accounting Information System Intern','Demo dashboard internship posting with realistic analytics data.','Kelantan','Active','gitu ni','Above RM1,200','2026-06-10 17:03:25'),(6,6,'Accounting System Intern','Demo dashboard internship posting with realistic analytics data.','Selangor','Active','Accounting knowledge, Excel skills and professional communication.',NULL,'2026-06-09 00:00:00'),(7,7,'Financial Reporting Intern','Demo dashboard internship posting with realistic analytics data.','Kuala Lumpur','Active','Accounting knowledge, Excel skills and professional communication.',NULL,'2026-06-08 00:00:00'),(8,8,'Auditing Intern','Demo dashboard internship posting with realistic analytics data.','Johor','Active','Accounting knowledge, Excel skills and professional communication.',NULL,'2026-06-07 00:00:00'),(9,9,'Tax Compliance Intern','Demo dashboard internship posting with realistic analytics data.','Pulau Pinang','Active','Accounting knowledge, Excel skills and professional communication.',NULL,'2026-06-06 00:00:00'),(10,10,'Data Analytics Intern','Demo dashboard internship posting with realistic analytics data.','Perak','Active','Accounting knowledge, Excel skills and professional communication.',NULL,'2026-06-05 00:00:00'),(11,11,'Management Accounting Intern','Demo dashboard internship posting with realistic analytics data.','Kedah','Active','Accounting knowledge, Excel skills and professional communication.',NULL,'2026-06-04 00:00:00'),(12,12,'Accounting System Intern','Demo dashboard internship posting with realistic analytics data.','Pahang','Active','Accounting knowledge, Excel skills and professional communication.',NULL,'2026-06-03 00:00:00'),(13,13,'Financial Reporting Intern','Demo dashboard internship posting with realistic analytics data.','Sabah','Active','Accounting knowledge, Excel skills and professional communication.',NULL,'2026-06-02 00:00:00'),(14,14,'Auditing Intern','Demo dashboard internship posting with realistic analytics data.','Sarawak','Active','Accounting knowledge, Excel skills and professional communication.',NULL,'2026-06-01 00:00:00'),(15,15,'Tax Compliance Intern','Demo dashboard internship posting with realistic analytics data.','Melaka','Active','Accounting knowledge, Excel skills and professional communication.',NULL,'2026-05-31 00:00:00'),(16,16,'Data Analytics Intern','Demo dashboard internship posting with realistic analytics data.','Terengganu','Active','Accounting knowledge, Excel skills and professional communication.',NULL,'2026-05-30 00:00:00'),(17,17,'Management Accounting Intern','Demo dashboard internship posting with realistic analytics data.','Kelantan','Active','Accounting knowledge, Excel skills and professional communication.',NULL,'2026-05-29 00:00:00');
/*!40000 ALTER TABLE `jobposting` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lecturer`
--

DROP TABLE IF EXISTS `lecturer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lecturer` (
  `lecturer_id` int(11) NOT NULL AUTO_INCREMENT,
  `lecturer_staff_id` varchar(30) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `lecturer_programme` enum('Bachelor of Accounting (Hons)','Bachelor of Accounting (Information Systems) (Hons)') NOT NULL,
  `lecturer_name` varchar(100) NOT NULL,
  `lecturer_gender` enum('Male','Female','Other') DEFAULT NULL,
  `lecturer_email` varchar(150) NOT NULL,
  `lecturer_phone` varchar(20) DEFAULT NULL,
  `lecturer_office_phone` varchar(20) DEFAULT NULL,
  `lecturer_department` varchar(100) DEFAULT NULL,
  `lecturer_role` varchar(50) DEFAULT 'Academic Advisor',
  `lecturer_max_student` int(11) DEFAULT 10,
  PRIMARY KEY (`lecturer_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `lecturer_email` (`lecturer_email`),
  UNIQUE KEY `lecturer_staff_id` (`lecturer_staff_id`),
  KEY `idx_lecturer_user` (`user_id`),
  CONSTRAINT `fk_lecturer_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lecturer`
--

LOCK TABLES `lecturer` WRITE;
/*!40000 ALTER TABLE `lecturer` DISABLE KEYS */;
INSERT INTO `lecturer` VALUES (1,'LECT001',2,'Bachelor of Accounting (Information Systems) (Hons)','Dr Aina Rahman','Female','aina.lecturer@internhub.test','012-200 1001','03-5544 2101','Accounting Information Systems','Academic Advisor',10),(2,'LECT002',3,'Bachelor of Accounting (Hons)','Ts Muhammad Hakim','Male','hakim.lecturer@internhub.test','012-200 1002','03-5544 2102','Accounting','Academic Advisor',8);
/*!40000 ALTER TABLE `lecturer` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lecturerevaluation`
--

DROP TABLE IF EXISTS `lecturerevaluation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lecturerevaluation` (
  `le_id` int(11) NOT NULL AUTO_INCREMENT,
  `internship_id` int(11) NOT NULL,
  `rws_id` int(11) DEFAULT NULL,
  `ls_id` int(11) DEFAULT NULL,
  `ss_id` int(11) DEFAULT NULL,
  `ops_id` int(11) DEFAULT NULL,
  `le_total_score` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`le_id`),
  UNIQUE KEY `internship_id` (`internship_id`),
  KEY `idx_le_internship` (`internship_id`),
  KEY `fk_le_rws` (`rws_id`),
  KEY `fk_le_ls` (`ls_id`),
  KEY `fk_le_ss` (`ss_id`),
  KEY `fk_le_ops` (`ops_id`),
  CONSTRAINT `fk_le_internship` FOREIGN KEY (`internship_id`) REFERENCES `internship` (`internship_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_le_ls` FOREIGN KEY (`ls_id`) REFERENCES `internshiplogbookscore` (`ls_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_le_ops` FOREIGN KEY (`ops_id`) REFERENCES `internshiporalpresentationscore` (`ops_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_le_rws` FOREIGN KEY (`rws_id`) REFERENCES `internshipreportwritingscore` (`rws_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_le_ss` FOREIGN KEY (`ss_id`) REFERENCES `internshipsystemscore` (`ss_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lecturerevaluation`
--

LOCK TABLES `lecturerevaluation` WRITE;
/*!40000 ALTER TABLE `lecturerevaluation` DISABLE KEYS */;
INSERT INTO `lecturerevaluation` VALUES (1,1,1,1,1,1,51.25);
/*!40000 ALTER TABLE `lecturerevaluation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logbook`
--

DROP TABLE IF EXISTS `logbook`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logbook` (
  `logbook_id` int(11) NOT NULL AUTO_INCREMENT,
  `internship_id` int(11) NOT NULL,
  `logbook_week_no` int(11) NOT NULL,
  `logbook_task` text DEFAULT NULL,
  `logbook_problem` text DEFAULT NULL,
  `logbook_solutions` text DEFAULT NULL,
  `logbook_status` enum('Submit','Review') DEFAULT 'Submit',
  `logbook_submitted_date` date DEFAULT NULL,
  PRIMARY KEY (`logbook_id`),
  KEY `idx_logbook_internship` (`internship_id`),
  CONSTRAINT `fk_logbook_internship` FOREIGN KEY (`internship_id`) REFERENCES `internship` (`internship_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logbook`
--

LOCK TABLES `logbook` WRITE;
/*!40000 ALTER TABLE `logbook` DISABLE KEYS */;
INSERT INTO `logbook` VALUES (1,1,1,'Completed onboarding, studied ERP module workflow and prepared user notes.','Needed clarity on approval rules.','Discussed with supervisor and updated workflow notes.','Submit','2026-06-07'),(2,1,2,'Tested purchase order module and recorded defects.','Some test data was incomplete.','Created a checklist for cleaner test setup.','Review','2026-06-14'),(3,2,1,'Prepared audit lead schedule and reconciled sample transactions.','Bank reference numbers were inconsistent.','Mapped references manually and documented exceptions.','Submit','2026-06-07'),(4,3,1,'Built dashboard draft for expense trend analysis.','Unsure which KPIs were most useful.','Proposed KPI list for supervisor feedback.','Submit','2026-07-07'),(5,4,45,'makan','saya suka makan','order amirul tomyam','Review','2026-06-10');
/*!40000 ALTER TABLE `logbook` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login`
--

DROP TABLE IF EXISTS `login`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login` (
  `login_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `login_username` varchar(100) NOT NULL,
  `login_status` enum('Success','Failed') DEFAULT 'Success',
  `login_datetime` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`login_id`),
  KEY `idx_login_user` (`user_id`),
  CONSTRAINT `fk_login_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login`
--

LOCK TABLES `login` WRITE;
/*!40000 ALTER TABLE `login` DISABLE KEYS */;
/*!40000 ALTER TABLE `login` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `registration_attempt`
--

DROP TABLE IF EXISTS `registration_attempt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registration_attempt` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(60) NOT NULL,
  `attempted_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`attempt_id`),
  KEY `idx_registration_ip_time` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registration_attempt`
--

LOCK TABLES `registration_attempt` WRITE;
/*!40000 ALTER TABLE `registration_attempt` DISABLE KEYS */;
INSERT INTO `registration_attempt` VALUES (1,'::1','2026-06-09 16:30:07'),(2,'::1','2026-06-09 16:30:27');
/*!40000 ALTER TABLE `registration_attempt` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `report`
--

DROP TABLE IF EXISTS `report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `internship_id` int(11) NOT NULL,
  `ce_id` int(11) DEFAULT NULL,
  `le_id` int(11) DEFAULT NULL,
  `report_total_score` decimal(5,2) DEFAULT NULL,
  `report_grade` enum('A+','A','A-','B+','B','B-','C+','C','C-','D+','D','F') DEFAULT NULL,
  PRIMARY KEY (`report_id`),
  UNIQUE KEY `internship_id` (`internship_id`),
  KEY `idx_report_internship` (`internship_id`),
  KEY `fk_report_ce` (`ce_id`),
  KEY `fk_report_le` (`le_id`),
  CONSTRAINT `fk_report_ce` FOREIGN KEY (`ce_id`) REFERENCES `companyevaluation` (`ce_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_report_internship` FOREIGN KEY (`internship_id`) REFERENCES `internship` (`internship_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_report_le` FOREIGN KEY (`le_id`) REFERENCES `lecturerevaluation` (`le_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `report`
--

LOCK TABLES `report` WRITE;
/*!40000 ALTER TABLE `report` DISABLE KEYS */;
INSERT INTO `report` VALUES (1,1,1,1,87.92,'A'),(2,2,2,NULL,33.33,NULL),(3,5,NULL,NULL,69.00,'B'),(4,6,NULL,NULL,76.00,'A-'),(5,7,NULL,NULL,83.00,'A'),(6,8,NULL,NULL,90.00,'A+'),(7,9,NULL,NULL,72.00,'B+'),(8,10,NULL,NULL,79.00,'A-'),(9,11,NULL,NULL,86.00,'A'),(10,12,NULL,NULL,93.00,'A+'),(11,13,NULL,NULL,75.00,'A-'),(12,14,NULL,NULL,82.00,'A'),(13,15,NULL,NULL,89.00,'A'),(14,16,NULL,NULL,64.00,'B-'),(15,17,NULL,NULL,78.00,'A-'),(16,18,NULL,NULL,85.00,'A'),(17,19,NULL,NULL,92.00,'A+'),(18,20,NULL,NULL,67.00,'B'),(19,21,NULL,NULL,81.00,'A'),(20,22,NULL,NULL,88.00,'A'),(21,23,NULL,NULL,63.00,'B-'),(22,24,NULL,NULL,70.00,'B+');
/*!40000 ALTER TABLE `report` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resume`
--

DROP TABLE IF EXISTS `resume`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resume` (
  `resume_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `resume_internship_start_date` date DEFAULT NULL,
  `resume_internship_end_date` date DEFAULT NULL,
  `resume_description` text DEFAULT NULL,
  `resume_skills` text DEFAULT NULL,
  `resume_experience` text DEFAULT NULL,
  `resume_education` text DEFAULT NULL,
  `resume_certificate` text DEFAULT NULL,
  `resume_language` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`resume_id`),
  UNIQUE KEY `student_id` (`student_id`),
  CONSTRAINT `fk_resume_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resume`
--

LOCK TABLES `resume` WRITE;
/*!40000 ALTER TABLE `resume` DISABLE KEYS */;
INSERT INTO `resume` VALUES (1,1,'2026-06-01','2026-09-30','Accounting Information Systems student interested in ERP and audit technology.','SQL, PHP, Excel, documentation, teamwork','Student project - Inventory control prototype','Bachelor of Accounting (Information Systems) (Hons), A251','Excel Associate','English, Malay'),(2,2,'2026-06-01','2026-09-30','Accounting student with interest in audit and financial reporting. ','Excel, audit sampling, reconciliation, communication','Treasurer - Accounting Club','Bachelor of Accounting (Hons), A251','MYOB basics','English, Malay, Mandarin'),(3,3,'2026-07-01','2026-10-31','Information systems student seeking hands-on analytics experience.','Power BI, SQL, Excel, requirements gathering','Dashboard coursework project','Bachelor of Accounting (Information Systems) (Hons), A252','Power BI fundamentals','English, Malay'),(4,4,'2026-06-01','2026-09-30','Accounting student currently inactive for testing.','Excel, bookkeeping','Part-time accounts assistant','Bachelor of Accounting (Hons), A242','','English, Mandarin'),(5,5,'2026-07-01','2026-10-31','Accounting student focused on audit analytics.','Excel, Power Query, financial analysis','Volunteer tax clinic','Bachelor of Accounting (Hons), A252','Data analytics microcredential','English, Malay'),(6,7,'2026-06-26','2026-06-29','djfksfb','bfkjcsdbs','ndskjnds','bjkdssd',' djkcs','bjkdssd'),(7,6,'2026-06-25','2026-06-26','fjsdfnw','vnekjf','bfwkjfwf','ewdfnwk','djcndk','vdsjbssd'),(8,8,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Hons)','Demo analytics certificate','English, Malay'),(9,9,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Hons)','Demo analytics certificate','English, Malay'),(10,10,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Information Systems) (Hons)','Demo analytics certificate','English, Malay'),(11,11,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Hons)','Demo analytics certificate','English, Malay'),(12,12,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Hons)','Demo analytics certificate','English, Malay'),(13,13,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Information Systems) (Hons)','Demo analytics certificate','English, Malay'),(14,14,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Hons)','Demo analytics certificate','English, Malay'),(15,15,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Hons)','Demo analytics certificate','English, Malay'),(16,16,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Information Systems) (Hons)','Demo analytics certificate','English, Malay'),(17,17,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Hons)','Demo analytics certificate','English, Malay'),(18,18,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Hons)','Demo analytics certificate','English, Malay'),(19,19,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Information Systems) (Hons)','Demo analytics certificate','English, Malay'),(20,20,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Hons)','Demo analytics certificate','English, Malay'),(21,21,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Hons)','Demo analytics certificate','English, Malay'),(22,22,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Information Systems) (Hons)','Demo analytics certificate','English, Malay'),(23,23,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Hons)','Demo analytics certificate','English, Malay'),(24,24,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Hons)','Demo analytics certificate','English, Malay'),(25,25,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Information Systems) (Hons)','Demo analytics certificate','English, Malay'),(26,26,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Hons)','Demo analytics certificate','English, Malay'),(27,27,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Hons)','Demo analytics certificate','English, Malay'),(28,28,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Information Systems) (Hons)','Demo analytics certificate','English, Malay'),(29,29,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Hons)','Demo analytics certificate','English, Malay'),(30,30,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Hons)','Demo analytics certificate','English, Malay'),(31,31,'2026-06-01','2026-09-30','Demo dashboard resume profile.','Excel, reporting, analytics, communication','Course project and club activity','Bachelor of Accounting (Information Systems) (Hons)','Demo analytics certificate','English, Malay');
/*!40000 ALTER TABLE `resume` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_state`
--

DROP TABLE IF EXISTS `security_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_state` (
  `security_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_email` varchar(150) NOT NULL,
  `user_role` varchar(30) NOT NULL,
  `failed_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `password_history` text DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_device` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 1,
  `approval_status` enum('Pending','Approved','Rejected') DEFAULT 'Approved',
  `reset_token` varchar(120) DEFAULT NULL,
  `reset_expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`security_id`),
  UNIQUE KEY `unique_security_user` (`user_email`,`user_role`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_state`
--

LOCK TABLES `security_state` WRITE;
/*!40000 ALTER TABLE `security_state` DISABLE KEYS */;
INSERT INTO `security_state` VALUES (1,'juliana@gmail.com','student',1,NULL,NULL,NULL,NULL,1,'Approved',NULL,NULL),(2,'coordinator@internhub.test','coordinator',0,NULL,NULL,'2026-06-09 16:13:48','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0',1,'Approved',NULL,NULL),(3,'aina.lecturer@internhub.test','lecturer',0,NULL,NULL,'2026-06-09 16:14:46','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0',1,'Approved',NULL,NULL),(4,'hr@finedge.test','company',0,NULL,NULL,'2026-06-09 16:15:08','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0',1,'Approved',NULL,NULL),(5,'gkdfnkds@gmail.com','company',0,NULL,'[\"$2y$10$94xPmeCbsvs2W2ZEWP7O9uEYxm7EHfWxPYcrKS3niQO0fRMDZFY\\/C\"]',NULL,NULL,0,'Pending',NULL,NULL);
/*!40000 ALTER TABLE `security_state` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student`
--

DROP TABLE IF EXISTS `student`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student` (
  `student_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_matric_no` varchar(30) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `student_course` enum('Bachelor of Accounting (Hons)','Bachelor of Accounting (Information Systems) (Hons)') NOT NULL,
  `student_intake` varchar(10) NOT NULL,
  `student_phone` varchar(20) DEFAULT NULL,
  `student_email` varchar(150) NOT NULL,
  `student_gender` enum('Male','Female','Other') DEFAULT NULL,
  `student_ic` varchar(20) NOT NULL,
  `student_status` enum('Active','Inactive') DEFAULT 'Active',
  `student_address` varchar(255) DEFAULT NULL,
  `student_postcode` varchar(5) DEFAULT NULL,
  `student_state` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `student_matric_no` (`student_matric_no`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `student_email` (`student_email`),
  UNIQUE KEY `student_ic` (`student_ic`),
  KEY `idx_student_user` (`user_id`),
  CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student`
--

LOCK TABLES `student` WRITE;
/*!40000 ALTER TABLE `student` DISABLE KEYS */;
INSERT INTO `student` VALUES (1,'A25CS001','Nur Aisyah Zain',4,'Bachelor of Accounting (Information Systems) (Hons)','A251','011-100 1001','aisyah.student@internhub.test','Female','030101-14-5001','Active',NULL,NULL,NULL),(2,'A25AC002','Daniel Tan',5,'Bachelor of Accounting (Hons)','A251','011-100 1002','daniel.student@internhub.test','Male','030202-10-6002','Active',NULL,NULL,NULL),(3,'A25IS003','Siti Hajar',6,'Bachelor of Accounting (Information Systems) (Hons)','A252','011-100 1003','siti.student@internhub.test','Female','030303-08-7003','Active',NULL,NULL,NULL),(4,'A24AC004','Lim Wei Jian',7,'Bachelor of Accounting (Hons)','A242','011-100 1004','weijian.student@internhub.test','Male','020404-07-8004','Inactive',NULL,NULL,NULL),(5,'A25AC005','Farah Nabila',10,'Bachelor of Accounting (Hons)','A252','011-100 1005','farah.student@internhub.test','Female','030505-06-9005','Inactive',NULL,NULL,NULL),(6,'A25AC012','ku nuraliya',11,'Bachelor of Accounting (Hons)','A251','0195343142','kunur@gmail.com','Female','040103494242','Active',NULL,NULL,NULL),(7,'297314','NORJULIANA BINTI MUHAMAD FAZLI',12,'Bachelor of Accounting (Hons)','A251','0174352336','juliana@gmail.com','Male','040504010242','Active',NULL,NULL,NULL),(8,'DD00001','Alya Sofea',28,'Bachelor of Accounting (Hons)','A251','011-2200001','alyasofea@demodash.internhub.test','Male','040000000001','Active','DemoDash student address, Malaysia',NULL,NULL),(9,'DD00002','Irfan Hakimi',29,'Bachelor of Accounting (Hons)','A251','011-2200002','irfanhakimi@demodash.internhub.test','Female','040000000002','Active','DemoDash student address, Malaysia',NULL,NULL),(10,'DD00003','Mei Ling Tan',30,'Bachelor of Accounting (Information Systems) (Hons)','A252','011-2200003','meilingtan@demodash.internhub.test','Male','040000000003','Active','DemoDash student address, Malaysia',NULL,NULL),(11,'DD00004','Nur Iman',31,'Bachelor of Accounting (Hons)','A251','011-2200004','nuriman@demodash.internhub.test','Female','040000000004','Active','DemoDash student address, Malaysia',NULL,NULL),(12,'DD00005','Arif Danish',32,'Bachelor of Accounting (Hons)','A251','011-2200005','arifdanish@demodash.internhub.test','Male','040000000005','Active','DemoDash student address, Malaysia',NULL,NULL),(13,'DD00006','Priya Nair',33,'Bachelor of Accounting (Information Systems) (Hons)','A252','011-2200006','priyanair@demodash.internhub.test','Female','040000000006','Active','DemoDash student address, Malaysia',NULL,NULL),(14,'DD00007','Haziq Amir',34,'Bachelor of Accounting (Hons)','A251','011-2200007','haziqamir@demodash.internhub.test','Male','040000000007','Active','DemoDash student address, Malaysia',NULL,NULL),(15,'DD00008','Chong Wei Min',35,'Bachelor of Accounting (Hons)','A251','011-2200008','chongweimin@demodash.internhub.test','Female','040000000008','Active','DemoDash student address, Malaysia',NULL,NULL),(16,'DD00009','Sabrina Yusuf',36,'Bachelor of Accounting (Information Systems) (Hons)','A252','011-2200009','sabrinayusuf@demodash.internhub.test','Male','040000000009','Active','DemoDash student address, Malaysia',NULL,NULL),(17,'DD00010','Adam Luqman',37,'Bachelor of Accounting (Hons)','A251','011-2200010','adamluqman@demodash.internhub.test','Female','040000000010','Active','DemoDash student address, Malaysia',NULL,NULL),(18,'DD00011','Nadia Farhan',38,'Bachelor of Accounting (Hons)','A251','011-2200011','nadiafarhan@demodash.internhub.test','Male','040000000011','Active','DemoDash student address, Malaysia',NULL,NULL),(19,'DD00012','Zara Amani',39,'Bachelor of Accounting (Information Systems) (Hons)','A252','011-2200012','zaraamani@demodash.internhub.test','Female','040000000012','Active','DemoDash student address, Malaysia',NULL,NULL),(20,'DD00013','Hakim Zulkifli',40,'Bachelor of Accounting (Hons)','A251','011-2200013','hakimzulkifli@demodash.internhub.test','Male','040000000013','Active','DemoDash student address, Malaysia',NULL,NULL),(21,'DD00014','Aina Syahirah',41,'Bachelor of Accounting (Hons)','A251','011-2200014','ainasyahirah@demodash.internhub.test','Female','040000000014','Active','DemoDash student address, Malaysia',NULL,NULL),(22,'DD00015','Marcus Lee',42,'Bachelor of Accounting (Information Systems) (Hons)','A252','011-2200015','marcuslee@demodash.internhub.test','Male','040000000015','Active','DemoDash student address, Malaysia',NULL,NULL),(23,'DD00016','Puteri Balqis',43,'Bachelor of Accounting (Hons)','A251','011-2200016','puteribalqis@demodash.internhub.test','Female','040000000016','Active','DemoDash student address, Malaysia',NULL,NULL),(24,'DD00017','Rania Batrisya',44,'Bachelor of Accounting (Hons)','A251','011-2200017','raniabatrisya@demodash.internhub.test','Male','040000000017','Active','DemoDash student address, Malaysia',NULL,NULL),(25,'DD00018','Jia Xin Wong',45,'Bachelor of Accounting (Information Systems) (Hons)','A252','011-2200018','jiaxinwong@demodash.internhub.test','Female','040000000018','Active','DemoDash student address, Malaysia',NULL,NULL),(26,'DD00019','Amirul Hafiz',46,'Bachelor of Accounting (Hons)','A251','011-2200019','amirulhafiz@demodash.internhub.test','Male','040000000019','Active','DemoDash student address, Malaysia',NULL,NULL),(27,'DD00020','Dina Maisarah',47,'Bachelor of Accounting (Hons)','A251','011-2200020','dinamaisarah@demodash.internhub.test','Female','040000000020','Active','DemoDash student address, Malaysia',NULL,NULL),(28,'DD00021','Syafiq Azlan',48,'Bachelor of Accounting (Information Systems) (Hons)','A252','011-2200021','syafiqazlan@demodash.internhub.test','Male','040000000021','Active','DemoDash student address, Malaysia',NULL,NULL),(29,'DD00022','Mira Qistina',49,'Bachelor of Accounting (Hons)','A251','011-2200022','miraqistina@demodash.internhub.test','Female','040000000022','Active','DemoDash student address, Malaysia',NULL,NULL),(30,'DD00023','Farid Hakim',50,'Bachelor of Accounting (Hons)','A251','011-2200023','faridhakim@demodash.internhub.test','Male','040000000023','Active','DemoDash student address, Malaysia',NULL,NULL),(31,'DD00024','Elina Chong',51,'Bachelor of Accounting (Information Systems) (Hons)','A252','011-2200024','elinachong@demodash.internhub.test','Female','040000000024','Active','DemoDash student address, Malaysia',NULL,NULL);
/*!40000 ALTER TABLE `student` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(100) NOT NULL,
  `user_email` varchar(150) NOT NULL,
  `user_password` varchar(255) NOT NULL,
  `user_status` enum('Active','Inactive') DEFAULT 'Active',
  `user_created_by` int(11) DEFAULT NULL,
  `user_role` enum('student','lecturer','company','coordinator') NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_email` (`user_email`),
  KEY `fk_user_created_by` (`user_created_by`),
  KEY `idx_user_email` (`user_email`),
  CONSTRAINT `fk_user_created_by` FOREIGN KEY (`user_created_by`) REFERENCES `user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (1,'Dr Hazrami','coordinator@internhub.test','$2y$10$rCsXwVeYns/B9YYmVqlQUOnQ9ZJX1ZsH0BszMDsoJk5YJ4myoPL2W','Active',NULL,'coordinator'),(2,'Dr Aina Rahman','aina.lecturer@internhub.test','$2y$10$rCsXwVeYns/B9YYmVqlQUOnQ9ZJX1ZsH0BszMDsoJk5YJ4myoPL2W','Active',1,'lecturer'),(3,'Ts Muhammad Hakim','hakim.lecturer@internhub.test','$2y$10$rCsXwVeYns/B9YYmVqlQUOnQ9ZJX1ZsH0BszMDsoJk5YJ4myoPL2W','Active',1,'lecturer'),(4,'Nur Aisyah Zain','aisyah.student@internhub.test','$2y$10$rCsXwVeYns/B9YYmVqlQUOnQ9ZJX1ZsH0BszMDsoJk5YJ4myoPL2W','Active',1,'student'),(5,'Daniel Tan','daniel.student@internhub.test','$2y$10$rCsXwVeYns/B9YYmVqlQUOnQ9ZJX1ZsH0BszMDsoJk5YJ4myoPL2W','Active',1,'student'),(6,'Siti Hajar','siti.student@internhub.test','$2y$10$rCsXwVeYns/B9YYmVqlQUOnQ9ZJX1ZsH0BszMDsoJk5YJ4myoPL2W','Active',1,'student'),(7,'Lim Wei Jian','weijian.student@internhub.test','$2y$10$rCsXwVeYns/B9YYmVqlQUOnQ9ZJX1ZsH0BszMDsoJk5YJ4myoPL2W','Inactive',1,'student'),(8,'TechNova Sdn Bhd','hr@technova.test','$2y$10$rCsXwVeYns/B9YYmVqlQUOnQ9ZJX1ZsH0BszMDsoJk5YJ4myoPL2W','Active',1,'company'),(9,'FinEdge Analytics','hr@finedge.test','$2y$10$rCsXwVeYns/B9YYmVqlQUOnQ9ZJX1ZsH0BszMDsoJk5YJ4myoPL2W','Active',1,'company'),(10,'Farah Nabila','farah.student@internhub.test','$2y$10$rCsXwVeYns/B9YYmVqlQUOnQ9ZJX1ZsH0BszMDsoJk5YJ4myoPL2W','Inactive',1,'student'),(11,'ku nuraliya','kunur@gmail.com','$2y$10$WsFkSWNc4Sh89QpLbhY/Wer2lPD5mB48J6rlXgfkvu7SHyuOKAjIi','Active',1,'student'),(12,'NORJULIANA BINTI MUHAMAD FAZLI','juliana@gmail.com','$2y$10$TABLXHQNo2Ng4RMA/Sjode7IvChWtKQaFxH0ltkCPVsvks.g794Me','Active',1,'student'),(13,'ju comel','gkdfnkds@gmail.com','$2y$10$wKvi.ZxeQz4RcoJLLszPLOff.8fUvkAYbhpfFqN3ItG7SF/W.Xxq2','Active',NULL,'company'),(14,'ju comel','nejd@gmail.com','$2y$10$fAjs6yvoBaYEOOZ2Ye0eR.oUjZQXm3uUR1XN.IoGWpRGwXn.P/0Je','Active',NULL,'company'),(15,'amirul tomyam','rosni@gmail.com','$2y$10$wIB69TkJONcE4R0yq4un/eHf9LGJQ/7QH7xobW2beJC3U/q4WZTie','Active',NULL,'company'),(16,'AuditPro Selangor','demodash.company1@internhub.test','$2y$10$FIrGKpF0/18kl7qMkUScEOgxyvD0pVF8Vb5Kb9lPbL.DAYnx6WaBa','Active',1,'company'),(17,'KL Tax Advisory','demodash.company2@internhub.test','$2y$10$.w2HDCiRjyzdYgCHEyZpxuHNbylHwdAGb.tNE0zdj2KDgDRQU1V3u','Active',1,'company'),(18,'Johor Ledger Hub','demodash.company3@internhub.test','$2y$10$tRxB/sRvwVW2JI1ZLr99Ou7glt8gqiEMEuvkKE/ocemaXjkfUeaSm','Active',1,'company'),(19,'Penang FinTech Accounts','demodash.company4@internhub.test','$2y$10$vUDqM3a8w6xYUfBnQp/S1.rOvlq48s6IB.FEgzrySn1QPdAe2spB.','Active',1,'company'),(20,'Perak Assurance Group','demodash.company5@internhub.test','$2y$10$CEPISr8R/OWvLEcWPFLBU.S.jurhHYlmHkEIKsbYIrj3sxDcL6xji','Active',1,'company'),(21,'Kedah Agro Accounts','demodash.company6@internhub.test','$2y$10$FbPgUaR4wBdHke4fmut02uyA3sOSgD1BMOnzXH5d7.VK2EYpz3w9S','Active',1,'company'),(22,'Pahang Corporate Services','demodash.company7@internhub.test','$2y$10$JoqvgviRYss4Gazd/cf4yOtxjiEY0ZjLWzNOdwByvDDlEkUeNpYRC','Active',1,'company'),(23,'Sabah Cloud Finance','demodash.company8@internhub.test','$2y$10$9D9kI.P08zI24dtYig7DreUEKWbXlOTHmvpaUsEO/lU0IVWuTcrz2','Active',1,'company'),(24,'Sarawak Management Accounting','demodash.company9@internhub.test','$2y$10$HtNsGQJswD7OoNGZw9cX/u0l2p55BxSZasEu88aB0YvoG.nNharLO','Active',1,'company'),(25,'Melaka Compliance Centre','demodash.company10@internhub.test','$2y$10$UHPcJIyULqQoZMh3G7CbzOv8Xe.lK4.yNcRLlUk.YO8HeFLW1iXTe','Active',1,'company'),(26,'Terengganu Zakat Finance','demodash.company11@internhub.test','$2y$10$hk8Ju/LKp2f.jhZyrqFVouOM3K7GFj0fsl8WA0Pm.FW4uTO27rsJm','Active',1,'company'),(27,'Kelantan SME Advisory','demodash.company12@internhub.test','$2y$10$txYemRlxWDBZmbQjPNp/u.QAsjrw5ZikeP4KI6poaN1ZoboXa8QDW','Active',1,'company'),(28,'Alya Sofea','alyasofea@demodash.internhub.test','$2y$10$qFmrEatz78gARoUGO3fzi.nx77DdWCBtrF24hykEG3B.bIljXs522','Active',1,'student'),(29,'Irfan Hakimi','irfanhakimi@demodash.internhub.test','$2y$10$zYNe1kN24iYufbQgLa3pHeY6MjzZ95xkQTEmDc1KjjS2G5HsrC5La','Active',1,'student'),(30,'Mei Ling Tan','meilingtan@demodash.internhub.test','$2y$10$75KSkjht/sZojHwK/dp83.eFuaalvNyJFwRH9Sxr9Y97uHM40pY2C','Active',1,'student'),(31,'Nur Iman','nuriman@demodash.internhub.test','$2y$10$nHuJThjjOZhLSHGCYHPp9uzoLaPjDAd67T47VKPVDUnSBjTE.2vge','Active',1,'student'),(32,'Arif Danish','arifdanish@demodash.internhub.test','$2y$10$jczIXLK4HTT/2oBeJ4wRPOzNjWjDu/7gVFNqJb6jdc3vpFvD4LN.K','Active',1,'student'),(33,'Priya Nair','priyanair@demodash.internhub.test','$2y$10$.PVvTyoliJrZErrnYvA8auomH3UTFFU5mEodpHUv4gS4dbcjEI2VK','Active',1,'student'),(34,'Haziq Amir','haziqamir@demodash.internhub.test','$2y$10$tZfLjCBi4Oms4MjFQTePj.lLZ4SrvunYzsTArAKxGYjndk4reYTAa','Active',1,'student'),(35,'Chong Wei Min','chongweimin@demodash.internhub.test','$2y$10$SDAIcYx4uLCnnh8kUw4l5.Jcppbe31bEPtnFtfKaJroUGTF2pp4Si','Active',1,'student'),(36,'Sabrina Yusuf','sabrinayusuf@demodash.internhub.test','$2y$10$2n0qjx46POseHvizrTjHXu65rCL6GxU5Kbu5O6mkeyLjc75WzVQEi','Active',1,'student'),(37,'Adam Luqman','adamluqman@demodash.internhub.test','$2y$10$usbxSoZmRhd/8.WLehBxm.Kyin2LX2O9GbJmIysPRIbuzVrNc0wy6','Active',1,'student'),(38,'Nadia Farhan','nadiafarhan@demodash.internhub.test','$2y$10$6sVUSEEXTKWXvqPkQaSiCOQP0ijNuGjlbJk5krwW/gz4fmMuiKvgW','Active',1,'student'),(39,'Zara Amani','zaraamani@demodash.internhub.test','$2y$10$1smx.fID2zzbCqJ4VYy2Qe1V735KAaTiWhwFjb1ihnZdrL6WtjRU2','Active',1,'student'),(40,'Hakim Zulkifli','hakimzulkifli@demodash.internhub.test','$2y$10$ahc2zUQJ2kGcW.jhGgEol.cCIoLNuesA9Pw0nYd6qGrLzEU9LTShW','Active',1,'student'),(41,'Aina Syahirah','ainasyahirah@demodash.internhub.test','$2y$10$fhF3gKWZkc54CBUPMXTxV.EXtORgb8u4AB/vPhPTCOYfHisVrk4EG','Active',1,'student'),(42,'Marcus Lee','marcuslee@demodash.internhub.test','$2y$10$CWNo3L5VKQRR7BvRSbHbE./u/8Coet.NSvjkyzJzWxeI5bJIZNtqC','Active',1,'student'),(43,'Puteri Balqis','puteribalqis@demodash.internhub.test','$2y$10$B7IWJg/vSTduU4yAxiEEDOdRw28RQIK72J8PK5iupT4QpLsQqPeue','Active',1,'student'),(44,'Rania Batrisya','raniabatrisya@demodash.internhub.test','$2y$10$UKSL/TpV8SMnlmf6kZYQgOVpMXmAxK.G4uUhpZSIoeqI0Cxw/1KkC','Active',1,'student'),(45,'Jia Xin Wong','jiaxinwong@demodash.internhub.test','$2y$10$TOMNwB64tTYs23D/N7vco.2YdbXHeBrC4L2yfv6jN8GWAup7At3Sa','Active',1,'student'),(46,'Amirul Hafiz','amirulhafiz@demodash.internhub.test','$2y$10$o1lbF/jOQMQbu0KzZlMJq.XQXA3necBLBxkcadSTxdW0BHz34NdzS','Active',1,'student'),(47,'Dina Maisarah','dinamaisarah@demodash.internhub.test','$2y$10$qoW6UP2G55kmY1UZlbb81.TYK9FaigiN/PhBGHfmQTyTl.qn2Q6Gy','Active',1,'student'),(48,'Syafiq Azlan','syafiqazlan@demodash.internhub.test','$2y$10$tEJEzuk5FpsoDkiOWCGpzeijsXhaqsenM5bPRrbf/lEbsctWB9JWG','Active',1,'student'),(49,'Mira Qistina','miraqistina@demodash.internhub.test','$2y$10$KYhkVRerH4YqBqwTvp7My.3FX94UBzapsvK/6c4X9yRfu9QWYU2vO','Active',1,'student'),(50,'Farid Hakim','faridhakim@demodash.internhub.test','$2y$10$blNTtumL/HluMVH7CaYmJ.99wzGqwlRUzYvIc86SPwyOcl1ob8B0m','Active',1,'student'),(51,'Elina Chong','elinachong@demodash.internhub.test','$2y$10$CRSrRA6TcYQokWt2YO53suUNZ6zCJixhYdLFvSL.V.OYSub/qhEny','Active',1,'student');
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-10 20:37:59
