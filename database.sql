-- database.sql
-- 创建图片评分系统所需的表

CREATE TABLE IF NOT EXISTS `images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `path` VARCHAR(255) NOT NULL,
  `elo` INT NOT NULL DEFAULT 1000,
  `wins` INT NOT NULL DEFAULT 0,
  `plays` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ip_votes` (
  `ip` VARCHAR(45) PRIMARY KEY,
  `last_vote` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- INSERT INTO `images` (`path`) VALUES ('img/sample1.jpg'),('img/sample2.jpg'),('img/sample3.jpg');
