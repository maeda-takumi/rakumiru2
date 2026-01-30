-- phpMyAdmin SQL Dump
-- version 5.2.1-1.el8.remi
-- https://www.phpmyadmin.net/
--
-- ホスト: localhost
-- 生成日時: 2026 年 1 月 30 日 10:52
-- サーバのバージョン： 10.5.22-MariaDB-log
-- PHP のバージョン: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- データベース: `ss911157_rakumiru2`
--

-- --------------------------------------------------------

--
-- テーブルの構造 `genres`
--

CREATE TABLE `genres` (
  `genre_id` bigint(20) UNSIGNED NOT NULL,
  `parent_genre_id` bigint(20) UNSIGNED DEFAULT NULL,
  `genre_name` varchar(255) NOT NULL,
  `depth` tinyint(3) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `items`
--

CREATE TABLE `items` (
  `item_code` varchar(128) NOT NULL,
  `item_name` varchar(512) DEFAULT NULL,
  `item_url` varchar(1024) DEFAULT NULL,
  `image_url` varchar(1024) DEFAULT NULL,
  `shop_code` varchar(128) DEFAULT NULL,
  `shop_name` varchar(255) DEFAULT NULL,
  `price_last` int(10) UNSIGNED DEFAULT NULL,
  `review_count_last` int(10) UNSIGNED DEFAULT NULL,
  `point_rate_last` smallint(5) UNSIGNED DEFAULT NULL,
  `first_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `job_state`
--

CREATE TABLE `job_state` (
  `job_name` varchar(64) NOT NULL,
  `last_run_at` datetime DEFAULT NULL,
  `last_run_date` date DEFAULT NULL,
  `status` enum('idle','running','ok','error') NOT NULL DEFAULT 'idle',
  `message` varchar(1024) DEFAULT NULL,
  `cursor_genre_id` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `rank_daily`
--

CREATE TABLE `rank_daily` (
  `captured_date` date NOT NULL,
  `captured_at` datetime NOT NULL DEFAULT current_timestamp(),
  `genre_id` bigint(20) UNSIGNED NOT NULL,
  `rank_pos` tinyint(3) UNSIGNED NOT NULL,
  `item_code` varchar(128) NOT NULL,
  `price` int(10) UNSIGNED DEFAULT NULL,
  `review_count` int(10) UNSIGNED DEFAULT NULL,
  `point_rate` smallint(5) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `rank_stats_30d`
--

CREATE TABLE `rank_stats_30d` (
  `genre_id` bigint(20) UNSIGNED NOT NULL,
  `item_code` varchar(128) NOT NULL,
  `appear_days_30d` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `best_rank_30d` tinyint(3) UNSIGNED DEFAULT NULL,
  `avg_rank_30d` decimal(5,2) DEFAULT NULL,
  `last_seen_date` date DEFAULT NULL,
  `last_rank` tinyint(3) UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ダンプしたテーブルのインデックス
--

--
-- テーブルのインデックス `genres`
--
ALTER TABLE `genres`
  ADD PRIMARY KEY (`genre_id`),
  ADD KEY `idx_parent` (`parent_genre_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- テーブルのインデックス `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_code`),
  ADD KEY `idx_last_seen` (`last_seen_at`),
  ADD KEY `idx_shop` (`shop_code`);

--
-- テーブルのインデックス `job_state`
--
ALTER TABLE `job_state`
  ADD PRIMARY KEY (`job_name`);

--
-- テーブルのインデックス `rank_daily`
--
ALTER TABLE `rank_daily`
  ADD PRIMARY KEY (`captured_date`,`genre_id`,`rank_pos`),
  ADD KEY `idx_genre_date` (`genre_id`,`captured_date`),
  ADD KEY `idx_item_date` (`item_code`,`captured_date`),
  ADD KEY `idx_genre_item_date` (`genre_id`,`item_code`,`captured_date`);

--
-- テーブルのインデックス `rank_stats_30d`
--
ALTER TABLE `rank_stats_30d`
  ADD PRIMARY KEY (`genre_id`,`item_code`),
  ADD KEY `idx_last_seen` (`last_seen_date`),
  ADD KEY `idx_item` (`item_code`);

--
-- ダンプしたテーブルの制約
--

--
-- テーブルの制約 `rank_daily`
--
ALTER TABLE `rank_daily`
  ADD CONSTRAINT `fk_rank_daily_genre` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`genre_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rank_daily_item` FOREIGN KEY (`item_code`) REFERENCES `items` (`item_code`) ON UPDATE CASCADE;

--
-- テーブルの制約 `rank_stats_30d`
--
ALTER TABLE `rank_stats_30d`
  ADD CONSTRAINT `fk_rank_stats_genre` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`genre_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rank_stats_item` FOREIGN KEY (`item_code`) REFERENCES `items` (`item_code`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
