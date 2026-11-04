<?php
class Database {
    private string $host = 'localhost';
    private string $username = 'root';
    private string $password = '';
    private string $database = 'db_guitar_loan';
    public PDO $conn;

    public function __construct()
    {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4";

            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new PDOException(
                'Database connection failed: ' . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }
    }
}
