<?php
// Expected variables from index.php:
// $customerModel: Customer
// $action: string (list|edit|add)
// $editCustomer: ?array
?>

<div class="page-header">
    <div>
        <h2>Customer Management</h2>
        <p class="subtitle">Manage customer records, contact information, and rental history.</p>
    </div>
    <?php if ($action !== 'add' && $action !== 'edit'): ?>
        <div>
            <a href="index.php?page=customers&action=add" class="btn btn-primary">+ Add New Customer</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <?php $isEdit = ($action === 'edit' && !empty($editCustomer)); ?>
    <section class="card form-card">
        <h3><?= $isEdit ? 'Edit Customer: ' . htmlspecialchars($editCustomer['name']) : 'Add New Customer' ?></h3>
        
        <form method="POST" action="index.php?page=customers" class="app-form">
            <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= (int)$editCustomer['id'] ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Full Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" required
                           value="<?= htmlspecialchars($isEdit ? $editCustomer['name'] : '') ?>"
                           placeholder="e.g. John Doe">
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required
                           value="<?= htmlspecialchars($isEdit ? $editCustomer['email'] : '') ?>"
                           placeholder="e.g. john@example.com">
                </div>

                <div class="form-group form-group-full">
                    <label for="phone">Phone Number <span class="required">*</span></label>
                    <input type="text" id="phone" name="phone" required
                           value="<?= htmlspecialchars($isEdit ? $editCustomer['phone'] : '') ?>"
                           placeholder="e.g. 08123456789">
                </div>

                <div class="form-group form-group-full">
                    <label for="address">Address <span class="required">*</span></label>
                    <textarea id="address" name="address" rows="3" required
                              placeholder="e.g. Jl. Melodi No. 12, Bandung"><?= htmlspecialchars($isEdit ? $editCustomer['address'] : '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update Customer' : 'Save Customer' ?></button>
                <a href="index.php?page=customers" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="card table-card">
    <div class="card-header">
        <h3>Customer Directory</h3>
        <span class="badge badge-info"><?= count($customers) ?> customer(s)</span>
    </div>

    <?php if (empty($customers)): ?>
        <div class="empty-state">
            <p>No customer records found.</p>
            <a href="index.php?page=customers&action=add" class="btn btn-primary">+ Add First Customer</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Active Loans</th>
                        <th style="width: 140px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                        <?php $activeLoans = $customerModel->getActiveLoansCount((int)$c['id']); ?>
                        <tr>
                            <td><?= (int)$c['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($c['name']) ?></strong>
                            </td>
                            <td>
                                <a href="mailto:<?= htmlspecialchars($c['email']) ?>"><?= htmlspecialchars($c['email']) ?></a>
                            </td>
                            <td><?= htmlspecialchars($c['phone']) ?></td>
                            <td><?= nl2br(htmlspecialchars($c['address'])) ?></td>
                            <td>
                                <?php if ($activeLoans > 0): ?>
                                    <span class="badge badge-warning"><?= $activeLoans ?> active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">0 active</span>
                                <?php endif; ?>
                            </td>
                            <td class="table-actions">
                                <a href="index.php?page=customers&action=edit&id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-edit" title="Edit Customer">
                                    Edit
                                </a>
                                <form method="POST" action="index.php?page=customers" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this customer?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-delete" title="Delete Customer">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
