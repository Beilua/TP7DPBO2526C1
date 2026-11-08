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
            <div class="nav-container">
                <a href="index.php" class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>">
                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span>Dashboard</span>
                </a>
                <a href="index.php?page=guitars" class="nav-link <?= $page === 'guitars' ? 'active' : '' ?>">
                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>
                    <span>Guitars</span>
                </a>
                <a href="index.php?page=customers" class="nav-link <?= $page === 'customers' ? 'active' : '' ?>">
                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <span>Customers</span>
                </a>
                <a href="index.php?page=loans" class="nav-link <?= $page === 'loans' ? 'active' : '' ?>">
                    <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                    <span>Loans</span>
                </a>
            </div>
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
                        <div class="hero-content">
                            <h2>Welcome to Guitar Loan Management</h2>
                            <p>
                                Professional guitar rental tracking system. Easily manage inventory stock, customer directory,
                                and equipment loans with automatic availability and due date management.
                            </p>
                        </div>
                    </section>

                    <section class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-header">
                                <span class="stat-label">Total Guitar Models</span>
                                <div class="stat-icon-badge stat-icon-guitars">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>
                                </div>
                            </div>
                            <div class="stat-number"><?= $totalGuitars ?></div>
                            <div class="stat-subtext"><span class="subtext-pill"><?= $availableGuitars ?> available</span> in stock</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <span class="stat-label">Registered Customers</span>
                                <div class="stat-icon-badge stat-icon-customers">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                </div>
                            </div>
                            <div class="stat-number"><?= $totalCustomers ?></div>
                            <div class="stat-subtext">Active renters on file</div>
                        </div>
                        <div class="stat-card stat-card-highlight">
                            <div class="stat-header">
                                <span class="stat-label">Active Rentals</span>
                                <div class="stat-icon-badge stat-icon-rentals">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                </div>
                            </div>
                            <div class="stat-number"><?= $activeLoans ?></div>
                            <div class="stat-subtext">
                                <?php if ($overdueLoans > 0): ?>
                                    <span class="text-danger font-semibold">⚠️ <?= $overdueLoans ?> overdue</span>
                                <?php else: ?>
                                    <span class="text-success font-semibold">✓ All on schedule</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-header">
                                <span class="stat-label">Completed Returns</span>
                                <div class="stat-icon-badge stat-icon-returns">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                </div>
                            </div>
                            <div class="stat-number"><?= $returnedLoans ?></div>
                            <div class="stat-subtext">Total past loans returned</div>
                        </div>
                    </section>

                    <section class="dashboard-grid">
                        <a class="dashboard-card" href="index.php?page=guitars">
                            <div class="dashboard-card-top">
                                <div class="dashboard-card-icon">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>
                                </div>
                                <span class="card-badge">Catalog</span>
                            </div>
                            <h3>Manage Guitars</h3>
                            <p>Browse guitar catalog, upload model photos, and update rental rates and inventory stock.</p>
                            <span class="card-link">Go to Guitars <span class="arrow">&rarr;</span></span>
                        </a>
                        <a class="dashboard-card" href="index.php?page=customers">
                            <div class="dashboard-card-top">
                                <div class="dashboard-card-icon">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                </div>
                                <span class="card-badge">Directory</span>
                            </div>
                            <h3>Manage Customers</h3>
                            <p>Register new renters, view customer contact information, and audit active rentals.</p>
                            <span class="card-link">Go to Customers <span class="arrow">&rarr;</span></span>
                        </a>
                        <a class="dashboard-card" href="index.php?page=loans">
                            <div class="dashboard-card-top">
                                <div class="dashboard-card-icon">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                                </div>
                                <span class="card-badge">Transactions</span>
                            </div>
                            <h3>Manage Loans</h3>
                            <p>Create new rental transactions, track due dates, and process equipment returns.</p>
                            <span class="card-link">Go to Loans <span class="arrow">&rarr;</span></span>
                        </a>
                    </section>

                    <?php if (!empty($loans)): ?>
                        <section class="card table-card" style="margin-top: 32px;">
                            <div class="card-header">
                                <div class="card-header-title">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                    <h3>Recent Rental Transactions</h3>
                                </div>
                                <a href="index.php?page=loans" class="btn btn-sm btn-secondary">View All Loans &rarr;</a>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 70px;">ID</th>
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
                                                <td><span class="id-tag">#<?= (int)$recentLoan['id'] ?></span></td>
                                                <td>
                                                    <strong class="table-primary-text"><?= htmlspecialchars($recentLoan['guitar_name']) ?></strong>
                                                    <span class="text-muted">(<?= htmlspecialchars($recentLoan['guitar_brand']) ?>)</span>
                                                </td>
                                                <td><?= htmlspecialchars($recentLoan['customer_name']) ?></td>
                                                <td><?= htmlspecialchars($recentLoan['loan_date']) ?></td>
                                                <td><?= htmlspecialchars($recentLoan['due_date']) ?></td>
                                                <td>
                                                    <?php if ($recentLoan['status'] === 'Active'): ?>
                                                        <span class="badge badge-warning"><span class="badge-dot"></span>Active</span>
                                                    <?php elseif ($recentLoan['status'] === 'Returned'): ?>
                                                        <span class="badge badge-success"><span class="badge-dot"></span>Returned</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger"><span class="badge-dot"></span>Overdue</span>
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
