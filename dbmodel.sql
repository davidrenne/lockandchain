CREATE TABLE IF NOT EXISTS `Cards` (
    `card_id` INT AUTO_INCREMENT PRIMARY KEY,
    `card_type` VARCHAR(32) NOT NULL,
    `player_id` INT(10) UNSIGNED,
    `card_type_arg` INT NOT NULL,
    `card_location` VARCHAR(32) NOT NULL,
    `card_location_arg` INT NOT NULL,
    FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PlayerSelections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `player_id` INT(10) UNSIGNED,
  `card_id` INT NOT NULL,
  FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`),
  FOREIGN KEY (`card_id`) REFERENCES `Cards`(`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PlayerHands` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `player_id` INT(10) UNSIGNED,
    `card_id` INT,
    FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`),
    FOREIGN KEY (`card_id`) REFERENCES `Cards`(`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




            CREATE TABLE IF NOT EXISTS `CardPlacements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `game_id` INT,
    `card_id` INT,
    `player_id` INT(10) UNSIGNED,
    `position` INT,
    FOREIGN KEY (`card_id`) REFERENCES `Cards`(`card_id`),
    FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



CREATE TABLE IF NOT EXISTS `Chains` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `player_id` INT(10) UNSIGNED,
    `start_position` INT,
    `end_position` INT,
    FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




CREATE TABLE IF NOT EXISTS `Locks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `player_id` INT(10) UNSIGNED,
    `start_position` INT,
    `end_position` INT,
    FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


            CREATE TABLE IF NOT EXISTS `GameActions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `game_id` INT,
    `player_id` INT(10) UNSIGNED,
    `action_type` VARCHAR(50),
    `card_id` INT,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`),
    FOREIGN KEY (`card_id`) REFERENCES `Cards`(`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            
CREATE TABLE IF NOT EXISTS `game_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `section` VARCHAR(50) NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;