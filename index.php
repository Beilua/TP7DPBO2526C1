<?php

require_once __DIR__ . '/config/db.php';

$databaseConnected = false;
$databaseError = null;

try {
    $connection = (new Database())->conn;
    $databaseConnected = true;
} catch (PDOException $exception) {
    $databaseError = 'Database connection failed. Check your MySQL service and configuration.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guitar Loan</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/view/header.php'; ?>

        <nav class="navigation" aria-label="Main navigation">
            <a href="index.php">Dashboard</a>
            <a href="?page=guitars">Guitars</a>
            <a href="?page=customers">Customers</a>
            <a href="?page=loans">Loans</a>
        </nav>

        <main class="content">
            <?php if ($databaseConnected): ?>
                <div class="alert alert-success">
                    Database connection is ready.
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($databaseError, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <section class="hero">
                <h2>Welcome to Guitar Loan</h2>
                <p>
                    Manage guitar inventory, customer information, and rental transactions
                    from one place.
                </p>
            </section>

            <section class="dashboard-grid">
                <a class="dashboard-card" href="?page=guitars">
                    <h3>Guitars</h3>
                    <p>Manage the guitar inventory and image information.</p>
                </a>
                <a class="dashboard-card" href="?page=customers">
                    <h3>Customers</h3>
                    <p>Manage customer records and contact information.</p>
                </a>
                <a class="dashboard-card" href="?page=loans">
                    <h3>Loans</h3>
                    <p>Manage rentals, due dates, returns, and loan status.</p>
                </a>
            </section>
        </main>

        <?php include __DIR__ . '/view/footer.php'; ?>
    </div>
</body>
</html>
