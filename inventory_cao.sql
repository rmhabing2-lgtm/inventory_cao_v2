-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 05, 2026 at 08:36 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventory_cao`
--

-- --------------------------------------------------------

--
-- Table structure for table `accountable_items`
--

CREATE TABLE `accountable_items` (
  `id` int(11) NOT NULL,
  `inventory_item_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `person_name` varchar(255) NOT NULL,
  `assigned_quantity` int(11) NOT NULL,
  `are_mr_ics_num` varchar(60) DEFAULT NULL,
  `property_number` varchar(300) DEFAULT NULL,
  `serial_number` varchar(70) DEFAULT NULL,
  `po_number` varchar(70) DEFAULT NULL,
  `account_code` varchar(50) DEFAULT NULL,
  `old_account_code` varchar(50) DEFAULT NULL,
  `condition_status` varchar(50) DEFAULT 'Serviceable',
  `date_assigned` datetime DEFAULT current_timestamp(),
  `remarks` varchar(255) DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_by_id` int(11) DEFAULT NULL COMMENT 'user.id of the user who first assigned this item',
  `created_by_name` varchar(255) DEFAULT NULL COMMENT 'Snapshot of creator name at assignment time',
  `last_updated_by_id` int(11) DEFAULT NULL COMMENT 'user.id of the last user who edited this record',
  `last_updated_by_name` varchar(255) DEFAULT NULL COMMENT 'Snapshot of editor name at last edit time',
  `last_updated_at` datetime DEFAULT NULL COMMENT 'Timestamp of the last edit'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accountable_items`
--

INSERT INTO `accountable_items` (`id`, `inventory_item_id`, `employee_id`, `person_name`, `assigned_quantity`, `are_mr_ics_num`, `property_number`, `serial_number`, `po_number`, `account_code`, `old_account_code`, `condition_status`, `date_assigned`, `remarks`, `is_deleted`, `created_by_id`, `created_by_name`, `last_updated_by_id`, `last_updated_by_name`, `last_updated_at`) VALUES
(17, 15, 1003, 'LILY DAWN  E.  FULGUERINAS', 1, 'ICS #2025-09-0497', '', '05ZD3NAMA02643', '', '', '', 'Serviceable', '2026-01-23 13:09:21', '#22', 0, NULL, NULL, NULL, NULL, NULL),
(18, 14, 1003, 'LILY DAWN  E.  FULGUERINAS', 1, 'PAR # 1081-2020-11-1834', '2020-07-1169', '2015910-11147', '', '', '', 'Serviceable', '2026-01-23 13:21:59', '', 1, NULL, NULL, NULL, NULL, NULL),
(19, 21, 1003, 'LILY DAWN  E.  FULGUERINAS', 1, 'ICS# 23-12-1124', '', 'NXKBNSP00124500DE37600', '', '', '', 'Serviceable', '2026-01-23 13:24:12', 'Computer Laptop i5 series Aspire Vero', 0, NULL, NULL, NULL, NULL, NULL),
(20, 32, 1003, 'LILY DAWN  E.  FULGUERINAS', 2, 'PAR # 1081-2020-11-1834', '2020-07-1169', '2015910-11147/ 20161910-11221', '', '', '', 'Serviceable', '2026-01-23 13:30:34', '', 0, NULL, NULL, NULL, NULL, NULL),
(22, 13, 1003, 'LILY DAWN  E.  FULGUERINAS', 1, NULL, NULL, NULL, NULL, NULL, NULL, 'Serviceable', '2026-01-27 03:27:57', 'Assigned via OfficeMap', 0, NULL, NULL, NULL, NULL, NULL),
(23, 33, 1003, 'LILY DAWN  E.  FULGUERINAS', 3, NULL, NULL, NULL, NULL, NULL, NULL, 'Serviceable', '2026-01-27 03:28:44', 'Assigned via OfficeMap', 0, NULL, NULL, NULL, NULL, NULL),
(24, 33, 1003, 'LILY DAWN  E.  FULGUERINAS', 1, NULL, NULL, NULL, NULL, NULL, NULL, 'Serviceable', '2026-01-27 03:31:00', 'Assigned via OfficeMap', 0, NULL, NULL, NULL, NULL, NULL),
(25, 34, 1003, 'LILY DAWN  E.  FULGUERINAS', 1, '000', '000', '000', '00', '00', '000', 'Serviceable', '2026-01-27 13:50:58', '', 0, NULL, NULL, NULL, NULL, NULL),
(26, 30, 2135, 'REY MAC-HIL  C.  ABING', 6, '123', '123', '123', '123', '123', '123', 'Serviceable', '2026-01-27 16:42:12', '123', 0, NULL, NULL, NULL, NULL, NULL),
(27, 26, 1881, 'JENNY  S.  BETAIZAR', 3, '987', '987', '978', '978', '987', '987', 'Serviceable', '2026-01-28 13:40:58', '987', 0, NULL, NULL, NULL, NULL, NULL),
(28, 24, 840, 'DIANNE LORAINE  L.  CARBONQUILLO', 1, NULL, NULL, NULL, NULL, NULL, NULL, 'Serviceable', '2026-01-28 06:46:01', 'Assigned via OfficeMap', 0, NULL, NULL, NULL, NULL, NULL),
(29, 13, 2297, 'ASDASD ASDASD ASDASD ASDASDASD', 1, NULL, NULL, NULL, NULL, NULL, NULL, 'Serviceable', '2026-01-28 09:32:32', 'Assigned via OfficeMap', 0, NULL, NULL, NULL, NULL, NULL),
(30, 9, 5074, 'HEIDE H. ABUNDA', 1, '123', '123', '123', '123', '123', '123', 'Serviceable', '2026-01-29 17:49:34', '123', 0, NULL, NULL, NULL, NULL, NULL),
(31, 19, 1591, 'VALERIE  C.  ABOY', 1, '123', '123', '123', '123', '123', '123', 'Serviceable', '2026-01-29 18:04:16', '123', 0, NULL, NULL, NULL, NULL, NULL),
(32, 17, 5072, 'DAVID C. ALICAYA JR', 1, '123', '123', '123', '123', '123', '123', 'Serviceable', '2026-01-29 18:19:23', '123', 0, NULL, NULL, NULL, NULL, NULL),
(33, 17, 5065, 'CHRISTIAN A. SOSOBRADO JR', 2, '123', 'qwe', '987', 'qwe', 'qwe', 'qwe', 'Serviceable', '2026-01-30 10:07:29', 'qwe', 0, NULL, NULL, NULL, NULL, NULL),
(34, 24, 1444, 'FAHREEN GAILE  B.  TULIAO', 4, '999999***', '123', '123', 'asdad1', '1212', NULL, 'Serviceable', '2026-01-30 13:41:12', 'dvsjhvsglsd,fg4;d;laskjdfakj;asdljkf;', 0, NULL, NULL, NULL, NULL, NULL),
(35, 17, 1444, 'FAHREEN GAILE  B.  TULIAO', 1, 'asd', 'asd', 'asd', 'asd', 'asd', 'a', 'Serviceable', '2026-02-02 12:33:45', '1', 0, NULL, NULL, NULL, NULL, NULL),
(36, 17, 1444, 'FAHREEN GAILE  B.  TULIAO', 1, '123', '123', '123', '123', '123', '123', 'Serviceable', '2026-02-02 12:48:25', '1', 0, NULL, NULL, NULL, NULL, NULL),
(37, 22, 1279, 'MAYFHEL  P.  ELIAB', 1, '123', '123', '123', '123', '123', '3132', 'Serviceable', '2026-02-02 12:53:35', '1', 0, NULL, NULL, NULL, NULL, NULL),
(38, 22, 1591, 'VALERIE  C.  ABOY', 2, '123', '123', '123', '123', '123', '123', 'Serviceable', '2026-02-02 12:58:21', '2', 0, NULL, NULL, NULL, NULL, NULL),
(39, 22, 5074, 'HEIDE H. ABUNDA', 1, '123', '123', '123', '123', '123', '123', 'Serviceable', '2026-02-02 13:29:27', '1', 1, NULL, NULL, NULL, NULL, NULL),
(40, 22, 1660, 'LILWEN MAE  P.  ALOVERA', 1, '123', '123', '123', '123', '123', '123', 'Serviceable', '2026-02-02 13:33:25', '1', 0, NULL, NULL, NULL, NULL, NULL),
(41, 22, 5062, 'PRINCES YABUT PINTOR', 10, '123', '123', '123', '1233', '123', NULL, 'Serviceable', '2026-02-05 08:35:23', '123', 0, NULL, NULL, NULL, NULL, NULL),
(42, 17, 1591, 'VALERIE  C.  ABOY', 9, 'qeweq 12342 sedfadf32', 'qweqweqwe', '123123564dfgdfgdfg', 'qweqwe', 'qweqweqwe', NULL, 'Serviceable', '2026-02-06 13:50:34', 'ad1edasdc 123 [Transferred]', 0, NULL, NULL, NULL, NULL, NULL),
(43, 9, 5075, 'SAMANTHA CHESKA  DIONIO', 1, ',.m.hujkif', 'k/l;\'/lghj', 'l/jklt645dcvsd', 'asdfg4356', '6768', NULL, 'Serviceable', '2026-04-29 09:01:26', '12123.//12/.12/.12', 0, NULL, NULL, NULL, NULL, NULL),
(44, 22, 2084, 'STEVEN RUFE CABREROS PADOLINA', 1, '1231asdasd', '123 sdfs43 55445', '123123 / 123123/edrgwsdrfg/ .,.fgdf', '', '', NULL, 'Serviceable', '2026-04-29 10:14:46', '23423423  [Transferred] [Transferred]', 0, NULL, NULL, NULL, NULL, NULL),
(46, 31, 1619, 'DIONA  L.  ADLAON', 1, '123fsdfsf  jkhlkhyyyyyyy', '231123167457856', '123/././....', '', '', NULL, 'Serviceable', '2026-04-29 12:56:46', '/-*/-++/-*/asdasd1231231 [Transferred] [Transferred] [Transferred from: HEIDE H. ABUNDA by: Jenny Betaizar]', 0, NULL, NULL, 3, 'Jenny Betaizar', '2026-04-30 12:51:05'),
(47, 35, 5062, 'PRINCES YABUT PINTOR', 1, '657wdrgv sa', '5464654654', '*-/+4+-446456', 'sdasdasasdasd', '123123 asda', NULL, 'Serviceable', '2026-04-30 08:48:41', 'asd2654asd354a12322333 [Transferred from: PRINCES YABUT PINTOR] [Transferred from: LILWEN MAE  P.  ALOVERA by: Rey Mac-Hil Abing]', 0, NULL, NULL, 1, 'Rey Mac-Hil Abing', '2026-04-30 12:49:54'),
(48, 22, 1444, 'FAHREEN GAILE  B.  TULIAO', 10, '111231', '654891981', '950965164', 'asda', '1111', '', 'Serviceable', '2026-04-30 12:56:33', '99999999', 0, 3, 'Jenny Betaizar', 3, 'Jenny Betaizar', '2026-04-30 12:57:03'),
(49, 17, 65, 'NANCY  A.  ARBIS', 1, '', 'arenaslkdj', 'abcsdjkj /', '', '', '', 'Serviceable', '2026-05-05 10:44:39', '123123123 [Partial return to CGSO: 1 unit(s), Ref: 0] [Partial return to CGSO: 1 unit(s), Ref: 0]', 0, 1, 'Rey Mac-Hil Abing', 1, '0', '2026-05-05 13:32:42'),
(50, 13, 1881, 'JENNY  S.  BETAIZAR', 2, '', 'asdasdfas 4adfasdf ', 'jhasdljkfhasdf / kasdjfl;askdjf / a;skldjf;akljsdf ', '', '', '', 'Serviceable', '2026-05-05 13:34:30', 'sdfadf asdfa ', 0, 1, 'Rey Mac-Hil Abing', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `module` varchar(100) NOT NULL COMMENT 'e.g., inventory_items, accountable_items',
  `action` enum('CREATE','UPDATE','DELETE') NOT NULL,
  `record_id` int(11) NOT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `user_name`, `module`, `action`, `record_id`, `old_data`, `new_data`, `created_at`) VALUES
(1, 0, NULL, 'accountable_items', 'CREATE', 43, NULL, '{\"inventory_item_id\":9,\"employee\":\"SAMANTHA CHESKA  DIONIO\",\"quantity\":1,\"par_ics\":\"\",\"property_no\":\"\",\"serial_no\":\"\"}', '2026-04-29 09:01:26'),
(2, 0, NULL, 'inventory_items', 'UPDATE', 9, '{\"id\":9,\"item_name\":\"Aircon window type\",\"particulars\":\"Aircon window type\",\"are_mr_ics_num\":\"\",\"quantity\":3,\"amount\":\"0\",\"value_amount\":0,\"date_delivered\":\"2026-01-23\",\"item_status\":\"Active\",\"date_updated\":\"2026-01-29 17:49:34\"}', '{\"id\":9,\"item_name\":\"Aircon window type\",\"particulars\":\"Aircon window type\",\"are_mr_ics_num\":\"\",\"quantity\":2,\"amount\":\"0\",\"value_amount\":0,\"date_delivered\":\"2026-01-23\",\"item_status\":\"Active\",\"date_updated\":\"2026-04-29 09:01:26\"}', '2026-04-29 09:01:26'),
(3, 0, NULL, 'accountable_items', 'CREATE', 44, NULL, '{\"inventory_item_id\":22,\"employee\":\"RHESABABES  G.  SUMALPONG\",\"quantity\":1,\"par_ics\":\"\",\"property_no\":\"123 sdfs43 55445\",\"serial_no\":\"123123 \\/ 123123\\/edrgwsdrfg\\/ .,.fgdf\"}', '2026-04-29 10:14:46'),
(4, 0, NULL, 'inventory_items', 'UPDATE', 22, '{\"id\":22,\"item_name\":\"Swivel Chair w\\/ arms\",\"particulars\":\"Swivel Chair w\\/ arms\",\"are_mr_ics_num\":\"\",\"quantity\":153,\"amount\":\"0\",\"value_amount\":0,\"date_delivered\":\"2026-01-23\",\"item_status\":\"Active\",\"date_updated\":\"2026-02-05 08:35:23\"}', '{\"id\":22,\"item_name\":\"Swivel Chair w\\/ arms\",\"particulars\":\"Swivel Chair w\\/ arms\",\"are_mr_ics_num\":\"\",\"quantity\":152,\"amount\":\"0\",\"value_amount\":0,\"date_delivered\":\"2026-01-23\",\"item_status\":\"Active\",\"date_updated\":\"2026-04-29 10:14:46\"}', '2026-04-29 10:14:46'),
(5, 0, 'System', 'accountable_items', 'UPDATE', 44, '{\"id\":44,\"inventory_item_id\":22,\"employee_id\":2266,\"person_name\":\"JENELYN  L.  SIMOGAN\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"1231asdasd\",\"property_number\":\"123 sdfs43 55445\",\"serial_number\":\"123123 \\/ 123123\\/edrgwsdrfg\\/ .,.fgdf\",\"po_number\":\"\",\"account_code\":\"\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-29 10:14:46\",\"remarks\":\"23423423  [Transferred]\",\"is_deleted\":0}', '{\"id\":44,\"inventory_item_id\":22,\"employee_id\":2084,\"person_name\":\"STEVEN RUFE CABREROS PADOLINA\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"1231asdasd\",\"property_number\":\"123 sdfs43 55445\",\"serial_number\":\"123123 \\/ 123123\\/edrgwsdrfg\\/ .,.fgdf\",\"po_number\":\"\",\"account_code\":\"\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-29 10:14:46\",\"remarks\":\"23423423  [Transferred] [Transferred]\",\"is_deleted\":0}', '2026-04-29 12:50:48'),
(7, 0, 'System', 'accountable_items', 'CREATE', 46, NULL, '{\"inventory_item_id\":31,\"employee\":\"STEVEN RUFE CABREROS PADOLINA\",\"quantity\":1,\"par_ics\":\"\",\"property_no\":\"231123167457856\",\"serial_no\":\"123\\/.\\/.\\/....\"}', '2026-04-29 12:56:46'),
(8, 0, 'System', 'inventory_items', 'UPDATE', 31, '{\"id\":31,\"item_name\":\"Monitor\",\"particulars\":\"Monitor\",\"are_mr_ics_num\":\"\",\"quantity\":7,\"amount\":\"0\",\"value_amount\":0,\"date_delivered\":\"2026-01-23\",\"item_status\":\"Active\",\"date_updated\":\"2026-01-23 13:13:23\"}', '{\"id\":31,\"item_name\":\"Monitor\",\"particulars\":\"Monitor\",\"are_mr_ics_num\":\"\",\"quantity\":6,\"amount\":\"0\",\"value_amount\":0,\"date_delivered\":\"2026-01-23\",\"item_status\":\"Active\",\"date_updated\":\"2026-04-29 12:56:46\"}', '2026-04-29 12:56:46'),
(9, 0, 'System', 'accountable_items', 'UPDATE', 46, '{\"id\":46,\"inventory_item_id\":31,\"employee_id\":2084,\"person_name\":\"STEVEN RUFE CABREROS PADOLINA\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"\",\"property_number\":\"231123167457856\",\"serial_number\":\"123\\/.\\/.\\/....\",\"po_number\":\"\",\"account_code\":\"\",\"old_account_code\":\"\",\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-29 12:56:46\",\"remarks\":\"\\/-*\\/-++\\/-*\\/asdasd1231231\",\"is_deleted\":0}', '{\"id\":46,\"inventory_item_id\":31,\"employee_id\":582,\"person_name\":\"ELVIRA  P.  ARCILLAS\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"\",\"property_number\":\"231123167457856\",\"serial_number\":\"123\\/.\\/.\\/....\",\"po_number\":\"\",\"account_code\":\"\",\"old_account_code\":\"\",\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-29 12:56:46\",\"remarks\":\"\\/-*\\/-++\\/-*\\/asdasd1231231 [Transferred]\",\"is_deleted\":0}', '2026-04-29 12:57:50'),
(10, 0, 'System', 'accountable_items', 'UPDATE', 46, '{\"id\":46,\"inventory_item_id\":31,\"employee_id\":582,\"person_name\":\"ELVIRA  P.  ARCILLAS\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"\",\"property_number\":\"231123167457856\",\"serial_number\":\"123\\/.\\/.\\/....\",\"po_number\":\"\",\"account_code\":\"\",\"old_account_code\":\"\",\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-29 12:56:46\",\"remarks\":\"\\/-*\\/-++\\/-*\\/asdasd1231231 [Transferred]\",\"is_deleted\":0}', '{\"id\":46,\"inventory_item_id\":31,\"employee_id\":5074,\"person_name\":\"HEIDE H. ABUNDA\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"\",\"property_number\":\"231123167457856\",\"serial_number\":\"123\\/.\\/.\\/....\",\"po_number\":\"\",\"account_code\":\"\",\"old_account_code\":\"\",\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-29 12:56:46\",\"remarks\":\"\\/-*\\/-++\\/-*\\/asdasd1231231 [Transferred] [Transferred]\",\"is_deleted\":0}', '2026-04-29 13:04:39'),
(11, 0, 'System', 'accountable_items', 'UPDATE', 46, '{\"id\":46,\"inventory_item_id\":31,\"employee_id\":5074,\"person_name\":\"HEIDE H. ABUNDA\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"\",\"property_number\":\"231123167457856\",\"serial_number\":\"123\\/.\\/.\\/....\",\"po_number\":\"\",\"account_code\":\"\",\"old_account_code\":\"\",\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-29 12:56:46\",\"remarks\":\"\\/-*\\/-++\\/-*\\/asdasd1231231 [Transferred] [Transferred]\",\"is_deleted\":0}', '{\"id\":46,\"inventory_item_id\":31,\"employee_id\":5074,\"person_name\":\"HEIDE H. ABUNDA\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"123fsdfsf  jkhlkhyyyyyyy\",\"property_number\":\"231123167457856\",\"serial_number\":\"123\\/.\\/.\\/....\",\"po_number\":\"\",\"account_code\":\"\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-29 12:56:46\",\"remarks\":\"\\/-*\\/-++\\/-*\\/asdasd1231231 [Transferred] [Transferred]\",\"is_deleted\":0}', '2026-04-29 16:33:13'),
(12, 0, 'System', 'accountable_items', 'UPDATE', 34, '{\"id\":34,\"inventory_item_id\":24,\"employee_id\":1444,\"person_name\":\"FAHREEN GAILE  B.  TULIAO\",\"assigned_quantity\":4,\"are_mr_ics_num\":\"123\",\"property_number\":\"123\",\"serial_number\":\"123\",\"po_number\":\"\",\"account_code\":\"\",\"old_account_code\":\"\",\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-01-30 13:41:12\",\"remarks\":\"dvsjhvsglsd,fg4;d;laskjdfakj;asdljkf;\",\"is_deleted\":0}', '{\"id\":34,\"inventory_item_id\":24,\"employee_id\":1444,\"person_name\":\"FAHREEN GAILE  B.  TULIAO\",\"assigned_quantity\":4,\"are_mr_ics_num\":\"999999***\",\"property_number\":\"123\",\"serial_number\":\"123\",\"po_number\":\"asdad1\",\"account_code\":\"1212\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-01-30 13:41:12\",\"remarks\":\"dvsjhvsglsd,fg4;d;laskjdfakj;asdljkf;\",\"is_deleted\":0}', '2026-04-29 16:36:28'),
(13, 1, 'Rey Mac-Hil Abing', 'inventory_items', 'CREATE', 35, NULL, '{\"id\":\"35\",\"item_name\":\"Acer Laptop\",\"particulars\":\"IT Equipment\",\"are_mr_ics_num\":\"\",\"quantity\":\"20\",\"amount\":\"50000\",\"value_amount\":\"0\",\"date_delivered\":\"2026-04-30\",\"item_status\":\"Active\",\"date_updated\":\"2026-04-30 08:39:58\"}', '2026-04-30 08:39:58'),
(14, 0, 'System', 'accountable_items', 'CREATE', 47, NULL, '{\"inventory_item_id\":35,\"employee\":\"PRINCES YABUT PINTOR\",\"quantity\":1,\"par_ics\":\"\",\"property_no\":\"5464654654\",\"serial_no\":\"*-\\/+4+-446456\"}', '2026-04-30 08:48:41'),
(15, 0, 'System', 'inventory_items', 'UPDATE', 35, '{\"id\":35,\"item_name\":\"Acer Laptop\",\"particulars\":\"IT Equipment\",\"are_mr_ics_num\":\"\",\"quantity\":20,\"amount\":\"50000\",\"value_amount\":0,\"date_delivered\":\"2026-04-30\",\"item_status\":\"Active\",\"date_updated\":\"2026-04-30 08:39:58\"}', '{\"id\":35,\"item_name\":\"Acer Laptop\",\"particulars\":\"IT Equipment\",\"are_mr_ics_num\":\"\",\"quantity\":19,\"amount\":\"50000\",\"value_amount\":0,\"date_delivered\":\"2026-04-30\",\"item_status\":\"Active\",\"date_updated\":\"2026-04-30 08:48:41\"}', '2026-04-30 08:48:41'),
(16, 0, 'System', 'accountable_items', 'UPDATE', 47, '{\"id\":47,\"inventory_item_id\":35,\"employee_id\":5062,\"person_name\":\"PRINCES YABUT PINTOR\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"\",\"property_number\":\"5464654654\",\"serial_number\":\"*-\\/+4+-446456\",\"po_number\":\"\",\"account_code\":\"\",\"old_account_code\":\"\",\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 08:48:41\",\"remarks\":\"asd2654asd354asd\",\"is_deleted\":0}', '{\"id\":47,\"inventory_item_id\":35,\"employee_id\":5062,\"person_name\":\"PRINCES YABUT PINTOR\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"657wdrgv sa\",\"property_number\":\"5464654654\",\"serial_number\":\"*-\\/+4+-446456\",\"po_number\":\"\",\"account_code\":\"123123 asdas4545645\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 08:48:41\",\"remarks\":\"asd2654asd354asd\",\"is_deleted\":0}', '2026-04-30 09:00:03'),
(17, 0, 'System', 'accountable_items', 'UPDATE', 47, '{\"id\":47,\"inventory_item_id\":35,\"employee_id\":5062,\"person_name\":\"PRINCES YABUT PINTOR\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"657wdrgv sa\",\"property_number\":\"5464654654\",\"serial_number\":\"*-\\/+4+-446456\",\"po_number\":\"\",\"account_code\":\"123123 asdas4545645\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 08:48:41\",\"remarks\":\"asd2654asd354asd\",\"is_deleted\":0}', '{\"id\":47,\"inventory_item_id\":35,\"employee_id\":5062,\"person_name\":\"PRINCES YABUT PINTOR\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"657wdrgv sa\",\"property_number\":\"5464654654\",\"serial_number\":\"*-\\/+4+-446456\",\"po_number\":\"sdasdas\",\"account_code\":\"123123 asdas4545645\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 08:48:41\",\"remarks\":\"asd2654asd354asd\",\"is_deleted\":0}', '2026-04-30 09:02:52'),
(18, 0, 'System', 'accountable_items', 'UPDATE', 47, '{\"id\":47,\"inventory_item_id\":35,\"employee_id\":5062,\"person_name\":\"PRINCES YABUT PINTOR\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"657wdrgv sa\",\"property_number\":\"5464654654\",\"serial_number\":\"*-\\/+4+-446456\",\"po_number\":\"sdasdas\",\"account_code\":\"123123 asdas4545645\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 08:48:41\",\"remarks\":\"asd2654asd354asd\",\"is_deleted\":0}', '{\"id\":47,\"inventory_item_id\":35,\"employee_id\":5062,\"person_name\":\"PRINCES YABUT PINTOR\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"657wdrgv sa\",\"property_number\":\"5464654654\",\"serial_number\":\"*-\\/+4+-446456\",\"po_number\":\"sdasdasasdasd\",\"account_code\":\"123123 asdas4545645\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 08:48:41\",\"remarks\":\"asd2654asd354asd\",\"is_deleted\":0}', '2026-04-30 10:40:22'),
(19, 0, 'System', 'accountable_items', 'UPDATE', 47, '{\"id\":47,\"inventory_item_id\":35,\"employee_id\":5062,\"person_name\":\"PRINCES YABUT PINTOR\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"657wdrgv sa\",\"property_number\":\"5464654654\",\"serial_number\":\"*-\\/+4+-446456\",\"po_number\":\"sdasdasasdasd\",\"account_code\":\"123123 asdas4545645\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 08:48:41\",\"remarks\":\"asd2654asd354asd\",\"is_deleted\":0}', '{\"id\":47,\"inventory_item_id\":35,\"employee_id\":5062,\"person_name\":\"PRINCES YABUT PINTOR\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"657wdrgv sa\",\"property_number\":\"5464654654\",\"serial_number\":\"*-\\/+4+-446456\",\"po_number\":\"sdasdasasdasd\",\"account_code\":\"123123 asdas4545645\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 08:48:41\",\"remarks\":\"asd2654asd354asdasd\",\"is_deleted\":0}', '2026-04-30 11:11:59'),
(20, 0, 'System', 'accountable_items', 'UPDATE', 47, '{\"id\":47,\"inventory_item_id\":35,\"employee_id\":5062,\"person_name\":\"PRINCES YABUT PINTOR\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"657wdrgv sa\",\"property_number\":\"5464654654\",\"serial_number\":\"*-\\/+4+-446456\",\"po_number\":\"sdasdasasdasd\",\"account_code\":\"123123 asdas4545645\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 08:48:41\",\"remarks\":\"asd2654asd354asdasd\",\"is_deleted\":0}', '{\"id\":47,\"inventory_item_id\":35,\"employee_id\":5062,\"person_name\":\"PRINCES YABUT PINTOR\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"657wdrgv sa\",\"property_number\":\"5464654654\",\"serial_number\":\"*-\\/+4+-446456\",\"po_number\":\"sdasdasasdasd\",\"account_code\":\"123123 asdas4545645\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 08:48:41\",\"remarks\":\"asd2654asd354a12322333\",\"is_deleted\":0}', '2026-04-30 11:15:32'),
(21, 0, 'System', 'accountable_items', 'UPDATE', 47, '{\"id\":47,\"inventory_item_id\":35,\"employee_id\":5062,\"person_name\":\"PRINCES YABUT PINTOR\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"657wdrgv sa\",\"property_number\":\"5464654654\",\"serial_number\":\"*-\\/+4+-446456\",\"po_number\":\"sdasdasasdasd\",\"account_code\":\"123123 asdas4545645\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 08:48:41\",\"remarks\":\"asd2654asd354a12322333\",\"is_deleted\":0}', '{\"id\":47,\"inventory_item_id\":35,\"employee_id\":5062,\"person_name\":\"PRINCES YABUT PINTOR\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"657wdrgv sa\",\"property_number\":\"5464654654\",\"serial_number\":\"*-\\/+4+-446456\",\"po_number\":\"sdasdasasdasd\",\"account_code\":\"123123 asda\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 08:48:41\",\"remarks\":\"asd2654asd354a12322333\",\"is_deleted\":0}', '2026-04-30 11:34:41'),
(22, 0, 'System', 'accountable_items', 'UPDATE', 47, '{\"id\":47,\"inventory_item_id\":35,\"employee_id\":5062,\"person_name\":\"PRINCES YABUT PINTOR\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"657wdrgv sa\",\"property_number\":\"5464654654\",\"serial_number\":\"*-\\/+4+-446456\",\"po_number\":\"sdasdasasdasd\",\"account_code\":\"123123 asda\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 08:48:41\",\"remarks\":\"asd2654asd354a12322333\",\"is_deleted\":0}', '{\"id\":47,\"inventory_item_id\":35,\"employee_id\":1660,\"person_name\":\"LILWEN MAE  P.  ALOVERA\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"657wdrgv sa\",\"property_number\":\"5464654654\",\"serial_number\":\"*-\\/+4+-446456\",\"po_number\":\"sdasdasasdasd\",\"account_code\":\"123123 asda\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 08:48:41\",\"remarks\":\"asd2654asd354a12322333 [Transferred from: PRINCES YABUT PINTOR]\",\"is_deleted\":0}', '2026-04-30 12:24:36'),
(23, 1, 'Rey Mac-Hil Abing', 'accountable_items', 'UPDATE', 47, '{\"id\":47,\"inventory_item_id\":35,\"employee_id\":1660,\"person_name\":\"LILWEN MAE  P.  ALOVERA\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"657wdrgv sa\",\"property_number\":\"5464654654\",\"serial_number\":\"*-\\/+4+-446456\",\"po_number\":\"sdasdasasdasd\",\"account_code\":\"123123 asda\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 08:48:41\",\"remarks\":\"asd2654asd354a12322333 [Transferred from: PRINCES YABUT PINTOR]\",\"is_deleted\":0,\"created_by_id\":null,\"created_by_name\":null,\"last_updated_by_id\":null,\"last_updated_by_name\":null,\"last_updated_at\":null}', '{\"id\":47,\"inventory_item_id\":35,\"employee_id\":5062,\"person_name\":\"PRINCES YABUT PINTOR\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"657wdrgv sa\",\"property_number\":\"5464654654\",\"serial_number\":\"*-\\/+4+-446456\",\"po_number\":\"sdasdasasdasd\",\"account_code\":\"123123 asda\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 08:48:41\",\"remarks\":\"asd2654asd354a12322333 [Transferred from: PRINCES YABUT PINTOR] [Transferred from: LILWEN MAE  P.  ALOVERA by: Rey Mac-Hil Abing]\",\"is_deleted\":0,\"created_by_id\":null,\"created_by_name\":null,\"last_updated_by_id\":1,\"last_updated_by_name\":\"Rey Mac-Hil Abing\",\"last_updated_at\":\"2026-04-30 12:49:54\"}', '2026-04-30 12:49:54'),
(24, 3, 'Jenny Betaizar', 'accountable_items', 'UPDATE', 46, '{\"id\":46,\"inventory_item_id\":31,\"employee_id\":5074,\"person_name\":\"HEIDE H. ABUNDA\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"123fsdfsf  jkhlkhyyyyyyy\",\"property_number\":\"231123167457856\",\"serial_number\":\"123\\/.\\/.\\/....\",\"po_number\":\"\",\"account_code\":\"\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-29 12:56:46\",\"remarks\":\"\\/-*\\/-++\\/-*\\/asdasd1231231 [Transferred] [Transferred]\",\"is_deleted\":0,\"created_by_id\":null,\"created_by_name\":null,\"last_updated_by_id\":null,\"last_updated_by_name\":null,\"last_updated_at\":null}', '{\"id\":46,\"inventory_item_id\":31,\"employee_id\":1619,\"person_name\":\"DIONA  L.  ADLAON\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"123fsdfsf  jkhlkhyyyyyyy\",\"property_number\":\"231123167457856\",\"serial_number\":\"123\\/.\\/.\\/....\",\"po_number\":\"\",\"account_code\":\"\",\"old_account_code\":null,\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-29 12:56:46\",\"remarks\":\"\\/-*\\/-++\\/-*\\/asdasd1231231 [Transferred] [Transferred] [Transferred from: HEIDE H. ABUNDA by: Jenny Betaizar]\",\"is_deleted\":0,\"created_by_id\":null,\"created_by_name\":null,\"last_updated_by_id\":3,\"last_updated_by_name\":\"Jenny Betaizar\",\"last_updated_at\":\"2026-04-30 12:51:05\"}', '2026-04-30 12:51:05'),
(25, 3, 'Jenny Betaizar', 'accountable_items', 'CREATE', 48, NULL, '{\"inventory_item_id\":22,\"custodian\":\"FAHREEN GAILE  B.  TULIAO\",\"quantity\":10,\"par_ics\":\"\",\"property_no\":\"654891981\",\"serial_no\":\"950965164\",\"assigned_by_id\":3,\"assigned_by_name\":\"Jenny Betaizar\"}', '2026-04-30 12:56:33'),
(26, 3, 'Jenny Betaizar', 'inventory_items', 'UPDATE', 22, '{\"id\":22,\"item_name\":\"Swivel Chair w\\/ arms\",\"particulars\":\"Swivel Chair w\\/ arms\",\"are_mr_ics_num\":\"\",\"quantity\":152,\"amount\":\"0\",\"value_amount\":0,\"date_delivered\":\"2026-01-23\",\"item_status\":\"Active\",\"date_updated\":\"2026-04-29 10:14:46\"}', '{\"id\":22,\"item_name\":\"Swivel Chair w\\/ arms\",\"particulars\":\"Swivel Chair w\\/ arms\",\"are_mr_ics_num\":\"\",\"quantity\":142,\"amount\":\"0\",\"value_amount\":0,\"date_delivered\":\"2026-01-23\",\"item_status\":\"Active\",\"date_updated\":\"2026-04-30 12:56:33\"}', '2026-04-30 12:56:33'),
(27, 3, 'Jenny Betaizar', 'accountable_items', 'UPDATE', 48, '{\"id\":48,\"inventory_item_id\":22,\"employee_id\":1444,\"person_name\":\"FAHREEN GAILE  B.  TULIAO\",\"assigned_quantity\":10,\"are_mr_ics_num\":\"\",\"property_number\":\"654891981\",\"serial_number\":\"950965164\",\"po_number\":\"\",\"account_code\":\"\",\"old_account_code\":\"\",\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 12:56:33\",\"remarks\":\"99999999\",\"is_deleted\":0,\"created_by_id\":3,\"created_by_name\":\"Jenny Betaizar\",\"last_updated_by_id\":null,\"last_updated_by_name\":null,\"last_updated_at\":null}', '{\"id\":48,\"inventory_item_id\":22,\"employee_id\":1444,\"person_name\":\"FAHREEN GAILE  B.  TULIAO\",\"assigned_quantity\":10,\"are_mr_ics_num\":\"111231\",\"property_number\":\"654891981\",\"serial_number\":\"950965164\",\"po_number\":\"asda\",\"account_code\":\"1111\",\"old_account_code\":\"\",\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-04-30 12:56:33\",\"remarks\":\"99999999\",\"is_deleted\":0,\"created_by_id\":3,\"created_by_name\":\"Jenny Betaizar\",\"last_updated_by_id\":3,\"last_updated_by_name\":\"Jenny Betaizar\",\"last_updated_at\":\"2026-04-30 12:57:03\"}', '2026-04-30 12:57:03'),
(28, 1, NULL, 'borrowed_items', 'CREATE', 35, NULL, '{\"status\":\"APPROVED\",\"reference_no\":\"BORROW-20260430091003-7023\",\"item\":\"Desktop Computer Asus H510MD\",\"qty\":1,\"borrower\":\"JOVER  APOSTOL\"}', '2026-04-30 15:10:03'),
(29, 5, NULL, 'borrowed_items', 'CREATE', 36, NULL, '{\"status\":\"PENDING\",\"reference_no\":\"BORROW-20260430091201-5555\",\"item\":\"Desktop Computer\",\"qty\":1,\"borrower\":\"REY MAC-HIL  ABING\"}', '2026-04-30 15:12:09'),
(30, 1, NULL, 'borrowed_items', 'UPDATE', 36, '{\"status\":\"PENDING\"}', '{\"status\":\"APPROVED\",\"approved_by\":1}', '2026-04-30 15:14:02'),
(31, 1, NULL, 'borrowed_items', 'UPDATE', 36, '{\"status\":\"RETURN_PENDING\",\"is_returned\":0}', '{\"status\":\"RETURNED\",\"is_returned\":1,\"return_approved_by\":1}', '2026-04-30 15:14:45'),
(32, 1, NULL, 'borrowed_items', 'UPDATE', 35, '{\"status\":\"RETURN_PENDING\",\"is_returned\":0}', '{\"status\":\"RETURNED\",\"is_returned\":1,\"return_approved_by\":1}', '2026-04-30 15:15:46'),
(33, 5, NULL, 'borrowed_items', 'CREATE', 37, NULL, '{\"status\":\"PENDING\",\"reference_no\":\"BORROW-20260430094552-3898\",\"item\":\"Desktop Computer\",\"qty\":1,\"borrower\":\"REY MAC-HIL  ABING\"}', '2026-04-30 15:46:00'),
(34, 7, NULL, 'borrowed_items', 'CREATE', 38, NULL, '{\"status\":\"PENDING\",\"reference_no\":\"BORROW-20260430101645-8614\",\"item\":\"Desktop Computer\",\"qty\":1,\"borrower\":\"FAHREEN GAILE TULIAO\"}', '2026-04-30 16:16:53'),
(35, 3, NULL, 'borrowed_items', 'UPDATE', 38, '{\"status\":\"PENDING\"}', '{\"status\":\"APPROVED\",\"approved_by\":3,\"actor_role\":\"MANAGER\"}', '2026-05-04 12:58:38'),
(36, 3, NULL, 'borrowed_items', 'UPDATE', 28, '{\"status\":\"PENDING\"}', '{\"status\":\"APPROVED\",\"approved_by\":3,\"actor_role\":\"MANAGER\"}', '2026-05-04 13:05:36'),
(37, 3, NULL, 'borrowed_items', 'UPDATE', 37, '{\"status\":\"PENDING\"}', '{\"status\":\"APPROVED\",\"approved_by\":3,\"actor_role\":\"MANAGER\"}', '2026-05-04 13:06:12'),
(38, 3, NULL, 'borrowed_items', 'UPDATE', 24, '{\"status\":\"PENDING\"}', '{\"status\":\"APPROVED\",\"approved_by\":3,\"actor_role\":\"MANAGER\"}', '2026-05-04 13:06:18'),
(39, 7, NULL, 'borrowed_items', 'CREATE', 39, NULL, '{\"status\":\"PENDING\",\"reference_no\":\"BORROW-20260504074815-2342\",\"item\":\"Document scanner high-speed of upto 35PPM/70\",\"qty\":1,\"borrower\":\"FAHREEN GAILE TULIAO\"}', '2026-05-04 13:48:23'),
(40, 1, NULL, 'borrowed_items', 'UPDATE', 39, '{\"status\":\"PENDING\"}', '{\"status\":\"DENIED\",\"decision_remarks\":\"123asd\",\"actor_role\":\"ADMIN\"}', '2026-05-04 15:50:33'),
(41, 7, NULL, 'borrowed_items', 'UPDATE', 38, '{\"status\":\"APPROVED\"}', '{\"status\":\"RETURN_PENDING\",\"return_requested_by\":7}', '2026-05-04 15:54:10'),
(42, 3, NULL, 'borrowed_items', 'UPDATE', 38, '{\"status\":\"RETURN_PENDING\",\"is_returned\":0}', '{\"status\":\"RETURNED\",\"is_returned\":1,\"return_approved_by\":3,\"actor_role\":\"MANAGER\"}', '2026-05-04 15:55:11'),
(43, 8, NULL, 'inventory_items', 'CREATE', 36, NULL, '{\"item_name\":\"laptop\",\"particulars\":\"asus ROG2026\",\"are_mr_ics_num\":\"are-2026-01-01\",\"quantity\":20,\"amount\":\"98000\",\"value_amount\":1960000,\"date_delivered\":\"2026-05-05\",\"item_status\":\"Active\"}', '2026-05-05 07:24:47'),
(44, 1, 'Rey Mac-Hil Abing', 'accountable_items', 'CREATE', 49, NULL, '{\"inventory_item_id\":17,\"custodian\":\"NANCY  A.  ARBIS\",\"quantity\":3,\"par_ics\":\"\",\"property_no\":\"arenaslkdj\",\"serial_no\":\"abcsdjkj \\/ 198hassd \\/ lkjd93n164 \",\"assigned_by_id\":1,\"assigned_by_name\":\"Rey Mac-Hil Abing\"}', '2026-05-05 10:44:39'),
(45, 1, 'Rey Mac-Hil Abing', 'inventory_items', 'UPDATE', 17, '{\"id\":17,\"item_name\":\"Desktop Computer\",\"particulars\":\"Desktop Computer\",\"are_mr_ics_num\":\"\",\"quantity\":84,\"amount\":\"0\",\"value_amount\":0,\"date_delivered\":\"2026-01-23\",\"item_status\":\"Active\",\"date_updated\":\"2026-02-06 13:50:34\"}', '{\"id\":17,\"item_name\":\"Desktop Computer\",\"particulars\":\"Desktop Computer\",\"are_mr_ics_num\":\"\",\"quantity\":81,\"amount\":\"0\",\"value_amount\":0,\"date_delivered\":\"2026-01-23\",\"item_status\":\"Active\",\"date_updated\":\"2026-05-05 10:44:39\"}', '2026-05-05 10:44:39'),
(46, 1, 'Rey Mac-Hil Abing', 'accountable_items', 'UPDATE', 49, '{\"id\":49,\"inventory_item_id\":17,\"employee_id\":65,\"person_name\":\"NANCY  A.  ARBIS\",\"assigned_quantity\":3,\"are_mr_ics_num\":\"\",\"property_number\":\"arenaslkdj\",\"serial_number\":\"abcsdjkj \\/ 198hassd \\/ lkjd93n164 \",\"po_number\":\"\",\"account_code\":\"\",\"old_account_code\":\"\",\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-05-05 10:44:39\",\"remarks\":\"123123123\",\"is_deleted\":0,\"created_by_id\":1,\"created_by_name\":\"Rey Mac-Hil Abing\",\"last_updated_by_id\":null,\"last_updated_by_name\":null,\"last_updated_at\":null,\"item_name\":\"Desktop Computer\"}', '{\"id\":49,\"inventory_item_id\":17,\"employee_id\":65,\"person_name\":\"NANCY  A.  ARBIS\",\"assigned_quantity\":2,\"are_mr_ics_num\":\"\",\"property_number\":\"arenaslkdj\",\"serial_number\":\"abcsdjkj \\/ lkjd93n164 \\/\",\"po_number\":\"\",\"account_code\":\"\",\"old_account_code\":\"\",\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-05-05 10:44:39\",\"remarks\":\"123123123 [Partial return to CGSO: 1 unit(s), Ref: 0]\",\"is_deleted\":0,\"created_by_id\":1,\"created_by_name\":\"Rey Mac-Hil Abing\",\"last_updated_by_id\":1,\"last_updated_by_name\":\"0\",\"last_updated_at\":\"2026-05-05 11:02:17\"}', '2026-05-05 11:02:17'),
(47, 1, 'Rey Mac-Hil Abing', 'returned_to_cgso', 'CREATE', 1, NULL, '{\"accountable_id\":49,\"inventory_item_id\":17,\"item_name\":\"Desktop Computer\",\"employee\":\"NANCY A. ARBIS\",\"returned_qty\":1,\"remaining_qty\":2,\"returned_serials\":\"198hassd \\/\",\"ref_no\":\"RTC-20260505050217-8685\",\"returned_by\":\"Rey Mac-Hil Abing\",\"role\":\"ADMIN\"}', '2026-05-05 11:02:17'),
(48, 1, 'Rey Mac-Hil Abing', 'inventory_items', 'UPDATE', 17, '{\"quantity\":3}', '{\"id\":17,\"item_name\":\"Desktop Computer\",\"particulars\":\"Desktop Computer\",\"are_mr_ics_num\":\"\",\"quantity\":82,\"amount\":\"0\",\"value_amount\":0,\"date_delivered\":\"2026-01-23\",\"item_status\":\"Active\",\"date_updated\":\"2026-05-05 11:02:17\"}', '2026-05-05 11:02:17'),
(49, 1, 'Rey Mac-Hil Abing', 'accountable_items', 'UPDATE', 49, '{\"id\":49,\"inventory_item_id\":17,\"employee_id\":65,\"person_name\":\"NANCY  A.  ARBIS\",\"assigned_quantity\":2,\"are_mr_ics_num\":\"\",\"property_number\":\"arenaslkdj\",\"serial_number\":\"abcsdjkj \\/ lkjd93n164 \\/\",\"po_number\":\"\",\"account_code\":\"\",\"old_account_code\":\"\",\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-05-05 10:44:39\",\"remarks\":\"123123123 [Partial return to CGSO: 1 unit(s), Ref: 0]\",\"is_deleted\":0,\"created_by_id\":1,\"created_by_name\":\"Rey Mac-Hil Abing\",\"last_updated_by_id\":1,\"last_updated_by_name\":\"0\",\"last_updated_at\":\"2026-05-05 11:02:17\",\"item_name\":\"Desktop Computer\"}', '{\"id\":49,\"inventory_item_id\":17,\"employee_id\":65,\"person_name\":\"NANCY  A.  ARBIS\",\"assigned_quantity\":1,\"are_mr_ics_num\":\"\",\"property_number\":\"arenaslkdj\",\"serial_number\":\"abcsdjkj \\/\",\"po_number\":\"\",\"account_code\":\"\",\"old_account_code\":\"\",\"condition_status\":\"Serviceable\",\"date_assigned\":\"2026-05-05 10:44:39\",\"remarks\":\"123123123 [Partial return to CGSO: 1 unit(s), Ref: 0] [Partial return to CGSO: 1 unit(s), Ref: 0]\",\"is_deleted\":0,\"created_by_id\":1,\"created_by_name\":\"Rey Mac-Hil Abing\",\"last_updated_by_id\":1,\"last_updated_by_name\":\"0\",\"last_updated_at\":\"2026-05-05 13:32:42\"}', '2026-05-05 13:32:42'),
(50, 1, 'Rey Mac-Hil Abing', 'returned_to_cgso', 'CREATE', 2, NULL, '{\"accountable_id\":49,\"inventory_item_id\":17,\"item_name\":\"Desktop Computer\",\"employee\":\"NANCY A. ARBIS\",\"returned_qty\":1,\"remaining_qty\":1,\"returned_serials\":\"lkjd93n164 \\/\",\"ref_no\":\"RTC-20260505073242-8706\",\"returned_by\":\"Rey Mac-Hil Abing\",\"role\":\"ADMIN\"}', '2026-05-05 13:32:42'),
(51, 1, 'Rey Mac-Hil Abing', 'inventory_items', 'UPDATE', 17, '{\"quantity\":2}', '{\"id\":17,\"item_name\":\"Desktop Computer\",\"particulars\":\"Desktop Computer\",\"are_mr_ics_num\":\"\",\"quantity\":83,\"amount\":\"0\",\"value_amount\":0,\"date_delivered\":\"2026-01-23\",\"item_status\":\"Active\",\"date_updated\":\"2026-05-05 13:32:42\"}', '2026-05-05 13:32:42'),
(52, 1, 'Rey Mac-Hil Abing', 'accountable_items', 'CREATE', 50, NULL, '{\"inventory_item_id\":13,\"custodian\":\"JENNY  S.  BETAIZAR\",\"quantity\":3,\"par_ics\":\"\",\"property_no\":\"asdasdfas 4adfasdf \",\"serial_no\":\"jhasdljkfhasdf \\/ kasdjfl;askdjf \\/ a;skldjf;akljsdf \",\"assigned_by_id\":1,\"assigned_by_name\":\"Rey Mac-Hil Abing\"}', '2026-05-05 13:34:30'),
(53, 1, 'Rey Mac-Hil Abing', 'inventory_items', 'UPDATE', 13, '{\"id\":13,\"item_name\":\"Aircon 3ton kolin floor mounted\",\"particulars\":\"Aircon 3ton kolin floor mounted\",\"are_mr_ics_num\":\"\",\"quantity\":4,\"amount\":\"0\",\"value_amount\":0,\"date_delivered\":\"2026-01-23\",\"item_status\":\"Active\",\"date_updated\":\"2026-01-23 11:41:26\"}', '{\"id\":13,\"item_name\":\"Aircon 3ton kolin floor mounted\",\"particulars\":\"Aircon 3ton kolin floor mounted\",\"are_mr_ics_num\":\"\",\"quantity\":1,\"amount\":\"0\",\"value_amount\":0,\"date_delivered\":\"2026-01-23\",\"item_status\":\"Active\",\"date_updated\":\"2026-05-05 13:34:30\"}', '2026-05-05 13:34:30'),
(54, 8, NULL, 'borrowed_items', 'CREATE', 40, NULL, '{\"accountable_id\":50,\"inventory_item_id\":13,\"from_person\":\"JENNY S. BETAIZAR\",\"to_person\":\"REY MAC-HIL C. ABING\",\"quantity\":1,\"borrow_date\":\"2026-05-05T08:18\",\"status\":\"PENDING\"}', '2026-05-05 14:23:14'),
(55, 3, NULL, 'borrowed_items', 'UPDATE', 40, '{\"status\":\"PENDING\"}', '{\"status\":\"APPROVED\",\"approved_by\":3,\"actor_role\":\"MANAGER\"}', '2026-05-05 14:29:20');

-- --------------------------------------------------------

--
-- Table structure for table `borrowed_items`
--

CREATE TABLE `borrowed_items` (
  `borrow_id` int(11) NOT NULL,
  `accountable_id` int(11) NOT NULL,
  `inventory_item_id` int(11) NOT NULL,
  `from_person` varchar(255) NOT NULL,
  `to_person` varchar(255) NOT NULL,
  `borrower_employee_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `borrow_date` datetime NOT NULL DEFAULT current_timestamp(),
  `return_date` datetime DEFAULT NULL,
  `are_mr_ics_num` varchar(60) DEFAULT NULL,
  `property_number` varchar(300) DEFAULT NULL,
  `serial_number` varchar(70) DEFAULT NULL,
  `po_number` varchar(70) DEFAULT NULL,
  `account_code` varchar(50) DEFAULT NULL,
  `old_account_code` varchar(50) DEFAULT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `is_returned` tinyint(1) NOT NULL DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `status` enum('PENDING','APPROVED','DENIED','CANCELLED','RETURN_PENDING','RETURNED') NOT NULL DEFAULT 'PENDING',
  `requested_by` int(11) DEFAULT NULL,
  `requested_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `decision_remarks` text DEFAULT NULL,
  `return_request_status` enum('NONE','PENDING','APPROVED','DENIED') NOT NULL DEFAULT 'NONE',
  `return_requested_by` int(11) DEFAULT NULL,
  `return_requested_at` datetime DEFAULT NULL,
  `return_approved_by` int(11) DEFAULT NULL,
  `return_approved_at` datetime DEFAULT NULL,
  `return_decision_remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowed_items`
--

INSERT INTO `borrowed_items` (`borrow_id`, `accountable_id`, `inventory_item_id`, `from_person`, `to_person`, `borrower_employee_id`, `quantity`, `borrow_date`, `return_date`, `are_mr_ics_num`, `property_number`, `serial_number`, `po_number`, `account_code`, `old_account_code`, `reference_no`, `is_returned`, `remarks`, `status`, `requested_by`, `requested_at`, `approved_by`, `approved_at`, `decision_remarks`, `return_request_status`, `return_requested_by`, `return_requested_at`, `return_approved_by`, `return_approved_at`, `return_decision_remarks`) VALUES
(11, 20, 32, 'LILY DAWN  E.  FULGUERINAS', 'LEAH JANE  B.  CASTAMAYOR', 2210, 0, '2026-01-26 11:18:28', '2026-01-26 11:21:44', '0', '2020-07-1169', '2015910-11147/ 20161910-11221', '', '', '', 'BORROW-20260126041828-6984', 1, '', 'PENDING', NULL, NULL, NULL, NULL, NULL, 'NONE', NULL, NULL, NULL, NULL, NULL),
(12, 17, 15, 'LILY DAWN  E.  FULGUERINAS', 'REY MAC-HIL  C.  ABING', 2135, 0, '2026-01-26 11:18:43', '2026-01-26 11:19:11', '0', '', '05ZD3NAMA02643', '', '', '', 'BORROW-20260126041843-4265', 1, '', 'PENDING', NULL, NULL, NULL, NULL, NULL, 'NONE', NULL, NULL, NULL, NULL, NULL),
(13, 24, 33, 'LILY DAWN  E.  FULGUERINAS', 'KAREEN ANN  R.  BANTOTO', 1485, 1, '2026-01-27 10:32:18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'BORROW-20260127033218-9114', 0, 'Motherboard: R7M00Y04B58565V\r\nProcessor:U24R22K200245\r\nOptical Disc Drive: ZAJS\r\nSDD: 30072346479\r\nMonitor: N200SG81A2307812689\r\nUPS: E2206112262', 'PENDING', NULL, NULL, NULL, NULL, NULL, 'NONE', NULL, NULL, NULL, NULL, NULL),
(14, 24, 33, 'LILY DAWN  E.  FULGUERINAS', 'SAMANTHA CHESKA  DIONIO ', 5075, 1, '2026-01-27 10:33:42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'BORROW-20260127033342-3747', 0, 'Intel Core i7-10700\r\nRAM: 8gb DDR4\r\nMotherboard: ASUS H510MD with AVL\r\nStorage: 256SSD\r\nMonitor: 16.5\" LCD Display\r\nKB & Mouse: USB A4tech or genius\r\n\r\nMotherboard: R7M00Y04B368YWD\r\nProcessor: U2N53Y6403758\r\nOptical Dics Drive DRW: AZG5\r\nSDD: 30072346486\r\nUPS: E2206104026\r\n\r\nMonitor with SN N200SG81A2307812703 has been returned to GSO', 'PENDING', NULL, NULL, NULL, NULL, NULL, 'NONE', NULL, NULL, NULL, NULL, NULL),
(15, 19, 21, 'LILY DAWN  E.  FULGUERINAS', 'REY MAC-HIL  C.  ABING', 2135, 0, '2026-01-27 13:51:45', '2026-01-27 13:58:11', '0', '', 'NXKBNSP00124500DE37600', '', '', '', 'BORROW-20260127065145-9672', 1, 'for meeting', 'APPROVED', NULL, NULL, 1, '2026-04-23 08:08:31', NULL, 'NONE', NULL, NULL, NULL, NULL, NULL),
(16, 26, 30, 'REY MAC-HIL  C.  ABING', 'SYDNEY  B.  ARNADO', 1629, 1, '2026-01-27 16:42:30', NULL, '123', '123', '123', '123', '123', '123', 'BORROW-20260127094230-6867', 0, '', 'PENDING', NULL, NULL, NULL, NULL, NULL, 'NONE', NULL, NULL, NULL, NULL, NULL),
(17, 27, 26, 'JENNY  S.  BETAIZAR', 'BENJAMIN M. AMOTO JR', 5067, 2, '2026-01-28 13:41:59', NULL, '987', '987', '978', '978', '987', '987', 'BORROW-20260128064159-9594', 0, '123', 'PENDING', NULL, NULL, NULL, NULL, NULL, 'NONE', NULL, NULL, NULL, NULL, NULL),
(21, 27, 26, 'JENNY  S.  BETAIZAR', 'CHRISTIAN A. SOSOBRADO JR', 5065, 0, '2026-01-29 15:20:08', '2026-01-29 15:20:35', '987', '987', '978', '978', '987', '987', 'BORROW-20260129082008-6898', 1, 'for FS', 'PENDING', NULL, NULL, NULL, NULL, NULL, 'NONE', NULL, NULL, NULL, NULL, NULL),
(23, 27, 26, 'JENNY  S.  BETAIZAR', 'VALERIE  C.  ABOY', 1591, 0, '2026-01-29 18:15:35', '2026-01-29 18:20:32', '987', '987', '978', '978', '987', '987', 'BORROW-20260129111535-7517', 1, '123', 'PENDING', NULL, NULL, NULL, NULL, NULL, 'NONE', NULL, NULL, NULL, NULL, NULL),
(24, 27, 26, 'JENNY  S.  BETAIZAR', 'SYDNEY  B.  ARNADO', 1629, 1, '2026-01-29 18:20:45', NULL, '987', '987', '978', '978', '987', '987', 'BORROW-20260129112045-8773', 0, '123', 'APPROVED', NULL, NULL, 3, '2026-05-04 13:06:16', '', 'NONE', NULL, NULL, NULL, NULL, NULL),
(25, 34, 24, 'FAHREEN GAILE  B.  TULIAO', 'SYDNEY  B.  ARNADO', 1629, 0, '2026-02-02 14:03:59', '2026-02-02 14:04:26', '123', '123', '123', '', '', '', 'BORROW-20260202070359-7359', 1, '123', 'PENDING', NULL, NULL, NULL, NULL, NULL, 'NONE', NULL, NULL, NULL, NULL, NULL),
(26, 34, 24, 'FAHREEN GAILE  B.  TULIAO', 'APPLE MAE JARABAY ONIA ', 5068, 0, '2026-02-02 14:05:08', '2026-02-02 17:46:47', '123', '123', '123', '', '', '', 'BORROW-20260202070508-1110', 1, '', 'PENDING', NULL, NULL, NULL, NULL, NULL, 'NONE', NULL, NULL, NULL, NULL, NULL),
(27, 33, 17, 'CHRISTIAN A. SOSOBRADO JR', 'MARYLOU  T.  ASUNTO', 1508, 0, '2026-02-02 15:28:27', '2026-02-02 15:28:36', '123', 'qwe', '987', 'qwe', 'qwe', 'qwe', 'BORROW-20260202082827-7722', 1, '', 'PENDING', NULL, NULL, NULL, NULL, NULL, 'NONE', NULL, NULL, NULL, NULL, NULL),
(28, 34, 24, 'FAHREEN GAILE  B.  TULIAO', 'BENJAMIN M. AMOTO JR', 5067, 0, '2026-02-03 08:46:50', '2026-02-03 09:32:06', '123', '123', '123', '', '', '', 'BORROW-20260203014650-2796', 1, '', 'APPROVED', 2, '2026-02-03 08:46:50', 3, '2026-05-04 13:05:30', '', 'NONE', NULL, NULL, NULL, NULL, NULL),
(29, 34, 24, 'FAHREEN GAILE  B.  TULIAO', 'LEAH JANE  B.  CASTAMAYOR', 2210, 0, '2026-02-03 09:45:47', '2026-02-06 13:35:56', '123', '123', '123', '', '', '', 'BORROW-20260203024547-2946', 1, '123', 'APPROVED', 2, '2026-02-03 09:45:47', 1, '2026-02-03 09:46:21', 'ok', 'NONE', NULL, NULL, NULL, NULL, NULL),
(30, 27, 26, 'JENNY  S.  BETAIZAR', 'ELVIRA  P.  ARCILLAS', 582, 1, '2026-02-03 14:17:48', NULL, '987', '987', '978', '978', '987', '987', 'BORROW-20260203071748-4327', 0, '', 'APPROVED', 2, '2026-02-03 14:17:48', 1, '2026-02-06 14:05:47', NULL, 'NONE', NULL, NULL, NULL, NULL, NULL),
(31, 26, 30, 'REY MAC-HIL  C.  ABING', 'PRINCES YABUT PINTOR ', 5062, 1, '2026-02-04 16:23:50', NULL, '123', '123', '123', '123', '123', '123', 'BORROW-20260204092350-1996', 0, '', 'APPROVED', 2, '2026-02-04 16:23:50', 1, '2026-02-06 09:02:57', NULL, 'NONE', NULL, NULL, NULL, NULL, NULL),
(32, 27, 26, 'JENNY  S.  BETAIZAR', 'SYDNEY  B.  ARNADO', 1629, 0, '2026-02-06 13:51:19', '2026-02-06 14:04:44', '987', '987', '978', '978', '987', '987', 'BORROW-20260206065119-9147', 1, '', 'APPROVED', 2, '2026-02-06 13:51:19', 1, '2026-02-06 13:51:43', NULL, 'NONE', NULL, NULL, NULL, NULL, NULL),
(33, 34, 24, 'FAHREEN GAILE  B.  TULIAO', 'ELVIRA  P.  ARCILLAS', 582, 0, '2026-02-06 15:34:59', '2026-02-06 15:36:23', '123', '123', '123', '', '', '', 'BORROW-20260206083459-7786', 1, '', 'APPROVED', 2, '2026-02-06 15:34:59', 1, '2026-02-06 15:35:42', NULL, 'NONE', NULL, NULL, NULL, NULL, NULL),
(34, 33, 17, 'CHRISTIAN A. SOSOBRADO JR', 'SYDNEY  B.  ARNADO', 1629, 0, '2026-02-06 15:36:45', '2026-04-30 14:07:11', '123', 'qwe', '987', 'qwe', 'qwe', 'qwe', 'BORROW-20260206083645-7500', 1, '', '', 2, '2026-02-06 15:36:45', 1, '2026-02-06 15:37:10', NULL, 'NONE', NULL, NULL, NULL, NULL, NULL),
(35, 23, 33, 'LILY DAWN  E.  FULGUERINAS', 'JOVER  APOSTOL', 870, 1, '2026-04-30 15:10:03', '2026-04-30 15:15:42', NULL, NULL, NULL, NULL, NULL, NULL, 'BORROW-20260430091003-7023', 1, 'dfg5g4', 'RETURNED', 1, '2026-04-30 15:10:03', 1, '2026-04-30 15:10:03', NULL, 'NONE', 3, '2026-04-30 15:15:10', 1, '2026-04-30 15:15:42', NULL),
(36, 42, 17, 'VALERIE  C.  ABOY', 'REY MAC-HIL  ABING', 2135, 1, '2026-04-30 15:12:01', '2026-04-30 15:14:40', 'qeweq 12342 sedfadf32', 'qweqweqwe', '123123564dfgdfgdfg', 'qweqwe', 'qweqweqwe', NULL, 'BORROW-20260430091201-5555', 1, '123................', 'RETURNED', 5, '2026-04-30 15:12:01', 1, '2026-04-30 15:13:58', '', 'NONE', 5, '2026-04-30 15:14:16', 1, '2026-04-30 15:14:40', NULL),
(37, 42, 17, 'VALERIE  C.  ABOY', 'REY MAC-HIL  ABING', 2135, 1, '2026-04-30 15:45:52', NULL, 'qeweq 12342 sedfadf32', 'qweqweqwe', '123123564dfgdfgdfg', 'qweqwe', 'qweqweqwe', NULL, 'BORROW-20260430094552-3898', 0, '99999999999999', 'APPROVED', 5, '2026-04-30 15:45:52', 3, '2026-05-04 13:06:06', '', 'NONE', NULL, NULL, NULL, NULL, NULL),
(38, 42, 17, 'VALERIE  C.  ABOY', 'FAHREEN GAILE TULIAO', 7, 1, '2026-04-30 16:16:45', '2026-05-04 15:55:05', 'qeweq 12342 sedfadf32', 'qweqweqwe', '123123564dfgdfgdfg', 'qweqwe', 'qweqweqwe', NULL, 'BORROW-20260430101645-8614', 1, '1111', 'RETURNED', 7, '2026-04-30 16:16:45', 3, '2026-05-04 12:58:32', '', 'NONE', 7, '2026-05-04 15:54:02', 3, '2026-05-04 15:55:05', NULL),
(39, 26, 30, 'REY MAC-HIL  C.  ABING', 'FAHREEN GAILE TULIAO', 7, 1, '2026-05-04 13:48:15', NULL, '123', '123', '123', '123', '123', '123', 'BORROW-20260504074815-2342', 0, '******sdasdasd', 'DENIED', 7, '2026-05-04 13:48:15', 1, '2026-05-04 15:50:25', '123asd', 'NONE', NULL, NULL, NULL, NULL, NULL),
(40, 50, 13, 'JENNY S. BETAIZAR', 'REY MAC-HIL C. ABING', 2135, 1, '2026-05-05 08:18:00', NULL, NULL, NULL, 'kasdjfl;askdjf', NULL, NULL, NULL, 'BRW-20260505081853-0008', 0, 'new new', 'APPROVED', 8, '2026-05-05 14:23:14', 3, '2026-05-05 14:29:13', '', 'NONE', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cao_employee`
--

CREATE TABLE `cao_employee` (
  `ID` int(11) NOT NULL,
  `end_user_id_number` int(11) DEFAULT NULL,
  `FIRSTNAME` varchar(255) DEFAULT NULL,
  `MIDDLENAME` varchar(255) DEFAULT NULL,
  `LASTNAME` varchar(255) DEFAULT NULL,
  `SUFFIX` varchar(255) DEFAULT NULL,
  `DEPARTMENT_ID` int(11) DEFAULT NULL,
  `DETAILED_DEPARTMENT_ID` int(11) DEFAULT NULL,
  `CREATED_BY` int(11) DEFAULT NULL,
  `CREATED_WHEN` datetime DEFAULT NULL,
  `UPDATED_BY` int(11) DEFAULT NULL,
  `UPDATED_WHEN` datetime DEFAULT NULL,
  `DELETED` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cao_employee`
--

INSERT INTO `cao_employee` (`ID`, `end_user_id_number`, `FIRSTNAME`, `MIDDLENAME`, `LASTNAME`, `SUFFIX`, `DEPARTMENT_ID`, `DETAILED_DEPARTMENT_ID`, `CREATED_BY`, `CREATED_WHEN`, `UPDATED_BY`, `UPDATED_WHEN`, `DELETED`) VALUES
(35, 21191, 'RENZO', 'SARANILLO', 'MINA', '', 1, 1, 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0),
(59, 23157, 'GABRIEL LOUIS NOE', 'CELO', 'QUIMOSING', '', 1, 1, 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0),
(64, 10170, 'LALAINE ', 'P. ', 'AMOROSO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(65, 10172, 'NANCY ', 'A. ', 'ARBIS', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(66, 10173, 'MARIA LUALHATI ', 'C. ', 'BALAN', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(67, 26392, 'GERALDINE', 'G.', 'LLERA', '', 1, 1, 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0),
(68, 10177, 'ZENAIDA ', 'C. ', 'CORTEZ', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(69, 10178, 'CHERYLEA ', 'D. ', 'JUANGA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(70, 10179, 'GEMMA ', 'L. ', 'DALMACIO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(71, 10180, 'CHARITO ', 'S. ', 'DIAZ', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(72, 10181, 'JOSEPHINE ', 'E. ', 'DILI', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(73, 26685, 'ARIES', '', 'CATOLICO', '', 1, 1, 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0),
(74, 10183, 'JOSE ROMIL ', 'Q. ', 'DUREMDES', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(75, 27137, 'LUIE JAY', 'SUAREZ', 'MOMO', '', 1, 1, 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0),
(76, 10188, 'EVA ', 'E. ', 'HERMO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(77, 27166, 'KENN', 'M.', 'JAVINES', '', 1, 1, 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0),
(78, 10191, 'CORAZON ', 'C. ', 'GUERRA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(79, 10193, 'NENITA ', 'L. ', 'ALANO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(80, 10195, 'AGNES ', 'V. ', 'ODAN', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(83, 10198, 'ELLEN ', 'R. ', 'QUILANTANG', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(84, 10201, 'SUSAN ', 'P. ', 'SIGNAR', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(85, 10202, 'RUBEN ', 'R. ', 'TIAGA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(86, 10203, 'CATALINA ', 'P. ', 'TUBO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(228, 10685, 'MYLENE ', 'M. ', 'BASILIO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(463, 11634, 'ZAITON ', 'P. ', 'BAYENA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(494, 11951, 'SARIP MOCSEN ', 'T. ', 'BAGATAO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(582, 12453, 'ELVIRA ', 'P. ', 'ARCILLAS', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(698, 13288, 'RHESABABES ', 'G. ', 'SUMALPONG', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(709, 13350, 'BELLIE ', 'A. ', 'PALMITOS', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(771, 13805, 'LORIE ', 'C. ', 'BERMEJO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(775, 13829, 'NOVELITO ', 'J. ', 'MENDEZ', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(781, 13848, 'SHEILA MARIA ', 'M. ', 'CABARUBIAS', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(833, 14266, 'EDGAR ', 'L. ', 'OCMAR', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(840, 14290, 'DIANNE LORAINE ', 'L. ', 'CARBONQUILLO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(870, 14459, 'JOVER ', 'N. ', 'APOSTOL', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(879, 14539, 'HARCY ', 'D. ', 'MALAGAT', 'JR', 1, 1, NULL, NULL, NULL, NULL, 0),
(887, 14600, 'MARVIN ', 'S. ', 'GONATO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(957, 15215, 'BENJAMIN ', 'A. ', 'SUMOG-OY', 'JR', 1, 1, NULL, NULL, NULL, NULL, 0),
(1003, 16121, 'LILY DAWN ', 'E. ', 'FULGUERINAS', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1008, 16214, 'FATIMA LORAINE ', 'G. ', 'TOBIAS', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1030, 17375, 'ANTHONEL ', 'A. ', 'BURGOS', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1107, 18661, 'LINN GRACE ', 'R. ', 'SAGANA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1112, 18672, 'MA. TERESA ', 'E. ', 'MADRIA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1149, 18870, 'MARISEL ', 'B. ', 'LINDONG', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1150, 18871, 'BEVERLY ', 'F. ', 'PABON', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1164, 18961, 'RELJAINE ', 'E. ', 'ENCARNACION', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1166, 18966, 'FLABERT ', 'B. ', 'VILLASENCIO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1168, 18980, 'JORGE ', 'A. ', 'LESTONES', 'SR', 1, 1, NULL, NULL, NULL, NULL, 0),
(1258, 19819, 'AMINAH FE ', 'F. ', 'PAIDUMAMA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1279, 19950, 'MAYFHEL ', 'P. ', 'ELIAB', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1319, 20197, 'AILEEN ', 'L. ', 'BAÑAS', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1346, 20455, 'GINO CARLO ', 'V. ', 'PERIA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1388, 20753, 'ELAINE MAE ', 'V. ', 'PANLAQUE', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1422, 20849, 'JONALYN ', 'A. ', 'PURAZO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1444, 20935, 'FAHREEN GAILE ', 'B. ', 'TULIAO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1473, 21058, 'CLEMENT ', 'T. ', 'VILLARANTE', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1485, 21100, 'KAREEN ANN ', 'R. ', 'BANTOTO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1494, 21146, 'CLYMER ', 'T. ', 'VILLARANTE', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1508, 21218, 'MARYLOU ', 'T. ', 'ASUNTO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1528, 21282, 'DAVIS', 'S. ', 'OCUMEN', 'III', 1, 1, NULL, NULL, NULL, NULL, 0),
(1539, 21333, 'CHARLES JOHN ', 'P. ', 'CUBAR', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1559, 21431, 'ROGELIO ', 'B. ', 'ODI', 'JR', 1, 1, NULL, NULL, NULL, NULL, 0),
(1568, 21485, 'MARY CLAIRE', NULL, 'MANDAWE', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1591, 21564, 'VALERIE ', 'C. ', 'ABOY', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1594, 21579, 'MA. CRISTINA CLAIRE ', 'B. ', 'GUJELDE', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1612, 21655, 'LYNETTE', 'P.', 'ABANDO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1619, 21672, 'DIONA ', 'L. ', 'ADLAON', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1628, 21696, 'JOHN KENNETH ', 'T. ', 'PANLAQUE', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1629, 21710, 'SYDNEY ', 'B. ', 'ARNADO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1656, 21835, 'RICHARD ', 'P. ', 'GALO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1657, 21836, 'DANILO ', 'G. ', 'KARUNUNGAN', 'JR', 1, 1, NULL, NULL, NULL, NULL, 0),
(1660, 21844, 'LILWEN MAE ', 'P. ', 'ALOVERA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1663, 21850, 'MARIA ELIZA ', 'B. ', 'LEOY', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1665, 21896, 'RUDY ', 'M. ', 'MARISCAL', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1672, 21942, 'PAULO EDGARDO ', 'A. ', 'ARGENTES', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1705, 22161, 'JETHRO ', 'J. ', 'JINGCO', NULL, 2, 1, NULL, NULL, NULL, NULL, 0),
(1783, 22598, 'JESSIE CHRIS ', 'P. ', 'GEPTE', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1876, 23144, 'EMIEREN ', 'N. ', 'ALUCILJA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1877, 23145, 'JENNIFER ', 'P. ', 'LOZANO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1878, 23146, 'CATHERINE ', 'V. ', 'GETURBOS', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1879, 23147, 'ANGELA LOURDES ', 'C. ', 'BUSTOS', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1880, 23148, 'APRIL ', 'P. ', 'BAREJA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1881, 23150, 'JENNY ', 'S. ', 'BETAIZAR', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1883, 23152, 'CRISTINE ', 'H. ', 'DUPALCO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(1951, 23610, 'JESSIBEL ', 'T. ', 'MILLAN', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2013, 24117, 'BENJIE ', 'G. ', 'OBERIO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2014, 24118, 'SHEILLAH ', 'G. ', 'TATE', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2039, 24219, 'SAMSON ', 'L. ', 'MISOLES', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2084, 24434, 'STEVEN RUFE', 'CABREROS', 'PADOLINA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2085, 24435, 'GERALD', 'F.', 'CAÑETE', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2135, 24579, 'REY MAC-HIL ', 'C. ', 'ABING', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2136, 24580, 'CHRISTINE ', 'B. ', 'BAQUERO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2172, 24731, 'IRENE MAE ', 'A. ', 'CHING', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2173, 24732, 'ALBERT ', 'D. ', 'VILLANUEVA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2174, 24733, 'CARMELLE JADE ', 'P. ', 'BRAGA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2176, 24735, 'IAN ', 'D. ', 'GESULGON', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2177, 24736, 'RESTY RYAN ', 'T. ', 'ORTENCIO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2178, 24737, 'JENNIELYN ', 'B. ', 'ROMERO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2179, 24738, 'GLENN ', 'P. ', 'ETURMA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2180, 24739, 'MARY MAE ', 'C. ', 'PIALAGO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2181, 24740, 'JOCIN ', 'D. ', 'GALAGAR', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2182, 24741, 'ALEXANDER VAN DAVID ', 'T. ', 'ALBIOLA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2183, 24742, 'RENE ', 'L. ', 'DADO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2184, 24743, 'HENRY ', 'S. ', 'GONZAGA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2185, 24744, 'JUVELYN ', 'O. ', 'PETCHA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2186, 24745, 'REDEN JOHN ', 'B. ', 'DATAGO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2187, 24746, 'AIR COOL ', 'C. ', 'ALISIN', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2188, 24747, 'RESTY RYAN ', 'P. ', 'BIBIOLATA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2189, 24748, 'REYNALDO ', 'T. ', 'ALAB', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2190, 24749, 'ALFIE ', 'R. ', 'BIANTAN', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2191, 24750, 'JULETO ', 'T. ', 'CALAGO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2192, 24751, 'JIMREY ', 'D. ', 'SACAY', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2193, 24752, 'YVONNE ', 'C. ', 'OCMAR', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2194, 24753, 'SANNIE ', 'P. ', 'SAMBRIO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2210, 24887, 'LEAH JANE ', 'B. ', 'CASTAMAYOR', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2214, 24892, 'LAARNI ', 'E. ', 'CABBAB', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2215, 24893, 'MA. CRISTINE LOREN ', 'C. ', 'JAMORA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2216, 24894, 'JOMERO ', 'E. ', 'VARONA', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2217, 24895, 'SANNE', NULL, 'SOMBRIO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2225, 24912, 'DENNIS ', 'T. ', 'ORTENCIO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2266, 25064, 'JENELYN ', 'L. ', 'SIMOGAN', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2292, 999995, 'CYRILH ', 'T. ', 'MILLIO', NULL, 1, 1, NULL, NULL, NULL, NULL, 0),
(2295, 1111111, 'SAMPLE FNAME', 'SAMPLE MNAME', 'SAMPLE LNAME', 'N/A', 1, 1, 23679, '2024-08-08 10:46:38', NULL, NULL, 1),
(2296, 1111212, 'TEST 1', 'ASDASD', 'ASDASDASD', 'JR.', 1, 1, 23679, '2024-08-08 10:50:18', NULL, NULL, 1),
(2297, 12121, 'ASDASD', 'ASDASD', 'ASDASD', 'ASDASDASD', 1, 1, 23679, '2024-08-08 16:36:58', NULL, NULL, 1),
(5061, 25594, 'MARIA MICHELL', 'VIBAR', 'ROSAURO', '', 1, 1, 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0),
(5062, 26375, 'PRINCES', 'YABUT', 'PINTOR', '', 1, 1, 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0),
(5064, 26438, 'JEFF LOUIE', 'GERSAVA', 'TENG', '', 1, 1, 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0),
(5065, 26544, 'CHRISTIAN', 'A.', 'SOSOBRADO', 'JR', 1, 1, 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0),
(5066, 26546, 'AIZA', 'N.', 'TALONA', '', 1, 1, 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0),
(5067, 26556, 'BENJAMIN', 'M.', 'AMOTO', 'JR', 1, 1, 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0),
(5068, 26559, 'APPLE MAE', 'JARABAY', 'ONIA', '', 1, 1, 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0),
(5070, 26876, 'CINDY NESS', 'T.', 'JOVITA', '', 1, 1, 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0),
(5072, 27138, 'DAVID', 'C.', 'ALICAYA', 'JR', 1, 1, 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0),
(5074, 27245, 'HEIDE', 'H.', 'ABUNDA', '', 1, 1, 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0),
(5075, 27246, 'SAMANTHA CHESKA', '', 'DIONIO', '', 1, 1, 0, '0000-00-00 00:00:00', 0, '0000-00-00 00:00:00', 0);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `particulars` varchar(255) NOT NULL,
  `are_mr_ics_num` varchar(300) NOT NULL,
  `quantity` int(11) NOT NULL,
  `amount` varchar(300) NOT NULL,
  `value_amount` int(32) NOT NULL,
  `date_delivered` date NOT NULL,
  `item_status` enum('Active','Inactive','Returned','Defective','Replaced') DEFAULT 'Active',
  `date_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `item_name`, `particulars`, `are_mr_ics_num`, `quantity`, `amount`, `value_amount`, `date_delivered`, `item_status`, `date_updated`) VALUES
(9, 'Aircon window type', 'Aircon window type', '', 2, '0', 0, '2026-01-23', 'Active', '2026-04-29 09:01:26'),
(10, 'Aircon wall mounted type 2.5ph koppel', 'Aircon wall mounted type 2.5ph koppel', '', 2, '0', 0, '2026-01-23', 'Active', '2026-01-23 11:38:41'),
(11, 'Aircon Split type kolin', 'Aircon Split type kolin', '', 2, '0', 0, '2026-01-23', 'Active', '2026-01-23 11:39:49'),
(12, 'Aircon Split type General Royal', 'Aircon Split type General Royal', '', 2, '0', 0, '2026-01-23', 'Active', '2026-01-23 11:40:33'),
(13, 'Aircon 3ton kolin floor mounted', 'Aircon 3ton kolin floor mounted', '', 1, '0', 0, '2026-01-23', 'Active', '2026-05-05 13:34:30'),
(14, 'Aircon 5ton keppel floor mounted', 'Aircon 5ton keppel floor mounted', '', 3, '0', 0, '2026-01-23', 'Active', '2026-01-23 13:26:17'),
(15, 'Television', 'Television', '', 2, '0', 0, '2026-01-23', 'Active', '2026-01-23 13:09:21'),
(16, 'projector multimedia', 'projector multimedia', '', 3, '0', 0, '2026-01-23', 'Active', '2026-01-23 11:43:46'),
(17, 'Desktop Computer', 'Desktop Computer', '', 83, '0', 0, '2026-01-23', 'Active', '2026-05-05 13:32:42'),
(18, 'Communication Equipt (Amplifier)', 'Communication Equipt (Amplifier)', '', 1, '0', 0, '2026-01-23', 'Active', '2026-01-23 11:45:20'),
(19, 'Steel Cabinet', 'Steel Cabinet', '', 7, '0', 0, '2026-01-23', 'Active', '2026-01-29 18:04:16'),
(20, 'Refrigirator 3.0 cubic', 'Refrigirator 3.0 cubic', '', 2, '0', 0, '2026-01-23', 'Active', '2026-01-23 11:46:35'),
(21, 'Laptop', 'Laptop', '', 8, '0', 0, '2026-01-23', 'Active', '2026-01-23 13:24:12'),
(22, 'Swivel Chair w/ arms', 'Swivel Chair w/ arms', '', 142, '0', 0, '2026-01-23', 'Active', '2026-04-30 12:56:33'),
(23, 'Chair gang 4 seaters plastic', 'Chair gang 4 seaters plastic', '', 3, '0', 0, '2026-01-23', 'Active', '2026-01-23 11:49:04'),
(24, 'UPS', 'UPS', '', 67, '0', 0, '2026-01-23', 'Active', '2026-01-30 13:41:12'),
(25, 'Photocopoer - HP Laserjet MFP M42623 Series', 'Photocopoer - HP Laserjet MFP M42623 Series', '', 2, '0', 0, '2026-01-23', 'Active', '2026-01-23 12:45:44'),
(26, 'Printer InkJet Colored', 'Printer InkJet Colored', '', 3, '0', 0, '2026-01-23', 'Active', '2026-01-28 13:40:58'),
(27, 'Printer All in One Epson sn#X6GD002641', 'Printer All in One Epson sn#X6GD002641', '', 0, '0', 0, '2026-01-23', 'Active', '2026-01-29 17:47:28'),
(28, 'Printer laserjet brother colored black &  White', 'Printer laserjet brother colored black &  White', '', 4, '0', 0, '2026-01-23', 'Active', '2026-01-23 12:51:41'),
(29, 'Uniterruptible power supply (UPS) BV 800VA, AVR', 'Uniterruptible power supply (UPS) BV 800VA, AVR', '', 2, '0', 0, '2026-01-23', 'Active', '2026-01-23 12:52:54'),
(30, 'Document scanner high-speed of upto 35PPM/70', 'Document scanner high-speed of upto 35PPM/70', '', 2, '0', 0, '2026-01-23', 'Active', '2026-01-27 16:42:12'),
(31, 'Monitor', 'Monitor', '', 6, '0', 0, '2026-01-23', 'Active', '2026-04-29 12:56:46'),
(32, 'KOLIN 5T Floor mounted', 'KOLIN 5T Floor mounted', '', 0, '99800', 0, '2026-01-23', 'Active', '2026-01-23 13:30:34'),
(33, 'Desktop Computer Asus H510MD', 'Desktop Computer Asus H510MD', '', 2, '155700', 0, '2026-01-23', 'Active', '2026-01-29 17:39:38'),
(34, 'Vehicle', 'L-300 Mitsubishi Polar White Notes/Specs: L300 2.2D with FB Dual Body AC MT Engine #: 4N14UAN9994 CS #: Y2-I342 COLOR: Polar White', '', 0, '1350000', 0, '2023-06-09', 'Active', '2026-01-27 13:50:58'),
(35, 'Acer Laptop', 'IT Equipment', '', 19, '50000', 0, '2026-04-30', 'Active', '2026-04-30 08:48:41'),
(36, 'laptop', 'asus ROG2026', 'are-2026-01-01', 20, '98000', 1960000, '2026-05-05', 'Active', '2026-05-05 07:24:47');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `transaction_id` int(11) NOT NULL,
  `inventory_item_id` int(11) NOT NULL,
  `performed_by_id` int(11) DEFAULT NULL COMMENT 'user.id of the logged-in user who created this transaction',
  `performed_by_name` varchar(255) DEFAULT NULL COMMENT 'Snapshot of user full name at time of transaction',
  `user_id` int(11) DEFAULT NULL,
  `transaction_type` enum('IN','OUT','ADJUSTMENT','SHRINKAGE','DEADSTOCK') NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(12,2) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_transactions`
--

INSERT INTO `inventory_transactions` (`transaction_id`, `inventory_item_id`, `performed_by_id`, `performed_by_name`, `user_id`, `transaction_type`, `quantity`, `unit_cost`, `reference_no`, `transaction_date`, `remarks`) VALUES
(39, 15, NULL, NULL, NULL, 'OUT', 1, NULL, 'ASSIGN-20260123060921', '2026-01-23 05:09:21', NULL),
(40, 14, NULL, NULL, NULL, 'OUT', 1, NULL, 'ASSIGN-20260123062159', '2026-01-23 05:21:59', NULL),
(41, 21, NULL, NULL, NULL, 'OUT', 1, NULL, 'ASSIGN-20260123062412', '2026-01-23 05:24:12', NULL),
(42, 14, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260123062617', '2026-01-23 05:26:17', NULL),
(43, 32, NULL, NULL, NULL, 'OUT', 2, NULL, 'ASSIGN-20260123063034', '2026-01-23 05:30:34', NULL),
(44, 32, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260126041828-6984', '2026-01-26 03:18:28', NULL),
(45, 15, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260126041843-4265', '2026-01-26 03:18:43', NULL),
(46, 15, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260126041911-7358', '2026-01-26 03:19:11', NULL),
(47, 32, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260126042144-3178', '2026-01-26 03:21:44', NULL),
(48, 9, NULL, NULL, NULL, 'OUT', 4, NULL, 'ASSIGN-20260127030058', '2026-01-27 02:00:58', NULL),
(49, 9, NULL, NULL, NULL, 'OUT', 1, NULL, 'ADJUST-20260127030117', '2026-01-27 02:01:17', NULL),
(50, 9, NULL, NULL, NULL, 'IN', 5, NULL, 'RETURN-20260127030139', '2026-01-27 02:01:39', NULL),
(51, 33, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260127033218-9114', '2026-01-27 02:32:18', NULL),
(52, 33, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260127033342-3747', '2026-01-27 02:33:42', NULL),
(53, 34, NULL, NULL, NULL, 'OUT', 1, NULL, 'ASSIGN-20260127065058', '2026-01-27 05:50:58', NULL),
(54, 21, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260127065145-9672', '2026-01-27 05:51:45', NULL),
(55, 21, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260127065811-5311', '2026-01-27 05:58:11', NULL),
(56, 30, NULL, NULL, NULL, 'OUT', 8, NULL, 'ASSIGN-20260127094212', '2026-01-27 08:42:12', NULL),
(57, 30, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260127094230-6867', '2026-01-27 08:42:30', NULL),
(58, 26, NULL, NULL, NULL, 'OUT', 7, NULL, 'ASSIGN-20260128064058', '2026-01-28 05:40:58', NULL),
(59, 26, NULL, NULL, NULL, 'OUT', 2, NULL, 'BORROW-20260128064159-9594', '2026-01-28 05:41:59', NULL),
(60, 26, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260129082008-6898', '2026-01-29 07:20:08', NULL),
(61, 26, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260129082035-9874', '2026-01-29 07:20:35', NULL),
(62, 33, NULL, NULL, NULL, 'OUT', 1, NULL, 'WEB-OUT-20260129103938', '2026-01-29 09:39:38', ''),
(63, 27, NULL, NULL, NULL, 'OUT', 1, NULL, 'WEB-OUT-20260129104728', '2026-01-29 09:47:28', '123'),
(64, 9, NULL, NULL, NULL, 'OUT', 1, NULL, 'ASSIGN-20260129104934', '2026-01-29 09:49:34', NULL),
(65, 19, NULL, NULL, NULL, 'OUT', 1, NULL, 'ASSIGN-20260129110416', '2026-01-29 10:04:16', NULL),
(66, 26, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260129111535-7517', '2026-01-29 10:15:35', NULL),
(67, 17, NULL, NULL, NULL, 'OUT', 1, NULL, 'ASSIGN-20260129111923', '2026-01-29 10:19:23', NULL),
(68, 26, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260129112032-8045', '2026-01-29 10:20:32', NULL),
(69, 26, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260129112045-8773', '2026-01-29 10:20:45', NULL),
(70, 17, NULL, NULL, NULL, 'OUT', 2, NULL, 'ASSIGN-20260130030729', '2026-01-30 02:07:29', NULL),
(71, 24, NULL, NULL, NULL, 'OUT', 3, NULL, 'ASSIGN-20260130064112', '2026-01-30 05:41:12', NULL),
(72, 17, NULL, NULL, NULL, 'OUT', 1, NULL, 'ASSIGN-20260202053345', '2026-02-02 04:33:45', NULL),
(73, 17, NULL, NULL, NULL, 'OUT', 1, NULL, 'ASSIGN-20260202054825', '2026-02-02 04:48:25', NULL),
(74, 22, NULL, NULL, NULL, 'OUT', 1, NULL, 'ASSIGN-20260202055335', '2026-02-02 04:53:35', NULL),
(75, 22, NULL, NULL, NULL, 'OUT', 2, NULL, 'ASSIGN-20260202055821', '2026-02-02 04:58:21', NULL),
(76, 22, NULL, NULL, NULL, 'OUT', 1, NULL, 'ASSIGN-20260202062927', '2026-02-02 05:29:27', NULL),
(77, 22, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260202063308', '2026-02-02 05:33:08', NULL),
(78, 22, NULL, NULL, NULL, 'OUT', 1, NULL, 'ASSIGN-20260202063325', '2026-02-02 05:33:25', NULL),
(79, 24, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260202070359-7359', '2026-02-02 06:03:59', NULL),
(80, 24, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260202070426-3720', '2026-02-02 06:04:26', NULL),
(81, 24, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260202070508-1110', '2026-02-02 06:05:08', NULL),
(82, 22, NULL, NULL, NULL, 'OUT', 1, NULL, 'ADJUST-20260202080003', '2026-02-02 07:00:03', NULL),
(83, 22, NULL, NULL, NULL, 'IN', 1, NULL, 'ADJUST-20260202080038', '2026-02-02 07:00:38', NULL),
(84, 17, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260202082827-7722', '2026-02-02 07:28:27', NULL),
(85, 17, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260202082836-1694', '2026-02-02 07:28:36', NULL),
(86, 24, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260202104647-8005', '2026-02-02 09:46:47', NULL),
(87, 24, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260203023206-3813', '2026-02-03 01:32:06', NULL),
(88, 24, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260203024547-2946', '2026-02-03 01:46:21', NULL),
(89, 22, NULL, NULL, NULL, 'OUT', 10, NULL, 'ASSIGN-20260205013523', '2026-02-05 00:35:23', NULL),
(90, 30, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260204092350-1996', '2026-02-06 01:02:57', NULL),
(91, 24, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260206063556-4463', '2026-02-06 05:35:56', NULL),
(92, 17, NULL, NULL, NULL, 'OUT', 10, NULL, 'ASSIGN-20260206065034', '2026-02-06 05:50:34', NULL),
(93, 26, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260206065119-9147', '2026-02-06 05:51:43', NULL),
(94, 26, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260206070444-4213', '2026-02-06 06:04:44', NULL),
(95, 24, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260206083459-7786', '2026-02-06 07:35:42', NULL),
(96, 24, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260206083623-3011', '2026-02-06 07:36:23', NULL),
(97, 17, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260206083645-7500', '2026-02-06 07:37:10', NULL),
(102, 21, NULL, NULL, NULL, 'OUT', 0, NULL, 'BORROW-20260127065145-9672', '2026-04-23 00:08:31', NULL),
(103, 9, NULL, NULL, NULL, 'OUT', 1, NULL, 'ASSIGN-20260429030126', '2026-04-29 01:01:26', NULL),
(104, 22, NULL, NULL, NULL, 'OUT', 1, NULL, 'ASSIGN-20260429041446', '2026-04-29 02:14:46', 'Assigned to RHESABABES  G.  SUMALPONG. Serial: 123123 / 123123/edrgwsdrfg/ .,.fgdf'),
(105, 31, NULL, NULL, NULL, 'OUT', 1, NULL, 'ASSIGN-20260429065646', '2026-04-29 04:56:46', 'Assigned to STEVEN RUFE CABREROS PADOLINA. Serial: 123/././....'),
(106, 35, NULL, NULL, NULL, 'IN', 20, 50000.00, 'INIT-20260430023958', '2026-04-30 00:39:58', NULL),
(107, 35, NULL, NULL, NULL, 'OUT', 1, NULL, 'ASSIGN-20260430024841', '2026-04-30 00:48:41', 'Assigned to PRINCES YABUT PINTOR. Serial: *-/+4+-446456'),
(108, 22, 3, 'Jenny Betaizar', NULL, 'OUT', 10, NULL, '0', '2026-04-30 04:56:33', 'Assigned to FAHREEN GAILE  B.  TULIAO. Serial: 950965164. By: Jenny Betaizar'),
(109, 17, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260430080711', '2026-04-30 06:07:11', NULL),
(110, 33, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260430091003-7023', '2026-04-30 07:10:03', NULL),
(111, 17, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260430091201-5555', '2026-04-30 07:13:58', NULL),
(112, 17, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260430091440-9245', '2026-04-30 07:14:40', NULL),
(113, 33, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260430091542-6957', '2026-04-30 07:15:42', NULL),
(114, 17, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260430101645-8614', '2026-05-04 04:58:32', NULL),
(115, 24, NULL, NULL, NULL, 'OUT', 0, NULL, 'BORROW-20260203014650-2796', '2026-05-04 05:05:30', NULL),
(116, 17, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260430094552-3898', '2026-05-04 05:06:06', NULL),
(117, 26, NULL, NULL, NULL, 'OUT', 1, NULL, 'BORROW-20260129112045-8773', '2026-05-04 05:06:16', NULL),
(118, 17, NULL, NULL, NULL, 'IN', 1, NULL, 'RETURN-20260504095505-1849', '2026-05-04 07:55:05', NULL),
(119, 17, 1, 'Rey Mac-Hil Abing', NULL, 'OUT', 3, NULL, '0', '2026-05-05 02:44:39', 'Assigned to NANCY  A.  ARBIS. Serial: abcsdjkj / 198hassd / lkjd93n164 . By: Rey Mac-Hil Abing'),
(120, 17, 1, 'Rey Mac-Hil Abing', NULL, 'IN', 1, NULL, '0', '2026-05-05 03:02:17', 'Returned to CGSO from NANCY A. ARBIS. Serial(s): 198hassd /. By: Rey Mac-Hil Abing (ADMIN). Ref: RTC-20260505050217-8685'),
(121, 17, 1, 'Rey Mac-Hil Abing', NULL, 'IN', 1, NULL, '0', '2026-05-05 05:32:42', 'Returned to CGSO from NANCY A. ARBIS. Serial(s): lkjd93n164 /. By: Rey Mac-Hil Abing (ADMIN). Ref: RTC-20260505073242-8706'),
(122, 13, 1, 'Rey Mac-Hil Abing', NULL, 'OUT', 3, NULL, '0', '2026-05-05 05:34:30', 'Assigned to JENNY  S.  BETAIZAR. Serial: jhasdljkfhasdf / kasdjfl;askdjf / a;skldjf;akljsdf . By: Rey Mac-Hil Abing'),
(123, 13, NULL, NULL, NULL, 'OUT', 1, NULL, 'BRW-20260505081853-0008', '2026-05-05 06:29:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `login_time` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `login_status` enum('SUCCESS','FAILED') NOT NULL,
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messenger_messages`
--

CREATE TABLE `messenger_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messenger_messages`
--

INSERT INTO `messenger_messages` (`id`, `sender_id`, `receiver_id`, `message_text`, `timestamp`, `is_read`) VALUES
(1, 2, 1, 'lkajsd', '2026-02-04 00:06:13', 1),
(2, 3, 2, 'heeeasdasdasd', '2026-02-04 00:07:45', 1),
(3, 2, 1, 'asdasdasd', '2026-02-04 06:45:31', 1),
(4, 2, 1, 'kmdskdjhfa', '2026-02-04 06:45:38', 1),
(5, 2, 1, 'a;lkdjfa;ldkfjak;ljj;lkl;kglkh;k', '2026-02-04 06:46:04', 1),
(6, 2, 1, 'fsdfsdf', '2026-02-04 06:46:11', 1),
(7, 1, 2, 'adfalk;djf;alkdfja;dfljkadf', '2026-02-04 06:46:20', 1),
(8, 2, 4, '7319827309u jsadkf', '2026-02-06 06:39:01', 1),
(9, 1, 3, 'fjkals;dfjasd', '2026-02-06 09:17:17', 1),
(10, 1, 2, 'asdfasdf', '2026-02-06 09:17:45', 1),
(11, 1, 2, 'asdfasdf', '2026-02-06 09:17:50', 1),
(12, 2, 1, 'kldjfklsjdf', '2026-02-06 09:17:54', 1),
(13, 2, 1, 'sdfkjsdifa;sldkfasd;flkasjdfin;lKndfan', '2026-02-06 09:18:10', 1),
(14, 1, 3, 'asdasdasdadasdasdadsasdsdsdasdas', '2026-04-27 02:45:52', 1),
(15, 3, 1, 'asdasdasdsadasdasdasdasdadadas', '2026-04-27 02:45:58', 1);

-- --------------------------------------------------------

--
-- Table structure for table `messenger_users`
--

CREATE TABLE `messenger_users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `status` enum('online','offline') DEFAULT 'offline',
  `is_typing` tinyint(1) DEFAULT 0,
  `last_active` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messenger_users`
--

INSERT INTO `messenger_users` (`id`, `username`, `email`, `password_hash`, `status`, `is_typing`, `last_active`) VALUES
(1, 'mac', NULL, NULL, 'online', 0, '2026-05-04 08:14:56'),
(2, 'mayumi', NULL, NULL, 'online', 0, '2026-02-06 09:18:13'),
(3, 'jennyB', NULL, NULL, 'online', 0, '2026-04-27 02:45:58'),
(4, 'pyp', NULL, NULL, 'online', 0, '2026-02-06 07:39:11');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `remarks` text DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `action` enum('NONE','APPROVED','DENIED') NOT NULL DEFAULT 'NONE',
  `action_by` int(11) DEFAULT NULL,
  `action_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `actor_user_id`, `type`, `related_id`, `payload`, `remarks`, `is_read`, `created_at`, `action`, `action_by`, `action_at`) VALUES
(1, 1, 2, 'borrow_request', 28, '{\"reference\":\"BORROW-20260203014650-2796\",\"borrower\":\"BENJAMIN M. AMOTO JR\",\"message\":\"Borrow request BORROW-20260203014650-2796 by BENJAMIN M. AMOTO JR\"}', NULL, 1, '2026-02-03 08:46:50', 'APPROVED', 3, '2026-05-04 13:05:36'),
(2, 3, 2, 'borrow_request', 28, '{\"reference\":\"BORROW-20260203014650-2796\",\"borrower\":\"BENJAMIN M. AMOTO JR\",\"message\":\"Borrow request BORROW-20260203014650-2796 by BENJAMIN M. AMOTO JR\"}', NULL, 1, '2026-02-03 08:46:52', 'APPROVED', 3, '2026-05-04 13:05:36'),
(3, 1, 2, 'return_request', 28, '{\"borrow_id\":28,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260203014650-2796\",\"message\":\"Return request BORROW-20260203014650-2796 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-03 09:24:01', 'APPROVED', NULL, '2026-02-04 16:40:30'),
(4, 3, 2, 'return_request', 28, '{\"borrow_id\":28,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260203014650-2796\",\"message\":\"Return request BORROW-20260203014650-2796 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-03 09:24:03', 'APPROVED', NULL, '2026-02-04 16:40:30'),
(5, 2, 1, 'return_approved', 28, '{\"reference\":\"RETURN-20260203023206-3813\",\"borrow_id\":28,\"quantity\":1}', NULL, 1, '2026-02-03 09:32:06', 'NONE', NULL, NULL),
(6, 1, 2, 'borrow_request', 29, '{\"reference\":\"BORROW-20260203024547-2946\",\"borrower\":\"LEAH JANE  B.  CASTAMAYOR\",\"message\":\"Borrow request BORROW-20260203024547-2946 by LEAH JANE  B.  CASTAMAYOR\"}', NULL, 1, '2026-02-03 09:45:47', 'APPROVED', 1, '2026-02-03 09:46:21'),
(7, 3, 2, 'borrow_request', 29, '{\"reference\":\"BORROW-20260203024547-2946\",\"borrower\":\"LEAH JANE  B.  CASTAMAYOR\",\"message\":\"Borrow request BORROW-20260203024547-2946 by LEAH JANE  B.  CASTAMAYOR\"}', NULL, 1, '2026-02-03 09:45:49', 'APPROVED', 1, '2026-02-03 09:46:21'),
(8, 2, 1, 'borrow_approved', 29, '{\"reference\":\"BORROW-20260203024547-2946\",\"borrow_id\":29}', NULL, 1, '2026-02-03 09:46:21', 'NONE', NULL, NULL),
(9, 1, 2, 'return_request', 29, '{\"borrow_id\":29,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260203024547-2946\",\"message\":\"Return request BORROW-20260203024547-2946 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-03 09:47:11', 'NONE', NULL, NULL),
(10, 3, 2, 'return_request', 29, '{\"borrow_id\":29,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260203024547-2946\",\"message\":\"Return request BORROW-20260203024547-2946 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-03 09:47:13', 'NONE', NULL, NULL),
(11, 1, 2, 'return_request', 29, '{\"borrow_id\":29,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260203024547-2946\",\"message\":\"Return request BORROW-20260203024547-2946 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-03 12:11:39', 'NONE', NULL, NULL),
(12, 3, 2, 'return_request', 29, '{\"borrow_id\":29,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260203024547-2946\",\"message\":\"Return request BORROW-20260203024547-2946 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-03 12:11:41', 'NONE', NULL, NULL),
(13, 1, 2, 'borrow_request', 30, '{\"reference\":\"BORROW-20260203071748-4327\",\"borrower\":\"ELVIRA  P.  ARCILLAS\",\"message\":\"Borrow request BORROW-20260203071748-4327 by ELVIRA  P.  ARCILLAS\"}', NULL, 1, '2026-02-03 14:17:48', 'NONE', NULL, NULL),
(14, 3, 2, 'borrow_request', 30, '{\"reference\":\"BORROW-20260203071748-4327\",\"borrower\":\"ELVIRA  P.  ARCILLAS\",\"message\":\"Borrow request BORROW-20260203071748-4327 by ELVIRA  P.  ARCILLAS\"}', NULL, 1, '2026-02-03 14:17:50', 'NONE', NULL, NULL),
(15, 3, 1, 'borrow_request_approved', 28, '{\"decision\": \"approved\", \"admin_id\": 1}', NULL, 1, '2026-02-03 18:40:11', 'NONE', NULL, NULL),
(16, 1, 2, 'borrow_request', 31, '{\"reference\":\"BORROW-20260204092350-1996\",\"borrower\":\"PRINCES YABUT PINTOR \",\"message\":\"Borrow request BORROW-20260204092350-1996 by PRINCES YABUT PINTOR \"}', NULL, 1, '2026-02-04 16:23:50', 'NONE', NULL, NULL),
(17, 3, 2, 'borrow_request', 31, '{\"reference\":\"BORROW-20260204092350-1996\",\"borrower\":\"PRINCES YABUT PINTOR \",\"message\":\"Borrow request BORROW-20260204092350-1996 by PRINCES YABUT PINTOR \"}', NULL, 1, '2026-02-04 16:23:52', 'NONE', NULL, NULL),
(18, 1, 2, 'return_request', 29, '{\"borrow_id\":29,\"quantity\":1,\"message\":\"Return Request: BORROW-20260203024547-2946\"}', NULL, 1, '2026-02-05 15:39:05', 'NONE', NULL, NULL),
(19, 3, 2, 'return_request', 29, '{\"borrow_id\":29,\"quantity\":1,\"message\":\"Return Request: BORROW-20260203024547-2946\"}', NULL, 1, '2026-02-05 15:39:07', 'NONE', NULL, NULL),
(20, 2, 1, '0', 31, '{\"borrow_id\":31,\"decision\":\"APPROVED\",\"reference\":\"BORROW-20260204092350-1996\"}', NULL, 1, '2026-02-06 09:02:57', 'NONE', NULL, NULL),
(21, 2, 1, '0', 30, '{\"borrow_id\":30,\"decision\":\"DENIED\",\"reference\":\"BORROW-20260203071748-4327\"}', NULL, 1, '2026-02-06 09:03:36', 'NONE', NULL, NULL),
(22, 1, 2, 'return_request', 29, '{\"borrow_id\":29,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260203024547-2946\",\"message\":\"Return request BORROW-20260203024547-2946 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-06 09:07:50', 'NONE', NULL, NULL),
(23, 3, 2, 'return_request', 29, '{\"borrow_id\":29,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260203024547-2946\",\"message\":\"Return request BORROW-20260203024547-2946 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-06 09:07:52', 'NONE', NULL, NULL),
(24, 1, 2, 'return_request', 29, '{\"borrow_id\":29,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260203024547-2946\",\"message\":\"Return request BORROW-20260203024547-2946 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-06 10:32:42', 'NONE', NULL, NULL),
(25, 3, 2, 'return_request', 29, '{\"borrow_id\":29,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260203024547-2946\",\"message\":\"Return request BORROW-20260203024547-2946 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-06 10:32:44', 'NONE', NULL, NULL),
(26, 1, 2, 'return_request', 29, '{\"borrow_id\":29,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260203024547-2946\",\"message\":\"Return request BORROW-20260203024547-2946 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-06 13:07:57', 'NONE', NULL, NULL),
(27, 3, 2, 'return_request', 29, '{\"borrow_id\":29,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260203024547-2946\",\"message\":\"Return request BORROW-20260203024547-2946 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-06 13:07:59', 'NONE', NULL, NULL),
(28, 1, 2, 'return_request', 29, '{\"borrow_id\":29,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260203024547-2946\",\"message\":\"Return request BORROW-20260203024547-2946 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-06 13:34:08', 'NONE', NULL, NULL),
(29, 3, 2, 'return_request', 29, '{\"borrow_id\":29,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260203024547-2946\",\"message\":\"Return request BORROW-20260203024547-2946 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-06 13:34:10', 'NONE', NULL, NULL),
(30, 1, 2, 'borrow_request', 32, '{\"reference\":\"BORROW-20260206065119-9147\",\"borrower\":\"SYDNEY  B.  ARNADO\",\"message\":\"Borrow request BORROW-20260206065119-9147 by SYDNEY  B.  ARNADO\"}', NULL, 1, '2026-02-06 13:51:19', 'NONE', NULL, NULL),
(31, 3, 2, 'borrow_request', 32, '{\"reference\":\"BORROW-20260206065119-9147\",\"borrower\":\"SYDNEY  B.  ARNADO\",\"message\":\"Borrow request BORROW-20260206065119-9147 by SYDNEY  B.  ARNADO\"}', NULL, 1, '2026-02-06 13:51:21', 'NONE', NULL, NULL),
(32, 2, 1, '0', 32, '{\"borrow_id\":32,\"decision\":\"APPROVED\",\"reference\":\"BORROW-20260206065119-9147\"}', NULL, 1, '2026-02-06 13:51:43', 'NONE', NULL, NULL),
(33, 1, 2, 'return_request', 32, '{\"borrow_id\":32,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260206065119-9147\",\"message\":\"Return request BORROW-20260206065119-9147 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-06 14:04:31', 'NONE', NULL, NULL),
(34, 3, 2, 'return_request', 32, '{\"borrow_id\":32,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260206065119-9147\",\"message\":\"Return request BORROW-20260206065119-9147 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-06 14:04:33', 'NONE', NULL, NULL),
(35, 2, 1, '0', 30, '{\"borrow_id\":30,\"decision\":\"APPROVED\",\"reference\":\"BORROW-20260203071748-4327\"}', NULL, 1, '2026-02-06 14:05:47', 'NONE', NULL, NULL),
(36, 1, 2, 'return_request', 30, '{\"borrow_id\":30,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260203071748-4327\",\"message\":\"Return request BORROW-20260203071748-4327 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-06 14:06:22', 'NONE', NULL, NULL),
(37, 3, 2, 'return_request', 30, '{\"borrow_id\":30,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260203071748-4327\",\"message\":\"Return request BORROW-20260203071748-4327 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-06 14:06:24', 'NONE', NULL, NULL),
(38, 1, 2, 'borrow_request', 33, '{\"reference\":\"BORROW-20260206083459-7786\",\"borrower\":\"ELVIRA  P.  ARCILLAS\",\"message\":\"Borrow request BORROW-20260206083459-7786 by ELVIRA  P.  ARCILLAS\"}', NULL, 1, '2026-02-06 15:34:59', 'NONE', NULL, NULL),
(39, 3, 2, 'borrow_request', 33, '{\"reference\":\"BORROW-20260206083459-7786\",\"borrower\":\"ELVIRA  P.  ARCILLAS\",\"message\":\"Borrow request BORROW-20260206083459-7786 by ELVIRA  P.  ARCILLAS\"}', NULL, 1, '2026-02-06 15:35:01', 'NONE', NULL, NULL),
(40, 2, 1, '0', 33, '{\"borrow_id\":33,\"decision\":\"APPROVED\",\"reference\":\"BORROW-20260206083459-7786\"}', NULL, 0, '2026-02-06 15:35:42', 'NONE', NULL, NULL),
(41, 1, 2, 'return_request', 33, '{\"borrow_id\":33,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260206083459-7786\",\"message\":\"Return request BORROW-20260206083459-7786 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-06 15:36:06', 'NONE', NULL, NULL),
(42, 3, 2, 'return_request', 33, '{\"borrow_id\":33,\"quantity\":1,\"requester\":\"mayumi\",\"reference\":\"BORROW-20260206083459-7786\",\"message\":\"Return request BORROW-20260206083459-7786 (Qty: 1) by mayumi\"}', NULL, 1, '2026-02-06 15:36:08', 'NONE', NULL, NULL),
(43, 1, 2, 'borrow_request', 34, '{\"reference\":\"BORROW-20260206083645-7500\",\"borrower\":\"SYDNEY  B.  ARNADO\",\"message\":\"Borrow request BORROW-20260206083645-7500 by SYDNEY  B.  ARNADO\"}', NULL, 1, '2026-02-06 15:36:45', 'NONE', NULL, NULL),
(44, 3, 2, 'borrow_request', 34, '{\"reference\":\"BORROW-20260206083645-7500\",\"borrower\":\"SYDNEY  B.  ARNADO\",\"message\":\"Borrow request BORROW-20260206083645-7500 by SYDNEY  B.  ARNADO\"}', NULL, 1, '2026-02-06 15:36:47', 'NONE', NULL, NULL),
(45, 2, 1, '0', 34, '{\"borrow_id\":34,\"decision\":\"APPROVED\",\"reference\":\"BORROW-20260206083645-7500\"}', NULL, 0, '2026-02-06 15:37:10', 'NONE', NULL, NULL),
(46, 1, 5, '0', 36, '{\"reference\":\"BORROW-20260430091201-5555\",\"borrow_id\":36,\"item\":\"Desktop Computer\",\"qty\":1,\"borrower\":\"REY MAC-HIL  ABING\",\"message\":\"Borrow request BORROW-20260430091201-5555 from REY MAC-HIL  ABING for Desktop Computer x1\"}', NULL, 1, '2026-04-30 15:12:01', 'NONE', NULL, NULL),
(47, 3, 5, '0', 36, '{\"reference\":\"BORROW-20260430091201-5555\",\"borrow_id\":36,\"item\":\"Desktop Computer\",\"qty\":1,\"borrower\":\"REY MAC-HIL  ABING\",\"message\":\"Borrow request BORROW-20260430091201-5555 from REY MAC-HIL  ABING for Desktop Computer x1\"}', NULL, 1, '2026-04-30 15:12:03', 'NONE', NULL, NULL),
(48, 6, 5, '0', 36, '{\"reference\":\"BORROW-20260430091201-5555\",\"borrow_id\":36,\"item\":\"Desktop Computer\",\"qty\":1,\"borrower\":\"REY MAC-HIL  ABING\",\"message\":\"Borrow request BORROW-20260430091201-5555 from REY MAC-HIL  ABING for Desktop Computer x1\"}', NULL, 0, '2026-04-30 15:12:05', 'NONE', NULL, NULL),
(49, 5, 1, 'borrow_approved', 36, '{\"borrow_id\":36,\"reference\":\"BORROW-20260430091201-5555\",\"item\":\"Desktop Computer\"}', NULL, 0, '2026-04-30 15:13:58', 'APPROVED', 1, '2026-04-30 15:13:58'),
(50, 1, 5, '0', 36, '{\"message\":\"Return request for Borrow #36\",\"quantity\":1,\"borrow_id\":36,\"item\":\"Desktop Computer\",\"reference\":\"BORROW-20260430091201-5555\"}', NULL, 1, '2026-04-30 15:14:16', 'NONE', NULL, NULL),
(51, 3, 5, '0', 36, '{\"message\":\"Return request for Borrow #36\",\"quantity\":1,\"borrow_id\":36,\"item\":\"Desktop Computer\",\"reference\":\"BORROW-20260430091201-5555\"}', NULL, 1, '2026-04-30 15:14:18', 'NONE', NULL, NULL),
(52, 6, 5, '0', 36, '{\"message\":\"Return request for Borrow #36\",\"quantity\":1,\"borrow_id\":36,\"item\":\"Desktop Computer\",\"reference\":\"BORROW-20260430091201-5555\"}', NULL, 0, '2026-04-30 15:14:20', 'NONE', NULL, NULL),
(53, 5, 1, 'return_approved', 36, '{\"borrow_id\":36,\"qty\":1,\"reference\":\"RETURN-20260430091440-9245\",\"item\":\"Desktop Computer\"}', NULL, 0, '2026-04-30 15:14:40', 'APPROVED', 1, '2026-04-30 15:14:40'),
(54, 1, 3, '0', 35, '{\"message\":\"Return request for Borrow #35\",\"quantity\":1,\"borrow_id\":35,\"item\":\"Desktop Computer Asus H510MD\",\"reference\":\"BORROW-20260430091003-7023\"}', NULL, 1, '2026-04-30 15:15:10', 'NONE', NULL, NULL),
(55, 3, 3, '0', 35, '{\"message\":\"Return request for Borrow #35\",\"quantity\":1,\"borrow_id\":35,\"item\":\"Desktop Computer Asus H510MD\",\"reference\":\"BORROW-20260430091003-7023\"}', NULL, 1, '2026-04-30 15:15:12', 'NONE', NULL, NULL),
(56, 6, 3, '0', 35, '{\"message\":\"Return request for Borrow #35\",\"quantity\":1,\"borrow_id\":35,\"item\":\"Desktop Computer Asus H510MD\",\"reference\":\"BORROW-20260430091003-7023\"}', NULL, 0, '2026-04-30 15:15:14', 'NONE', NULL, NULL),
(57, 3, 1, 'return_approved', 35, '{\"borrow_id\":35,\"qty\":1,\"reference\":\"RETURN-20260430091542-6957\",\"item\":\"Desktop Computer Asus H510MD\"}', NULL, 1, '2026-04-30 15:15:42', 'APPROVED', 1, '2026-04-30 15:15:42'),
(58, 1, 5, '0', 37, '{\"reference\":\"BORROW-20260430094552-3898\",\"borrow_id\":37,\"item\":\"Desktop Computer\",\"qty\":1,\"borrower\":\"REY MAC-HIL  ABING\",\"message\":\"Borrow request BORROW-20260430094552-3898 from REY MAC-HIL  ABING for Desktop Computer x1\"}', NULL, 1, '2026-04-30 15:45:52', 'NONE', NULL, NULL),
(59, 3, 5, '0', 37, '{\"reference\":\"BORROW-20260430094552-3898\",\"borrow_id\":37,\"item\":\"Desktop Computer\",\"qty\":1,\"borrower\":\"REY MAC-HIL  ABING\",\"message\":\"Borrow request BORROW-20260430094552-3898 from REY MAC-HIL  ABING for Desktop Computer x1\"}', NULL, 1, '2026-04-30 15:45:54', 'NONE', NULL, NULL),
(60, 6, 5, '0', 37, '{\"reference\":\"BORROW-20260430094552-3898\",\"borrow_id\":37,\"item\":\"Desktop Computer\",\"qty\":1,\"borrower\":\"REY MAC-HIL  ABING\",\"message\":\"Borrow request BORROW-20260430094552-3898 from REY MAC-HIL  ABING for Desktop Computer x1\"}', NULL, 0, '2026-04-30 15:45:56', 'NONE', NULL, NULL),
(61, 1, 7, '0', 38, '{\"reference\":\"BORROW-20260430101645-8614\",\"borrow_id\":38,\"item\":\"Desktop Computer\",\"qty\":1,\"borrower\":\"FAHREEN GAILE TULIAO\",\"requested_by_user_id\":7,\"message\":\"Borrow request BORROW-20260430101645-8614 — Desktop Computer x1 by FAHREEN GAILE TULIAO\"}', NULL, 1, '2026-04-30 16:16:45', 'NONE', NULL, NULL),
(62, 3, 7, '0', 38, '{\"reference\":\"BORROW-20260430101645-8614\",\"borrow_id\":38,\"item\":\"Desktop Computer\",\"qty\":1,\"borrower\":\"FAHREEN GAILE TULIAO\",\"requested_by_user_id\":7,\"message\":\"Borrow request BORROW-20260430101645-8614 — Desktop Computer x1 by FAHREEN GAILE TULIAO\"}', NULL, 1, '2026-04-30 16:16:47', 'NONE', NULL, NULL),
(63, 6, 7, '0', 38, '{\"reference\":\"BORROW-20260430101645-8614\",\"borrow_id\":38,\"item\":\"Desktop Computer\",\"qty\":1,\"borrower\":\"FAHREEN GAILE TULIAO\",\"requested_by_user_id\":7,\"message\":\"Borrow request BORROW-20260430101645-8614 — Desktop Computer x1 by FAHREEN GAILE TULIAO\"}', NULL, 0, '2026-04-30 16:16:49', 'NONE', NULL, NULL),
(64, 7, 3, 'borrow_approved', 38, '{\"borrow_id\":38,\"reference\":\"BORROW-20260430101645-8614\",\"item\":\"Desktop Computer\"}', NULL, 1, '2026-05-04 12:58:32', 'APPROVED', 3, '2026-05-04 12:58:32'),
(65, 1, 3, '0', 38, '{\"reference\":\"BORROW-20260430101645-8614\",\"borrow_id\":38,\"item\":\"Desktop Computer\",\"qty\":1,\"message\":\"Borrow #38 approved by Manager #3\"}', NULL, 1, '2026-05-04 12:58:36', 'NONE', NULL, NULL),
(66, 2, 3, 'borrow_approved', 28, '{\"borrow_id\":28,\"reference\":\"BORROW-20260203014650-2796\",\"item\":\"UPS\"}', NULL, 0, '2026-05-04 13:05:30', 'APPROVED', 3, '2026-05-04 13:05:30'),
(67, 1, 3, '0', 28, '{\"reference\":\"BORROW-20260203014650-2796\",\"borrow_id\":28,\"item\":\"UPS\",\"qty\":0,\"message\":\"Borrow #28 approved by Manager #3\"}', NULL, 1, '2026-05-04 13:05:34', 'NONE', NULL, NULL),
(68, 5, 3, 'borrow_approved', 37, '{\"borrow_id\":37,\"reference\":\"BORROW-20260430094552-3898\",\"item\":\"Desktop Computer\"}', NULL, 0, '2026-05-04 13:06:06', 'APPROVED', 3, '2026-05-04 13:06:06'),
(69, 1, 3, '0', 37, '{\"reference\":\"BORROW-20260430094552-3898\",\"borrow_id\":37,\"item\":\"Desktop Computer\",\"qty\":1,\"message\":\"Borrow #37 approved by Manager #3\"}', NULL, 1, '2026-05-04 13:06:10', 'NONE', NULL, NULL),
(70, 1, 3, '0', 24, '{\"reference\":\"BORROW-20260129112045-8773\",\"borrow_id\":24,\"item\":\"Printer InkJet Colored\",\"qty\":1,\"message\":\"Borrow #24 approved by Manager #3\"}', NULL, 1, '2026-05-04 13:06:16', 'NONE', NULL, NULL),
(71, 1, 7, '0', 39, '{\"reference\":\"BORROW-20260504074815-2342\",\"borrow_id\":39,\"item\":\"Document scanner high-speed of upto 35PPM/70\",\"qty\":1,\"borrower\":\"FAHREEN GAILE TULIAO\",\"requested_by_user_id\":7,\"message\":\"Borrow request BORROW-20260504074815-2342 — Document scanner high-speed of upto 35PPM/70 x1 by FAHREEN GAILE TULIAO\"}', NULL, 1, '2026-05-04 13:48:15', 'NONE', NULL, NULL),
(72, 3, 7, '0', 39, '{\"reference\":\"BORROW-20260504074815-2342\",\"borrow_id\":39,\"item\":\"Document scanner high-speed of upto 35PPM/70\",\"qty\":1,\"borrower\":\"FAHREEN GAILE TULIAO\",\"requested_by_user_id\":7,\"message\":\"Borrow request BORROW-20260504074815-2342 — Document scanner high-speed of upto 35PPM/70 x1 by FAHREEN GAILE TULIAO\"}', NULL, 1, '2026-05-04 13:48:17', 'NONE', NULL, NULL),
(73, 6, 7, '0', 39, '{\"reference\":\"BORROW-20260504074815-2342\",\"borrow_id\":39,\"item\":\"Document scanner high-speed of upto 35PPM/70\",\"qty\":1,\"borrower\":\"FAHREEN GAILE TULIAO\",\"requested_by_user_id\":7,\"message\":\"Borrow request BORROW-20260504074815-2342 — Document scanner high-speed of upto 35PPM/70 x1 by FAHREEN GAILE TULIAO\"}', NULL, 0, '2026-05-04 13:48:19', 'NONE', NULL, NULL),
(74, 7, 1, 'borrow_denied', 39, '{\"borrow_id\":39,\"reason\":\"123asd\",\"item\":\"Document scanner high-speed of upto 35PPM\\/70\"}', NULL, 1, '2026-05-04 15:50:25', 'DENIED', 1, '2026-05-04 15:50:25'),
(75, 3, 1, '0', 39, '{\"borrow_id\":39,\"item\":\"Document scanner high-speed of upto 35PPM/70\",\"reason\":\"123asd\",\"message\":\"Borrow #39 denied by Admin #1\"}', NULL, 1, '2026-05-04 15:50:29', 'NONE', NULL, NULL),
(76, 6, 1, '0', 39, '{\"borrow_id\":39,\"item\":\"Document scanner high-speed of upto 35PPM/70\",\"reason\":\"123asd\",\"message\":\"Borrow #39 denied by Admin #1\"}', NULL, 0, '2026-05-04 15:50:31', 'NONE', NULL, NULL),
(77, 1, 7, '0', 38, '{\"message\":\"Return request for Borrow #38\",\"quantity\":1,\"borrow_id\":38,\"item\":\"Desktop Computer\",\"reference\":\"BORROW-20260430101645-8614\",\"requested_by_user_id\":7,\"requester_name\":\"FAHREEN GAILE TULIAO\"}', NULL, 1, '2026-05-04 15:54:02', 'NONE', NULL, NULL),
(78, 3, 7, '0', 38, '{\"message\":\"Return request for Borrow #38\",\"quantity\":1,\"borrow_id\":38,\"item\":\"Desktop Computer\",\"reference\":\"BORROW-20260430101645-8614\",\"requested_by_user_id\":7,\"requester_name\":\"FAHREEN GAILE TULIAO\"}', NULL, 1, '2026-05-04 15:54:04', 'NONE', NULL, NULL),
(79, 6, 7, '0', 38, '{\"message\":\"Return request for Borrow #38\",\"quantity\":1,\"borrow_id\":38,\"item\":\"Desktop Computer\",\"reference\":\"BORROW-20260430101645-8614\",\"requested_by_user_id\":7,\"requester_name\":\"FAHREEN GAILE TULIAO\"}', NULL, 0, '2026-05-04 15:54:06', 'NONE', NULL, NULL),
(80, 7, 3, 'return_approved', 38, '{\"borrow_id\":38,\"qty\":1,\"reference\":\"RETURN-20260504095505-1849\",\"item\":\"Desktop Computer\"}', NULL, 1, '2026-05-04 15:55:05', 'APPROVED', 3, '2026-05-04 15:55:05'),
(81, 1, 3, '0', 38, '{\"borrow_id\":38,\"item\":\"Desktop Computer\",\"qty\":1,\"ref\":\"RETURN-20260504095505-1849\",\"message\":\"Return for Borrow #38 approved by Manager #3\"}', NULL, 1, '2026-05-04 15:55:09', 'NONE', NULL, NULL),
(82, 8, 3, 'borrow_approved', 40, '{\"borrow_id\":40,\"reference\":\"BRW-20260505081853-0008\",\"item\":\"Aircon 3ton kolin floor mounted\"}', NULL, 0, '2026-05-05 14:29:13', 'APPROVED', 3, '2026-05-05 14:29:13'),
(83, 1, 3, '0', 40, '{\"reference\":\"BRW-20260505081853-0008\",\"borrow_id\":40,\"item\":\"Aircon 3ton kolin floor mounted\",\"qty\":1,\"message\":\"Borrow #40 approved by Manager #3\"}', NULL, 1, '2026-05-05 14:29:18', 'NONE', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notification_logs`
--

CREATE TABLE `notification_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `page` varchar(2083) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `lineno` int(11) DEFAULT NULL,
  `colno` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `stack` longtext DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `notification_id` int(11) DEFAULT NULL,
  `event` varchar(50) DEFAULT NULL,
  `action` enum('NONE','APPROVED','DENIED','MARK_READ') NOT NULL DEFAULT 'NONE',
  `action_by` int(11) DEFAULT NULL,
  `action_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_logs`
--

INSERT INTO `notification_logs` (`id`, `created_at`, `user_id`, `ip`, `user_agent`, `page`, `filename`, `lineno`, `colno`, `message`, `stack`, `payload`, `notification_id`, `event`, `action`, `action_by`, `action_at`) VALUES
(1, '2026-02-03 03:22:36', NULL, '127.0.0.1', 'cli', NULL, NULL, NULL, NULL, NULL, NULL, '[]', NULL, NULL, 'NONE', NULL, NULL),
(2, '2026-02-03 03:28:46', NULL, '127.0.0.1', 'cli', NULL, NULL, NULL, NULL, NULL, NULL, '[]', NULL, NULL, 'NONE', NULL, NULL),
(3, '2026-02-03 03:30:26', NULL, '127.0.0.1', 'cli', NULL, NULL, NULL, NULL, NULL, NULL, '[]', NULL, NULL, 'NONE', NULL, NULL),
(4, '2026-02-03 03:31:08', NULL, '127.0.0.1', 'cli-test', '/table/notifications.php', 'notifications.php', 1, 1, 'db smoke test', NULL, '{\"message\":\"db smoke test\",\"page\":\"/table/notifications.php\"}', NULL, NULL, 'NONE', NULL, NULL),
(5, '2026-02-03 03:32:20', 1, NULL, NULL, NULL, 'test_event', NULL, NULL, 'push notify test', NULL, '{\"type\":\"test_event\",\"related_id\":123,\"payload\":{\"message\":\"push notify test\",\"extra\":\"cli\"},\"created_at\":\"2026-02-03 04:32:18\"}', NULL, NULL, 'NONE', NULL, NULL),
(6, '2026-02-03 04:09:51', 1, NULL, NULL, NULL, 'test_event', NULL, NULL, 'push notify test', NULL, '{\"type\":\"test_event\",\"related_id\":123,\"payload\":{\"message\":\"push notify test\",\"extra\":\"cli\"},\"created_at\":\"2026-02-03 05:09:49\"}', NULL, NULL, 'NONE', NULL, NULL),
(7, '2026-02-06 01:02:59', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', NULL, 'borrow_decision', NULL, NULL, '', NULL, '{\"type\":\"borrow_decision\",\"related_id\":31,\"payload\":{\"status\":\"APPROVED\"},\"created_at\":\"2026-02-06 02:02:57\"}', NULL, NULL, 'NONE', NULL, NULL),
(8, '2026-02-06 01:03:38', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', NULL, 'borrow_decision', NULL, NULL, '', NULL, '{\"type\":\"borrow_decision\",\"related_id\":30,\"payload\":{\"status\":\"DENIED\"},\"created_at\":\"2026-02-06 02:03:36\"}', NULL, NULL, 'NONE', NULL, NULL),
(9, '2026-02-06 05:51:45', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', NULL, 'borrow_decision', NULL, NULL, '', NULL, '{\"type\":\"borrow_decision\",\"related_id\":32,\"payload\":{\"status\":\"APPROVED\"},\"created_at\":\"2026-02-06 06:51:43\"}', NULL, NULL, 'NONE', NULL, NULL),
(10, '2026-02-06 06:05:49', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', NULL, 'borrow_decision', NULL, NULL, '', NULL, '{\"type\":\"borrow_decision\",\"related_id\":30,\"payload\":{\"status\":\"APPROVED\"},\"created_at\":\"2026-02-06 07:05:47\"}', NULL, NULL, 'NONE', NULL, NULL),
(11, '2026-02-06 07:29:46', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"notification_id\":36,\"action\":\"mark_read\",\"by\":1}', NULL, NULL, 'NONE', NULL, NULL),
(12, '2026-02-06 07:30:01', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"notification_id\":36,\"action\":\"mark_read\",\"by\":1}', NULL, NULL, 'NONE', NULL, NULL),
(13, '2026-02-06 07:30:03', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"notification_id\":33,\"action\":\"mark_read\",\"by\":1}', NULL, NULL, 'NONE', NULL, NULL),
(14, '2026-02-06 07:30:06', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"notification_id\":30,\"action\":\"mark_read\",\"by\":1}', NULL, NULL, 'NONE', NULL, NULL),
(15, '2026-02-06 07:30:08', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"notification_id\":28,\"action\":\"mark_read\",\"by\":1}', NULL, NULL, 'NONE', NULL, NULL),
(16, '2026-02-06 07:30:09', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"notification_id\":26,\"action\":\"mark_read\",\"by\":1}', NULL, NULL, 'NONE', NULL, NULL),
(17, '2026-02-06 07:30:11', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"notification_id\":24,\"action\":\"mark_read\",\"by\":1}', NULL, NULL, 'NONE', NULL, NULL),
(18, '2026-02-06 07:30:12', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"notification_id\":22,\"action\":\"mark_read\",\"by\":1}', NULL, NULL, 'NONE', NULL, NULL),
(19, '2026-02-06 07:30:14', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"notification_id\":18,\"action\":\"mark_read\",\"by\":1}', NULL, NULL, 'NONE', NULL, NULL),
(20, '2026-02-06 07:30:15', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"notification_id\":16,\"action\":\"mark_read\",\"by\":1}', NULL, NULL, 'NONE', NULL, NULL),
(21, '2026-02-06 07:34:29', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"notification_id\":35,\"action\":\"mark_read\",\"by\":2}', NULL, NULL, 'NONE', NULL, NULL),
(22, '2026-02-06 07:34:31', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"notification_id\":32,\"action\":\"mark_read\",\"by\":2}', NULL, NULL, 'NONE', NULL, NULL),
(23, '2026-02-06 07:34:33', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"notification_id\":21,\"action\":\"mark_read\",\"by\":2}', NULL, NULL, 'NONE', NULL, NULL),
(24, '2026-02-06 07:34:35', 2, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"notification_id\":20,\"action\":\"mark_read\",\"by\":2}', NULL, NULL, 'NONE', NULL, NULL),
(25, '2026-02-06 07:35:44', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', NULL, 'borrow_decision', NULL, NULL, '', NULL, '{\"type\":\"borrow_decision\",\"related_id\":33,\"payload\":{\"status\":\"APPROVED\"},\"created_at\":\"2026-02-06 08:35:42\"}', NULL, NULL, 'NONE', NULL, NULL),
(26, '2026-02-06 07:37:12', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', NULL, 'borrow_decision', NULL, NULL, '', NULL, '{\"type\":\"borrow_decision\",\"related_id\":34,\"payload\":{\"status\":\"APPROVED\"},\"created_at\":\"2026-02-06 08:37:10\"}', NULL, NULL, 'NONE', NULL, NULL),
(27, '2026-02-06 07:38:34', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"action\":\"mark_all\",\"by\":1}', NULL, NULL, 'NONE', NULL, NULL),
(28, '2026-04-30 08:16:53', 7, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:151.0) Gecko/20100101 Firefox/151.0', 'borrow_items', 'borrow_items.php', NULL, NULL, 'borrow_request_submitted', NULL, '{\"borrow_id\":38,\"ref\":\"BORROW-20260430101645-8614\",\"item\":\"Desktop Computer\",\"qty\":1,\"borrower\":\"FAHREEN GAILE TULIAO\"}', NULL, NULL, 'NONE', NULL, NULL),
(29, '2026-05-04 04:58:38', 3, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'borrow_items', 'borrow_items.php', NULL, NULL, 'borrow_approved', NULL, '{\"borrow_id\":38,\"item\":\"Desktop Computer\",\"qty\":1,\"ref\":\"BORROW-20260430101645-8614\",\"actor_role\":\"MANAGER\"}', NULL, NULL, 'NONE', NULL, NULL),
(30, '2026-05-04 05:05:36', 3, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'borrow_items', 'borrow_items.php', NULL, NULL, 'borrow_approved', NULL, '{\"borrow_id\":28,\"item\":\"UPS\",\"qty\":0,\"ref\":\"BORROW-20260203014650-2796\",\"actor_role\":\"MANAGER\"}', NULL, NULL, 'NONE', NULL, NULL),
(31, '2026-05-04 05:06:12', 3, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'borrow_items', 'borrow_items.php', NULL, NULL, 'borrow_approved', NULL, '{\"borrow_id\":37,\"item\":\"Desktop Computer\",\"qty\":1,\"ref\":\"BORROW-20260430094552-3898\",\"actor_role\":\"MANAGER\"}', NULL, NULL, 'NONE', NULL, NULL),
(32, '2026-05-04 05:06:18', 3, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'borrow_items', 'borrow_items.php', NULL, NULL, 'borrow_approved', NULL, '{\"borrow_id\":24,\"item\":\"Printer InkJet Colored\",\"qty\":1,\"ref\":\"BORROW-20260129112045-8773\",\"actor_role\":\"MANAGER\"}', NULL, NULL, 'NONE', NULL, NULL),
(33, '2026-05-04 05:19:34', 1, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"action\":\"mark_all\",\"by\":1}', NULL, NULL, 'NONE', NULL, NULL),
(34, '2026-05-04 05:20:12', 3, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"action\":\"mark_all\",\"by\":3}', NULL, NULL, 'NONE', NULL, NULL),
(35, '2026-05-04 05:48:23', 7, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'borrow_items', 'borrow_items.php', NULL, NULL, 'borrow_request_submitted', NULL, '{\"borrow_id\":39,\"ref\":\"BORROW-20260504074815-2342\",\"item\":\"Document scanner high-speed of upto 35PPM/70\",\"qty\":1,\"borrower\":\"FAHREEN GAILE TULIAO\"}', NULL, NULL, 'NONE', NULL, NULL),
(36, '2026-05-04 07:01:00', 1, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"action\":\"mark_all\",\"by\":1}', NULL, NULL, 'NONE', NULL, NULL),
(37, '2026-05-04 07:23:58', 7, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"action\":\"mark_all\",\"by\":7}', NULL, NULL, 'NONE', NULL, NULL),
(38, '2026-05-04 07:50:33', 1, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'borrow_items', 'borrow_items.php', NULL, NULL, 'borrow_denied', NULL, '{\"borrow_id\":39,\"item\":\"Document scanner high-speed of upto 35PPM/70\",\"reason\":\"123asd\",\"actor_role\":\"ADMIN\"}', NULL, NULL, 'NONE', NULL, NULL),
(39, '2026-05-04 07:51:44', 3, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"action\":\"mark_all\",\"by\":3}', NULL, NULL, 'NONE', NULL, NULL),
(40, '2026-05-04 07:53:45', 7, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"action\":\"mark_all\",\"by\":7}', NULL, NULL, 'NONE', NULL, NULL),
(41, '2026-05-04 07:54:10', 7, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'borrow_items', 'borrow_items.php', NULL, NULL, 'return_request_submitted', NULL, '{\"borrow_id\":38,\"qty\":1,\"item\":\"Desktop Computer\",\"ref\":\"BORROW-20260430101645-8614\"}', NULL, NULL, 'NONE', NULL, NULL),
(42, '2026-05-04 07:54:41', 3, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"action\":\"mark_all\",\"by\":3}', NULL, NULL, 'NONE', NULL, NULL),
(43, '2026-05-04 07:55:11', 3, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'borrow_items', 'borrow_items.php', NULL, NULL, 'return_approved', NULL, '{\"borrow_id\":38,\"qty\":1,\"item\":\"Desktop Computer\",\"ref\":\"RETURN-20260504095505-1849\",\"actor_role\":\"MANAGER\"}', NULL, NULL, 'NONE', NULL, NULL),
(44, '2026-05-04 07:55:46', 1, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"action\":\"mark_all\",\"by\":1}', NULL, NULL, 'NONE', NULL, NULL),
(45, '2026-05-04 07:59:54', 7, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"action\":\"mark_all\",\"by\":7}', NULL, NULL, 'NONE', NULL, NULL),
(46, '2026-05-05 06:29:20', 3, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'borrow_items', 'borrow_items.php', NULL, NULL, 'borrow_approved', NULL, '{\"borrow_id\":40,\"item\":\"Aircon 3ton kolin floor mounted\",\"qty\":1,\"ref\":\"BRW-20260505081853-0008\",\"actor_role\":\"MANAGER\"}', NULL, NULL, 'NONE', NULL, NULL),
(47, '2026-05-05 06:29:52', 1, '192.168.1.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36', 'notifications_api', 'notifications_api.php', NULL, NULL, '0', NULL, '{\"action\":\"mark_all\",\"by\":1}', NULL, NULL, 'NONE', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `office_desks`
--

CREATE TABLE `office_desks` (
  `id` varchar(64) NOT NULL,
  `name` varchar(255) DEFAULT '',
  `x` int(11) DEFAULT 0,
  `y` int(11) DEFAULT 0,
  `items` text DEFAULT '[]',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `office_desk_items`
--

CREATE TABLE `office_desk_items` (
  `id` int(11) NOT NULL,
  `desk_id` varchar(64) NOT NULL,
  `item_name` varchar(255) DEFAULT '',
  `holder` varchar(255) DEFAULT '',
  `accountable` varchar(255) DEFAULT '',
  `inventory_item_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `office_map_desks`
--

CREATE TABLE `office_map_desks` (
  `id` varchar(100) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `x` int(11) DEFAULT NULL,
  `y` int(11) DEFAULT NULL,
  `items` longtext DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `office_map_images`
--

CREATE TABLE `office_map_images` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `width` int(11) DEFAULT 0,
  `height` int(11) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `office_map_images`
--

INSERT INTO `office_map_images` (`id`, `filename`, `width`, `height`, `uploaded_at`) VALUES
(11, 'officemap_1769588826_officemap.PNG', 972, 743, '2026-01-28 08:27:06'),
(12, 'officemap_1769589030_officemap.PNG', 972, 743, '2026-01-28 08:30:30'),
(13, 'officemap_1769589140_officemap.PNG', 972, 743, '2026-01-28 08:32:20'),
(14, 'officemap_1769645604_officemap.PNG', 972, 743, '2026-01-29 00:13:24');

-- --------------------------------------------------------

--
-- Table structure for table `returned_to_cgso`
--

CREATE TABLE `returned_to_cgso` (
  `id` int(11) NOT NULL,
  `accountable_id` int(11) NOT NULL COMMENT 'accountable_items.id at time of return',
  `inventory_item_id` int(11) NOT NULL COMMENT 'inventory_items.id',
  `employee_id` int(11) DEFAULT NULL COMMENT 'cao_employee.ID',
  `employee_id_number` int(11) DEFAULT NULL COMMENT 'cao_employee.end_user_id_number',
  `employee_name` varchar(255) NOT NULL COMMENT 'Snapshot of full name at return time',
  `item_name` varchar(255) NOT NULL COMMENT 'Snapshot of inventory_items.item_name',
  `are_mr_ics_num` varchar(60) DEFAULT NULL,
  `property_number` varchar(300) DEFAULT NULL,
  `returned_serial_numbers` text DEFAULT NULL COMMENT 'Slash-delimited serials extracted from source record',
  `po_number` varchar(70) DEFAULT NULL,
  `account_code` varchar(50) DEFAULT NULL,
  `condition_status` varchar(50) NOT NULL DEFAULT 'Returned to CGSO',
  `returned_quantity` int(11) NOT NULL COMMENT 'Units returned in this transaction',
  `remaining_quantity` int(11) NOT NULL COMMENT 'Units left with custodian after this return',
  `return_reference_no` varchar(100) NOT NULL COMMENT 'e.g. RTC-20260505143025-3812',
  `remarks` varchar(255) DEFAULT NULL,
  `returned_by_id` int(11) DEFAULT NULL COMMENT 'user.id of Admin/Manager who processed',
  `returned_by_name` varchar(255) DEFAULT NULL COMMENT 'Snapshot of their full name',
  `returned_by_role` varchar(20) DEFAULT NULL COMMENT 'ADMIN or MANAGER',
  `returned_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Immutable ledger of items returned to CGSO from accountable custodians';

--
-- Dumping data for table `returned_to_cgso`
--

INSERT INTO `returned_to_cgso` (`id`, `accountable_id`, `inventory_item_id`, `employee_id`, `employee_id_number`, `employee_name`, `item_name`, `are_mr_ics_num`, `property_number`, `returned_serial_numbers`, `po_number`, `account_code`, `condition_status`, `returned_quantity`, `remaining_quantity`, `return_reference_no`, `remarks`, `returned_by_id`, `returned_by_name`, `returned_by_role`, `returned_at`) VALUES
(1, 49, 17, 65, 10172, 'NANCY A. ARBIS', 'Desktop Computer', '', 'arenaslkdj', '198hassd /', '', '', 'Returned to CGSO', 1, 2, 'RTC-20260505050217-8685', '123123', 1, 'Rey Mac-Hil Abing', 'ADMIN', '2026-05-05 11:02:17'),
(2, 49, 17, 65, 10172, 'NANCY A. ARBIS', 'Desktop Computer', '', 'arenaslkdj', 'lkjd93n164 /', '', '', 'Returned to CGSO', 1, 1, 'RTC-20260505073242-8706', 'guba', 1, 'Rey Mac-Hil Abing', 'ADMIN', '2026-05-05 13:32:42');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL COMMENT 'Snapshot of user name at time of action',
  `action_type` varchar(100) DEFAULT NULL,
  `module` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `user_name`, `action_type`, `module`, `description`, `created_at`) VALUES
(1, 0, NULL, 'UPDATE_ACCOUNTABLE', NULL, 'Updated accountable ID 42', '2026-04-28 00:46:52'),
(2, 0, NULL, 'UPDATE_ACCOUNTABLE', NULL, 'Updated accountable ID 42', '2026-04-28 00:47:42'),
(3, 0, NULL, 'TRANSFER_ITEM', NULL, 'Transferred accountable ID 42 to employee ID 1591', '2026-04-28 00:49:01'),
(4, 0, NULL, 'UPDATE_ACCOUNTABLE', NULL, 'Updated accountable ID 41', '2026-04-28 01:50:05'),
(5, 0, NULL, 'UPDATE_ACCOUNTABLE', NULL, 'Updated accountable ID 43', '2026-04-29 01:02:00'),
(6, 0, NULL, 'UPDATE_ACCOUNTABLE', NULL, 'Updated accountable ID 42', '2026-04-29 01:29:12'),
(7, 0, NULL, 'TRANSFER_ITEM', NULL, 'Transferred accountable ID 44 to employee ID 2266', '2026-04-29 03:21:33'),
(8, 0, NULL, 'UPDATE_ACCOUNTABLE', NULL, 'Updated accountable ID 44', '2026-04-29 04:41:05'),
(9, 0, NULL, 'TRANSFER_ITEM', NULL, 'Transferred accountable ID 44 to employee ID 2084', '2026-04-29 04:50:48'),
(10, 0, NULL, 'TRANSFER_ITEM', NULL, 'Transferred accountable ID 46 to employee ID 582', '2026-04-29 04:57:50'),
(11, 0, NULL, 'TRANSFER_ITEM', NULL, 'Transferred accountable ID 46 to employee ID 5074', '2026-04-29 05:04:39'),
(12, 0, NULL, 'UPDATE_ACCOUNTABLE', NULL, 'Updated accountable ID 46', '2026-04-29 08:33:13'),
(13, 0, NULL, 'UPDATE_ACCOUNTABLE', NULL, 'Updated accountable ID 34', '2026-04-29 08:36:28'),
(14, 0, NULL, 'UPDATE_ACCOUNTABLE', NULL, 'Updated accountable ID 47', '2026-04-30 01:00:03'),
(15, 0, NULL, 'UPDATE_ACCOUNTABLE', NULL, 'Updated accountable ID 47', '2026-04-30 01:02:52'),
(16, 0, NULL, 'UPDATE_ACCOUNTABLE', NULL, 'Updated accountable ID 47', '2026-04-30 02:40:22'),
(17, 0, NULL, 'UPDATE_ACCOUNTABLE', NULL, 'Updated accountable ID 47', '2026-04-30 03:11:59'),
(18, 0, NULL, 'UPDATE_ACCOUNTABLE', NULL, 'Updated accountable ID 47', '2026-04-30 03:15:32'),
(19, 0, NULL, 'UPDATE_ACCOUNTABLE', NULL, 'Updated accountable ID 47', '2026-04-30 03:34:41'),
(20, 0, NULL, 'TRANSFER_ITEM', NULL, 'FULL transfer of 1 unit(s) from accountable ID 47 to employee ID 1660', '2026-04-30 04:24:36'),
(21, 1, 'Rey Mac-Hil Abing', 'TRANSFER_ITEM', NULL, '[Rey Mac-Hil Abing] FULL transfer of 1 unit(s) from accountable ID 47 (LILWEN MAE  P.  ALOVERA) to PRINCES YABUT PINTOR', '2026-04-30 04:49:54'),
(22, 3, 'Jenny Betaizar', 'TRANSFER_ITEM', NULL, '[Jenny Betaizar] FULL transfer of 1 unit(s) from accountable ID 46 (HEIDE H. ABUNDA) to DIONA  L.  ADLAON', '2026-04-30 04:51:05'),
(23, 3, 'Jenny Betaizar', 'ASSIGN_ITEM', NULL, '[Jenny Betaizar] Assigned 10 x item ID 22 to FAHREEN GAILE  B.  TULIAO (accountable ID 48)', '2026-04-30 04:56:33'),
(24, 3, 'Jenny Betaizar', 'UPDATE_ACCOUNTABLE', NULL, '[Jenny Betaizar] Updated accountable ID 48 (custodian: FAHREEN GAILE  B.  TULIAO)', '2026-04-30 04:57:03'),
(25, 1, NULL, 'BORROW_APPROVED_DIRECT', NULL, 'Admin directly approved borrow #35 — Desktop Computer Asus H510MD x1 to JOVER  APOSTOL (ref: BORROW-20260430091003-7023) | {\"borrow_id\":35,\"ref\":\"BORROW-20260430091003-7023\",\"qty\":1}', '2026-04-30 07:10:03'),
(26, 5, NULL, 'BORROW_REQUEST', NULL, 'Staff #5 submitted borrow request #36 — Desktop Computer x1 (ref: BORROW-20260430091201-5555) | {\"borrow_id\":36,\"ref\":\"BORROW-20260430091201-5555\"}', '2026-04-30 07:12:09'),
(27, 1, NULL, 'BORROW_APPROVED', NULL, 'Admin #1 approved Borrow #36 — Desktop Computer x1 (ref: BORROW-20260430091201-5555) | {\"borrow_id\":36}', '2026-04-30 07:14:02'),
(28, 5, NULL, 'RETURN_REQUEST', NULL, 'Staff #5 submitted return request for Borrow #36 — Desktop Computer x1 (ref: BORROW-20260430091201-5555) | {\"borrow_id\":36,\"qty\":1}', '2026-04-30 07:14:24'),
(29, 1, NULL, 'RETURN_APPROVED', NULL, 'Admin #1 approved return for Borrow #36 — Desktop Computer x1 (ref: RETURN-20260430091440-9245) | {\"borrow_id\":36,\"qty\":1}', '2026-04-30 07:14:45'),
(30, 3, NULL, 'RETURN_REQUEST', NULL, 'Staff #3 submitted return request for Borrow #35 — Desktop Computer Asus H510MD x1 (ref: BORROW-20260430091003-7023) | {\"borrow_id\":35,\"qty\":1}', '2026-04-30 07:15:18'),
(31, 1, NULL, 'RETURN_APPROVED', NULL, 'Admin #1 approved return for Borrow #35 — Desktop Computer Asus H510MD x1 (ref: RETURN-20260430091542-6957) | {\"borrow_id\":35,\"qty\":1}', '2026-04-30 07:15:46'),
(32, 5, NULL, 'BORROW_REQUEST', NULL, 'Staff #5 submitted borrow request #37 — Desktop Computer x1 (ref: BORROW-20260430094552-3898) | {\"borrow_id\":37,\"ref\":\"BORROW-20260430094552-3898\"}', '2026-04-30 07:46:00'),
(33, 7, NULL, 'BORROW_REQUEST', NULL, 'Staff #7 (FAHREEN GAILE TULIAO) submitted borrow request #38 — Desktop Computer x1 (ref: BORROW-20260430101645-8614) | {\"borrow_id\":38,\"ref\":\"BORROW-20260430101645-8614\"}', '2026-04-30 08:16:53'),
(34, 3, NULL, 'BORROW_APPROVED', NULL, 'Manager #3 approved Borrow #38 — Desktop Computer x1 (ref: BORROW-20260430101645-8614) | {\"borrow_id\":38,\"actor_role\":\"MANAGER\"}', '2026-05-04 04:58:38'),
(35, 3, NULL, 'BORROW_APPROVED', NULL, 'Manager #3 approved Borrow #28 — UPS x0 (ref: BORROW-20260203014650-2796) | {\"borrow_id\":28,\"actor_role\":\"MANAGER\"}', '2026-05-04 05:05:36'),
(36, 3, NULL, 'BORROW_APPROVED', NULL, 'Manager #3 approved Borrow #37 — Desktop Computer x1 (ref: BORROW-20260430094552-3898) | {\"borrow_id\":37,\"actor_role\":\"MANAGER\"}', '2026-05-04 05:06:12'),
(37, 3, NULL, 'BORROW_APPROVED', NULL, 'Manager #3 approved Borrow #24 — Printer InkJet Colored x1 (ref: BORROW-20260129112045-8773) | {\"borrow_id\":24,\"actor_role\":\"MANAGER\"}', '2026-05-04 05:06:18'),
(38, 7, NULL, 'BORROW_REQUEST', NULL, 'Staff #7 (FAHREEN GAILE TULIAO) submitted borrow request #39 — Document scanner high-speed of upto 35PPM/70 x1 (ref: BORROW-20260504074815-2342) | {\"borrow_id\":39,\"ref\":\"BORROW-20260504074815-2342\"}', '2026-05-04 05:48:23'),
(39, 1, NULL, 'BORROW_DENIED', NULL, 'Admin #1 denied Borrow #39 — Document scanner high-speed of upto 35PPM/70. Reason: 123asd | {\"borrow_id\":39,\"actor_role\":\"ADMIN\"}', '2026-05-04 07:50:33'),
(40, 7, NULL, 'RETURN_REQUEST', NULL, 'Staff #7 (FAHREEN GAILE TULIAO) submitted return request for Borrow #38 — Desktop Computer x1 (ref: BORROW-20260430101645-8614) | {\"borrow_id\":38,\"qty\":1}', '2026-05-04 07:54:10'),
(41, 3, NULL, 'RETURN_APPROVED', NULL, 'Manager #3 approved return for Borrow #38 — Desktop Computer x1 (ref: RETURN-20260504095505-1849) | {\"borrow_id\":38,\"qty\":1,\"actor_role\":\"MANAGER\"}', '2026-05-04 07:55:11'),
(42, 8, NULL, 'INVENTORY_ITEM_CREATED', NULL, 'Encoder \'ojt\' added inventory item \'laptop\' (ID:36). | {\"item_id\":36,\"quantity\":20}', '2026-05-04 23:24:47'),
(43, 1, 'Rey Mac-Hil Abing', 'ASSIGN_ITEM', NULL, '[Rey Mac-Hil Abing] Assigned 3 x item ID 17 to NANCY  A.  ARBIS (accountable ID 49)', '2026-05-05 02:44:39'),
(44, 1, 'Rey Mac-Hil Abing', 'RETURN_TO_CGSO', NULL, '[Rey Mac-Hil Abing / ADMIN] PARTIAL return to CGSO — 1 unit(s) of \'Desktop Computer\' from NANCY A. ARBIS. Ref: RTC-20260505050217-8685. Serials returned: 198hassd /', '2026-05-05 03:02:17'),
(45, 1, 'Rey Mac-Hil Abing', 'RETURN_TO_CGSO', NULL, '[Rey Mac-Hil Abing / ADMIN] PARTIAL return to CGSO — 1 unit(s) of \'Desktop Computer\' from NANCY A. ARBIS. Ref: RTC-20260505073242-8706. Serials returned: lkjd93n164 /', '2026-05-05 05:32:42'),
(46, 1, 'Rey Mac-Hil Abing', 'ASSIGN_ITEM', NULL, '[Rey Mac-Hil Abing] Assigned 3 x item ID 13 to JENNY  S.  BETAIZAR (accountable ID 50)', '2026-05-05 05:34:30'),
(47, 8, NULL, 'BORROW_RECORD_CREATED', NULL, 'Encoder \'ojt\' created borrow record (ID:40) for item #13 to \'REY MAC-HIL C. ABING\'. | {\"borrow_id\":40,\"quantity\":1}', '2026-05-05 06:23:14'),
(48, 3, NULL, 'BORROW_APPROVED', NULL, 'Manager #3 approved Borrow #40 — Aircon 3ton kolin floor mounted x1 (ref: BRW-20260505081853-0008) | {\"borrow_id\":40,\"actor_role\":\"MANAGER\"}', '2026-05-05 06:29:20');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('ADMIN','STAFF','MANAGER','ENCODER') NOT NULL DEFAULT 'STAFF',
  `status` enum('ACTIVE','INACTIVE','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `avatar` varchar(255) DEFAULT 'default.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id`, `first_name`, `last_name`, `department`, `position`, `username`, `email`, `password_hash`, `role`, `status`, `last_login`, `created_at`, `updated_at`, `avatar`) VALUES
(1, 'Rey Mac-Hil', 'Abing', 'Accounting', 'Computer Programmer 1', 'mac', 'mac@gmail.com', '$2y$10$O0krUS4VEBtOcW1gWBATtOkFZlJadSRmCes9Nk0i93F/rdf04Raby', 'ADMIN', 'ACTIVE', '2026-05-05 13:32:21', '2026-01-05 15:36:36', '2026-05-05 13:32:21', 'avatar_1_1770023753.jpg'),
(2, 'Mayumi s', 'Takahashi', 'Accounting', 'Computer Programmer 1', 'mayumi', 'mayumi@gmail.com', '$2y$10$9khgMznRBhTfzQRMFWyL5Oex3eBuI0YGYyweiD4RcGaIBvui2J8YO', 'STAFF', 'ACTIVE', '2026-04-27 15:24:57', '2026-01-08 09:54:04', '2026-04-27 16:11:42', 'avatar_2_1770025536.png'),
(3, 'Jenny', 'Betaizar', 'Accounting', 'Accountant 3', 'jennyB', 'jenny@mail.com', '$2y$10$wIVY/wZQGGe2ONY/BTo.9u1umcUkOmEAZw2oHgw0vcIr5lNh/0Bvq', 'MANAGER', 'ACTIVE', '2026-05-05 14:28:21', '2026-01-23 13:38:35', '2026-05-05 14:28:21', 'default.png'),
(4, 'pyp', 'pyp', 'Accounting', 'Staff', 'pyp', '123456@mail.com', '$2y$10$MK07uzIYNDAzH4ODlFk2WuwDzi0/SJDVTk604GZMrFCkxmKDhLi8a', 'STAFF', 'ACTIVE', '2026-02-06 14:17:59', '2026-02-06 13:55:15', '2026-02-06 14:17:59', 'default.png'),
(5, 'user', 'user', 'Accounting', 'Staff', 'user', 'user@mail.com', '$2y$10$ubrE4dOuufWdmDkpTOYdGOff1Pxz/FQPSa3GKwc3Rd3JhalDfGO1q', 'STAFF', 'ACTIVE', '2026-05-04 09:07:35', '2026-04-27 10:08:17', '2026-05-04 09:07:35', 'default.png'),
(6, 'Renzo', 'Mina', 'Accounting', '', 'kim', 'minarenzo10@gmail.com', '$2y$10$NHTJNqOpM1tWivpyQJHS.eTVM34uGSvV/VDr.wBlAPGJ1S2gtQUI6', 'MANAGER', 'ACTIVE', '2026-04-30 09:04:06', '2026-04-28 11:29:40', '2026-04-30 09:04:06', 'default.png'),
(7, 'FAHREEN GAILE', 'TULIAO', '', '', 'gaile', 'gaile@mail.com', '$2y$10$YrZKjAx.nzu6.qsPEkt.JeknMVuRzS8E2aQ5hTvLKQtU7atebrViW', 'STAFF', 'ACTIVE', '2026-05-05 08:30:24', '2026-04-30 16:15:24', '2026-05-05 08:30:24', 'default.png'),
(8, 'ojt', 'ojt', '', '', 'ojt', 'ojt@mail.com', '$2y$10$6FfrfOjH.jNm8S.wfL3fI.pCAWntQThGTHdY4bEgrHvb7Sev0IZRu', 'ENCODER', 'ACTIVE', '2026-05-05 12:59:11', '2026-05-04 18:28:08', '2026-05-05 12:59:11', 'default.png');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accountable_items`
--
ALTER TABLE `accountable_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_accountable_inventory` (`inventory_item_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_module_record` (`module`,`record_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `borrowed_items`
--
ALTER TABLE `borrowed_items`
  ADD PRIMARY KEY (`borrow_id`),
  ADD KEY `accountable_id` (`accountable_id`),
  ADD KEY `inventory_item_id` (`inventory_item_id`);

--
-- Indexes for table `cao_employee`
--
ALTER TABLE `cao_employee`
  ADD PRIMARY KEY (`ID`) USING BTREE,
  ADD UNIQUE KEY `uk_end_user_id_number` (`end_user_id_number`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `idx_inventory_item_id` (`inventory_item_id`),
  ADD KEY `fk_transaction_user` (`user_id`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_login_time` (`login_time`),
  ADD KEY `idx_login_status` (`login_status`);

--
-- Indexes for table `messenger_messages`
--
ALTER TABLE `messenger_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `messenger_users`
--
ALTER TABLE `messenger_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`),
  ADD KEY `idx_notifications_action` (`action`),
  ADD KEY `idx_notifications_action_by` (`action_by`);

--
-- Indexes for table `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notification_logs_user` (`user_id`),
  ADD KEY `idx_notification_logs_created_at` (`created_at`),
  ADD KEY `idx_notification_logs_notification_id` (`notification_id`),
  ADD KEY `idx_notification_logs_action` (`action`);

--
-- Indexes for table `office_desks`
--
ALTER TABLE `office_desks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `office_desk_items`
--
ALTER TABLE `office_desk_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `office_map_desks`
--
ALTER TABLE `office_map_desks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `office_map_images`
--
ALTER TABLE `office_map_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `returned_to_cgso`
--
ALTER TABLE `returned_to_cgso`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rtc_accountable_id` (`accountable_id`),
  ADD KEY `idx_rtc_inventory_item_id` (`inventory_item_id`),
  ADD KEY `idx_rtc_employee_id` (`employee_id`),
  ADD KEY `idx_rtc_reference_no` (`return_reference_no`),
  ADD KEY `idx_rtc_returned_at` (`returned_at`),
  ADD KEY `idx_rtc_returned_by_id` (`returned_by_id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_system_logs_action_type` (`action_type`),
  ADD KEY `idx_system_logs_user_id` (`user_id`),
  ADD KEY `idx_system_logs_created_at` (`created_at`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accountable_items`
--
ALTER TABLE `accountable_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `borrowed_items`
--
ALTER TABLE `borrowed_items`
  MODIFY `borrow_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `cao_employee`
--
ALTER TABLE `cao_employee`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9993;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `messenger_messages`
--
ALTER TABLE `messenger_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `messenger_users`
--
ALTER TABLE `messenger_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `notification_logs`
--
ALTER TABLE `notification_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `office_desk_items`
--
ALTER TABLE `office_desk_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `office_map_images`
--
ALTER TABLE `office_map_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `returned_to_cgso`
--
ALTER TABLE `returned_to_cgso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accountable_items`
--
ALTER TABLE `accountable_items`
  ADD CONSTRAINT `accountable_items_ibfk_1` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `fk_accountable_inventory` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `borrowed_items`
--
ALTER TABLE `borrowed_items`
  ADD CONSTRAINT `borrowed_items_ibfk_1` FOREIGN KEY (`accountable_id`) REFERENCES `accountable_items` (`id`),
  ADD CONSTRAINT `borrowed_items_ibfk_2` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `fk_transaction_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD CONSTRAINT `fk_login_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messenger_messages`
--
ALTER TABLE `messenger_messages`
  ADD CONSTRAINT `messenger_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `messenger_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messenger_messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `messenger_users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
