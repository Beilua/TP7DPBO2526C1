<?php
// Expected variables from index.php:
// $loanModel: Loan
// $guitarModel: Guitar
// $customerModel: Customer
// $action: string (list|edit|add)
// $editLoan: ?array
// $loans: array
?>

<div class="page-header">
    <div>
        <h2>Guitar Rental Loans</h2>
        <p class="subtitle">Manage rental transactions, schedules, guitar returns, and statuses.</p>
    </div>
    <?php if ($action !== 'add' && $action !== 'edit'): ?>
        <div>
            <a href="index.php?page=loans&action=add" class="btn btn-primary">+ Rent a Guitar (New Loan)</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <?php
    $isEdit = ($action === 'edit' && !empty($editLoan));
    $availableGuitars = $guitarModel->getAvailableGuitars();
    $allGuitars = $guitarModel->getAllGuitars();
    $allCustomers = $customerModel->getAllCustomers();
    ?>
    <section class="card form-card">
        <h3><?= $isEdit ? 'Edit Loan Transaction #' . (int)$editLoan['id'] : 'Create New Guitar Rental' ?></h3>

        <form method="POST" action="index.php?page=loans" class="app-form" id="loanForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= (int)$editLoan['id'] ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group form-group-full">
                    <label for="guitar_id">Select Guitar <span class="required">*</span></label>
                    <select id="guitar_id" name="guitar_id" required>
                        <option value="">-- Choose a Guitar --</option>
                        <?php if ($isEdit): ?>
                            <?php foreach ($allGuitars as $g): ?>
                                <option value="<?= (int)$g['id'] ?>"
                                        data-rate="<?= (float)$g['daily_rate'] ?>"
                                        <?= (int)$editLoan['guitar_id'] === (int)$g['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g['name']) ?> (<?= htmlspecialchars($g['brand']) ?>) &mdash;
                                    Rp <?= number_format((float)$g['daily_rate'], 0, ',', '.') ?>/day &mdash;
                                    Available: <?= (int)$g['available_stock'] ?>/<?= (int)$g['stock'] ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($availableGuitars as $g): ?>
                                <option value="<?= (int)$g['id'] ?>"
                                        data-rate="<?= (float)$g['daily_rate'] ?>">
                                    <?= htmlspecialchars($g['name']) ?> (<?= htmlspecialchars($g['brand']) ?> - <?= htmlspecialchars($g['type']) ?>) &mdash;
                                    Rp <?= number_format((float)$g['daily_rate'], 0, ',', '.') ?>/day &mdash;
                                    Available: <?= (int)$g['available_stock'] ?> unit(s)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if (!$isEdit && empty($availableGuitars)): ?>
                        <small class="text-danger">Notice: No guitars currently have available stock. Please add stock or return active loans first.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group form-group-full">
                    <label for="customer_id">Select Customer <span class="required">*</span></label>
                    <select id="customer_id" name="customer_id" required>
                        <option value="">-- Choose a Customer --</option>
                        <?php foreach ($allCustomers as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"
                                <?= ($isEdit && (int)$editLoan['customer_id'] === (int)$c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['email']) ?> &bull; <?= htmlspecialchars($c['phone']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="loan_date">Loan Date <span class="required">*</span></label>
                    <input type="date" id="loan_date" name="loan_date" required
                           value="<?= htmlspecialchars($isEdit ? $editLoan['loan_date'] : date('Y-m-d')) ?>">
                </div>

                <div class="form-group">
                    <label for="due_date">Due Date <span class="required">*</span></label>
                    <input type="date" id="due_date" name="due_date" required
                           value="<?= htmlspecialchars($isEdit ? $editLoan['due_date'] : date('Y-m-d', strtotime('+3 days'))) ?>">
                </div>

                <?php if ($isEdit): ?>
                    <div class="form-group">
                        <label for="return_date">Return Date (Optional)</label>
                        <input type="date" id="return_date" name="return_date"
                               value="<?= htmlspecialchars($editLoan['return_date'] ?? '') ?>">
                        <small class="text-muted">Setting status to Returned will automatically set today's date if left blank.</small>
                    </div>

                    <div class="form-group">
                        <label for="status">Loan Status <span class="required">*</span></label>
                        <select id="status" name="status" required>
                            <?php
                            $statuses = ['Active', 'Returned', 'Overdue'];
                            foreach ($statuses as $st):
                            ?>
                                <option value="<?= $st ?>" <?= $editLoan['status'] === $st ? 'selected' : '' ?>>
                                    <?= $st ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group form-group-full">
                    <label for="total_cost">Total Rental Cost (IDR)</label>
                    <input type="number" id="total_cost" name="total_cost" min="0" step="1000"
                           value="<?= htmlspecialchars($isEdit ? (string)(int)$editLoan['total_cost'] : '') ?>"
                           placeholder="Leave blank to calculate automatically (Daily Rate x Rental Days)">
                    <small class="text-muted" id="costHint">Auto-calculated if left blank based on guitar daily rate & rental duration.</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update Loan' : 'Confirm & Rent Guitar' ?></button>
                <a href="index.php?page=loans" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="card table-card">
    <div class="card-header">
        <div class="card-header-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            <h3>Rental Transactions</h3>
        </div>
        <span class="badge badge-info"><?= count($loans) ?> transaction(s)</span>
    </div>

    <?php if (empty($loans)): ?>
        <div class="empty-state">
            <p>No rental loans recorded yet.</p>
            <a href="index.php?page=loans&action=add" class="btn btn-primary">+ Create First Rental</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 65px;">ID</th>
                        <th>Guitar</th>
                        <th>Customer</th>
                        <th>Loan Date</th>
                        <th>Due Date</th>
                        <th>Return Date</th>
                        <th>Total Cost</th>
                        <th>Status</th>
                        <th style="width: 190px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loans as $l): ?>
                        <tr>
                            <td><span class="id-tag">#<?= (int)$l['id'] ?></span></td>
                            <td>
                                <div class="media-cell">
                                    <?php if (!empty($l['guitar_image']) && is_file(__DIR__ . '/../' . $l['guitar_image'])): ?>
                                        <img src="<?= htmlspecialchars($l['guitar_image']) ?>" alt="Guitar" class="table-thumbnail-sm">
                                    <?php endif; ?>
                                    <div>
                                        <strong class="table-primary-text"><?= htmlspecialchars($l['guitar_name']) ?></strong>
                                        <div class="text-muted"><?= htmlspecialchars($l['guitar_brand']) ?> &bull; <?= htmlspecialchars($l['guitar_type']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong class="table-primary-text"><?= htmlspecialchars($l['customer_name']) ?></strong>
                                <div class="text-muted"><?= htmlspecialchars($l['customer_phone']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($l['loan_date']) ?></td>
                            <td><?= htmlspecialchars($l['due_date']) ?></td>
                            <td>
                                <?php if (!empty($l['return_date'])): ?>
                                    <span class="return-date-badge"><?= htmlspecialchars($l['return_date']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong class="rate-text">Rp <?= number_format((float)$l['total_cost'], 0, ',', '.') ?></strong>
                            </td>
                            <td>
                                <?php if ($l['status'] === 'Active'): ?>
                                    <span class="badge badge-warning"><span class="badge-dot"></span>Active</span>
                                <?php elseif ($l['status'] === 'Returned'): ?>
                                    <span class="badge badge-success"><span class="badge-dot"></span>Returned</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><span class="badge-dot"></span>Overdue</span>
                                <?php endif; ?>
                            </td>
                            <td class="table-actions">
                                <?php if ($l['status'] !== 'Returned'): ?>
                                    <form method="POST" action="index.php?page=loans" style="display:inline;" onsubmit="return confirm('Process return for this guitar? This will restore stock availability.');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="return">
                                        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success" title="Mark as Returned">
                                            Return
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <a href="index.php?page=loans&action=edit&id=<?= (int)$l['id'] ?>" class="btn btn-sm btn-edit" title="Edit Loan">
                                    Edit
                                </a>

                                <form method="POST" action="index.php?page=loans" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this loan record?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-delete" title="Delete Loan">
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
