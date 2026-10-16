ALTER TABLE users MODIFY role ENUM('customer','admin','employee') NOT NULL DEFAULT 'customer';
