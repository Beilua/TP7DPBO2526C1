CREATE DATABASE IF NOT EXISTS db_guitar_loan
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE db_guitar_loan;

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
    ('CG122MS Classical', 'Yamaha', 'Classical', 'Fair', 60000.00, 1, 1, 'Available', 'assets/guitars/yamaha-cg122ms-classical.png');

INSERT INTO customers
    (name, email, phone, address)
VALUES
    ('Budi Santoso', 'budi@example.com', '081234567890', 'Jl. Merdeka No. 10'),
    ('Siti Rahma', 'siti@example.com', '082345678901', 'Jl. Melodi No. 25'),
    ('Andi Pratama', 'andi@example.com', '083456789012', 'Jl. Harmoni No. 7');

INSERT INTO loans
    (guitar_id, customer_id, loan_date, due_date, return_date, status, total_cost)
VALUES
    (1, 1, '2026-11-01', '2026-11-04', NULL, 'Active', 225000.00),
    (3, 2, '2026-10-20', '2026-10-23', '2026-10-23', 'Returned', 300000.00);

UPDATE guitars
SET available_stock = stock - (
        SELECT COUNT(*)
        FROM loans
        WHERE loans.guitar_id = guitars.id
          AND loans.status = 'Active'
    ),
    availability_status = CASE
        WHEN stock - (
            SELECT COUNT(*)
            FROM loans
            WHERE loans.guitar_id = guitars.id
              AND loans.status = 'Active'
        ) > 0 THEN 'Available'
        ELSE 'Unavailable'
    END;
