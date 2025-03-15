-- Script to add missing elements from radius.sql
-- This script adds the missing table, columns, and character set specifications

-- 1. Create the missing nasreload table
CREATE TABLE IF NOT EXISTS `nasreload` (
  `nasipaddress` varchar(15) NOT NULL,
  `reloadtime` datetime NOT NULL,
  PRIMARY KEY (`nasipaddress`)
) ENGINE = INNODB;

-- 2. Add missing columns to existing tables
-- Add class column to radacct if it doesn't exist
ALTER TABLE `radacct` 
  ADD COLUMN IF NOT EXISTS `class` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  ADD INDEX IF NOT EXISTS `class` (`class`),
  MODIFY COLUMN `connectinfo_start` varchar(128) COLLATE utf8mb4_general_ci DEFAULT NULL,
  MODIFY COLUMN `connectinfo_stop` varchar(128) COLLATE utf8mb4_general_ci DEFAULT NULL;

-- Add plan_id column to radgroupreply if it doesn't exist
ALTER TABLE `radgroupreply` 
  ADD COLUMN IF NOT EXISTS `plan_id` int(11) NOT NULL DEFAULT '0';

-- Add class column to radpostauth if it doesn't exist
ALTER TABLE `radpostauth` 
  ADD COLUMN IF NOT EXISTS `class` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  ADD INDEX IF NOT EXISTS `class` (`class`);

-- Add routers column to nas if it doesn't exist
ALTER TABLE `nas` 
  ADD COLUMN IF NOT EXISTS `routers` varchar(32) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '';

-- 3. Convert tables to use utf8mb4 character set and collation
ALTER TABLE `radacct` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `radcheck` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `radgroupcheck` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `radgroupreply` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `radpostauth` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `radreply` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `radusergroup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `nas` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Note: This script uses ALTER TABLE with IF NOT EXISTS which requires MySQL 8.0.16+
-- For older MySQL versions, you may need to use a different approach. 