-- Messenger module schema for inventory_cao_v2
CREATE TABLE IF NOT EXISTS messenger_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) DEFAULT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    status ENUM('online','offline') DEFAULT 'offline',
    is_typing TINYINT(1) DEFAULT 0,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS messenger_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message_text TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0,
    FOREIGN KEY (sender_id) REFERENCES messenger_users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES messenger_users(id) ON DELETE CASCADE
);

-- Optional: seed examples
-- INSERT INTO messenger_users (username, email) VALUES ('alice','alice@example.com');
-- INSERT INTO messenger_users (username, email) VALUES ('bob','bob@example.com');
