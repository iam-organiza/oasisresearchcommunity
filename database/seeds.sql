-- ============================================================
-- OasisResearchCommunity — Database Seeds
-- Generated: 2026-03-16 from local development database
--
-- IMPORTANT: Run database/schema.sql FIRST to create tables,
-- then run this file to populate initial data.
--
-- Run in phpMyAdmin → SQL tab, or via CLI:
--   mysql -u <user> -p <dbname> < database/seeds.sql
-- ============================================================
--
-- Host: 127.0.0.1    Database: oasiwpbc_orc
-- Server version: 9.3.0


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- ----------------------------
-- Clear existing data (safe re-run — truncate in reverse FK order)
-- ----------------------------
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `blog_comments`;
TRUNCATE TABLE `blog_posts`;
TRUNCATE TABLE `blog_categories`;
TRUNCATE TABLE `featured_members`;
TRUNCATE TABLE `profiles`;
TRUNCATE TABLE `users`;
TRUNCATE TABLE `settings`;
SET FOREIGN_KEY_CHECKS = 1;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` (`id`, `type`, `value`, `createdAt`) VALUES
(1,  'no_reply_email', 'no-reply@oasisresearchcommunity.org', '2023-01-11 08:43:49'),
(2,  'email_host',     'mail.oasisresearchcommunity.org',     '2023-01-11 08:43:49'),
(3,  'support_email',  'support@oasisresearchcommunity.org',  '2023-01-11 08:44:14'),
(4,  'smtp_port',      '465',                                 '2023-01-11 08:44:14'),
(5,  'base_url',       'https://dev.oasisresearchcommunity.org/', '2023-01-11 08:45:18'),
(6,  'api_base_url',   'https://dev.oasisresearchcommunity.org/api/', '2023-01-11 08:45:18'),
(7,  'facebook_url',   'https://web.facebook.com/OASIS-Journals-106668801373977', '2023-01-11 10:36:19'),
(8,  'instagram_url',  'https://www.instagram.com/oasisjournals/', '2023-01-11 10:36:19'),
(9,  'telegram_url',   'https://t.me/joinchat/TiK_AVkghRRKHyxwFLJHjQ', '2023-01-11 10:36:29'),
(10, 'admin_base_url', 'https://dev.oasisresearchcommunity.org/admin/', '2025-07-01 12:09:44');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`, `user_ref`, `email`, `password`, `verification_code`, `verified`, `verified_at`, `login_attempts`, `locked_until`, `role`, `created_at`) VALUES (1,'8b8d610e-01cd-4ed9-879f-5761a0e9a97f','organizersamuel.os@gmail.com','$2y$10$byymBHa.wlHa7qrAlSUdX.g9qIdCs6tItyMXmsRuYrS6LBAkfUehK',NULL,1,'2025-07-02 14:39:20',0,NULL,'admin','2025-07-02 13:35:48'),(2,'e895c4e0-670a-4956-aff9-184cf5e02a00','gyxywog@mailinator.com','$2y$10$v9TLk/d17fOOPxUB8MpJZOAHDC19wKrd99hiulTPHNOUGCo0gqmwe','811520',0,NULL,0,NULL,'member','2025-07-21 16:39:23'),(3,'5cf5d818-4691-4e02-919e-ae6b85684e60','bevu@mailinator.com','$2y$10$ECj5T3dUct7dvbc6totdqeDhEnNU8mytUQyMoJv7CPHjvgs4s/noO','801328',0,NULL,0,NULL,'member','2025-07-21 16:41:19'),(4,'017e5da2-a47a-4484-bebf-900bcb9c7867','xyfakazo@mailinator.com','$2y$10$ogvNMYSQYxRl4exZdtMQmuZUAorqGi8PmK8VcxvhTf6WObRLArPbu','718713',0,NULL,0,NULL,'member','2025-07-21 16:44:56'),(5,'bb0eba43-6fe0-4de3-b59e-02ec1c81913f','kogotut@mailinator.com','$2y$10$5OejPav20z6V09IAL218p.yYFyDaScXMfyX3nuNvg8t/47jJLmZfC','347729',0,NULL,0,NULL,'member','2025-07-21 16:53:26'),(6,'4cfb5268-d5fa-4295-9472-0acbd883c5c6','qymesy@mailinator.com','$2y$10$OZNL4fwwQiMCjW.lDc76I.ow7AS66Sq.3j620rT3Rm60USPFjjqOm','832795',0,NULL,0,NULL,'member','2025-07-21 16:54:02'),(7,'5b416696-7ea7-421f-90c2-39a8b9b95e2c','pahobebaze@mailinator.com','$2y$10$EH6z2DMdJR4DIVgP9snMeOpOTU3ncEz0fq45e8gl9novzalpvDYI.','555169',0,NULL,0,NULL,'member','2025-07-21 16:55:00'),(8,'43fbc238-57b9-4e1e-bdcd-84ec5379df7e','kyxikil@mailinator.com','$2y$10$pVQwd/r0mcuXKmmxW3dO0.Eu.NJrHkGpM6ZDnMNj.U/9H0GssNgea','432463',0,NULL,0,NULL,'member','2025-07-21 17:00:25'),(9,'8558825d-477f-4847-b2b0-8842842baa97','rytefa@mailinator.com','$2y$10$pbPaDpCKEZIVDRO2Lf8ix.GYxucscx4luSymD3VkL/0snmMofy0wG','695586',0,NULL,0,NULL,'member','2025-07-21 17:10:35'),(10,'d3977bc2-2d73-4e12-8a78-7a0124a0704c','pavy@mailinator.com','$2y$10$0zGN5zLRUVIPrcjILeHduuyonS1DqmuOPjmyzCduuKEU/AIE1l/ri',NULL,1,'2025-07-21 18:39:32',0,NULL,'member','2025-07-21 17:12:49');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `profiles`
--

LOCK TABLES `profiles` WRITE;
/*!40000 ALTER TABLE `profiles` DISABLE KEYS */;
INSERT INTO `profiles` (`id`, `user_ref`, `first_name`, `last_name`, `phone`, `dob`, `address`, `created_at`) VALUES (1,'8b8d610e-01cd-4ed9-879f-5761a0e9a97f','Samuel','Organizer',NULL,NULL,NULL,'2025-07-02 13:35:48'),(2,'e895c4e0-670a-4956-aff9-184cf5e02a00','Armand','Whitfield',NULL,NULL,NULL,'2025-07-21 16:39:23'),(3,'5cf5d818-4691-4e02-919e-ae6b85684e60','Lisandra','Kirk',NULL,NULL,NULL,'2025-07-21 16:41:19'),(4,'017e5da2-a47a-4484-bebf-900bcb9c7867','Curran','Osborn',NULL,NULL,NULL,'2025-07-21 16:44:56'),(5,'bb0eba43-6fe0-4de3-b59e-02ec1c81913f','Harding','Macias',NULL,NULL,NULL,'2025-07-21 16:53:26'),(6,'4cfb5268-d5fa-4295-9472-0acbd883c5c6','Joseph','Marshall',NULL,NULL,NULL,'2025-07-21 16:54:02'),(7,'5b416696-7ea7-421f-90c2-39a8b9b95e2c','Joelle','Sweeney',NULL,NULL,NULL,'2025-07-21 16:55:00'),(8,'43fbc238-57b9-4e1e-bdcd-84ec5379df7e','Hall','Whitney',NULL,NULL,NULL,'2025-07-21 17:00:25'),(9,'8558825d-477f-4847-b2b0-8842842baa97','Ulysses','Donovan',NULL,NULL,NULL,'2025-07-21 17:10:35'),(10,'d3977bc2-2d73-4e12-8a78-7a0124a0704c','Cecilia','Harding',NULL,NULL,NULL,'2025-07-21 17:12:49');
/*!40000 ALTER TABLE `profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `blog_categories`
--

LOCK TABLES `blog_categories` WRITE;
/*!40000 ALTER TABLE `blog_categories` DISABLE KEYS */;
INSERT INTO `blog_categories` (`id`, `categoryRef`, `name`, `slug`, `description`, `created_at`, `updated_at`) VALUES (1,'CAT-ABBE33','travel','travel','','2026-03-12 16:41:46','2026-03-12 16:41:46'),(2,'CAT-551C48','business','business','','2026-03-12 16:41:57','2026-03-12 16:41:57'),(3,'CAT-204857','politics','politics','','2026-03-12 16:42:10','2026-03-12 16:42:10'),(4,'CAT-79D81C','charity','charity','','2026-03-12 16:42:15','2026-03-12 16:42:15'),(5,'CAT-5D4FEF','environment','environment','','2026-03-12 16:42:29','2026-03-12 16:42:29'),(6,'CAT-E0150F','social','social','','2026-03-12 16:42:38','2026-03-12 16:42:38'),(7,'CAT-1957C4','artificial intelligence','artificial-intelligence','','2026-03-12 19:29:53','2026-03-12 19:29:53');
/*!40000 ALTER TABLE `blog_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `blog_posts`
--

LOCK TABLES `blog_posts` WRITE;
/*!40000 ALTER TABLE `blog_posts` DISABLE KEYS */;
INSERT INTO `blog_posts` (`id`, `postRef`, `title`, `slug`, `excerpt`, `content`, `meta_title`, `canonical_url`, `meta_description`, `og_title`, `og_description`, `reading_time`, `status`, `author`, `category`, `tags`, `visibility`, `allow_comments`, `is_featured`, `is_editors_choice`, `is_popular`, `content_type`, `publish_date`, `featured_image`, `og_image`, `created_at`, `updated_at`) VALUES (6,'a00285b4-11e4-4f21-a28d-7a0a2b3c9ab1','Global Business Goal Make Life Easy From Tech','global-business-goal-make-life-easy-from-tech','Quickly predominate enabled technology and web-enabled schemas. Completely evisculate diverse communities whereas pandemic data. Quickly build covalent data after turnkey content. Distinctively reconceptualize customized growth strategies via prospective potentialities. Professionally pursue cutting-edge web-readiness vis-a-vis just in time infrastructures.','<p>Quickly predominate enabled technology and web-enabled schemas. Completely evisculate diverse communities whereas pandemic data. Quickly build covalent data after turnkey content. Distinctively reconceptualize customized growth strategies via prospective potentialities. Professionally pursue cutting-edge web-readiness vis-a-vis just in time infrastructures.<br><br>Conveniently target client-based systems with turnkey sources. Collaboratively syndicate focused opportunities for interactive deliverables. Assertively initiate client-based infomediaries through collaborative mindshare. Completely create bleeding-edge meta-services through compelling functionalities. Distinctively redefine timely initiatives rather than resource maximizing value.<br><br>Professionally pursue cutting-edge web-readiness vis-a-vis just in time infrastructures. Conveniently target client-based systems with turnkey sources. Collaboratively syndicate focused opportunities for interactive deliverables. Assertively initiate client-based infomediaries through collaborative mindshare. Completely create bleeding-edge meta-services through compelling functionalities.<br><br>Professionally pursue cutting-edge web-readiness vis-a-vis just in time infrastructures. Conveniently target client-based systems with turnkey sources. Collaboratively syndicate focused opportunities for interactive deliverables. Assertively initiate client-based infomediaries through collaborative mindshare. Completely create bleeding-edge meta-services through compelling functionalities.<br><br><img class=\"image_resized\" style=\"width:221px;\"> &nbsp;<img class=\"image_resized\" style=\"width:222px;\"></p><p>Quickly build covalent data after turnkey content. Distinctively reconceptualize customized growth strategies via prospective potentialities. Professionally pursue cutting-edge web-readiness vis-a-vis just in time infrastructures. Conveniently target client-based systems with turnkey sources.<br><br>Collaboratively syndicate focused opportunities for interactive deliverables. Assertively initiate client-based infomediaries through collaborative mindshare create bleeding-edge meta-services</p>','Global Business Goal Make Life Easy From Tech','','Quickly predominate enabled technology and web-enabled schemas. Completely evisculate diverse communities whereas pandemic data. Quickly build covalent data after turnkey content. Distinctively reconceptualize customized growth strategies via prospective potentialities. Professionally pursue cutting-edge web-readiness vis-a-vis just in time infrastructures.','Global Business Goal Make Life Easy From Tech','Quickly predominate enabled technology and web-enabled schemas. Completely evisculate diverse communities whereas pandemic data. Quickly build covalent data after turnkey content. Distinctively reconceptualize customized growth strategies via prospective potentialities. Professionally pursue cutting-edge web-readiness vis-a-vis just in time infrastructures.',25,'Published','Admin','environment','Lawyer, Expert','public',1,1,1,1,'text','2026-03-12 17:41:00','assets/media/blog_images/img_69b2f4cdbba383.09258372.jpg','assets/media/blog_images/img_69b2f4cdbc76a7.02301772.jpg','2026-03-12 17:15:57','2026-03-12 19:00:36'),(7,'e74b24c7-375a-457b-9062-36d8c6544ac4','Why Transparency Matters More Than Ever in Modern Governance','why-transparency-matters-more-than-ever-in-modern-governance','Our newsroom harnesses modern tools and multimedia formats to create a dynamic experience—from live updates and data-driven graphics to interactive features and video coverage.','<p>Quickly predominate enabled technology and web-enabled schemas. Completely evisculate diverse communities whereas pandemic data. Quickly build covalent data after turnkey content. Distinctively reconceptualize customized growth strategies via prospective potentialities. Professionally pursue cutting-edge web-readiness vis-a-vis just in time infrastructures.</p><p>Conveniently target client-based systems with turnkey sources. Collaboratively syndicate focused opportunities for interactive deliverables. Assertively initiate client-based infomediaries through collaborative mindshare. Completely create bleeding-edge meta-services through compelling functionalities. Distinctively redefine timely initiatives rather than resource maximizing value.</p>','Facilis aut inventor','https://www.merawygofyl.info','Dolorem temporibus d','Odit reprehenderit','Quia sed deleniti ve',8,'Published','Admin','artificial intelligence','Technology, Development, AI','public',1,1,0,1,'text','2026-03-12 20:28:00','assets/media/blog_images/img_69b314cb15ce28.70888090.jpg',NULL,'2026-03-12 19:32:27','2026-03-12 20:32:27'),(8,'71eb4490-45b8-40c2-8e57-57632cbb8afb','Top 10 Innovations in Artificial Intelligence for 2026','top-10-innovations-ai-2026','Discover the most groundbreaking AI advancements that are reshaping our future this year.','<p>AI is moving faster than ever. In this post, we explore the top 10 innovations ranging from neural-link interfaces to advanced predictive modeling in climate science...</p>',NULL,NULL,NULL,NULL,NULL,12,'Published','Dr. Aris Thorne','Artificial intelligence','AI, Future, Innovation, Tech','public',1,1,1,0,'text','2026-03-13 15:49:54','assets/media/blog_images/img_69b2f4cdbba383.09258372.jpg',NULL,'2026-03-13 15:49:54','2026-03-13 16:49:54'),(9,'7fbcddfc-8656-4504-a1a5-84445b1a52ee','The Future of Renewable Energy: Beyond Solar and Wind','future-renewable-energy-beyond-solar-wind','Exploring tidal, geothermal, and fusion energy as the next pillars of a sustainable world.','<p>While solar and wind have dominated the green energy landscape, new players are emerging. Tidal energy is seeing massive investment in coastal regions...</p>',NULL,NULL,NULL,NULL,NULL,8,'Published','Sarah Green','Environment','Green Energy, Environment, Sustainability','public',1,1,0,1,'text','2026-03-13 15:49:54','assets/media/blog_images/img_69b2f4cdbc76a7.02301772.jpg',NULL,'2026-03-13 15:49:54','2026-03-13 16:49:54'),(10,'923a3167-29a2-4cf4-9c3d-2f04c475e5bb','Healthcare Revolution: Personalized Medicine and Genomics','healthcare-revolution-personalized-medicine','How genetic sequencing is allowing doctors to tailor treatments to your unique DNA.','<p>The one-size-fits-all approach to medicine is dying. Genomics is enabling a new era where your prescriptions are as unique as your fingerprint...</p>',NULL,NULL,NULL,NULL,NULL,15,'Published','Dr. Elena Vance','Healthcare','Health, DNA, Medicine, Science','public',1,0,1,1,'text','2026-03-13 15:49:54','assets/media/blog_images/img_69b314cb15ce28.70888090.jpg',NULL,'2026-03-13 15:49:54','2026-03-13 16:49:54'),(11,'3da7e4c8-11d2-4e4e-8f6f-2f930348269b','The Rise of Digital Nomads: Impact on Urban Economies','rise-digital-nomads-urban-economies','How the shift to remote work is changing the real estate and service industries in major cities.','<p>Cities are no longer just hubs for offices; they are becoming hubs for lifestyle. Digital nomads are redistributing wealth from tech centers to more affordable urban areas...</p>',NULL,NULL,NULL,NULL,NULL,6,'Published','Mark Steiner','Business','Remote Work, Economy, Business, Travel','public',1,1,0,0,'text','2026-03-13 15:49:54','assets/media/blog_images/img_69b25160b749f5.41805882.png',NULL,'2026-03-13 15:49:54','2026-03-13 16:49:54'),(12,'8629d8d5-781f-4a7e-8dee-788ce082706e','Quantum Computing: Breaking the Unbreakable','quantum-computing-breaking-unbreakable','What happens to modern encryption when quantum advantage becomes a reality?','<p>Quantum computers threaten to render our current RSA encryption obsolete. Scientists are racing to develop post-quantum cryptography to secure the web...</p>',NULL,NULL,NULL,NULL,NULL,10,'Published','Prof. Julian Dax','Technology','Quantum, Encryption, Cyber Security','public',1,0,1,0,'text','2026-03-13 15:49:54','assets/media/blog_images/img_69b250a66e7f62.20481651.png',NULL,'2026-03-13 15:49:54','2026-03-13 16:49:54'),(13,'ddee5a44-f397-40fe-a52d-1986f65662ba','Education 4.0: Gamification in Higher Learning','education-4-gamification-higher-learning','Can turning university courses into interactive games improve retention and student engagement?','<p>Universities are adopting RPG-style progression systems and interactive simulations to make complex subjects like organic chemistry more engaging...</p>',NULL,NULL,NULL,NULL,NULL,7,'Published','Linda Moore','Education','Education, Gamification, Learning','public',1,0,0,1,'text','2026-03-13 15:49:54','assets/media/blog_images/img_69b24f4b213965.50117275.png',NULL,'2026-03-13 15:49:54','2026-03-13 16:49:54');
/*!40000 ALTER TABLE `blog_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `blog_comments`
--

LOCK TABLES `blog_comments` WRITE;
/*!40000 ALTER TABLE `blog_comments` DISABLE KEYS */;
INSERT INTO `blog_comments` (`id`, `post_id`, `parent_id`, `author_name`, `author_email`, `content`, `status`, `created_at`, `updated_at`) VALUES (1,6,NULL,'Test User','test@example.com','This is a test comment.','Approved','2026-03-13 04:04:20','2026-03-13 04:34:37'),(2,7,NULL,'Automated Tester','tester@example.com','This is an automated test comment.','Approved','2026-03-13 04:25:08','2026-03-13 04:34:37'),(3,7,NULL,'Test 2','test2@example.com','Testing again to see the message.','Approved','2026-03-13 04:26:03','2026-03-13 04:34:37'),(4,6,NULL,'Athena Woods','qunuxi@mailinator.com','Id in doloribus quis','Approved','2026-03-13 04:27:10','2026-03-13 04:28:17'),(6,6,1,'Reply User','reply@example.com','This is a test reply.','Approved','2026-03-13 04:04:59','2026-03-13 04:34:37'),(7,7,3,'Replier','replier@example.com','This is a test reply.','Approved','2026-03-13 06:08:05','2026-03-13 07:22:53'),(8,6,4,'samuel organizer','organizersamuel.os@gmail.com','yes you are right','Approved','2026-03-13 06:33:35','2026-03-13 06:34:44'),(9,7,NULL,'Root User','root@example.com','This is a root comment.','Approved','2026-03-13 07:18:14','2026-03-13 07:22:53'),(10,7,7,'Level 4 User','level4@example.com','Level 4 reply testing!','Approved','2026-03-13 07:28:28','2026-03-13 07:33:18'),(11,7,NULL,'Level 4 User','level4@example.com','Level 4 reply testing!','Approved','2026-03-13 07:29:18','2026-03-13 07:33:18'),(12,6,8,'Athena Woods','organizersamuel.os@gmail.com','replying myself','Approved','2026-03-13 07:43:30','2026-03-13 07:44:57'),(13,6,12,'Carly Hampton','kifemyb@mailinator.com','Alias aliquam quia t level 3','Approved','2026-03-13 07:45:27','2026-03-13 07:45:48');
/*!40000 ALTER TABLE `blog_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `featured_members`
--

LOCK TABLES `featured_members` WRITE;
/*!40000 ALTER TABLE `featured_members` DISABLE KEYS */;
INSERT INTO `featured_members` (`id`, `memberRef`, `avatar`, `title`, `first_name`, `last_name`, `middle_name`, `position`, `speciality`, `job_description`, `created_at`) VALUES (11,'2f7839e1-dd1f-473e-ac03-126f8c508eb0','assets/media/avatars/avatar_687e5d980d9493.86130124.jpeg','Dr.','Usman','Lawan','Ali','Fellow','','Founder/CEO, USAIFA International Limited\r\nAspen New Voices & Mandela Washington Fellow\r\nTony Elumelu Entrepreneur & Research Consultant','2025-07-21 14:32:40'),(12,'3cc2cff7-8805-472f-8754-16a8fa9495c0','assets/media/avatars/avatar_687e5de2b278a2.17914112.jpeg','Dr.','Christy','Zwingina','','Fellow','','Dean, Faculty of Administration\r\nBingham Univeristy, Karu','2025-07-21 14:33:54'),(13,'b6a5404b-368e-4c1b-afab-0372f4ae1530','assets/media/avatars/avatar_687e5e2a9e4929.42254959.jpeg','Dr.','Kingsley','Onana','','Fellow','Research & Data Consultant','Editor at International Journal of Management and Social Sciences.','2025-07-21 14:35:06'),(14,'baec6ab9-ab92-454d-9475-e365ee32bac7','assets/media/avatars/avatar_687e5e6910e985.75636351.jpeg','Dr.','Yusuf','Kpalo','','Fellow','','Alumni of the University of Malaysia,\r\nDirector, Institute of Environmental Research,\r\nNasarawa State University, Keffi.','2025-07-21 14:36:09'),(15,'d4190194-7cbc-4107-91a2-602ca973ea90','assets/media/avatars/avatar_687e5ea0e40415.00068032.jpeg','Dr.','Andrew','Okoye','','Fellow','','Academic Researcher\r\nLecturer, Arden University, Manchester Campus.','2025-07-21 14:37:04'),(16,'fd7dcf64-c052-4fc5-b60b-2811a3f68139','assets/media/avatars/avatar_687e5edb42d887.56619941.jpeg','Dr.','Obinna','Ejiogu','Kennedy','Fellow','','Academic Researcher\r\nLecturer, University of Suffolk, Ipswich Campus','2025-07-21 14:38:03'),(26,'2da4d53d-7980-4e95-a14c-5992b167639a','assets/media/avatars/avatar_6937e25d23ae02.03487490.jpg','Mr.','Samuel','Organizer','Charki','Mobile Developer','React Native','Yes it works!','2025-12-09 07:47:44');
/*!40000 ALTER TABLE `featured_members` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-16 10:42:59
