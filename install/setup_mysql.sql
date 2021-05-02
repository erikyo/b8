-- SPDX-FileCopyrightText: none
--
-- SPDX-License-Identifier: CC0-1.0

CREATE TABLE `b8_wordlist` (
  `token` varchar(255) character set utf8 collate utf8_bin NOT NULL,
  `count_ham` int unsigned default NULL,
  `count_spam` int unsigned default NULL,
  PRIMARY KEY (`token`)
) DEFAULT CHARSET=utf8;

INSERT INTO `b8_wordlist` (`token`, `count_ham`) VALUES ('b8*dbversion', '3');
INSERT INTO `b8_wordlist` (`token`, `count_ham`, `count_spam`) VALUES ('b8*texts', '0', '0');
