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
        <div class="card-header-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            <h3>Customer Directory</h3>
        </div>
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
                        <th style="width: 65px;">ID</th>
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
                            <td><span class="id-tag">#<?= (int)$c['id'] ?></span></td>
                            <td>
                                <strong class="table-primary-text"><?= htmlspecialchars($c['name']) ?></strong>
                            </td>
                            <td>
                                <a href="mailto:<?= htmlspecialchars($c['email']) ?>" class="table-link"><?= htmlspecialchars($c['email']) ?></a>
                            </td>
                            <td><span class="phone-text"><?= htmlspecialchars($c['phone']) ?></span></td>
                            <td><span class="address-text"><?= nl2br(htmlspecialchars($c['address'])) ?></span></td>
                            <td>
                                <?php if ($activeLoans > 0): ?>
                                    <span class="badge badge-warning"><span class="badge-dot"></span><?= $activeLoans ?> active</span>
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
