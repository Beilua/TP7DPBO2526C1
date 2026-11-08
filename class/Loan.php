<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Guitar.php';
require_once __DIR__ . '/Customer.php';

class Loan {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? (new Database())->conn;
    }

    /**
     * Retrieve all rental loans with joined guitar and customer details.
     */
    public function getAllLoans(): array {
        $sql = "
            SELECT 
                loans.*,
                guitars.name AS guitar_name,
                guitars.brand AS guitar_brand,
                guitars.type AS guitar_type,
                guitars.daily_rate,
                guitars.image_path AS guitar_image,
                customers.name AS customer_name,
                customers.email AS customer_email,
                customers.phone AS customer_phone
            FROM loans
            JOIN guitars ON loans.guitar_id = guitars.id
            JOIN customers ON loans.customer_id = customers.id
            ORDER BY loans.id DESC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve a specific loan by ID with joined details.
     */
    public function getLoanById(int $id): ?array {
        $sql = "
            SELECT 
                loans.*,
                guitars.name AS guitar_name,
                guitars.brand AS guitar_brand,
                guitars.type AS guitar_type,
                guitars.daily_rate,
                guitars.image_path AS guitar_image,
                customers.name AS customer_name,
                customers.email AS customer_email,
                customers.phone AS customer_phone
            FROM loans
            JOIN guitars ON loans.guitar_id = guitars.id
            JOIN customers ON loans.customer_id = customers.id
            WHERE loans.id = ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $loan = $stmt->fetch(PDO::FETCH_ASSOC);
        return $loan ?: null;
    }

    /**
     * Create a new rental transaction and atomically decrement available stock.
     */
    public function createLoan(array $data): int {
        $guitarId = (int)($data['guitar_id'] ?? 0);
        $customerId = (int)($data['customer_id'] ?? 0);
        $loanDate = trim($data['loan_date'] ?? date('Y-m-d'));
        $dueDate = trim($data['due_date'] ?? '');
        $totalCost = isset($data['total_cost']) && $data['total_cost'] !== '' ? (float)$data['total_cost'] : null;

        if ($guitarId <= 0 || $customerId <= 0) {
            throw new InvalidArgumentException("Please select a valid guitar and customer.");
        }

        if ($loanDate === '' || $dueDate === '') {
            throw new InvalidArgumentException("Loan date and due date are required.");
        }

        if (strtotime($dueDate) < strtotime($loanDate)) {
            throw new InvalidArgumentException("Due date cannot be earlier than the loan date.");
        }

        $guitarModel = new Guitar($this->db);
        $guitar = $guitarModel->getGuitarById($guitarId);
        if (!$guitar) {
            throw new InvalidArgumentException("Selected guitar does not exist.");
        }

        if ((int)$guitar['available_stock'] <= 0 || $guitar['availability_status'] !== 'Available') {
            throw new RuntimeException("Selected guitar is currently out of stock or unavailable.");
        }

        $customerModel = new Customer($this->db);
        $customer = $customerModel->getCustomerById($customerId);
        if (!$customer) {
            throw new InvalidArgumentException("Selected customer does not exist.");
        }

        // Calculate total rental cost if not manually specified
        if ($totalCost === null || $totalCost <= 0) {
            $days = max(1, (int)round((strtotime($dueDate) - strtotime($loanDate)) / 86400));
            $totalCost = $days * (float)$guitar['daily_rate'];
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO loans 
                    (guitar_id, customer_id, loan_date, due_date, return_date, status, total_cost)
                VALUES 
                    (?, ?, ?, ?, NULL, 'Active', ?)
            ");
            $stmt->execute([$guitarId, $customerId, $loanDate, $dueDate, $totalCost]);
            $loanId = (int)$this->db->lastInsertId();

            // Decrement available stock
            $stmtStock = $this->db->prepare("
                UPDATE guitars
                SET available_stock = available_stock - 1,
                    availability_status = CASE WHEN available_stock - 1 > 0 THEN 'Available' ELSE 'Unavailable' END
                WHERE id = ? AND available_stock > 0
            ");
            $stmtStock->execute([$guitarId]);

            if ($stmtStock->rowCount() === 0) {
                throw new RuntimeException("Failed to allocate guitar stock; guitar may have just become unavailable.");
            }

            $this->db->commit();
            return $loanId;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Process guitar return, marking transaction as returned and restoring inventory.
     */
    public function returnLoan(int $id, ?string $returnDate = null): bool {
        $loan = $this->getLoanById($id);
        if (!$loan) {
            throw new InvalidArgumentException("Loan transaction with ID {$id} not found.");
        }

        if ($loan['status'] === 'Returned') {
            throw new RuntimeException("This loan has already been marked as returned.");
        }

        $returnDate = !empty($returnDate) ? trim($returnDate) : date('Y-m-d');

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                UPDATE loans 
                SET return_date = ?, status = 'Returned'
                WHERE id = ?
            ");
            $stmt->execute([$returnDate, $id]);

            // Restore guitar available stock
            $stmtStock = $this->db->prepare("
                UPDATE guitars
                SET available_stock = available_stock + 1,
                    availability_status = 'Available'
                WHERE id = ?
            ");
            $stmtStock->execute([$loan['guitar_id']]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Update an existing loan transaction with proper inventory reconciliation.
     */
    public function updateLoan(int $id, array $data): bool {
        $loan = $this->getLoanById($id);
        if (!$loan) {
            throw new InvalidArgumentException("Loan transaction with ID {$id} not found.");
        }

        $guitarId = (int)($data['guitar_id'] ?? $loan['guitar_id']);
        $customerId = (int)($data['customer_id'] ?? $loan['customer_id']);
        $loanDate = trim($data['loan_date'] ?? $loan['loan_date']);
        $dueDate = trim($data['due_date'] ?? $loan['due_date']);
        $status = trim($data['status'] ?? $loan['status']);
        $returnDate = !empty($data['return_date']) ? trim($data['return_date']) : null;
        $totalCost = isset($data['total_cost']) && $data['total_cost'] !== '' ? (float)$data['total_cost'] : (float)$loan['total_cost'];

        if ($guitarId <= 0 || $customerId <= 0) {
            throw new InvalidArgumentException("Please select a valid guitar and customer.");
        }

        if (strtotime($dueDate) < strtotime($loanDate)) {
            throw new InvalidArgumentException("Due date cannot be earlier than loan date.");
        }

        $guitarModel = new Guitar($this->db);
        $targetGuitar = $guitarModel->getGuitarById($guitarId);
        if (!$targetGuitar) {
            throw new InvalidArgumentException("Target guitar does not exist.");
        }

        $customerModel = new Customer($this->db);
        if (!$customerModel->getCustomerById($customerId)) {
            throw new InvalidArgumentException("Target customer does not exist.");
        }

        // Handle return date semantics based on status
        if ($status === 'Returned') {
            if (empty($returnDate)) {
                $returnDate = date('Y-m-d');
            }
        } else {
            $returnDate = null;
        }

        // Inventory state transition
        $oldGuitarId = (int)$loan['guitar_id'];
        $oldHolding = ($loan['status'] !== 'Returned');
        $newHolding = ($status !== 'Returned');

        $this->db->beginTransaction();
        try {
            if ($oldGuitarId === $guitarId) {
                if ($oldHolding && !$newHolding) {
                    // Transitioned from Active/Overdue to Returned -> return 1 stock
                    $stmtStock = $this->db->prepare("
                        UPDATE guitars 
                        SET available_stock = available_stock + 1, availability_status = 'Available'
                        WHERE id = ?
                    ");
                    $stmtStock->execute([$guitarId]);
                } elseif (!$oldHolding && $newHolding) {
                    // Transitioned from Returned back to Active/Overdue -> allocate 1 stock
                    $stmtStock = $this->db->prepare("
                        UPDATE guitars 
                        SET available_stock = available_stock - 1,
                            availability_status = CASE WHEN available_stock - 1 > 0 THEN 'Available' ELSE 'Unavailable' END
                        WHERE id = ? AND available_stock > 0
                    ");
                    $stmtStock->execute([$guitarId]);
                    if ($stmtStock->rowCount() === 0) {
                        throw new RuntimeException("Cannot reactivate loan: guitar has no available stock.");
                    }
                }
            } else {
                // Guitar was changed in this loan
                if ($oldHolding) {
                    // Release stock on previous guitar
                    $stmtOld = $this->db->prepare("
                        UPDATE guitars 
                        SET available_stock = available_stock + 1, availability_status = 'Available'
                        WHERE id = ?
                    ");
                    $stmtOld->execute([$oldGuitarId]);
                }

                if ($newHolding) {
                    // Claim stock on new guitar
                    $stmtNew = $this->db->prepare("
                        UPDATE guitars 
                        SET available_stock = available_stock - 1,
                            availability_status = CASE WHEN available_stock - 1 > 0 THEN 'Available' ELSE 'Unavailable' END
                        WHERE id = ? AND available_stock > 0
                    ");
                    $stmtNew->execute([$guitarId]);
                    if ($stmtNew->rowCount() === 0) {
                        throw new RuntimeException("Cannot switch to new guitar: guitar has no available stock.");
                    }
                }
            }

            $stmt = $this->db->prepare("
                UPDATE loans
                SET guitar_id = ?,
                    customer_id = ?,
                    loan_date = ?,
                    due_date = ?,
                    return_date = ?,
                    status = ?,
                    total_cost = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $guitarId,
                $customerId,
                $loanDate,
                $dueDate,
                $returnDate,
                $status,
                $totalCost,
                $id
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Delete a loan record, safely returning allocated stock if the loan was active.
     */
    public function deleteLoan(int $id): bool {
        $loan = $this->getLoanById($id);
        if (!$loan) {
            throw new InvalidArgumentException("Loan transaction with ID {$id} not found.");
        }

        $this->db->beginTransaction();
        try {
            // If loan was holding stock, restore it to inventory
            if ($loan['status'] !== 'Returned') {
                $stmtStock = $this->db->prepare("
                    UPDATE guitars
                    SET available_stock = available_stock + 1,
                        availability_status = 'Available'
                    WHERE id = ?
                ");
                $stmtStock->execute([$loan['guitar_id']]);
            }

            $stmt = $this->db->prepare("DELETE FROM loans WHERE id = ?");
            $deleted = $stmt->execute([$id]);

            $this->db->commit();
            return $deleted;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Automatically update active loans that passed their due date to 'Overdue'.
     */
    public function checkAndUpdateOverdueLoans(): int {
        $today = date('Y-m-d');
        $stmt = $this->db->prepare("
            UPDATE loans 
            SET status = 'Overdue' 
            WHERE status = 'Active' AND due_date < ?
        ");
        $stmt->execute([$today]);
        return $stmt->rowCount();
    }
}
