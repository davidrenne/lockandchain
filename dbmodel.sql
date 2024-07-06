        CREATE TABLE IF NOT EXISTS `Cards` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `number` INT NOT NULL,
            `color` VARCHAR(7) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        CREATE TABLE IF NOT EXISTS `PlayerHands` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `player_id` INT,
            `card_id` INT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        CREATE TABLE IF NOT EXISTS `CardPlacements` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `game_id` INT,
            `card_id` INT,
            `player_id` INT,
            `position` INT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        CREATE TABLE IF NOT EXISTS `Chains` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `player_id` INT,
            `start_position` INT,
            `end_position` INT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        CREATE TABLE IF NOT EXISTS `Locks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `player_id` INT,
            `start_position` INT,
            `end_position` INT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        CREATE TABLE IF NOT EXISTS `GameActions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `game_id` INT,
            `player_id` INT,
            `action_type` VARCHAR(50),
            `card_id` INT,
            `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
