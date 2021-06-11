-- SPDX-FileCopyrightText: none
--
-- SPDX-License-Identifier: CC0-1.0


-- Upgrades for MySQL table structure
-- If you have an existing installation of b8, you can run these MySQL commands to upgrade your
-- table structure to the most recent version.
-- Currently this file is designed to do no harm even if you run it multiple times.
-- Below is a description when and why the changes were made.


-- Switched to collation "utf8mb4_bin" in June 2021.
-- It supports the full 4-Byte UTF-8 range, as opposed to the 3-Byte range with "utf8_bin" that was used before.
-- This way even complex emoticons can be stored, avoiding possible errors.
-- As some MySQL/MariaDB versions limit the index-size to 768 Byte, the varchar of the "token" field was
-- reduced to 190 to stay below 768 if 190 characters with 4-Byte are used.

ALTER TABLE `b8_wordlist` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
ALTER TABLE `b8_wordlist` CHANGE `token` `token` VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL;
