<?php

require_once __DIR__ . '/../config/db.php';

class Customer {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? (new Database())->conn;
    }

    /**
     * Retrieve all customers ordered by ID descending.
     */
    public function getAllCustomers(): array {
        $stmt = $this->db->query("SELECT * FROM customers ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve a customer by their ID.
     */
    public function getCustomerById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        return $customer ?: null;
    }

    /**
     * Create a new customer record after validating inputs and email uniqueness.
     */
    public function createCustomer(array $data): int {
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $address = trim($data['address'] ?? '');

        if ($name === '') {
            throw new InvalidArgumentException("Customer name cannot be empty.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("A valid email address is required.");
        }

        if ($phone === '') {
            throw new InvalidArgumentException("Customer phone number is required.");
        }

        if ($address === '') {
            throw new InvalidArgumentException("Customer address is required.");
        }

        if ($this->emailExists($email)) {
            throw new InvalidArgumentException("Email '{$email}' is already registered to another customer.");
        }

        $stmt = $this->db->prepare("
            INSERT INTO customers (name, email, phone, address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$name, $email, $phone, $address]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update an existing customer record.
     */
    public function updateCustomer(int $id, array $data): bool {
        $customer = $this->getCustomerById($id);
        if (!$customer) {
            throw new InvalidArgumentException("Customer with ID {$id} not found.");
        }

        $name = trim($data['name'] ?? $customer['name']);
        $email = trim($data['email'] ?? $customer['email']);
        $phone = trim($data['phone'] ?? $customer['phone']);
        $address = trim($data['address'] ?? $customer['address']);

        if ($name === '') {
            throw new InvalidArgumentException("Customer name cannot be empty.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("A valid email address is required.");
        }

        if ($phone === '') {
            throw new InvalidArgumentException("Customer phone number is required.");
        }

        if ($address === '') {
            throw new InvalidArgumentException("Customer address is required.");
        }

        if ($this->emailExists($email, $id)) {
            throw new InvalidArgumentException("Email '{$email}' is already registered to another customer.");
        }

        $stmt = $this->db->prepare("
            UPDATE customers
            SET name = ?, email = ?, phone = ?, address = ?
            WHERE id = ?
        ");

        return $stmt->execute([$name, $email, $phone, $address, $id]);
    }

    /**
     * Delete a customer, protecting referential integrity if active loans or rental history exists.
     */
    public function deleteCustomer(int $id): bool {
        $customer = $this->getCustomerById($id);
        if (!$customer) {
            throw new InvalidArgumentException("Customer with ID {$id} not found.");
        }

        $activeLoans = $this->getActiveLoansCount($id);
        if ($activeLoans > 0) {
            throw new RuntimeException("Cannot delete customer: customer currently has {$activeLoans} active rental loan(s).");
        }

        $stmtLoans = $this->db->prepare("SELECT COUNT(*) FROM loans WHERE customer_id = ?");
        $stmtLoans->execute([$id]);
        if ((int)$stmtLoans->fetchColumn() > 0) {
            throw new RuntimeException("Cannot delete customer with past rental history due to database foreign key constraints.");
        }

        $stmt = $this->db->prepare("DELETE FROM customers WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Get count of active loans for this customer.
     */
    public function getActiveLoansCount(int $id): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM loans WHERE customer_id = ? AND status IN ('Active', 'Overdue')");
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Check if an email is already registered, optionally excluding a customer ID for updates.
     */
    public function emailExists(string $email, ?int $excludeId = null): bool {
        if ($excludeId !== null) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM customers WHERE email = ? AND id != ?");
            $stmt->execute([$email, $excludeId]);
        } else {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM customers WHERE email = ?");
            $stmt->execute([$email]);
        }
        return (int)$stmt->fetchColumn() > 0;
    }
}
