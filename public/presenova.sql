SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `presenova`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `GenerateWeeklyStudentSchedules` ()   BEGIN
    -- Generate jadwal untuk 1 minggu ke depan (menyesuaikan konfigurasi jam per hari)
    INSERT INTO student_schedule (student_id, teacher_schedule_id, schedule_date, time_in, time_out, status)
    SELECT 
        s.id,
        x.schedule_id,
        DATE_ADD(
            CURDATE(),
            INTERVAL ((x.day_id - (((DAYOFWEEK(CURDATE()) + 5) % 7) + 1) + 7) % 7) DAY
        ) AS schedule_date,
        CASE
            WHEN x.shift_name REGEXP '^JP[0-9]+-JP[0-9]+$'
                 AND x.jp_start > 0 AND x.jp_end >= x.jp_start THEN
                ADDTIME(
                    ADDTIME(x.cfg_school_start, SEC_TO_TIME(x.cfg_pre_minutes * 60)),
                    SEC_TO_TIME(((45 * (x.jp_start - 1)) - (30 * ((x.jp_start > 5) + (x.jp_start > 9)))) * 60)
                )
            ELSE x.time_in
        END AS time_in,
        CASE
            WHEN x.shift_name REGEXP '^JP[0-9]+-JP[0-9]+$'
                 AND x.jp_start > 0 AND x.jp_end >= x.jp_start THEN
                ADDTIME(
                    ADDTIME(
                        ADDTIME(
                            ADDTIME(x.cfg_school_start, SEC_TO_TIME(x.cfg_pre_minutes * 60)),
                            SEC_TO_TIME(((45 * (x.jp_start - 1)) - (30 * ((x.jp_start > 5) + (x.jp_start > 9)))) * 60)
                        ),
                        SEC_TO_TIME(((45 * (x.jp_end - x.jp_start + 1)) - (30 * (((x.jp_start <= 5) AND (x.jp_end >= 5)) + ((x.jp_start <= 9) AND (x.jp_end >= 9))))) * 60)
                    ),
                    SEC_TO_TIME(x.cfg_tolerance * 60)
                )
            ELSE ADDTIME(x.time_out, SEC_TO_TIME(x.cfg_tolerance * 60))
        END AS time_out,
        'ACTIVE'
    FROM (
        SELECT 
            ts.schedule_id,
            ts.day_id,
            ts.class_id,
            sh.shift_name,
            sh.time_in,
            sh.time_out,
            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(sh.shift_name, '-', 1), 'JP', -1) AS UNSIGNED) AS jp_start,
            CAST(SUBSTRING_INDEX(sh.shift_name, '-', -1) AS UNSIGNED) AS jp_end,
            COALESCE(cfg.school_start_time, '06:30:00') AS cfg_school_start,
            COALESCE(cfg.activity1_minutes, 0) + COALESCE(cfg.activity2_minutes, 0) AS cfg_pre_minutes,
            COALESCE(st.time_tolerance, 0) AS cfg_tolerance
        FROM teacher_schedule ts
        JOIN shift sh ON ts.shift_id = sh.shift_id
        LEFT JOIN day_schedule_config cfg ON cfg.day_id = ts.day_id
        LEFT JOIN site st ON 1=1
    ) x
    JOIN student s ON x.class_id = s.class_id
    WHERE NOT EXISTS (
        SELECT 1 FROM student_schedule ss 
        WHERE ss.student_id = s.id 
        AND ss.teacher_schedule_id = x.schedule_id
        AND ss.schedule_date = DATE_ADD(
            CURDATE(),
            INTERVAL ((x.day_id - (((DAYOFWEEK(CURDATE()) + 5) % 7) + 1) + 7) % 7) DAY
        )
    );
END$$

DELIMITER ;

-- --------------------------------------------------------
--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('student','teacher','admin') DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `user_type`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, NULL, '', 'failed_login', 'Failed login attempt for admin: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-29 23:34:40'),
(2, NULL, '', 'failed_login', 'Failed login attempt for admin: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-30 00:02:25'),
(3, NULL, '', 'failed_login', 'Failed login attempt for admin: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-30 00:04:12'),
(4, NULL, '', 'failed_login', 'Failed login attempt for admin: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-30 00:04:12'),
(5, NULL, '', 'failed_login', 'Failed login attempt for admin: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-30 00:11:47'),
(6, NULL, '', 'failed_login', 'Failed login attempt for admin: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-30 00:11:58'),
(7, NULL, '', 'failed_login', 'Failed login attempt for admin: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-30 08:37:33'),
(8, NULL, '', 'failed_login', 'Failed login attempt for admin: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-01-30 08:37:38'),
(86, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-04 21:31:17'),
(87, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-04 21:36:23'),
(88, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-04 21:48:04'),
(89, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-04 22:15:41'),
(90, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 09:20:42'),
(91, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 11:36:33'),
(92, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 12:58:57'),
(93, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 13:05:18'),
(94, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 15:58:53'),
(95, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 16:15:23'),
(96, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 17:43:55'),
(97, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 19:34:42'),
(98, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 22:45:17'),
(99, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 22:47:54'),
(100, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-05 22:49:49'),
(101, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 10:30:41'),
(102, 5, '', 'login', 'Guru login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 10:31:00'),
(103, NULL, '', 'failed_login', 'Failed login attempt for admin: adm', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 19:08:43'),
(104, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 19:08:50'),
(105, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 20:32:32'),
(106, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 21:22:53'),
(107, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 09:15:16'),
(108, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 10:18:14'),
(109, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 10:30:32'),
(110, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 12:00:38'),
(111, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 14:50:12'),
(112, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 17:57:30'),
(113, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 17:58:24'),
(114, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 18:02:04'),
(115, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 18:06:57'),
(116, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 18:18:36'),
(117, 6, '', 'login', 'Guru login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 18:43:41'),
(118, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 18:53:22'),
(119, 6, '', 'login', 'Guru login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 18:58:03'),
(120, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 19:03:29'),
(121, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 20:01:36'),
(122, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 21:39:18'),
(123, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 21:42:07'),
(124, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 22:12:48'),
(125, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-08 22:15:19'),
(126, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 09:48:20'),
(127, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 10:07:44'),
(128, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 10:20:47'),
(129, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 10:30:32'),
(130, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 10:31:54'),
(131, NULL, '', 'failed_login', 'Failed login attempt for admin: adm', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 10:34:33'),
(132, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 10:34:42');

-- --------------------------------------------------------

--
-- Table structure for table `class`
--

CREATE TABLE `class` (
  `class_id` int(11) NOT NULL,
  `class_name` varchar(30) DEFAULT NULL,
  `jurusan_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class`
--

INSERT INTO `class` (`class_id`, `class_name`, `jurusan_id`) VALUES
(13, 'XII TJKT C', 7),
(14, 'XII TJKT B', 7),
(15, 'XII TJKT A', 7);

-- --------------------------------------------------------

--
-- Table structure for table `day`
--

CREATE TABLE `day` (
  `day_id` int(11) NOT NULL,
  `day_code` varchar(10) DEFAULT NULL,
  `day_name` varchar(15) DEFAULT NULL,
  `day_order` int(11) DEFAULT NULL,
  `is_active` enum('Y','N') DEFAULT 'Y'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `day`
--

INSERT INTO `day` (`day_id`, `day_code`, `day_name`, `day_order`, `is_active`) VALUES
(1, 'MON', 'Senin', 1, 'Y'),
(2, 'TUE', 'Selasa', 2, 'Y'),
(3, 'WED', 'Rabu', 3, 'Y'),
(4, 'THU', 'Kamis', 4, 'Y'),
(5, 'FRI', 'Jumat', 5, 'Y'),
(6, 'SAT', 'Sabtu', 6, 'N'),
(7, 'SUN', 'Minggu', 7, 'N');

-- --------------------------------------------------------

--
-- Table structure for table `day_schedule_config`
--

CREATE TABLE `day_schedule_config` (
  `day_id` int(11) NOT NULL,
  `school_start_time` time NOT NULL DEFAULT '06:30:00',
  `activity1_label` varchar(50) DEFAULT NULL,
  `activity1_minutes` int(11) NOT NULL DEFAULT 0,
  `activity2_label` varchar(50) DEFAULT NULL,
  `activity2_minutes` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `day_schedule_config`
--

INSERT INTO `day_schedule_config` (`day_id`, `school_start_time`, `activity1_label`, `activity1_minutes`, `activity2_label`, `activity2_minutes`) VALUES
(1, '06:30:00', 'Upacara', 90, '', 0),
(2, '06:30:00', 'Apel Pagi', 45, '', 0),
(3, '06:30:00', 'Senam Pagi', 45, '', 0),
(4, '06:30:00', 'Apel Jurusan', 45, '', 0),
(5, '06:30:00', 'Tilawah', 60, '', 0),

-- --------------------------------------------------------

--
-- Table structure for table `jurusan`
--

CREATE TABLE `jurusan` (
  `jurusan_id` int(11) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `jurusan_scanner` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jurusan`
--

INSERT INTO `jurusan` (`jurusan_id`, `code`, `name`, `jurusan_scanner`) VALUES
(7, 'TJKT', 'TEKNIK JARINGAN KOMPUTER DAN TELEKOMUNIKASI', NULL),
(8, 'AKL', 'AKUNTANSI KEUANGAN LEMBAGA', NULL),
(9, 'TITL', 'TEKNIK INSTALASI TENAGA LISTRIK', NULL),
(10, 'TP', 'TEKNIK PERMESINAN', NULL),
(11, 'TE', 'TEKNIK ELEKTRO', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `presence`
--

CREATE TABLE `presence` (
  `presence_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `student_schedule_id` int(11) DEFAULT NULL,
  `presence_date` date DEFAULT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `picture_in` varchar(255) DEFAULT NULL,
  `picture_out` varchar(255) DEFAULT NULL,
  `present_id` int(11) DEFAULT NULL,
  `presence_address` text DEFAULT NULL,
  `latitude_in` decimal(10,8) DEFAULT NULL,
  `longitude_in` decimal(11,8) DEFAULT NULL,
  `latitude_out` decimal(10,8) DEFAULT NULL,
  `longitude_out` decimal(11,8) DEFAULT NULL,
  `distance_in` int(11) DEFAULT NULL,
  `distance_out` int(11) DEFAULT NULL,
  `is_late` enum('Y','N') DEFAULT 'N',
  `late_time` int(11) DEFAULT 0,
  `information` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `present_status`
--

CREATE TABLE `present_status` (
  `present_id` int(11) NOT NULL,
  `present_name` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `present_status`
--

INSERT INTO `present_status` (`present_id`, `present_name`) VALUES
(1, 'Hadir'),
(2, 'Sakit'),
(3, 'Izin'),
(4, 'Alpa');

-- --------------------------------------------------------

--
-- Table structure for table `push_tokens`
--

CREATE TABLE `push_tokens` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `endpoint` varchar(512) NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `content_encoding` varchar(40) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `platform` varchar(80) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `is_active` enum('Y','N') DEFAULT 'Y',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `push_notification_logs`
--

CREATE TABLE `push_notification_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `student_schedule_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `status` enum('PENDING','SENT','FAILED') DEFAULT 'PENDING',
  `error_message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_location`
--

CREATE TABLE `school_location` (
  `location_id` int(11) NOT NULL,
  `location_name` varchar(150) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `radius` int(11) DEFAULT 100,
  `address` text DEFAULT NULL,
  `is_active` enum('Y','N') DEFAULT 'Y',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school_location`
--

INSERT INTO `school_location` (`location_id`, `location_name`, `latitude`, `longitude`, `radius`, `address`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'SMKN 1 Cikarang Selatan', -6.35240000, 107.10620000, 93, 'Jalan Ciantra, Sukadami, Cikarang Selatan', 'Y', '2026-01-29 23:34:20', '2026-02-08 21:40:33'),
(4, 'testing', -6.43269300, 107.07638500, 200, 'cibarusah', 'Y', '2026-02-06 20:34:03', '2026-02-08 21:01:40');

-- --------------------------------------------------------

--
-- Table structure for table `shift`
--

CREATE TABLE `shift` (
  `shift_id` int(11) NOT NULL,
  `shift_name` varchar(50) DEFAULT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shift`
--

INSERT INTO `shift` (`shift_id`, `shift_name`, `time_in`, `time_out`) VALUES
(8, 'JP1-JP12', '07:00:00', '15:00:00'),
(9, 'JP5-JP7', '10:00:00', '11:45:00'),
(10, 'JP7-JP10', '11:00:00', '13:30:00'),
(11, 'JP7-JP11', '11:00:00', '14:15:00'),
(12, 'JP7-JP12', '11:00:00', '15:00:00'),
(13, 'JP1-JP10', '07:00:00', '13:30:00'),
(14, 'JP5-JP12', '10:00:00', '15:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `site`
--

CREATE TABLE `site` (
  `site_id` int(4) NOT NULL,
  `site_url` varchar(255) NOT NULL,
  `site_name` varchar(150) NOT NULL,
  `site_phone` varchar(30) DEFAULT NULL,
  `site_address` text DEFAULT NULL,
  `site_description` text DEFAULT NULL,
  `site_logo` varchar(100) DEFAULT NULL,
  `site_email` varchar(100) DEFAULT NULL,
  `site_email_domain` varchar(100) DEFAULT NULL,
  `time_tolerance` int(11) NOT NULL DEFAULT 15,
  `enable_gps_validation` enum('Y','N') NOT NULL DEFAULT 'Y',
  `enable_photo_validation` enum('Y','N') NOT NULL DEFAULT 'Y',
  `default_location_id` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site`
--

INSERT INTO `site` (`site_id`, `site_url`, `site_name`, `site_phone`, `site_address`, `site_description`, `site_logo`, `site_email`, `site_email_domain`, `time_tolerance`, `enable_gps_validation`, `enable_photo_validation`, `default_location_id`) VALUES
(1, 'http://localhost/absensi-smk/', 'testing', '082377823390', 'Jl. Ciantra, Sukadami, Cikarang Selatan', 'Sistem Absensi Online dengan Foto Selfie & GPS Validation', 'logo.png', 'admin@smkn1cikarang.sch.id', 'smkn1cikarang.sch.id', 15, 'Y', 'Y', 1);

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `id` int(11) NOT NULL,
  `student_code` varchar(20) DEFAULT NULL,
  `student_nisn` varchar(30) DEFAULT NULL,
  `student_password` varchar(255) DEFAULT NULL,
  `student_name` varchar(150) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `jurusan_id` int(11) DEFAULT NULL,
  `photo` varchar(100) DEFAULT NULL,
  `photo_reference` varchar(100) DEFAULT NULL,
  `face_embedding` text DEFAULT NULL,
  `last_face_update` datetime DEFAULT NULL,
  `location_id` int(11) DEFAULT 1,
  `created_login` datetime DEFAULT NULL,
  `created_cookies` varchar(100) DEFAULT '-'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`id`, `student_code`, `student_nisn`, `student_password`, `student_name`, `class_id`, `jurusan_id`, `photo`, `photo_reference`, `face_embedding`, `last_face_update`, `location_id`, `created_login`, `created_cookies`) VALUES
(10, 'SW999', '123456', '4613c554b5636edc7d41680e20147b86cc213f0df5acf1ab53a5f0be1a9033eb', 'hapis', 15, 7, NULL, '123456-HAPIS_1770548264.jpg', NULL, NULL, 1, '2026-02-09 10:34:13', '-');

-- --------------------------------------------------------

--
-- Table structure for table `student_schedule`
--

CREATE TABLE `student_schedule` (
  `student_schedule_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `teacher_schedule_id` int(11) NOT NULL,
  `schedule_date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` enum('ACTIVE','COMPLETED','CANCELLED','CLOSED') DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
-- --------------------------------------------------------

--
-- Table structure for table `teacher`
--

CREATE TABLE `teacher` (
  `id` int(11) NOT NULL,
  `teacher_code` varchar(20) DEFAULT NULL,
  `teacher_username` varchar(100) DEFAULT NULL,
  `teacher_password` varchar(255) DEFAULT NULL,
  `teacher_name` varchar(150) DEFAULT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `teacher_type` enum('UMUM','KEJURUAN') DEFAULT NULL,
  `photo` varchar(100) DEFAULT NULL,
  `photo_reference` varchar(100) DEFAULT NULL,
  `location_id` int(11) DEFAULT 1,
  `created_login` datetime DEFAULT NULL,
  `created_cookies` varchar(100) DEFAULT '-'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher`
--

INSERT INTO `teacher` (`id`, `teacher_code`, `teacher_username`, `teacher_password`, `teacher_name`, `subject`, `teacher_type`, `photo`, `photo_reference`, `location_id`, `created_login`, `created_cookies`) VALUES
(6, 'GR001', 'raga', '4613c554b5636edc7d41680e20147b86cc213f0df5acf1ab53a5f0be1a9033eb', 'RAGA', 'KIMIA', 'KEJURUAN', NULL, NULL, 1, '2026-02-08 18:58:03', '-');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_schedule`
--

CREATE TABLE `teacher_schedule` (
  `schedule_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `day_id` int(11) DEFAULT NULL,
  `shift_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_schedule`
--

INSERT INTO `teacher_schedule` (`schedule_id`, `teacher_id`, `class_id`, `subject`, `day_id`, `shift_id`) VALUES
(8, 6, 15, 'KIMIA', 1, 8);

--
-- Triggers `teacher_schedule` (dihapus, logika dijalankan di aplikasi)
--

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `username` varchar(60) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `registered` datetime NOT NULL DEFAULT current_timestamp(),
  `created_login` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `session` varchar(100) DEFAULT '-',
  `ip` varchar(50) DEFAULT '0',
  `browser` varchar(100) DEFAULT 'Unknown',
  `level` int(11) NOT NULL,
  `is_active` enum('Y','N') DEFAULT 'Y'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `username`, `email`, `password`, `fullname`, `registered`, `created_login`, `last_login`, `session`, `ip`, `browser`, `level`, `is_active`) VALUES
(1, 'adm', 'admin@smkn1cikarang.sch.id', '0e8910802f4d94f33b73469695c7ac7783941e8134c24005e706d6760a228276', 'Administrator', '2026-01-29 23:34:21', '2026-01-29 23:34:21', '2026-02-09 10:34:42', '-', '127.0.0.1', 'Chrome', 1, 'Y'),
(2, 'operator', 'operator@smkn1cikarang.sch.id', '0c5acac0738a76bbf05236de1854533ef1413f54fed126ce2ed788f237330370', 'Operator', '2026-01-29 23:34:21', '2026-01-29 23:34:21', '2026-01-29 23:34:21', '-', '127.0.0.1', 'Chrome', 2, 'Y');

-- --------------------------------------------------------

--
-- Table structure for table `user_level`
--

CREATE TABLE `user_level` (
  `level_id` int(11) NOT NULL,
  `level_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_level`
--

INSERT INTO `user_level` (`level_id`, `level_name`) VALUES
(1, 'Administrator'),
(2, 'Operator');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_student_schedule_integration`
-- (See below for the actual view)
--
CREATE TABLE `v_student_schedule_integration` (
`teacher_schedule_id` int(11)
,`teacher_id` int(11)
,`teacher_name` varchar(150)
,`class_id` int(11)
,`class_name` varchar(30)
,`subject` varchar(100)
,`day_name` varchar(15)
,`shift_name` varchar(50)
,`time_in` time
,`time_out` time
,`total_students` bigint(21)
,`active_students` bigint(21)
,`earliest_date` date
,`latest_date` date
);

-- --------------------------------------------------------

--
-- Structure for view `v_student_schedule_integration`
--
DROP TABLE IF EXISTS `v_student_schedule_integration`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_student_schedule_integration`  AS SELECT `ts`.`schedule_id` AS `teacher_schedule_id`, `ts`.`teacher_id` AS `teacher_id`, `t`.`teacher_name` AS `teacher_name`, `ts`.`class_id` AS `class_id`, `c`.`class_name` AS `class_name`, `ts`.`subject` AS `subject`, `d`.`day_name` AS `day_name`, `s`.`shift_name` AS `shift_name`, `s`.`time_in` AS `time_in`, `s`.`time_out` AS `time_out`, count(distinct `ss`.`student_id`) AS `total_students`, count(distinct case when `ss`.`status` = 'ACTIVE' then `ss`.`student_id` end) AS `active_students`, min(`ss`.`schedule_date`) AS `earliest_date`, max(`ss`.`schedule_date`) AS `latest_date` FROM (((((`teacher_schedule` `ts` left join `teacher` `t` on(`ts`.`teacher_id` = `t`.`id`)) left join `class` `c` on(`ts`.`class_id` = `c`.`class_id`)) left join `day` `d` on(`ts`.`day_id` = `d`.`day_id`)) left join `shift` `s` on(`ts`.`shift_id` = `s`.`shift_id`)) left join `student_schedule` `ss` on(`ts`.`schedule_id` = `ss`.`teacher_schedule_id`)) GROUP BY `ts`.`schedule_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `class`
--
ALTER TABLE `class`
  ADD PRIMARY KEY (`class_id`),
  ADD KEY `jurusan_id` (`jurusan_id`);

--
-- Indexes for table `day`
--
ALTER TABLE `day`
  ADD PRIMARY KEY (`day_id`);

--
-- Indexes for table `day_schedule_config`
--
ALTER TABLE `day_schedule_config`
  ADD PRIMARY KEY (`day_id`);

--
-- Indexes for table `jurusan`
--
ALTER TABLE `jurusan`
  ADD PRIMARY KEY (`jurusan_id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `presence`
--
ALTER TABLE `presence`
  ADD PRIMARY KEY (`presence_id`),
  ADD KEY `present_id` (`present_id`),
  ADD KEY `idx_presence_student_date` (`student_id`,`presence_date`),
  ADD KEY `idx_presence_schedule` (`student_schedule_id`);

--
-- Indexes for table `present_status`
--
ALTER TABLE `present_status`
  ADD PRIMARY KEY (`present_id`);

--
-- Indexes for table `push_tokens`
--
ALTER TABLE `push_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_push_endpoint` (`endpoint`),
  ADD KEY `idx_push_student` (`student_id`);

--
-- Indexes for table `push_notification_logs`
--
ALTER TABLE `push_notification_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_push_notification` (`student_id`,`student_schedule_id`,`type`,`scheduled_at`),
  ADD KEY `idx_push_log_schedule` (`student_schedule_id`);

--
-- Indexes for table `school_location`
--
ALTER TABLE `school_location`
  ADD PRIMARY KEY (`location_id`);

--
-- Indexes for table `shift`
--
ALTER TABLE `shift`
  ADD PRIMARY KEY (`shift_id`);

--
-- Indexes for table `site`
--
ALTER TABLE `site`
  ADD PRIMARY KEY (`site_id`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_code` (`student_code`),
  ADD UNIQUE KEY `student_nisn` (`student_nisn`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `jurusan_id` (`jurusan_id`),
  ADD KEY `idx_student_location` (`location_id`);

--
-- Indexes for table `student_schedule`
--
ALTER TABLE `student_schedule`
  ADD PRIMARY KEY (`student_schedule_id`),
  ADD KEY `idx_student_schedule_student` (`student_id`),
  ADD KEY `idx_student_schedule_teacher` (`teacher_schedule_id`),
  ADD KEY `idx_student_schedule_date` (`schedule_date`);

--
-- Indexes for table `teacher`
--
ALTER TABLE `teacher`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `teacher_code` (`teacher_code`),
  ADD UNIQUE KEY `teacher_username` (`teacher_username`),
  ADD KEY `idx_teacher_location` (`location_id`);

--
-- Indexes for table `teacher_schedule`
--
ALTER TABLE `teacher_schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `day_id` (`day_id`),
  ADD KEY `shift_id` (`shift_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `level` (`level`);

--
-- Indexes for table `user_level`
--
ALTER TABLE `user_level`
  ADD PRIMARY KEY (`level_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=133;

--
-- AUTO_INCREMENT for table `class`
--
ALTER TABLE `class`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `day`
--
ALTER TABLE `day`
  MODIFY `day_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `jurusan`
--
ALTER TABLE `jurusan`
  MODIFY `jurusan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `presence`
--
ALTER TABLE `presence`
  MODIFY `presence_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `present_status`
--
ALTER TABLE `present_status`
  MODIFY `present_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `push_tokens`
--
ALTER TABLE `push_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `push_notification_logs`
--
ALTER TABLE `push_notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `school_location`
--
ALTER TABLE `school_location`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `shift`
--
ALTER TABLE `shift`
  MODIFY `shift_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `site`
--
ALTER TABLE `site`
  MODIFY `site_id` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student`
--
ALTER TABLE `student`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `student_schedule`
--
ALTER TABLE `student_schedule`
  MODIFY `student_schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `teacher`
--
ALTER TABLE `teacher`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `teacher_schedule`
--
ALTER TABLE `teacher_schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_level`
--
ALTER TABLE `user_level`
  MODIFY `level_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `class`
--
ALTER TABLE `class`
  ADD CONSTRAINT `class_ibfk_1` FOREIGN KEY (`jurusan_id`) REFERENCES `jurusan` (`jurusan_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `day_schedule_config`
--
ALTER TABLE `day_schedule_config`
  ADD CONSTRAINT `day_schedule_config_ibfk_1` FOREIGN KEY (`day_id`) REFERENCES `day` (`day_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `presence`
--
ALTER TABLE `presence`
  ADD CONSTRAINT `presence_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `presence_ibfk_2` FOREIGN KEY (`student_schedule_id`) REFERENCES `student_schedule` (`student_schedule_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `presence_ibfk_3` FOREIGN KEY (`present_id`) REFERENCES `present_status` (`present_id`) ON UPDATE CASCADE;

--
-- Constraints for table `push_tokens`
--
ALTER TABLE `push_tokens`
  ADD CONSTRAINT `push_tokens_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `push_notification_logs`
--
ALTER TABLE `push_notification_logs`
  ADD CONSTRAINT `push_notification_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `push_notification_logs_ibfk_2` FOREIGN KEY (`student_schedule_id`) REFERENCES `student_schedule` (`student_schedule_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `student`
--
ALTER TABLE `student`
  ADD CONSTRAINT `student_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `student_ibfk_2` FOREIGN KEY (`jurusan_id`) REFERENCES `jurusan` (`jurusan_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `student_ibfk_3` FOREIGN KEY (`location_id`) REFERENCES `school_location` (`location_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `student_schedule`
--
ALTER TABLE `student_schedule`
  ADD CONSTRAINT `student_schedule_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `student_schedule_ibfk_2` FOREIGN KEY (`teacher_schedule_id`) REFERENCES `teacher_schedule` (`schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `teacher`
--
ALTER TABLE `teacher`
  ADD CONSTRAINT `teacher_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `school_location` (`location_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `teacher_schedule`
--
ALTER TABLE `teacher_schedule`
  ADD CONSTRAINT `teacher_schedule_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `teacher_schedule_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `teacher_schedule_ibfk_3` FOREIGN KEY (`day_id`) REFERENCES `day` (`day_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `teacher_schedule_ibfk_4` FOREIGN KEY (`shift_id`) REFERENCES `shift` (`shift_id`) ON UPDATE CASCADE;

--
-- Constraints for table `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `fk_user_level` FOREIGN KEY (`level`) REFERENCES `user_level` (`level_id`) ON UPDATE CASCADE;
COMMIT;
