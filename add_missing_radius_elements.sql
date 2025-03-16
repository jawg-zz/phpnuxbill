-- Script to add missing elements from radius.sql and standardize collation
-- This script adds the missing table, columns, and applies a uniform collation to all tables

-- 1. Create the missing nasreload table
CREATE TABLE IF NOT EXISTS `nasreload` (
  `nasipaddress` varchar(15) NOT NULL,
  `reloadtime` datetime NOT NULL,
  PRIMARY KEY (`nasipaddress`)
) ENGINE = INNODB CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

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

-- 3. Apply uniform collation to all database tables
-- First, convert all RADIUS tables to utf8mb4_general_ci
ALTER TABLE `radacct` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `radcheck` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `radgroupcheck` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `radgroupreply` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `radpostauth` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `radreply` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `radusergroup` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `nas` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `nasreload` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Now convert all other application tables to the same collation
-- This will ensure uniform collation across the entire database
ALTER TABLE `dictionary` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `tbl_appconfig` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `tbl_bandwidth` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `tbl_customers` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `tbl_logs` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `tbl_payment_gateway` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `tbl_plans` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `tbl_pool` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `tbl_routers` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `tbl_transactions` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `tbl_user_recharges` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `tbl_users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `tbl_voucher` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `userinfo` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Fix specifically for the dictionary table which is causing join issues with radgroupcheck
-- This ensures the 'attribute' column used in the join has the same collation
ALTER TABLE `dictionary` 
  MODIFY COLUMN `attribute` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;

-- 4. Set database default collation to ensure new tables use the same collation
SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;
-- You should also run this command separately to change the database default:
-- ALTER DATABASE your_database_name CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Note: This script uses ALTER TABLE with IF NOT EXISTS which requires MySQL 8.0.16+
-- For older MySQL versions, you may need to use a different approach.
-- Also, if any table names above don't exist in your database, you may need to remove or adjust them.