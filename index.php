<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/class/Guitar.php';
require_once __DIR__ . '/class/Customer.php';
require_once __DIR__ . '/class/Loan.php';

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

$databaseConnected = false;
$databaseError = null;
$guitarModel = null;
$customerModel = null;
$loanModel = null;

try {
    $dbInstance = new Database();
    $connection = $dbInstance->conn;
    $databaseConnected = true;

    $guitarModel = new Guitar($connection);
    $customerModel = new Customer($connection);
    $loanModel = new Loan($connection);

    // Synchronize overdue loans status
    $loanModel->checkAndUpdateOverdueLoans();
} catch (PDOException $exception) {
    $databaseError = 'Database connection error: ' . $exception->getMessage();
}

// -------------------------------------------------------------
// POST Request Handling (CRUD Actions)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $databaseConnected) {
    $page = $_GET['page'] ?? 'dashboard';
    $postAction = $_POST['action'] ?? '';

    try {
        if ($page === 'guitars') {
            if ($postAction === 'create') {
                $imagePath = $guitarModel->handleImageUpload($_FILES['image'] ?? []);
                $_POST['image_path'] = $imagePath;
                $guitarModel->createGuitar($_POST);
                setFlash('success', 'Guitar added successfully to inventory.');
            } elseif ($postAction === 'update') {
                $id = (int)($_POST['id'] ?? 0);
                $imagePath = $guitarModel->handleImageUpload($_FILES['image'] ?? []);
                $data = $_POST;
                if ($imagePath !== null) {
                    $data['image_path'] = $imagePath;
                }
                $guitarModel->updateGuitar($id, $data);
                setFlash('success', 'Guitar inventory details updated successfully.');
            } elseif ($postAction === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $guitarModel->deleteGuitar($id);
                setFlash('success', 'Guitar record removed successfully.');
            }
            header('Location: index.php?page=guitars');
            exit;
        }

        if ($page === 'customers') {
            if ($postAction === 'create') {
                $customerModel->createCustomer($_POST);
                setFlash('success', 'Customer record registered successfully.');
            } elseif ($postAction === 'update') {
                $id = (int)($_POST['id'] ?? 0);
                $customerModel->updateCustomer($id, $_POST);
                setFlash('success', 'Customer details updated successfully.');
            } elseif ($postAction === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $customerModel->deleteCustomer($id);
                setFlash('success', 'Customer record deleted successfully.');
            }
            header('Location: index.php?page=customers');
            exit;
        }

        if ($page === 'loans') {
            if ($postAction === 'create') {
                $loanModel->createLoan($_POST);
                setFlash('success', 'Guitar rental loan created successfully.');
            } elseif ($postAction === 'return') {
                $id = (int)($_POST['id'] ?? 0);
                $loanModel->returnLoan($id);
                setFlash('success', 'Guitar marked as returned and inventory restored.');
            } elseif ($postAction === 'update') {
                $id = (int)($_POST['id'] ?? 0);
                $loanModel->updateLoan($id, $_POST);
                setFlash('success', 'Loan transaction updated successfully.');
            } elseif ($postAction === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $loanModel->deleteLoan($id);
                setFlash('success', 'Loan record removed successfully.');
            }
            header('Location: index.php?page=loans');
            exit;
        }
    } catch (Exception $e) {
        setFlash('danger', $e->getMessage());
        header('Location: index.php?page=' . urlencode($page));
        exit;
    }
}

// -------------------------------------------------------------
// GET Page & Routing Preparation
// -------------------------------------------------------------
$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'list';
$flash = getFlash();

$guitars = [];
$customers = [];
$loans = [];
$editGuitar = null;
$editCustomer = null;
$editLoan = null;

if ($databaseConnected) {
    if ($page === 'guitars') {
        $guitars = $guitarModel->getAllGuitars();
        if ($action === 'edit' && isset($_GET['id'])) {
            $editGuitar = $guitarModel->getGuitarById((int)$_GET['id']);
        }
    } elseif ($page === 'customers') {
        $customers = $customerModel->getAllCustomers();
        if ($action === 'edit' && isset($_GET['id'])) {
            $editCustomer = $customerModel->getCustomerById((int)$_GET['id']);
        }
    } elseif ($page === 'loans') {
        $loans = $loanModel->getAllLoans();
        if ($action === 'edit' && isset($_GET['id'])) {
            $editLoan = $loanModel->getLoanById((int)$_GET['id']);
        }
    } elseif ($page === 'dashboard') {
        $guitars = $guitarModel->getAllGuitars();
        $customers = $customerModel->getAllCustomers();
        $loans = $loanModel->getAllLoans();

        $totalGuitars = count($guitars);
        $availableGuitars = count(array_filter($guitars, fn($g) => (int)$g['available_stock'] > 0));
        $totalCustomers = count($customers);
        $activeLoans = count(array_filter($loans, fn($l) => $l['status'] === 'Active'));
        $overdueLoans = count(array_filter($loans, fn($l) => $l['status'] === 'Overdue'));
        $returnedLoans = count(array_filter($loans, fn($l) => $l['status'] === 'Returned'));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guitar Loan - Rental Management System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/view/header.php'; ?>

        <nav class="navigation" aria-label="Main navigation">
            <a href="index.php" class="<?= $page === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <a href="index.php?page=guitars" class="<?= $page === 'guitars' ? 'active' : '' ?>">Guitars</a>
            <a href="index.php?page=customers" class="<?= $page === 'customers' ? 'active' : '' ?>">Customers</a>
            <a href="index.php?page=loans" class="<?= $page === 'loans' ? 'active' : '' ?>">Loans</a>
        </nav>

        <main class="content">
            <?php if (!$databaseConnected): ?>
                <div class="alert alert-danger">
                    <strong>Connection Error:</strong> <?= htmlspecialchars($databaseError ?? 'Could not connect to database.', ENT_QUOTES, 'UTF-8') ?>
                    <p style="margin-top: 8px; margin-bottom: 0;">
                        Please ensure your MySQL service is running in XAMPP and that <code>database/db_guitar_loan.sql</code> has been imported into MySQL.
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($databaseConnected): ?>
                <?php if ($page === 'guitars'): ?>
                    <?php include __DIR__ . '/view/guitars.php'; ?>
                <?php elseif ($page === 'customers'): ?>
                    <?php include __DIR__ . '/view/customers.php'; ?>
                <?php elseif ($page === 'loans'): ?>
                    <?php include __DIR__ . '/view/loans.php'; ?>
                <?php else: ?>
                    <!-- Dashboard Home -->
                    <section class="hero">
                        <h2>Welcome to Guitar Loan Management</h2>
                        <p>
                            Professional guitar rental tracking system. Easily manage inventory stock, customer directory,
                            and equipment loans with automatic availability and due date management.
                        </p>
                    </section>

                    <section class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?= $totalGuitars ?></div>
                            <div class="stat-label">Total Guitar Models</div>
                            <div class="stat-subtext"><?= $availableGuitars ?> available in stock</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= $totalCustomers ?></div>
                            <div class="stat-label">Registered Customers</div>
                            <div class="stat-subtext">Active renters on file</div>
                        </div>
                        <div class="stat-card stat-card-highlight">
                            <div class="stat-number"><?= $activeLoans ?></div>
                            <div class="stat-label">Active Rentals</div>
                            <div class="stat-subtext">
                                <?php if ($overdueLoans > 0): ?>
                                    <span class="text-danger"><strong><?= $overdueLoans ?> overdue</strong></span>
                                <?php else: ?>
                                    <span>All on schedule</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= $returnedLoans ?></div>
                            <div class="stat-label">Completed Returns</div>
                            <div class="stat-subtext">Total past loans returned</div>
                        </div>
                    </section>

                    <section class="dashboard-grid">
                        <a class="dashboard-card" href="index.php?page=guitars">
                            <h3>Manage Guitars</h3>
                            <p>Browse guitar catalog, upload model photos, and update rental rates and inventory stock.</p>
                            <span class="card-link">&rarr; Go to Guitars</span>
                        </a>
                        <a class="dashboard-card" href="index.php?page=customers">
                            <h3>Manage Customers</h3>
                            <p>Register new renters, view customer contact information, and audit active rentals.</p>
                            <span class="card-link">&rarr; Go to Customers</span>
                        </a>
                        <a class="dashboard-card" href="index.php?page=loans">
                            <h3>Manage Loans</h3>
                            <p>Create new rental transactions, track due dates, and process equipment returns.</p>
                            <span class="card-link">&rarr; Go to Loans</span>
                        </a>
                    </section>

                    <?php if (!empty($loans)): ?>
                        <section class="card table-card" style="margin-top: 32px;">
                            <div class="card-header">
                                <h3>Recent Rental Transactions</h3>
                                <a href="index.php?page=loans" class="btn btn-sm btn-secondary">View All Loans</a>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Guitar</th>
                                            <th>Customer</th>
                                            <th>Loan Date</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($loans, 0, 5) as $recentLoan): ?>
                                            <tr>
                                                <td>#<?= (int)$recentLoan['id'] ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($recentLoan['guitar_name']) ?></strong>
                                                    <span class="text-muted">(<?= htmlspecialchars($recentLoan['guitar_brand']) ?>)</span>
                                                </td>
                                                <td><?= htmlspecialchars($recentLoan['customer_name']) ?></td>
                                                <td><?= htmlspecialchars($recentLoan['loan_date']) ?></td>
                                                <td><?= htmlspecialchars($recentLoan['due_date']) ?></td>
                                                <td>
                                                    <?php if ($recentLoan['status'] === 'Active'): ?>
                                                        <span class="badge badge-warning">Active</span>
                                                    <?php elseif ($recentLoan['status'] === 'Returned'): ?>
                                                        <span class="badge badge-success">Returned</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">Overdue</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </main>

        <?php include __DIR__ . '/view/footer.php'; ?>
    </div>
</body>
</html>
