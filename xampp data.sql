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
DROP PROCEDURE IF EXISTS `GenerateWeeklyStudentSchedules`$$
CREATE PROCEDURE `GenerateWeeklyStudentSchedules` ()   BEGIN
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
(132, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 10:34:42'),
(133, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 20:51:58'),
(134, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 20:53:47'),
(135, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-09 20:59:08'),
(136, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 08:19:30'),
(137, NULL, '', 'failed_login', 'Failed login attempt for admin: adm', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 08:45:07'),
(138, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 08:45:17'),
(139, NULL, '', 'failed_login', 'Failed login attempt for admin: adm', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 08:46:55'),
(140, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 08:47:00'),
(141, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 08:48:21'),
(142, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 09:22:22'),
(143, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 09:49:21'),
(144, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 14:15:30'),
(145, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 15:53:34'),
(146, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 16:05:48'),
(147, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 16:16:14'),
(148, 10, 'student', 'attendance', '{\"schedule_id\":\"95\",\"similarity\":92,\"match_details\":{\"lbph_similarity\":0,\"lbph_confidence\":148.65,\"lbph_base_confidence\":0,\"lbph_conf_ratio\":1,\"hist_similarity\":77.82,\"hist_corr\":0.5565,\"edge_similarity\":4.23,\"edge_corr\":0.0423,\"corr_similarity\":63.2,\"corr_value\":0.2641,\"hog_similarity\":85,\"hog_value\":0.85,\"baseline_lbph\":100,\"baseline_hist\":76.3,\"baseline_edge\":84.83,\"baseline_corr\":99.89,\"baseline_hog\":99.58,\"baseline_quality\":89.86,\"variant_index\":0,\"lighting_diff\":9.69,\"aligned_ref\":true,\"aligned_cand\":true,\"quality\":69.72,\"quality_struct\":69.72,\"name_detected\":true,\"min_lbph\":70.09,\"min_corr\":71.27,\"min_hist\":45.59,\"min_hog\":71.04,\"min_quality\":63.76,\"source\":\"python-lbph\",\"threshold\":89},\"status\":\"SUCCESS\",\"attendance_path\":\"uploads\\/attendance\\/2026-02-10\\/ATT_10_20260210_163809.jpg\",\"validation_path\":\"uploads\\/attendance\\/2026-02-10\\/VAL_10_20260210_163809.jpg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 16:38:09'),
(149, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 16:39:24'),
(150, 6, '', 'login', 'Guru login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 16:39:36'),
(151, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-10 22:29:09'),
(152, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 10:11:23'),
(153, 10, 'student', 'attendance', '{\"schedule_id\":\"150\",\"similarity\":92,\"match_details\":{\"lbph_similarity\":0,\"lbph_confidence\":123.32,\"lbph_base_confidence\":0,\"lbph_conf_ratio\":1,\"hist_similarity\":89.88,\"hist_corr\":0.7976,\"edge_similarity\":6.93,\"edge_corr\":0.0693,\"corr_similarity\":85.81,\"corr_value\":0.7162,\"hog_similarity\":89.49,\"hog_value\":0.8949,\"baseline_lbph\":100,\"baseline_hist\":76.3,\"baseline_edge\":84.83,\"baseline_corr\":99.89,\"baseline_hog\":99.58,\"baseline_quality\":89.86,\"variant_index\":0,\"lighting_diff\":4.78,\"aligned_ref\":true,\"aligned_cand\":true,\"quality\":83.79,\"quality_struct\":83.79,\"name_detected\":true,\"min_lbph\":72.32,\"min_corr\":72.87,\"min_hist\":47.78,\"min_hog\":72.63,\"min_quality\":65.2,\"source\":\"python-lbph\",\"threshold\":89},\"status\":\"SUCCESS\",\"attendance_path\":\"uploads\\/attendance\\/2026-02-11\\/hapis_10_20260211_101402.jpg\",\"validation_path\":\"uploads\\/attendance\\/2026-02-11\\/hapis_10_20260211_101402.jpg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 10:14:04'),
(154, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 15:22:53'),
(155, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 15:24:21'),
(156, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 15:29:37'),
(157, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 21:23:20'),
(158, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 21:30:30'),
(159, 6, '', 'login', 'Guru login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-11 22:03:15'),
(160, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-12 09:39:07'),
(161, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-12 09:44:21'),
(162, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-12 10:56:15'),
(163, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-12 11:18:54'),
(164, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-12 18:11:03'),
(165, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-12 18:56:48'),
(166, NULL, '', 'failed_login', 'Failed login attempt for admin: adm', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-12 19:22:55'),
(167, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-12 19:23:01'),
(168, 6, '', 'login', 'Guru login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-12 21:37:10'),
(169, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 17:40:33'),
(170, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 18:10:56'),
(171, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 18:16:27'),
(172, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 21:38:25'),
(173, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 21:54:11'),
(174, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 22:06:34'),
(175, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 23:19:30'),
(176, 3, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 23:20:41'),
(177, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 23:38:52'),
(178, 0, '', 'forgot_password_failed', 'student_not_found|ip=::1|id_hash=8d969eef6ecad3c2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 23:41:40'),
(179, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 23:46:59'),
(180, 0, '', 'forgot_password_failed', 'name_mismatch|ip=::1|id_hash=8d969eef6ecad3c2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 23:50:45'),
(181, 0, '', 'forgot_password_failed', 'name_mismatch|ip=::1|id_hash=8bb0cf6eb9b17d0f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 23:50:54'),
(182, 0, '', 'forgot_password_success', 'student_reset|ip=::1|id_hash=8d969eef6ecad3c2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-13 23:51:17'),
(183, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-14 22:36:47'),
(184, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-14 22:39:29'),
(185, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 10:48:06'),
(186, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 11:00:30'),
(187, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 11:01:15'),
(188, 10, 'student', 'attendance', '{\"schedule_id\":\"43\",\"similarity\":91.5,\"match_details\":{\"match_source\":\"face_matching_ticket\",\"ticket_issued_at\":1771218765,\"ticket_expires_at\":1771219365,\"client_distance\":0.6296,\"client_distance_threshold\":0.55,\"client_descriptor_mismatch\":true},\"status\":\"SUCCESS\",\"attendance_path\":\"uploads\\/attendance\\/2026-02-16\\/hapis_10_20260216_121350.jpg\",\"validation_path\":\"uploads\\/attendance\\/2026-02-16\\/hapis_10_20260216_121350.jpg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 12:14:03'),
(189, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 12:46:51'),
(190, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 18:13:06'),
(191, 1, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 19:00:00'),
(192, 1, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 19:00:00'),
(193, 1, 'admin', 'login_blocked', 'Admin account disabled', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 19:00:22'),
(194, 3, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 19:00:52'),
(195, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 19:00:52'),
(196, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 19:02:56'),
(197, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 19:27:01'),
(198, 1, 'admin', 'login_blocked', 'Admin account disabled', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 19:27:08'),
(199, 3, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 19:27:17'),
(200, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 19:27:17'),
(201, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 19:30:40'),
(202, 3, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 19:30:49'),
(203, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 19:30:49'),
(204, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 19:49:39'),
(205, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 19:55:32'),
(206, 2, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 19:55:59'),
(207, 2, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 19:55:59'),
(208, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 20:00:30'),
(209, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 20:01:51'),
(210, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 20:08:26'),
(211, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 20:09:10'),
(212, 3, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 20:17:32'),
(213, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 20:17:32'),
(214, 3, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 20:23:01'),
(215, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 20:23:01'),
(216, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 21:35:53'),
(217, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 21:39:20'),
(218, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 21:46:17'),
(219, 2, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 21:46:30'),
(220, 2, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 21:46:30'),
(221, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 21:49:52'),
(222, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 22:26:03'),
(223, 3, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 22:27:09'),
(224, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 22:27:09'),
(225, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 22:33:11'),
(226, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 22:44:03'),
(227, NULL, '', 'failed_login', 'Failed login attempt for admin: hafizh', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 22:45:12'),
(228, 3, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 22:45:19'),
(229, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 22:45:19'),
(230, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 22:50:38'),
(231, 3, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 22:54:21'),
(232, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 22:54:21'),
(233, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 23:01:58'),
(234, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 23:12:20'),
(235, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 23:20:53'),
(236, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-16 23:33:37'),
(237, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 08:51:30'),
(238, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 09:05:48'),
(239, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 09:33:28'),
(240, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 09:40:08'),
(241, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 10:00:32'),
(242, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 10:08:09'),
(243, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 10:30:45'),
(244, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 10:42:11'),
(245, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 10:52:14'),
(246, 3, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 10:55:56'),
(247, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 10:55:56'),
(248, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 11:01:40'),
(249, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 11:07:01'),
(250, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 11:13:28'),
(251, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 11:18:35'),
(252, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 11:27:28'),
(253, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 11:29:20'),
(254, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 11:57:14'),
(255, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 12:04:24'),
(256, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 12:04:55'),
(257, 3, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 12:07:52'),
(258, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 12:07:54'),
(259, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 12:11:32'),
(260, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 12:25:17'),
(261, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 12:29:26'),
(262, 3, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 12:33:33'),
(263, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 12:33:33'),
(264, 2, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 12:34:28'),
(265, 2, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 12:34:28'),
(266, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 12:38:21'),
(267, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 12:50:11'),
(268, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 13:03:44'),
(269, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 13:27:20'),
(270, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 21:42:57'),
(271, 3, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 21:43:09'),
(272, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 21:43:09'),
(273, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 21:49:40'),
(274, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 21:55:29'),
(275, 0, '', 'forgot_password_failed', 'student_not_found|ip=::1|id_hash=e11d8cb94b54e0a2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 21:58:13'),
(276, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 21:59:25'),
(277, 3, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 22:06:19'),
(278, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 22:06:23'),
(279, 3, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 22:09:21'),
(280, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 22:09:21'),
(281, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 22:13:35'),
(282, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 22:18:44'),
(283, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 22:47:09'),
(284, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-17 22:59:04'),
(285, NULL, '', 'failed_login', 'Failed login attempt for admin: operator', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 09:29:09'),
(286, 2, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 09:29:14'),
(287, 2, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 09:29:14'),
(288, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '127.0.0.1', 'curl/8.16.0', '2026-02-18 11:17:00'),
(289, 6, '', 'dashboard_access', 'Guru dashboard accessed', '127.0.0.1', 'curl/8.16.0', '2026-02-18 11:18:23'),
(290, 6, '', 'dashboard_access', 'Guru dashboard accessed', '127.0.0.1', 'curl/8.16.0', '2026-02-18 11:19:00'),
(291, 6, '', 'password_auto_rotate', 'Teacher default password auto-rotated', '127.0.0.1', 'curl/8.16.0', '2026-02-18 11:19:00'),
(292, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '127.0.0.1', 'curl/8.16.0', '2026-02-18 11:23:58'),
(293, 6, '', 'dashboard_access', 'Guru dashboard accessed', '127.0.0.1', 'curl/8.16.0', '2026-02-18 11:24:00'),
(294, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '127.0.0.1', 'curl/8.16.0', '2026-02-18 11:30:23'),
(295, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '127.0.0.1', 'curl/8.16.0', '2026-02-18 11:30:46'),
(296, 6, '', 'dashboard_access', 'Guru dashboard accessed', '127.0.0.1', 'curl/8.16.0', '2026-02-18 11:32:06'),
(297, 6, '', 'password_auto_rotate', 'Teacher default password auto-rotated', '127.0.0.1', 'curl/8.16.0', '2026-02-18 11:32:06'),
(298, 2, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 14:54:50'),
(299, 2, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 14:54:52'),
(300, 2, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 14:54:52'),
(301, 2, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 14:54:53'),
(302, 2, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 14:54:53'),
(303, 2, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 14:54:54'),
(304, 2, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 14:54:55'),
(305, 2, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 14:54:56'),
(306, 2, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 14:54:56'),
(307, 1, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 14:55:19'),
(308, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 15:19:42'),
(309, 3, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 15:19:59'),
(310, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 15:19:59'),
(311, 3, 'admin', 'login', 'Admin login successful', '::1', 'curl/8.16.0', '2026-02-18 15:27:41'),
(312, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 15:27:49'),
(313, 7, '', 'login', 'Guru login successful', '::1', 'curl/8.16.0', '2026-02-18 15:27:58'),
(314, 7, '', 'dashboard_access', 'Guru dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 15:28:05'),
(315, 7, '', 'password_auto_rotate', 'Teacher default password auto-rotated', '::1', 'curl/8.16.0', '2026-02-18 15:28:05'),
(316, 11, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 15:28:26'),
(317, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 15:29:18'),
(318, 11, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 15:29:27'),
(319, 3, 'admin', 'login', 'Admin login successful', '::1', 'curl/8.16.0', '2026-02-18 15:31:52'),
(320, 3, 'admin', 'login', 'Admin login successful', '::1', 'curl/8.16.0', '2026-02-18 15:34:49'),
(321, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 15:34:56'),
(322, 7, '', 'login', 'Guru login successful', '::1', 'curl/8.16.0', '2026-02-18 15:35:06'),
(323, 7, '', 'dashboard_access', 'Guru dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 15:35:14'),
(324, 7, '', 'password_auto_rotate', 'Teacher default password auto-rotated', '::1', 'curl/8.16.0', '2026-02-18 15:35:14'),
(325, 11, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 15:35:25');
INSERT INTO `activity_logs` (`log_id`, `user_id`, `user_type`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(326, 11, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 15:35:40'),
(327, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 15:35:40'),
(328, 7, '', 'dashboard_access', 'Guru dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 15:35:40'),
(329, 3, 'admin', 'login', 'Admin login successful', '::1', 'curl/8.16.0', '2026-02-18 15:36:30'),
(330, 3, 'admin', 'login', 'Admin login successful', '::1', 'curl/8.16.0', '2026-02-18 15:40:12'),
(331, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 15:41:19'),
(332, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 15:43:10'),
(333, 3, 'admin', 'login', 'Admin login successful', '::1', 'curl/8.16.0', '2026-02-18 15:43:59'),
(334, 7, '', 'login', 'Guru login successful', '::1', 'curl/8.16.0', '2026-02-18 15:44:00'),
(335, 3, 'admin', 'login', 'Admin login successful', '::1', 'curl/8.16.0', '2026-02-18 15:45:00'),
(336, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 15:46:04'),
(337, 3, 'admin', 'login', 'Admin login successful', '::1', 'curl/8.16.0', '2026-02-18 15:47:21'),
(338, 7, '', 'login', 'Guru login successful', '::1', 'curl/8.16.0', '2026-02-18 15:47:21'),
(339, 3, 'admin', 'login', 'Admin login successful', '::1', 'curl/8.16.0', '2026-02-18 15:47:47'),
(340, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 15:47:48'),
(341, 11, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 15:47:58'),
(342, 3, 'admin', 'login', 'Admin login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 16:42:31'),
(343, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 16:42:31'),
(344, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 16:42:35'),
(345, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 16:47:36'),
(346, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 16:48:38'),
(347, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 16:48:51'),
(348, 10, 'student', 'password_changed', 'Student changed password after forced default-password policy', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 16:50:00'),
(349, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 16:51:29'),
(350, 7, '', 'dashboard_access', 'Guru dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 17:06:25'),
(351, 7, '', 'password_auto_rotate', 'Teacher default password auto-rotated', '::1', 'curl/8.16.0', '2026-02-18 17:06:25'),
(352, 11, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 17:06:25'),
(353, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 17:08:23'),
(354, 8, '', 'dashboard_access', 'Guru dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 17:34:51'),
(355, 8, '', 'password_auto_rotate', 'Teacher default password auto-rotated', '::1', 'curl/8.16.0', '2026-02-18 17:34:51'),
(356, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'curl/8.16.0', '2026-02-18 17:36:57'),
(357, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 21:59:56'),
(358, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 22:20:41'),
(359, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 22:33:20'),
(360, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 22:38:47'),
(361, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 22:41:32'),
(362, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 22:42:10'),
(363, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 22:56:07'),
(364, 10, 'student', 'password_changed', 'Student changed password after forced default-password policy', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 22:56:42'),
(365, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 23:05:19'),
(366, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 23:10:27'),
(367, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 23:23:09'),
(368, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 23:25:13'),
(369, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 23:25:42'),
(370, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 23:39:54'),
(371, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-18 23:47:43'),
(372, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 00:11:17'),
(373, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 00:19:27'),
(374, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 00:21:41'),
(375, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 05:37:15'),
(376, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 05:37:33'),
(377, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 05:42:26'),
(378, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 05:42:59'),
(379, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 05:50:07'),
(380, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 05:51:55'),
(381, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 05:52:40'),
(382, 2, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 05:53:01'),
(383, 2, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 06:06:26'),
(384, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 10:32:57'),
(385, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 11:05:33'),
(386, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 11:18:50'),
(387, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 11:26:30'),
(388, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 11:28:36'),
(389, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 11:34:47'),
(390, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 11:47:38'),
(391, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 15:10:01'),
(392, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 17:39:07'),
(393, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 17:39:28'),
(394, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 17:50:30'),
(395, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 17:50:48'),
(396, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 17:50:59'),
(397, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 17:52:55'),
(398, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 17:56:00'),
(399, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 21:13:25'),
(400, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 21:18:34'),
(401, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 21:21:35'),
(402, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 21:21:48'),
(403, 3, 'admin', 'dashboard_access', 'Admin dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 21:22:36'),
(404, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 21:22:53'),
(405, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 21:30:39'),
(406, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 21:42:59'),
(407, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-19 22:09:46'),
(408, 10, 'student', 'dashboard_access', 'Student dashboard accessed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-20 00:14:04');

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

INSERT INTO `day_schedule_config` (`day_id`, `school_start_time`, `activity1_label`, `activity1_minutes`, `activity2_label`, `activity2_minutes`, `created_at`, `updated_at`) VALUES
(1, '06:30:00', 'Upacara', 45, NULL, 0, '2026-02-09 13:10:16', '2026-02-10 01:46:10'),
(2, '13:30:00', 'Pengisian', 30, NULL, 0, '2026-02-09 13:10:16', '2026-02-10 08:54:14'),
(3, '10:30:00', 'Pengisian', 30, NULL, 0, '2026-02-09 13:10:16', '2026-02-11 08:30:38'),
(4, '17:28:00', 'Pengisian', 30, NULL, 0, '2026-02-09 13:10:16', '2026-02-19 10:55:47'),
(5, '06:30:00', 'Pengisian', 30, NULL, 0, '2026-02-09 13:10:16', '2026-02-09 13:53:35'),
(6, '06:30:00', '', 0, '', 0, '2026-02-09 13:10:16', '2026-02-09 13:10:16'),
(7, '06:30:00', '', 0, '', 0, '2026-02-09 13:10:16', '2026-02-09 13:10:16');

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
(9, 'TITL', 'TEKNIK INSTALASI TENAGA LISTRIK', NULL),
(10, 'TP', 'TEKNIK PERMESINAN', NULL),
(11, 'TE', 'TEKNIK ELEKTRO', NULL),
(22, 'AKL', 'AKUNTANSI KEUANGAN LEMBAGA', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `master_data_audit_logs`
--

CREATE TABLE `master_data_audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `actor_id` varchar(64) DEFAULT NULL,
  `actor_role` varchar(32) NOT NULL,
  `entity_type` varchar(64) NOT NULL,
  `entity_id` varchar(64) DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `before_json` longtext DEFAULT NULL,
  `after_json` longtext DEFAULT NULL,
  `meta_json` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `master_data_audit_logs`
--

INSERT INTO `master_data_audit_logs` (`id`, `actor_id`, `actor_role`, `entity_type`, `entity_id`, `action`, `before_json`, `after_json`, `meta_json`, `created_at`) VALUES
(1, '6', 'guru', 'credential', '6', 'auto_rotate_default_password', '{\"password\":\"default_masked\"}', '{\"password\":\"random_masked\"}', '{\"ip_address\":\"127.0.0.1\",\"user_agent\":\"curl\\/8.16.0\",\"source\":\"dashboard\\/guru\",\"sync\":\"admin_masked_only\"}', '2026-02-18 11:19:00'),
(2, '6', 'guru', 'credential', '6', 'auto_rotate_default_password', '{\"password\":\"default_masked\"}', '{\"password\":\"random_masked\"}', '{\"ip_address\":\"127.0.0.1\",\"user_agent\":\"curl\\/8.16.0\",\"source\":\"dashboard\\/guru\",\"sync\":\"admin_masked_only\"}', '2026-02-18 11:32:06'),
(3, '7', 'guru', 'credential', '7', 'auto_rotate_default_password', '{\"password\":\"default_masked\"}', '{\"password\":\"random_masked\"}', '{\"ip_address\":\"::1\",\"user_agent\":\"curl\\/8.16.0\",\"source\":\"dashboard\\/guru\",\"sync\":\"admin_masked_only\"}', '2026-02-18 15:28:05'),
(4, '7', 'guru', 'credential', '7', 'auto_rotate_default_password', '{\"password\":\"default_masked\"}', '{\"password\":\"random_masked\"}', '{\"ip_address\":\"::1\",\"user_agent\":\"curl\\/8.16.0\",\"source\":\"dashboard\\/guru\",\"sync\":\"admin_masked_only\"}', '2026-02-18 15:35:14'),
(5, '10', 'student', 'credential', '10', 'change_password_forced', '{\"password\":\"masked\"}', '{\"password\":\"masked\"}', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/144.0.0.0 Safari\\/537.36\",\"source\":\"siswa\\/change_password\"}', '2026-02-18 16:50:00'),
(6, '7', 'guru', 'credential', '7', 'auto_rotate_default_password', '{\"password\":\"default_masked\"}', '{\"password\":\"random_masked\"}', '{\"ip_address\":\"::1\",\"user_agent\":\"curl\\/8.16.0\",\"source\":\"dashboard\\/guru\",\"sync\":\"admin_masked_only\"}', '2026-02-18 17:06:25'),
(7, '8', 'guru', 'credential', '8', 'auto_rotate_default_password', '{\"password\":\"default_masked\"}', '{\"password\":\"random_masked\"}', '{\"ip_address\":\"::1\",\"user_agent\":\"curl\\/8.16.0\",\"source\":\"dashboard\\/guru\",\"sync\":\"admin_masked_only\"}', '2026-02-18 17:34:51'),
(9, '3', 'admin', 'credential', '13', 'reset_student_password_default', '{\"password\":\"masked\"}', '{\"password\":\"masked\"}', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/144.0.0.0 Safari\\/537.36\",\"target\":\"student\",\"reset_to\":\"student_code\"}', '2026-02-18 22:22:13'),
(10, '3', 'admin', 'credential', '19', 'reset_student_password_default', '{\"password\":\"masked\"}', '{\"password\":\"masked\"}', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/144.0.0.0 Safari\\/537.36\",\"target\":\"student\",\"reset_to\":\"student_code\"}', '2026-02-18 22:33:56'),
(11, '10', 'student', 'credential', '10', 'change_password_forced', '{\"password\":\"masked\"}', '{\"password\":\"masked\"}', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/144.0.0.0 Safari\\/537.36\",\"source\":\"siswa\\/change_password\"}', '2026-02-18 22:56:42'),
(12, '13', 'guru', 'credential', '13', 'auto_rotate_default_password', '{\"password\":\"default_masked\"}', '{\"password\":\"random_masked\"}', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/144.0.0.0 Safari\\/537.36\",\"source\":\"dashboard\\/guru\",\"sync\":\"admin_masked_only\"}', '2026-02-19 05:43:32'),
(13, '2', 'admin', 'credential', '13', 'reset_teacher_password_default', '{\"password\":\"masked\"}', '{\"password\":\"masked\"}', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/144.0.0.0 Safari\\/537.36\",\"target\":\"teacher\",\"reset_to\":\"guru123\"}', '2026-02-19 06:06:39'),
(14, '13', 'guru', 'credential', '13', 'auto_rotate_default_password', '{\"password\":\"default_masked\"}', '{\"password\":\"random_masked\"}', '{\"ip_address\":\"::1\",\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/144.0.0.0 Safari\\/537.36\",\"source\":\"dashboard\\/guru\",\"sync\":\"admin_masked_only\"}', '2026-02-19 06:07:02');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2026_02_18_000100_create_master_data_audit_logs_table', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` varchar(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

--
-- Dumping data for table `presence`
--

INSERT INTO `presence` (`presence_id`, `student_id`, `student_schedule_id`, `presence_date`, `time_in`, `time_out`, `picture_in`, `picture_out`, `present_id`, `presence_address`, `latitude_in`, `longitude_in`, `latitude_out`, `longitude_out`, `distance_in`, `distance_out`, `is_late`, `late_time`, `information`) VALUES
(1, 10, 95, '2026-02-10', '16:38:05', NULL, 'ATT_10_20260210_163809.jpg', NULL, 1, NULL, -6.43218800, 107.07648483, NULL, NULL, 57, NULL, 'N', 0, 'Waktu absen: 16.38.05 WIB | Nama mapel: KIMIA | Hari: Selasa | Tanggal: 10 Februari 2026 | Jam pelajaran ke: JP1-JP12 | Nama guru: RAGA | Nama siswa: hapis | Absen: Hadir'),
(2, 10, 150, '2026-02-11', '10:13:58', NULL, '2026-02-11_101358/xii-tjkt-a/hapis-123456-rabu.jpg', NULL, 1, NULL, -6.35233550, 107.10674550, NULL, NULL, 61, NULL, 'N', 0, 'Waktu absen: 10.13.57 WIB | Nama mapel: PSIKOLOGI | Hari: Rabu | Tanggal: 11 Februari 2026 | Jam pelajaran ke: JP1-JP12 | Nama guru: ALDINO | Nama siswa: hapis | Absen: Hadir'),
(3, 10, 43, '2026-02-16', '12:13:50', NULL, '2026-02-16_121350/xii-tjkt-a/hapis-123456-senin.jpg', NULL, 2, NULL, -6.43218200, 107.07648500, NULL, NULL, 9460, NULL, 'N', 0, 'Waktu absen: 12.13.50 WIB | Nama mapel: KIMIA | Hari: Senin | Tanggal: 16 Februari 2026 | Jam pelajaran ke: JP1-JP12 | Nama guru: RAGA | Nama siswa: hapis | Absen: Sakit');

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

--
-- Dumping data for table `push_tokens`
--

INSERT INTO `push_tokens` (`id`, `student_id`, `endpoint`, `p256dh`, `auth`, `content_encoding`, `browser`, `platform`, `user_agent`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 10, 'https://fcm.googleapis.com/fcm/send/f6xCMIpfg5U:APA91bF3W9Ru7ZlXmhBKh_qzVX6P9MHtgT4ctPZ-hkdRKGj3xF_-QLALJTH3ZyzF7tSilx133Lha7SQJwJ_pe5dsIks9zc8SwY3X9E32rsOFwQbOJbI52O1v3kIgniEHMhMRZSx2EjLC', 'BKTh3HhMUbBYtTr1wsPO1bgxje-gQl0ROfa9sVf8NWEqnt_aXJd3W4_D2RxrOO6dRgg6lBee_IzWAE0F8chZFLo', '-lDBQOyJrJOXB5JLqd6esA', NULL, 'Chrome', '\"Windows\"', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'Y', '2026-02-19 05:37:37', '2026-02-19 22:10:08');

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
(8, 'JP1-JP12', '14:00:00', '22:15:00'),
(9, 'JP5-JP7', '17:00:00', '19:00:00'),
(10, 'JP7-JP10', '18:00:00', '20:45:00'),
(11, 'JP7-JP11', '18:00:00', '21:30:00'),
(12, 'JP7-JP12', '18:00:00', '22:15:00'),
(13, 'JP1-JP10', '14:00:00', '20:45:00'),
(14, 'JP5-JP12', '17:00:00', '22:15:00'),
(15, 'JP1-JP4', '14:00:00', '17:15:00'),
(16, 'JP6-JP12', '17:15:00', '22:15:00'),
(17, 'JP8-JP12', '18:45:00', '22:15:00');

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
(1, 'http://localhost/presenova/', 'presenova', '0811-1444-240', 'Jl. Ciantra, Sukadami, Cikarang Selatan', 'Sistem Absensi Online dengan Foto Selfie & GPS Validation', 'presenova.png', 'admin@presenova.my.id', 'presenova.my.id', 15, 'Y', 'Y', 1);

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
  `created_cookies` varchar(100) DEFAULT '-',
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`id`, `student_code`, `student_nisn`, `student_password`, `student_name`, `class_id`, `jurusan_id`, `photo`, `photo_reference`, `face_embedding`, `last_face_update`, `location_id`, `created_login`, `created_cookies`, `email`, `phone`) VALUES
(10, 'SW999', '123456', '6516f379c4bef706d9ebbc15c42e106d215810c265381acee6a87eaf741e1b8a', 'hapis', 15, 7, NULL, 'xii-tjkt-a/hapis/123456-hapis.jpg', NULL, NULL, 1, '2026-02-19 21:42:59', '-', 'hzfpgamin@gmail.com', '083871643026'),
(11, 'SW0002', '1234567', '7c33357c27162ee8de3b595a2a844d2b1812c1260c2f1e961f9af0fa77fa0102', 'FF', 15, 7, NULL, 'xii-tjkt-a/ff/1234567-ff.jpg', NULL, NULL, 1, '2026-02-18 17:07:57', '-', NULL, NULL);

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

--
-- Dumping data for table `student_schedule`
--

INSERT INTO `student_schedule` (`student_schedule_id`, `student_id`, `teacher_schedule_id`, `schedule_date`, `time_in`, `time_out`, `status`, `created_at`, `updated_at`) VALUES
(42, 10, 1, '2026-02-09', '19:15:00', '03:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-09 13:53:35'),
(43, 10, 1, '2026-02-16', '07:15:00', '15:30:00', 'COMPLETED', '2026-02-09 13:52:23', '2026-02-16 05:14:03'),
(44, 10, 1, '2026-02-23', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(45, 10, 1, '2026-03-02', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(46, 10, 1, '2026-03-09', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(47, 10, 1, '2026-03-16', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(48, 10, 1, '2026-03-23', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(49, 10, 1, '2026-03-30', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(50, 10, 1, '2026-04-06', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(51, 10, 1, '2026-04-13', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(52, 10, 1, '2026-04-20', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(53, 10, 1, '2026-04-27', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(54, 10, 1, '2026-05-04', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(55, 10, 1, '2026-05-11', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(56, 10, 1, '2026-05-18', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(57, 10, 1, '2026-05-25', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(58, 10, 1, '2026-06-01', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(59, 10, 1, '2026-06-08', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(60, 10, 1, '2026-06-15', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(61, 10, 1, '2026-06-22', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(62, 10, 1, '2026-06-29', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(63, 10, 1, '2026-07-06', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(64, 10, 1, '2026-07-13', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(65, 10, 1, '2026-07-20', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(66, 10, 1, '2026-07-27', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(67, 10, 1, '2026-08-03', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-09 13:52:23', '2026-02-10 01:46:10'),
(68, 10, 1, '2026-08-10', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:20:45', '2026-02-10 01:46:10'),
(69, 11, 1, '2026-02-16', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(70, 11, 1, '2026-02-23', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(71, 11, 1, '2026-03-02', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(72, 11, 1, '2026-03-09', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(73, 11, 1, '2026-03-16', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(74, 11, 1, '2026-03-23', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(75, 11, 1, '2026-03-30', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(76, 11, 1, '2026-04-06', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(77, 11, 1, '2026-04-13', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(78, 11, 1, '2026-04-20', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(79, 11, 1, '2026-04-27', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(80, 11, 1, '2026-05-04', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(81, 11, 1, '2026-05-11', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(82, 11, 1, '2026-05-18', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(83, 11, 1, '2026-05-25', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(84, 11, 1, '2026-06-01', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(85, 11, 1, '2026-06-08', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(86, 11, 1, '2026-06-15', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(87, 11, 1, '2026-06-22', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(88, 11, 1, '2026-06-29', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(89, 11, 1, '2026-07-06', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(90, 11, 1, '2026-07-13', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(91, 11, 1, '2026-07-20', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(92, 11, 1, '2026-07-27', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(93, 11, 1, '2026-08-03', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(94, 11, 1, '2026-08-10', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-10 01:45:41', '2026-02-10 01:46:10'),
(95, 10, 2, '2026-02-10', '14:00:00', '22:15:00', 'COMPLETED', '2026-02-10 01:46:32', '2026-02-10 09:38:09'),
(96, 11, 2, '2026-02-10', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(97, 10, 2, '2026-02-17', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(98, 11, 2, '2026-02-17', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(99, 10, 2, '2026-02-24', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(100, 11, 2, '2026-02-24', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(101, 10, 2, '2026-03-03', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(102, 11, 2, '2026-03-03', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(103, 10, 2, '2026-03-10', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(104, 11, 2, '2026-03-10', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(105, 10, 2, '2026-03-17', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(106, 11, 2, '2026-03-17', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(107, 10, 2, '2026-03-24', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(108, 11, 2, '2026-03-24', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(109, 10, 2, '2026-03-31', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(110, 11, 2, '2026-03-31', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(111, 10, 2, '2026-04-07', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(112, 11, 2, '2026-04-07', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(113, 10, 2, '2026-04-14', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(114, 11, 2, '2026-04-14', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(115, 10, 2, '2026-04-21', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(116, 11, 2, '2026-04-21', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(117, 10, 2, '2026-04-28', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(118, 11, 2, '2026-04-28', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(119, 10, 2, '2026-05-05', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(120, 11, 2, '2026-05-05', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(121, 10, 2, '2026-05-12', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(122, 11, 2, '2026-05-12', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(123, 10, 2, '2026-05-19', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(124, 11, 2, '2026-05-19', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(125, 10, 2, '2026-05-26', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(126, 11, 2, '2026-05-26', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(127, 10, 2, '2026-06-02', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(128, 11, 2, '2026-06-02', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(129, 10, 2, '2026-06-09', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(130, 11, 2, '2026-06-09', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(131, 10, 2, '2026-06-16', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(132, 11, 2, '2026-06-16', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(133, 10, 2, '2026-06-23', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(134, 11, 2, '2026-06-23', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(135, 10, 2, '2026-06-30', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(136, 11, 2, '2026-06-30', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(137, 10, 2, '2026-07-07', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(138, 11, 2, '2026-07-07', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(139, 10, 2, '2026-07-14', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(140, 11, 2, '2026-07-14', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(141, 10, 2, '2026-07-21', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(142, 11, 2, '2026-07-21', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(143, 10, 2, '2026-07-28', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(144, 11, 2, '2026-07-28', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(145, 10, 2, '2026-08-04', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(146, 11, 2, '2026-08-04', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-10 01:46:32', '2026-02-10 08:54:14'),
(147, 10, 2, '2026-08-11', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-11 03:11:30', '2026-02-11 03:11:30'),
(148, 11, 2, '2026-08-11', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-11 03:11:30', '2026-02-11 03:11:30'),
(150, 10, 3, '2026-02-11', '07:00:00', '15:15:00', 'COMPLETED', '2026-02-11 03:12:48', '2026-02-11 03:14:04'),
(151, 11, 3, '2026-02-11', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(152, 10, 3, '2026-02-18', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(153, 11, 3, '2026-02-18', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(154, 10, 3, '2026-02-25', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(155, 11, 3, '2026-02-25', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(156, 10, 3, '2026-03-04', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(157, 11, 3, '2026-03-04', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(158, 10, 3, '2026-03-11', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(159, 11, 3, '2026-03-11', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(160, 10, 3, '2026-03-18', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(161, 11, 3, '2026-03-18', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(162, 10, 3, '2026-03-25', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(163, 11, 3, '2026-03-25', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(164, 10, 3, '2026-04-01', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(165, 11, 3, '2026-04-01', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(166, 10, 3, '2026-04-08', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(167, 11, 3, '2026-04-08', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(168, 10, 3, '2026-04-15', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(169, 11, 3, '2026-04-15', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(170, 10, 3, '2026-04-22', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(171, 11, 3, '2026-04-22', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(172, 10, 3, '2026-04-29', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(173, 11, 3, '2026-04-29', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(174, 10, 3, '2026-05-06', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(175, 11, 3, '2026-05-06', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(176, 10, 3, '2026-05-13', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(177, 11, 3, '2026-05-13', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(178, 10, 3, '2026-05-20', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(179, 11, 3, '2026-05-20', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(180, 10, 3, '2026-05-27', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(181, 11, 3, '2026-05-27', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(182, 10, 3, '2026-06-03', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(183, 11, 3, '2026-06-03', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(184, 10, 3, '2026-06-10', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(185, 11, 3, '2026-06-10', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(186, 10, 3, '2026-06-17', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(187, 11, 3, '2026-06-17', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(188, 10, 3, '2026-06-24', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(189, 11, 3, '2026-06-24', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(190, 10, 3, '2026-07-01', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(191, 11, 3, '2026-07-01', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(192, 10, 3, '2026-07-08', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(193, 11, 3, '2026-07-08', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(194, 10, 3, '2026-07-15', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(195, 11, 3, '2026-07-15', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(196, 10, 3, '2026-07-22', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(197, 11, 3, '2026-07-22', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(198, 10, 3, '2026-07-29', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(199, 11, 3, '2026-07-29', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(200, 10, 3, '2026-08-05', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(201, 11, 3, '2026-08-05', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-11 03:12:48', '2026-02-11 08:30:51'),
(202, 10, 4, '2026-02-11', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(203, 11, 4, '2026-02-11', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(204, 10, 4, '2026-02-18', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(205, 11, 4, '2026-02-18', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(206, 10, 4, '2026-02-25', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(207, 11, 4, '2026-02-25', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(208, 10, 4, '2026-03-04', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(209, 11, 4, '2026-03-04', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(210, 10, 4, '2026-03-11', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(211, 11, 4, '2026-03-11', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(212, 10, 4, '2026-03-18', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(213, 11, 4, '2026-03-18', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(214, 10, 4, '2026-03-25', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(215, 11, 4, '2026-03-25', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(216, 10, 4, '2026-04-01', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(217, 11, 4, '2026-04-01', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(218, 10, 4, '2026-04-08', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(219, 11, 4, '2026-04-08', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(220, 10, 4, '2026-04-15', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(221, 11, 4, '2026-04-15', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(222, 10, 4, '2026-04-22', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(223, 11, 4, '2026-04-22', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(224, 10, 4, '2026-04-29', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(225, 11, 4, '2026-04-29', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(226, 10, 4, '2026-05-06', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(227, 11, 4, '2026-05-06', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(228, 10, 4, '2026-05-13', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(229, 11, 4, '2026-05-13', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(230, 10, 4, '2026-05-20', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(231, 11, 4, '2026-05-20', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(232, 10, 4, '2026-05-27', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(233, 11, 4, '2026-05-27', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(234, 10, 4, '2026-06-03', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(235, 11, 4, '2026-06-03', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(236, 10, 4, '2026-06-10', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(237, 11, 4, '2026-06-10', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(238, 10, 4, '2026-06-17', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(239, 11, 4, '2026-06-17', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(240, 10, 4, '2026-06-24', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(241, 11, 4, '2026-06-24', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(242, 10, 4, '2026-07-01', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(243, 11, 4, '2026-07-01', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(244, 10, 4, '2026-07-08', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(245, 11, 4, '2026-07-08', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(246, 10, 4, '2026-07-15', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(247, 11, 4, '2026-07-15', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(248, 10, 4, '2026-07-22', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(249, 11, 4, '2026-07-22', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(250, 10, 4, '2026-07-29', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(251, 11, 4, '2026-07-29', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(252, 10, 4, '2026-08-05', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(253, 11, 4, '2026-08-05', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-11 08:24:40', '2026-02-11 08:31:01'),
(254, 10, 3, '2026-08-12', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-12 02:40:13', '2026-02-12 02:40:13'),
(255, 11, 3, '2026-08-12', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-12 02:40:13', '2026-02-12 02:40:13'),
(257, 10, 4, '2026-08-12', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-12 02:40:13', '2026-02-12 02:40:13'),
(258, 11, 4, '2026-08-12', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-12 02:40:13', '2026-02-12 02:40:13'),
(260, 10, 5, '2026-02-12', '19:00:00', '03:15:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-12 11:56:54'),
(261, 11, 5, '2026-02-12', '19:00:00', '03:15:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-12 11:56:54'),
(262, 10, 5, '2026-02-19', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(263, 11, 5, '2026-02-19', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(264, 10, 5, '2026-02-26', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(265, 11, 5, '2026-02-26', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(266, 10, 5, '2026-03-05', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(267, 11, 5, '2026-03-05', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(268, 10, 5, '2026-03-12', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(269, 11, 5, '2026-03-12', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(270, 10, 5, '2026-03-19', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(271, 11, 5, '2026-03-19', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(272, 10, 5, '2026-03-26', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(273, 11, 5, '2026-03-26', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(274, 10, 5, '2026-04-02', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(275, 11, 5, '2026-04-02', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(276, 10, 5, '2026-04-09', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(277, 11, 5, '2026-04-09', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(278, 10, 5, '2026-04-16', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(279, 11, 5, '2026-04-16', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(280, 10, 5, '2026-04-23', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(281, 11, 5, '2026-04-23', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(282, 10, 5, '2026-04-30', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(283, 11, 5, '2026-04-30', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(284, 10, 5, '2026-05-07', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(285, 11, 5, '2026-05-07', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(286, 10, 5, '2026-05-14', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(287, 11, 5, '2026-05-14', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(288, 10, 5, '2026-05-21', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(289, 11, 5, '2026-05-21', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(290, 10, 5, '2026-05-28', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(291, 11, 5, '2026-05-28', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(292, 10, 5, '2026-06-04', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(293, 11, 5, '2026-06-04', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(294, 10, 5, '2026-06-11', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(295, 11, 5, '2026-06-11', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(296, 10, 5, '2026-06-18', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(297, 11, 5, '2026-06-18', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(298, 10, 5, '2026-06-25', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(299, 11, 5, '2026-06-25', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(300, 10, 5, '2026-07-02', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(301, 11, 5, '2026-07-02', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(302, 10, 5, '2026-07-09', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(303, 11, 5, '2026-07-09', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(304, 10, 5, '2026-07-16', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(305, 11, 5, '2026-07-16', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(306, 10, 5, '2026-07-23', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(307, 11, 5, '2026-07-23', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(308, 10, 5, '2026-07-30', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(309, 11, 5, '2026-07-30', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(310, 10, 5, '2026-08-06', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(311, 11, 5, '2026-08-06', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 02:42:45', '2026-02-19 10:55:47'),
(312, 10, 6, '2026-02-12', '19:00:00', '03:15:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-12 11:56:54'),
(313, 11, 6, '2026-02-12', '19:00:00', '03:15:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-12 11:56:54'),
(314, 10, 6, '2026-02-19', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(315, 11, 6, '2026-02-19', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(316, 10, 6, '2026-02-26', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(317, 11, 6, '2026-02-26', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(318, 10, 6, '2026-03-05', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(319, 11, 6, '2026-03-05', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(320, 10, 6, '2026-03-12', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(321, 11, 6, '2026-03-12', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(322, 10, 6, '2026-03-19', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(323, 11, 6, '2026-03-19', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(324, 10, 6, '2026-03-26', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(325, 11, 6, '2026-03-26', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(326, 10, 6, '2026-04-02', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(327, 11, 6, '2026-04-02', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(328, 10, 6, '2026-04-09', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(329, 11, 6, '2026-04-09', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(330, 10, 6, '2026-04-16', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(331, 11, 6, '2026-04-16', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(332, 10, 6, '2026-04-23', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(333, 11, 6, '2026-04-23', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(334, 10, 6, '2026-04-30', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(335, 11, 6, '2026-04-30', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(336, 10, 6, '2026-05-07', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(337, 11, 6, '2026-05-07', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(338, 10, 6, '2026-05-14', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(339, 11, 6, '2026-05-14', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(340, 10, 6, '2026-05-21', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(341, 11, 6, '2026-05-21', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(342, 10, 6, '2026-05-28', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(343, 11, 6, '2026-05-28', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(344, 10, 6, '2026-06-04', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(345, 11, 6, '2026-06-04', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(346, 10, 6, '2026-06-11', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(347, 11, 6, '2026-06-11', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(348, 10, 6, '2026-06-18', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(349, 11, 6, '2026-06-18', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(350, 10, 6, '2026-06-25', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(351, 11, 6, '2026-06-25', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(352, 10, 6, '2026-07-02', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(353, 11, 6, '2026-07-02', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(354, 10, 6, '2026-07-09', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(355, 11, 6, '2026-07-09', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(356, 10, 6, '2026-07-16', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(357, 11, 6, '2026-07-16', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(358, 10, 6, '2026-07-23', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(359, 11, 6, '2026-07-23', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(360, 10, 6, '2026-07-30', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(361, 11, 6, '2026-07-30', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(362, 10, 6, '2026-08-06', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(363, 11, 6, '2026-08-06', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-12 11:56:16', '2026-02-19 10:55:47'),
(364, 10, 5, '2026-08-13', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-13 01:31:01', '2026-02-19 10:55:47'),
(365, 10, 6, '2026-08-13', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-13 01:31:01', '2026-02-19 10:55:47'),
(366, 11, 5, '2026-08-13', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-13 10:40:38', '2026-02-19 10:55:47'),
(367, 11, 6, '2026-08-13', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-13 10:40:38', '2026-02-19 10:55:47'),
(368, 10, 7, '2026-02-16', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(369, 11, 7, '2026-02-16', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(370, 10, 7, '2026-02-23', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(371, 11, 7, '2026-02-23', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(372, 10, 7, '2026-03-02', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(373, 11, 7, '2026-03-02', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(374, 10, 7, '2026-03-09', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(375, 11, 7, '2026-03-09', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(376, 10, 7, '2026-03-16', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(377, 11, 7, '2026-03-16', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(378, 10, 7, '2026-03-23', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(379, 11, 7, '2026-03-23', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(380, 10, 7, '2026-03-30', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(381, 11, 7, '2026-03-30', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(382, 10, 7, '2026-04-06', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(383, 11, 7, '2026-04-06', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(384, 10, 7, '2026-04-13', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(385, 11, 7, '2026-04-13', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(386, 10, 7, '2026-04-20', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(387, 11, 7, '2026-04-20', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(388, 10, 7, '2026-04-27', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(389, 11, 7, '2026-04-27', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(390, 10, 7, '2026-05-04', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(391, 11, 7, '2026-05-04', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(392, 10, 7, '2026-05-11', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(393, 11, 7, '2026-05-11', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(394, 10, 7, '2026-05-18', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(395, 11, 7, '2026-05-18', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(396, 10, 7, '2026-05-25', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(397, 11, 7, '2026-05-25', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(398, 10, 7, '2026-06-01', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(399, 11, 7, '2026-06-01', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(400, 10, 7, '2026-06-08', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(401, 11, 7, '2026-06-08', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(402, 10, 7, '2026-06-15', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(403, 11, 7, '2026-06-15', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(404, 10, 7, '2026-06-22', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(405, 11, 7, '2026-06-22', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(406, 10, 7, '2026-06-29', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(407, 11, 7, '2026-06-29', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(408, 10, 7, '2026-07-06', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(409, 11, 7, '2026-07-06', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(410, 10, 7, '2026-07-13', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(411, 11, 7, '2026-07-13', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(412, 10, 7, '2026-07-20', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(413, 11, 7, '2026-07-20', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(414, 10, 7, '2026-07-27', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(415, 11, 7, '2026-07-27', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(416, 10, 7, '2026-08-03', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(417, 11, 7, '2026-08-03', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(418, 10, 7, '2026-08-10', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(419, 11, 7, '2026-08-10', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-16 05:47:20', '2026-02-16 05:47:20'),
(784, 10, 1, '2026-08-17', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-17 01:53:05', '2026-02-17 01:53:05'),
(785, 11, 1, '2026-08-17', '07:15:00', '15:30:00', 'ACTIVE', '2026-02-17 01:53:05', '2026-02-17 01:53:05'),
(787, 10, 7, '2026-08-17', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-17 01:53:05', '2026-02-17 01:53:05'),
(788, 11, 7, '2026-08-17', '12:00:00', '15:30:00', 'ACTIVE', '2026-02-17 01:53:05', '2026-02-17 01:53:05'),
(790, 10, 2, '2026-08-18', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-18 09:42:45', '2026-02-18 09:42:45'),
(791, 11, 2, '2026-08-18', '14:00:00', '22:15:00', 'ACTIVE', '2026-02-18 09:42:45', '2026-02-18 09:42:45'),
(975, 10, 3, '2026-08-19', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-18 17:11:18', '2026-02-18 17:11:18'),
(976, 11, 3, '2026-08-19', '11:00:00', '14:15:00', 'ACTIVE', '2026-02-18 17:11:18', '2026-02-18 17:11:18'),
(978, 10, 4, '2026-08-19', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-18 17:11:18', '2026-02-18 17:11:18'),
(979, 11, 4, '2026-08-19', '14:15:00', '19:15:00', 'ACTIVE', '2026-02-18 17:11:18', '2026-02-18 17:11:18'),
(980, 10, 5, '2026-08-20', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-19 17:14:06', '2026-02-19 17:14:06'),
(981, 10, 6, '2026-08-20', '17:58:00', '02:13:00', 'ACTIVE', '2026-02-19 17:14:06', '2026-02-19 17:14:06');

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
(6, 'GR001', 'raga', '7a6ab157edfbfe77d6a35872a220a1a9a828205f0527d06245da0bf2d9d427e2', 'RAGA', 'KIMIA', 'KEJURUAN', NULL, NULL, 1, '2026-02-12 21:37:10', '-'),
(7, 'GR002', 'aldino', '9869b4ee74de9adef912d4cf892243f21b873bff08e638796508d277642a98ec', 'ALDINO', 'PSIKOLOGI', 'KEJURUAN', NULL, NULL, 1, '2026-02-18 17:06:16', '-'),
(8, 'GR003', 'aulia', '970a787364239a31d969e86a01b4a003cc90c715b992e9dff7421c217ebd0de6', 'AULIA', 'inggris', 'UMUM', NULL, NULL, 1, '2026-02-18 17:32:47', '-'),
(12, 'GR999', 'dirga', '0e29b20ef87c0a11048f89363aff2dc914808f445dd3cb1d407baa26eef14ea7', 'DIRGAS', 'BIOLOGI', 'KEJURUAN', NULL, NULL, 1, NULL, '-'),
(13, 'GR004', 'jjj', 'c42344f1996f5b35921c80ea1ec5e2de362409e30558def43c7e560470088863', 'jjj', 'ask', 'KEJURUAN', NULL, NULL, 1, '2026-02-19 06:07:01', '-');

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
(1, 6, 15, 'KIMIA', 1, 8),
(2, 6, 15, 'KIMIA', 2, 8),
(3, 7, 15, 'PSIKOLOGI', 3, 15),
(4, 6, 15, 'KIMIA', 3, 16),
(5, 7, 15, 'PSIKOLOGI', 4, 8),
(6, 8, 15, 'inggris', 4, 8),
(7, 8, 15, 'inggris', 1, 17);

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
  `is_active` enum('Y','N') NOT NULL DEFAULT 'Y'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `username`, `email`, `password`, `fullname`, `registered`, `created_login`, `last_login`, `session`, `ip`, `browser`, `level`, `is_active`) VALUES
(1, 'adm', 'admin@presenova.my.id', '0e8910802f4d94f33b73469695c7ac7783941e8134c24005e706d6760a228276', 'Administrator', '2026-01-29 23:34:21', '2026-01-29 23:34:21', '2026-02-16 19:00:00', '-', '127.0.0.1', 'Chrome', 1, 'N'),
(2, 'operator', 'operator@smkn1cikarang.sch.id', 'ec276d4c3452a528915c218e1b878d0e8119c5b1142215817747d1e784bb0a8b', 'Operator', '2026-01-29 23:34:21', '2026-01-29 23:34:21', '2026-02-19 05:53:00', '-', '127.0.0.1', 'Chrome', 2, 'Y'),
(3, 'hafizh', 'hafizhoffcll@gmail.com', 'ec276d4c3452a528915c218e1b878d0e8119c5b1142215817747d1e784bb0a8b', 'hafizh', '2026-02-13 23:19:55', NULL, '2026-02-19 21:22:36', '-', '0', 'Unknown', 1, 'Y');

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
DROP VIEW IF EXISTS `v_student_schedule_integration`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_student_schedule_integration`  AS SELECT `ts`.`schedule_id` AS `teacher_schedule_id`, `ts`.`teacher_id` AS `teacher_id`, `t`.`teacher_name` AS `teacher_name`, `ts`.`class_id` AS `class_id`, `c`.`class_name` AS `class_name`, `ts`.`subject` AS `subject`, `d`.`day_name` AS `day_name`, `s`.`shift_name` AS `shift_name`, `s`.`time_in` AS `time_in`, `s`.`time_out` AS `time_out`, count(distinct `ss`.`student_id`) AS `total_students`, count(distinct case when `ss`.`status` = 'ACTIVE' then `ss`.`student_id` end) AS `active_students`, min(`ss`.`schedule_date`) AS `earliest_date`, max(`ss`.`schedule_date`) AS `latest_date` FROM (((((`teacher_schedule` `ts` left join `teacher` `t` on(`ts`.`teacher_id` = `t`.`id`)) left join `class` `c` on(`ts`.`class_id` = `c`.`class_id`)) left join `day` `d` on(`ts`.`day_id` = `d`.`day_id`)) left join `shift` `s` on(`ts`.`shift_id` = `s`.`shift_id`)) left join `student_schedule` `ss` on(`ts`.`schedule_id` = `ss`.`teacher_schedule_id`)) GROUP BY `ts`.`schedule_id` ;

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
-- Indexes for table `master_data_audit_logs`
--
ALTER TABLE `master_data_audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `master_data_audit_logs_actor_role_index` (`actor_role`),
  ADD KEY `master_data_audit_logs_entity_type_index` (`entity_type`),
  ADD KEY `master_data_audit_logs_created_at_index` (`created_at`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`);

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
-- Indexes for table `push_notification_logs`
--
ALTER TABLE `push_notification_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_push_notification` (`student_id`,`student_schedule_id`,`type`,`scheduled_at`),
  ADD KEY `idx_push_log_schedule` (`student_schedule_id`);

--
-- Indexes for table `push_tokens`
--
ALTER TABLE `push_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_push_endpoint` (`endpoint`),
  ADD KEY `idx_push_student` (`student_id`);

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
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=409;

--
-- AUTO_INCREMENT for table `class`
--
ALTER TABLE `class`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `day`
--
ALTER TABLE `day`
  MODIFY `day_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `jurusan`
--
ALTER TABLE `jurusan`
  MODIFY `jurusan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `master_data_audit_logs`
--
ALTER TABLE `master_data_audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `presence`
--
ALTER TABLE `presence`
  MODIFY `presence_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `present_status`
--
ALTER TABLE `present_status`
  MODIFY `present_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `push_notification_logs`
--
ALTER TABLE `push_notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `push_tokens`
--
ALTER TABLE `push_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `school_location`
--
ALTER TABLE `school_location`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `shift`
--
ALTER TABLE `shift`
  MODIFY `shift_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `site`
--
ALTER TABLE `site`
  MODIFY `site_id` int(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student`
--
ALTER TABLE `student`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `student_schedule`
--
ALTER TABLE `student_schedule`
  MODIFY `student_schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=982;

--
-- AUTO_INCREMENT for table `teacher`
--
ALTER TABLE `teacher`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `teacher_schedule`
--
ALTER TABLE `teacher_schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
-- Constraints for table `push_notification_logs`
--
ALTER TABLE `push_notification_logs`
  ADD CONSTRAINT `push_notification_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `push_notification_logs_ibfk_2` FOREIGN KEY (`student_schedule_id`) REFERENCES `student_schedule` (`student_schedule_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `push_tokens`
--
ALTER TABLE `push_tokens`
  ADD CONSTRAINT `push_tokens_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`id`) ON DELETE CASCADE;

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
