CREATE DATABASE IF NOT EXISTS db_guitar_loan
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE db_guitar_loan;

-- drop tables first so the file can be re-imported safely
DROP TABLE IF EXISTS loans;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS guitars;

CREATE TABLE guitars (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    brand VARCHAR(100) NOT NULL,
    type ENUM('Acoustic', 'Electric', 'Bass', 'Classical', 'Other') NOT NULL DEFAULT 'Other',
    condition_status ENUM('Excellent', 'Good', 'Fair', 'Needs Maintenance') NOT NULL DEFAULT 'Good',
    daily_rate DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    available_stock INT UNSIGNED NOT NULL DEFAULT 0,
    availability_status ENUM('Available', 'Unavailable') NOT NULL DEFAULT 'Available',
    image_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_guitars_daily_rate CHECK (daily_rate >= 0),
    CONSTRAINT chk_guitars_available_stock CHECK (available_stock <= stock)
) ENGINE=InnoDB;

CREATE TABLE customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE loans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guitar_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    loan_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE DEFAULT NULL,
    status ENUM('Active', 'Returned', 'Overdue') NOT NULL DEFAULT 'Active',
    total_cost DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_loans_guitar
        FOREIGN KEY (guitar_id) REFERENCES guitars (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_loans_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_loans_due_date CHECK (due_date >= loan_date),
    CONSTRAINT chk_loans_total_cost CHECK (total_cost >= 0)
) ENGINE=InnoDB;

INSERT INTO guitars
    (name, brand, type, condition_status, daily_rate, stock, available_stock, availability_status, image_path)
VALUES
    ('NDX 20 Acoustic', 'Cort', 'Acoustic', 'Good', 75000.00, 3, 3, 'Available', 'assets/guitars/cort-d20-acoustic.png'),
    ('RG421 Electric', 'Ibanez', 'Electric', 'Excellent', 125000.00, 2, 2, 'Available', 'assets/guitars/ibanez-rg421-electric.png'),
    ('TRBX174 Bass', 'Yamaha', 'Bass', 'Good', 100000.00, 2, 2, 'Available', 'assets/guitars/yamaha-trbx174-bass.png'),
    ('CG122MS Classical', 'Yamaha', 'Classical', 'Fair', 60000.00, 1, 1, 'Available', 'assets/guitars/yamaha-cg122ms-classical.png'),
    ('Stratocaster', 'Fender', 'Electric', 'Excellent', 140000.00, 2, 2, 'Available', 'assets/guitars/fender-stratocaster-electric.png'),
    ('Les Paul Standard', 'Gibson', 'Electric', 'Good', 160000.00, 1, 1, 'Available', 'assets/guitars/gibson-les-paul-electric.png'),
    ('214ce Acoustic', 'Taylor', 'Acoustic', 'Excellent', 110000.00, 2, 2, 'Available', 'assets/guitars/taylor-214ce-acoustic.png'),
    ('D-28 Acoustic', 'Martin', 'Acoustic', 'Good', 130000.00, 1, 1, 'Available', 'assets/guitars/martin-d28-acoustic.png'),
    ('V7 Bass', 'Sire', 'Bass', 'Excellent', 115000.00, 1, 1, 'Available', 'assets/guitars/sire-v7-bass.png'),
    ('C5 Classical', 'Cordoba', 'Classical', 'Fair', 65000.00, 2, 2, 'Available', 'assets/guitars/cordoba-c5-classical.png'),
    ('GL1 Guitarlele', 'Yamaha', 'Other', 'Needs Maintenance', 30000.00, 1, 1, 'Available', 'assets/guitars/yamaha-gl1-guitarlele.png');

INSERT INTO customers
    (name, email, phone, address)
VALUES
    ('Hatsune Miku', 'miku@sekai.example.com', '081100000001', 'Crypthon Street No. 1, Shibuya'),
    ('Kanade Yoisaki', 'kanade@sekai.example.com', '081100000002', 'Evening Park No. 2, Shibuya'),
    ('Mafuyu Asahina', 'mafuyu@sekai.example.com', '081100000003', 'Winter Lane No. 3, Shibuya'),
    ('Ena Shinonome', 'ena@sekai.example.com', '081100000004', 'Canvas Road No. 4, Shibuya'),
    ('Mizuki Akiyama', 'mizuki@sekai.example.com', '081100000005', 'Rainbow Alley No. 5, Shibuya'),
    ('Hoshino Ichika', 'ichika@sekai.example.com', '081100000006', 'Star Stage No. 6, Shibuya'),
    ('Saki Tenma', 'saki@sekai.example.com', '081100000007', 'Sunny Garden No. 7, Shibuya'),
    ('Shiho Hinomori', 'shiho@sekai.example.com', '081100000008', 'Bass Line No. 8, Shibuya'),
    ('Kohane Azusawa', 'kohane@sekai.example.com', '081100000009', 'Wave Street No. 9, Shibuya'),
    ('Tsukasa Tenma', 'tsukasa@sekai.example.com', '081100000010', 'Wonder Stage No. 10, Shibuya');

INSERT INTO loans
    (guitar_id, customer_id, loan_date, due_date, return_date, status, total_cost)
VALUES
    (1, 1, '2026-11-08', '2026-11-11', NULL, 'Active', 225000.00),
    (2, 2, '2026-11-07', '2026-11-10', NULL, 'Active', 375000.00),
    (5, 3, '2026-11-09', '2026-11-12', NULL, 'Active', 420000.00),
    (1, 4, '2026-11-02', '2026-11-05', NULL, 'Overdue', 225000.00),
    (7, 5, '2026-11-01', '2026-11-04', NULL, 'Overdue', 330000.00),
    (6, 6, '2026-11-03', '2026-11-06', NULL, 'Overdue', 480000.00),
    (3, 2, '2026-10-20', '2026-10-23', '2026-10-23', 'Returned', 300000.00),
    (4, 1, '2026-10-25', '2026-10-27', '2026-10-26', 'Returned', 120000.00),
    (10, 7, '2026-10-28', '2026-10-31', '2026-10-31', 'Returned', 195000.00),
    (11, 8, '2026-10-30', '2026-11-02', '2026-11-02', 'Returned', 90000.00);

UPDATE guitars
SET available_stock = stock - (
        SELECT COUNT(*)
        FROM loans
        WHERE loans.guitar_id = guitars.id
          AND loans.status IN ('Active', 'Overdue')
    ),
    availability_status = CASE
        WHEN stock - (
            SELECT COUNT(*)
            FROM loans
            WHERE loans.guitar_id = guitars.id
              AND loans.status IN ('Active', 'Overdue')
        ) > 0 THEN 'Available'
        ELSE 'Unavailable'
    END;
