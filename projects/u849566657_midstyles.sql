-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 19, 2025 at 11:07 AM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u849566657_midstyles`
--

-- --------------------------------------------------------

--
-- Table structure for table `about_images`
--

CREATE TABLE `about_images` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `styles_used` text NOT NULL,
  `upload_date` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `profile_image` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `banner_image` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `preferred_mode` varchar(10) DEFAULT 'dark',
  `highlight_color` varchar(7) DEFAULT '#d36115',
  `last_login_ip` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `accounts_achievements`
--

CREATE TABLE `accounts_achievements` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `logins` int(11) DEFAULT 0,
  `prompts_stored` int(11) DEFAULT 0,
  `styles_collected` int(11) DEFAULT 0,
  `collections_created` int(11) DEFAULT 0,
  `styles_liked` int(11) DEFAULT 0,
  `last_login` date DEFAULT NULL,
  `first_login` date DEFAULT NULL,
  `first_public_collection` tinyint(1) DEFAULT 0,
  `profile_image_uploaded` tinyint(1) DEFAULT 0,
  `banner_image_uploaded` tinyint(1) DEFAULT 0,
  `notification_shown` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `accounts_info`
--

CREATE TABLE `accounts_info` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `twitter` varchar(100) DEFAULT NULL,
  `instagram` varchar(100) DEFAULT NULL,
  `discord` varchar(100) DEFAULT NULL,
  `deviantart` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `accounts_preferences`
--

CREATE TABLE `accounts_preferences` (
  `user_id` int(11) NOT NULL,
  `card_order` text DEFAULT NULL,
  `hidden_cards` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `accounts_profile_preferences`
--

CREATE TABLE `accounts_profile_preferences` (
  `user_id` int(11) NOT NULL,
  `main_column_order` text DEFAULT NULL,
  `sidebar_column_order` text DEFAULT NULL,
  `hidden_sections` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `accounts_votes`
--

CREATE TABLE `accounts_votes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `style_id` int(11) NOT NULL,
  `voted_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_settings`
--

CREATE TABLE `admin_settings` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_keys`
--

CREATE TABLE `api_keys` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `api_key` varchar(64) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blocked_ips`
--

CREATE TABLE `blocked_ips` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `added_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog`
--

CREATE TABLE `blog` (
  `id` int(11) NOT NULL,
  `headline` varchar(255) NOT NULL,
  `post_date` date NOT NULL,
  `status_update` text DEFAULT NULL,
  `post_content` text NOT NULL,
  `image_1` varchar(255) DEFAULT NULL,
  `image_2` varchar(255) DEFAULT NULL,
  `image_3` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `image_4` varchar(255) DEFAULT NULL,
  `image_5` varchar(255) DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT 'General'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `collections`
--

CREATE TABLE `collections` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `folder_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('private','public') DEFAULT 'private',
  `last_updated` datetime DEFAULT '2000-01-01 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `curations`
--

CREATE TABLE `curations` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `keywords` text DEFAULT NULL,
  `last_updated` datetime DEFAULT '2000-01-01 00:00:00',
  `auto_tag` varchar(100) DEFAULT NULL,
  `is_auto_curation` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `curation_notifications`
--

CREATE TABLE `curation_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `curation_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `curation_styles`
--

CREATE TABLE `curation_styles` (
  `id` int(11) NOT NULL,
  `curation_id` int(11) DEFAULT NULL,
  `style_filename` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `curation_subscriptions`
--

CREATE TABLE `curation_subscriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `curation_id` int(11) NOT NULL,
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `curation_views`
--

CREATE TABLE `curation_views` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `curation_id` int(11) NOT NULL,
  `last_viewed` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disallowed_usernames`
--

CREATE TABLE `disallowed_usernames` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `folder_items`
--

CREATE TABLE `folder_items` (
  `id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `style_filename` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `images`
--

CREATE TABLE `images` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `primary_color` varchar(30) DEFAULT NULL,
  `color_palette` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`color_palette`)),
  `style_version` varchar(10) DEFAULT 'v7.0',
  `votes` int(11) DEFAULT 0,
  `style_number` bigint(20) GENERATED ALWAYS AS (cast(substring_index(`filename`,'.',1) as unsigned)) STORED,
  `ai_tags` text DEFAULT NULL,
  `tag_status` enum('pending','processing','complete','error') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `images_samples`
--

CREATE TABLE `images_samples` (
  `id` int(11) NOT NULL,
  `style_number` varchar(255) NOT NULL,
  `sample_image_filename` varchar(255) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `master_tags`
--

CREATE TABLE `master_tags` (
  `id` int(11) NOT NULL,
  `tag_name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT 'general',
  `description` text DEFAULT NULL,
  `color_hex` varchar(7) DEFAULT '#6c757d',
  `usage_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meta_images`
--

CREATE TABLE `meta_images` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `upload_date` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `moodboard_images`
--

CREATE TABLE `moodboard_images` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `moodboard_code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `moodboard_samples`
--

CREATE TABLE `moodboard_samples` (
  `id` int(11) NOT NULL,
  `mood_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mosaic_files`
--

CREATE TABLE `mosaic_files` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `niji_collections`
--

CREATE TABLE `niji_collections` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `folder_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('private','public') DEFAULT 'private',
  `last_updated` datetime DEFAULT '2000-01-01 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `niji_folder_items`
--

CREATE TABLE `niji_folder_items` (
  `id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `style_filename` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `niji_images`
--

CREATE TABLE `niji_images` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp(),
  `primary_color` varchar(30) DEFAULT NULL,
  `color_palette` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`color_palette`)),
  `votes` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_to` varchar(50) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prompts`
--

CREATE TABLE `prompts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `prompt_text` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `image` varchar(255) DEFAULT NULL,
  `status` enum('public','private') DEFAULT 'private',
  `keywords` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prompt_styles`
--

CREATE TABLE `prompt_styles` (
  `id` int(11) NOT NULL,
  `prompt_id` int(11) NOT NULL,
  `style_number` varchar(50) NOT NULL,
  `is_niji` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `session_niji_votes`
--

CREATE TABLE `session_niji_votes` (
  `id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `style_id` int(11) NOT NULL,
  `voted_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `session_votes`
--

CREATE TABLE `session_votes` (
  `id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `style_id` int(11) NOT NULL,
  `voted_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `showcase_gallery`
--

CREATE TABLE `showcase_gallery` (
  `id` int(11) NOT NULL,
  `style_number` varchar(255) NOT NULL,
  `showcase_image` varchar(255) NOT NULL,
  `date_added` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `special_event_logins`
--

CREATE TABLE `special_event_logins` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `login_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `style_notes`
--

CREATE TABLE `style_notes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `note_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `style_notes_items`
--

CREATE TABLE `style_notes_items` (
  `id` int(11) NOT NULL,
  `note_id` int(11) NOT NULL,
  `style_filename` varchar(255) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `style_tags`
--

CREATE TABLE `style_tags` (
  `id` int(11) NOT NULL,
  `style_filename` varchar(255) NOT NULL,
  `tag` varchar(100) NOT NULL,
  `confidence` decimal(3,2) DEFAULT 1.00,
  `source` enum('ai','manual','imported') DEFAULT 'ai',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `style_tags`
--
DELIMITER $$
CREATE TRIGGER `update_tag_usage_count` AFTER INSERT ON `style_tags` FOR EACH ROW BEGIN
    UPDATE master_tags 
    SET usage_count = usage_count + 1
    WHERE tag_name = NEW.tag;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `temp_accounts`
--

CREATE TABLE `temp_accounts` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `verification_token` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin') NOT NULL DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `dashboard_notifications` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visitors`
--

CREATE TABLE `visitors` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `country_code` varchar(2) DEFAULT NULL,
  `country_name` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `user_agent` text NOT NULL,
  `browser_name` varchar(50) DEFAULT NULL,
  `browser_version` varchar(20) DEFAULT NULL,
  `operating_system` varchar(50) DEFAULT NULL,
  `device_type` varchar(20) DEFAULT 'desktop',
  `screen_resolution` varchar(20) DEFAULT NULL,
  `language` varchar(10) DEFAULT NULL,
  `referrer` varchar(500) DEFAULT NULL,
  `page_visited` varchar(500) NOT NULL,
  `visit_count` int(11) DEFAULT 1,
  `first_visit` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_visit` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `session_duration` int(11) DEFAULT 0,
  `total_pages_viewed` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `about_images`
--
ALTER TABLE `about_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `accounts_achievements`
--
ALTER TABLE `accounts_achievements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `accounts_info`
--
ALTER TABLE `accounts_info`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `accounts_preferences`
--
ALTER TABLE `accounts_preferences`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `accounts_profile_preferences`
--
ALTER TABLE `accounts_profile_preferences`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `accounts_votes`
--
ALTER TABLE `accounts_votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`style_id`),
  ADD UNIQUE KEY `unique_vote` (`user_id`,`style_id`),
  ADD KEY `image_id` (`style_id`),
  ADD KEY `idx_user_style` (`user_id`,`style_id`);

--
-- Indexes for table `admin_settings`
--
ALTER TABLE `admin_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admin_setting_unique` (`admin_id`,`setting_key`);

--
-- Indexes for table `api_keys`
--
ALTER TABLE `api_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `api_key` (`api_key`);

--
-- Indexes for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip_address` (`ip_address`);

--
-- Indexes for table `blog`
--
ALTER TABLE `blog`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `collections`
--
ALTER TABLE `collections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_user_status_updated` (`user_id`,`status`,`last_updated`);

--
-- Indexes for table `curations`
--
ALTER TABLE `curations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `curation_notifications`
--
ALTER TABLE `curation_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `curation_id` (`curation_id`);

--
-- Indexes for table `curation_styles`
--
ALTER TABLE `curation_styles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_curation` (`curation_id`),
  ADD KEY `idx_curation_filename` (`curation_id`,`style_filename`);

--
-- Indexes for table `curation_subscriptions`
--
ALTER TABLE `curation_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_curation_unique` (`user_id`,`curation_id`),
  ADD KEY `curation_id` (`curation_id`),
  ADD KEY `idx_user_curation` (`user_id`,`curation_id`);

--
-- Indexes for table `curation_views`
--
ALTER TABLE `curation_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_curation_unique` (`user_id`,`curation_id`),
  ADD KEY `curation_id` (`curation_id`);

--
-- Indexes for table `disallowed_usernames`
--
ALTER TABLE `disallowed_usernames`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `folder_items`
--
ALTER TABLE `folder_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `folder_id` (`folder_id`),
  ADD KEY `idx_folder_style` (`folder_id`,`style_filename`);

--
-- Indexes for table `images`
--
ALTER TABLE `images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_style_number` (`style_number`),
  ADD KEY `idx_uploaded_at` (`uploaded_at`),
  ADD KEY `idx_filename` (`filename`),
  ADD KEY `idx_votes` (`votes`);

--
-- Indexes for table `images_samples`
--
ALTER TABLE `images_samples`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sample` (`style_number`,`sample_image_filename`);

--
-- Indexes for table `master_tags`
--
ALTER TABLE `master_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tag_name` (`tag_name`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_usage_count` (`usage_count`);

--
-- Indexes for table `meta_images`
--
ALTER TABLE `meta_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `moodboard_images`
--
ALTER TABLE `moodboard_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `moodboard_samples`
--
ALTER TABLE `moodboard_samples`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mood_id` (`mood_id`);

--
-- Indexes for table `mosaic_files`
--
ALTER TABLE `mosaic_files`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `niji_collections`
--
ALTER TABLE `niji_collections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `niji_folder_items`
--
ALTER TABLE `niji_folder_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `folder_id` (`folder_id`);

--
-- Indexes for table `niji_images`
--
ALTER TABLE `niji_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_votes` (`votes`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `token` (`token`);

--
-- Indexes for table `prompts`
--
ALTER TABLE `prompts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_status_created` (`user_id`,`status`,`created_at`);

--
-- Indexes for table `prompt_styles`
--
ALTER TABLE `prompt_styles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prompt_id` (`prompt_id`);

--
-- Indexes for table `session_niji_votes`
--
ALTER TABLE `session_niji_votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_session_niji_style` (`session_id`,`style_id`),
  ADD KEY `idx_niji_style_id` (`style_id`),
  ADD KEY `idx_session_id` (`session_id`);

--
-- Indexes for table `session_votes`
--
ALTER TABLE `session_votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_session_style` (`session_id`,`style_id`),
  ADD KEY `idx_style_id` (`style_id`),
  ADD KEY `idx_session_id` (`session_id`);

--
-- Indexes for table `showcase_gallery`
--
ALTER TABLE `showcase_gallery`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `special_event_logins`
--
ALTER TABLE `special_event_logins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `style_notes`
--
ALTER TABLE `style_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `style_notes_items`
--
ALTER TABLE `style_notes_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `note_id` (`note_id`);

--
-- Indexes for table `style_tags`
--
ALTER TABLE `style_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_style_tag` (`style_filename`,`tag`),
  ADD KEY `idx_style_filename` (`style_filename`),
  ADD KEY `idx_tag` (`tag`),
  ADD KEY `idx_confidence` (`confidence`);

--
-- Indexes for table `temp_accounts`
--
ALTER TABLE `temp_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `visitors`
--
ALTER TABLE `visitors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip_address` (`ip_address`),
  ADD KEY `first_visit` (`first_visit`),
  ADD KEY `country_code` (`country_code`),
  ADD KEY `last_visit` (`last_visit`),
  ADD KEY `analytics_index` (`first_visit`,`country_code`,`device_type`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `about_images`
--
ALTER TABLE `about_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `accounts_achievements`
--
ALTER TABLE `accounts_achievements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `accounts_info`
--
ALTER TABLE `accounts_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `accounts_votes`
--
ALTER TABLE `accounts_votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_settings`
--
ALTER TABLE `admin_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_keys`
--
ALTER TABLE `api_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog`
--
ALTER TABLE `blog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `collections`
--
ALTER TABLE `collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `curations`
--
ALTER TABLE `curations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `curation_notifications`
--
ALTER TABLE `curation_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `curation_styles`
--
ALTER TABLE `curation_styles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `curation_subscriptions`
--
ALTER TABLE `curation_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `curation_views`
--
ALTER TABLE `curation_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disallowed_usernames`
--
ALTER TABLE `disallowed_usernames`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `folder_items`
--
ALTER TABLE `folder_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `images`
--
ALTER TABLE `images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `images_samples`
--
ALTER TABLE `images_samples`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `master_tags`
--
ALTER TABLE `master_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meta_images`
--
ALTER TABLE `meta_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `moodboard_images`
--
ALTER TABLE `moodboard_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `moodboard_samples`
--
ALTER TABLE `moodboard_samples`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mosaic_files`
--
ALTER TABLE `mosaic_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `niji_collections`
--
ALTER TABLE `niji_collections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `niji_folder_items`
--
ALTER TABLE `niji_folder_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `niji_images`
--
ALTER TABLE `niji_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prompts`
--
ALTER TABLE `prompts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prompt_styles`
--
ALTER TABLE `prompt_styles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `session_niji_votes`
--
ALTER TABLE `session_niji_votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `session_votes`
--
ALTER TABLE `session_votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `showcase_gallery`
--
ALTER TABLE `showcase_gallery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `special_event_logins`
--
ALTER TABLE `special_event_logins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `style_notes`
--
ALTER TABLE `style_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `style_notes_items`
--
ALTER TABLE `style_notes_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `style_tags`
--
ALTER TABLE `style_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `temp_accounts`
--
ALTER TABLE `temp_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `visitors`
--
ALTER TABLE `visitors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounts_achievements`
--
ALTER TABLE `accounts_achievements`
  ADD CONSTRAINT `accounts_achievements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`);

--
-- Constraints for table `accounts_info`
--
ALTER TABLE `accounts_info`
  ADD CONSTRAINT `accounts_info_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `accounts_preferences`
--
ALTER TABLE `accounts_preferences`
  ADD CONSTRAINT `accounts_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `accounts_profile_preferences`
--
ALTER TABLE `accounts_profile_preferences`
  ADD CONSTRAINT `fk_profile_prefs_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `accounts_votes`
--
ALTER TABLE `accounts_votes`
  ADD CONSTRAINT `accounts_votes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `accounts_votes_ibfk_2` FOREIGN KEY (`style_id`) REFERENCES `images` (`id`);

--
-- Constraints for table `admin_settings`
--
ALTER TABLE `admin_settings`
  ADD CONSTRAINT `fk_admin_settings_admin` FOREIGN KEY (`admin_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `collections`
--
ALTER TABLE `collections`
  ADD CONSTRAINT `collections_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `curation_notifications`
--
ALTER TABLE `curation_notifications`
  ADD CONSTRAINT `curation_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `curation_notifications_ibfk_2` FOREIGN KEY (`curation_id`) REFERENCES `curations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `curation_styles`
--
ALTER TABLE `curation_styles`
  ADD CONSTRAINT `curation_styles_ibfk_1` FOREIGN KEY (`curation_id`) REFERENCES `curations` (`id`),
  ADD CONSTRAINT `fk_curation` FOREIGN KEY (`curation_id`) REFERENCES `curations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `curation_subscriptions`
--
ALTER TABLE `curation_subscriptions`
  ADD CONSTRAINT `curation_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `curation_subscriptions_ibfk_2` FOREIGN KEY (`curation_id`) REFERENCES `curations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `curation_views`
--
ALTER TABLE `curation_views`
  ADD CONSTRAINT `curation_views_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `curation_views_ibfk_2` FOREIGN KEY (`curation_id`) REFERENCES `curations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `folder_items`
--
ALTER TABLE `folder_items`
  ADD CONSTRAINT `folder_items_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `collections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `moodboard_samples`
--
ALTER TABLE `moodboard_samples`
  ADD CONSTRAINT `moodboard_samples_ibfk_1` FOREIGN KEY (`mood_id`) REFERENCES `moodboard_images` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `niji_collections`
--
ALTER TABLE `niji_collections`
  ADD CONSTRAINT `niji_collections_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `niji_folder_items`
--
ALTER TABLE `niji_folder_items`
  ADD CONSTRAINT `niji_folder_items_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `niji_collections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prompt_styles`
--
ALTER TABLE `prompt_styles`
  ADD CONSTRAINT `prompt_styles_ibfk_1` FOREIGN KEY (`prompt_id`) REFERENCES `prompts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `session_niji_votes`
--
ALTER TABLE `session_niji_votes`
  ADD CONSTRAINT `session_niji_votes_ibfk_1` FOREIGN KEY (`style_id`) REFERENCES `niji_images` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `session_votes`
--
ALTER TABLE `session_votes`
  ADD CONSTRAINT `session_votes_ibfk_1` FOREIGN KEY (`style_id`) REFERENCES `images` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `special_event_logins`
--
ALTER TABLE `special_event_logins`
  ADD CONSTRAINT `special_event_logins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`);

--
-- Constraints for table `style_notes`
--
ALTER TABLE `style_notes`
  ADD CONSTRAINT `style_notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `style_notes_items`
--
ALTER TABLE `style_notes_items`
  ADD CONSTRAINT `style_notes_items_ibfk_1` FOREIGN KEY (`note_id`) REFERENCES `style_notes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
