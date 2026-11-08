<?php

require_once __DIR__ . '/../config/db.php';

class Guitar {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? (new Database())->conn;
    }

    /**
     * Retrieve all guitars ordered by ID ascending.
     */
    public function getAllGuitars(): array {
        $stmt = $this->db->query("SELECT * FROM guitars ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve all guitars that currently have available stock.
     */
    public function getAvailableGuitars(): array {
        $stmt = $this->db->query("SELECT * FROM guitars WHERE available_stock > 0 ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve a specific guitar by its primary key ID.
     */
    public function getGuitarById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM guitars WHERE id = ?");
        $stmt->execute([$id]);
        $guitar = $stmt->fetch(PDO::FETCH_ASSOC);
        return $guitar ?: null;
    }

    /**
     * Create a new guitar inventory record.
     */
    public function createGuitar(array $data): int {
        $name = trim($data['name'] ?? '');
        $brand = trim($data['brand'] ?? '');
        $type = $data['type'] ?? 'Other';
        $conditionStatus = $data['condition_status'] ?? 'Good';
        $dailyRate = (float)($data['daily_rate'] ?? 0);
        $stock = max(0, (int)($data['stock'] ?? 0));
        $imagePath = !empty($data['image_path']) ? $data['image_path'] : null;

        if ($name === '' || $brand === '') {
            throw new InvalidArgumentException("Guitar name and brand are required.");
        }

        if ($dailyRate < 0) {
            throw new InvalidArgumentException("Daily rate cannot be negative.");
        }

        $availableStock = $stock;
        $availabilityStatus = ($availableStock > 0) ? 'Available' : 'Unavailable';

        $stmt = $this->db->prepare("
            INSERT INTO guitars 
                (name, brand, type, condition_status, daily_rate, stock, available_stock, availability_status, image_path)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $name,
            $brand,
            $type,
            $conditionStatus,
            $dailyRate,
            $stock,
            $availableStock,
            $availabilityStatus,
            $imagePath
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update an existing guitar record and keep availability consistent.
     */
    public function updateGuitar(int $id, array $data): bool {
        $guitar = $this->getGuitarById($id);
        if (!$guitar) {
            throw new InvalidArgumentException("Guitar with ID {$id} not found.");
        }

        $name = trim($data['name'] ?? $guitar['name']);
        $brand = trim($data['brand'] ?? $guitar['brand']);
        $type = $data['type'] ?? $guitar['type'];
        $conditionStatus = $data['condition_status'] ?? $guitar['condition_status'];
        $dailyRate = isset($data['daily_rate']) ? (float)$data['daily_rate'] : (float)$guitar['daily_rate'];
        $stock = isset($data['stock']) ? max(0, (int)$data['stock']) : (int)$guitar['stock'];

        if ($name === '' || $brand === '') {
            throw new InvalidArgumentException("Guitar name and brand are required.");
        }

        if ($dailyRate < 0) {
            throw new InvalidArgumentException("Daily rate cannot be negative.");
        }

        $activeLoans = $this->getActiveLoansCount($id);
        if ($stock < $activeLoans) {
            throw new InvalidArgumentException("Cannot reduce stock to {$stock}; {$activeLoans} unit(s) are currently on active rental loan.");
        }

        $availableStock = $stock - $activeLoans;
        $availabilityStatus = ($availableStock > 0) ? 'Available' : 'Unavailable';

        $imagePath = $guitar['image_path'];
        if (array_key_exists('image_path', $data) && $data['image_path'] !== null) {
            if ($imagePath && $imagePath !== $data['image_path']) {
                $this->deleteImageFile($imagePath);
            }
            $imagePath = $data['image_path'];
        }

        $stmt = $this->db->prepare("
            UPDATE guitars
            SET name = ?,
                brand = ?,
                type = ?,
                condition_status = ?,
                daily_rate = ?,
                stock = ?,
                available_stock = ?,
                availability_status = ?,
                image_path = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $name,
            $brand,
            $type,
            $conditionStatus,
            $dailyRate,
            $stock,
            $availableStock,
            $availabilityStatus,
            $imagePath,
            $id
        ]);
    }

    /**
     * Delete a guitar record, safely handling referential integrity and image cleanup.
     */
    public function deleteGuitar(int $id): bool {
        $guitar = $this->getGuitarById($id);
        if (!$guitar) {
            throw new InvalidArgumentException("Guitar with ID {$id} not found.");
        }

        $activeLoans = $this->getActiveLoansCount($id);
        if ($activeLoans > 0) {
            throw new RuntimeException("Cannot delete guitar: {$activeLoans} unit(s) are currently rented out. Please complete or return the loans first.");
        }

        $stmtLoans = $this->db->prepare("SELECT COUNT(*) FROM loans WHERE guitar_id = ?");
        $stmtLoans->execute([$id]);
        if ((int)$stmtLoans->fetchColumn() > 0) {
            throw new RuntimeException("Cannot delete guitar with rental history because database foreign key constraints protect past transaction records.");
        }

        $stmt = $this->db->prepare("DELETE FROM guitars WHERE id = ?");
        $deleted = $stmt->execute([$id]);

        if ($deleted && !empty($guitar['image_path'])) {
            $this->deleteImageFile($guitar['image_path']);
        }

        return $deleted;
    }

    /**
     * Get count of active loans for this guitar.
     */
    public function getActiveLoansCount(int $id): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM loans WHERE guitar_id = ? AND status IN ('Active', 'Overdue')");
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Synchronize guitar available stock and availability status based on total stock and active loans.
     */
    public function syncAvailability(int $id): bool {
        $guitar = $this->getGuitarById($id);
        if (!$guitar) {
            return false;
        }

        $activeLoans = $this->getActiveLoansCount($id);
        $availableStock = max(0, (int)$guitar['stock'] - $activeLoans);
        $availabilityStatus = ($availableStock > 0) ? 'Available' : 'Unavailable';

        $stmt = $this->db->prepare("
            UPDATE guitars 
            SET available_stock = ?, availability_status = ? 
            WHERE id = ?
        ");
        return $stmt->execute([$availableStock, $availabilityStatus, $id]);
    }

    /**
     * Process an uploaded guitar image, validating format and size, saving to assets/guitars/.
     */
    public function handleImageUpload(array $file): ?string {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("File upload failed with error code: " . $file['error']);
        }

        // Limit maximum size to 5MB
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new InvalidArgumentException("Guitar image exceeds the 5MB size limit.");
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!array_key_exists($mime, $allowedMimes)) {
            throw new InvalidArgumentException("Invalid file type ({$mime}). Only JPG, PNG, WEBP, and GIF images are allowed.");
        }

        $ext = $allowedMimes[$mime];
        $filename = 'guitar_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $targetDir = __DIR__ . '/../assets/guitars/';

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            if (!copy($file['tmp_name'], $targetPath)) {
                throw new RuntimeException("Failed to save uploaded image to destination directory.");
            }
        }

        return 'assets/guitars/' . $filename;
    }

    /**
     * Safely delete an image file under assets/guitars/ if no other guitar record is using it.
     */
    public function deleteImageFile(string $relativePath): void {
        $baseDir = realpath(__DIR__ . '/../assets/guitars');
        $fullPath = realpath(__DIR__ . '/../' . $relativePath);

        // Security check: ensure file is inside assets/guitars directory
        if ($baseDir && $fullPath && strpos($fullPath, $baseDir) === 0 && file_exists($fullPath)) {
            // Prevent deleting image if another guitar uses the same path
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM guitars WHERE image_path = ?");
            $stmt->execute([$relativePath]);
            $count = (int)$stmt->fetchColumn();

            if ($count <= 1) {
                @unlink($fullPath);
            }
        }
    }
}
