<?php
// Expected variables from index.php:
// $guitarModel: Guitar
// $action: string (list|edit|add)
// $editGuitar: ?array
?>

<!-- guitar inventory page -->
<div class="page-header">
    <div>
        <h2>Guitar Inventory</h2>
        <p class="subtitle">Manage rentable guitars, specifications, stock, and photos.</p>
    </div>
    <?php if ($action !== 'add' && $action !== 'edit'): ?>
        <!-- show add button -->
        <div>
            <a href="index.php?page=guitars&action=add" class="btn btn-primary">+ Add New Guitar</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <!-- show guitar form -->

    <?php $isEdit = ($action === 'edit' && !empty($editGuitar)); ?>
    <section class="card form-card">
        <h3><?= $isEdit ? 'Edit Guitar: ' . htmlspecialchars($editGuitar['name']) : 'Add New Guitar' ?></h3>
        
        <form method="POST" action="index.php?page=guitars" enctype="multipart/form-data" class="app-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= (int)$editGuitar['id'] ?>">
            <?php endif; ?>

            <!-- show form fields -->
            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Guitar / Model Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" required
                           value="<?= htmlspecialchars($isEdit ? $editGuitar['name'] : '') ?>"
                           placeholder="e.g. RG421 Standard">
                </div>

                <div class="form-group">
                    <label for="brand">Brand / Manufacturer <span class="required">*</span></label>
                    <input type="text" id="brand" name="brand" required
                           value="<?= htmlspecialchars($isEdit ? $editGuitar['brand'] : '') ?>"
                           placeholder="e.g. Ibanez, Fender, Yamaha">
                </div>

                <div class="form-group">
                    <label for="type">Type <span class="required">*</span></label>
                    <select id="type" name="type" required>
                        <?php
                        $types = ['Acoustic', 'Electric', 'Bass', 'Classical', 'Other'];
                        $currentType = $isEdit ? $editGuitar['type'] : 'Acoustic';
                        foreach ($types as $type):
                        ?>
                            <option value="<?= $type ?>" <?= $currentType === $type ? 'selected' : '' ?>>
                                <?= $type ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="condition_status">Condition Status <span class="required">*</span></label>
                    <select id="condition_status" name="condition_status" required>
                        <?php
                        $conditions = ['Excellent', 'Good', 'Fair', 'Needs Maintenance'];
                        $currentCondition = $isEdit ? $editGuitar['condition_status'] : 'Good';
                        foreach ($conditions as $cond):
                        ?>
                            <option value="<?= $cond ?>" <?= $currentCondition === $cond ? 'selected' : '' ?>>
                                <?= $cond ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="daily_rate">Daily Rental Rate (IDR) <span class="required">*</span></label>
                    <input type="number" id="daily_rate" name="daily_rate" min="0" step="1000" required
                           value="<?= htmlspecialchars($isEdit ? (string)(int)$editGuitar['daily_rate'] : '75000') ?>"
                           placeholder="e.g. 75000">
                </div>

                <div class="form-group">
                    <label for="stock">Total Stock <span class="required">*</span></label>
                    <input type="number" id="stock" name="stock" min="0" required
                           value="<?= htmlspecialchars($isEdit ? (string)(int)$editGuitar['stock'] : '1') ?>"
                           placeholder="e.g. 2">
                </div>

                <div class="form-group form-group-full">
                    <label for="image">Guitar Image (JPG, PNG, WEBP, GIF - max 5MB)</label>
                    <input type="file" id="image" name="image" accept="image/png,image/jpeg,image/webp,image/gif">
                    <?php if ($isEdit && !empty($editGuitar['image_path'])): ?>
                        <div class="image-preview-box">
                            <p class="text-muted">Current image:</p>
                            <img src="<?= htmlspecialchars($editGuitar['image_path']) ?>" alt="Guitar Preview" class="preview-thumbnail">
                            <span class="preview-note">Leave empty to keep current image, or select a new file to replace it.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update Guitar' : 'Save Guitar' ?></button>
                <a href="index.php?page=guitars" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </section>
<?php endif; ?>

<!-- show guitar inventory table -->
<section class="card table-card">
    <div class="card-header">
        <div class="card-header-title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>
            <h3>Inventory Catalog</h3>
        </div>
        <span class="badge badge-info"><?= count($guitars) ?> guitar(s)</span>
    </div>

    <?php if (empty($guitars)): ?>
        <!-- show empty inventory message -->
        <div class="empty-state">
            <p>No guitars in inventory yet.</p>
            <a href="index.php?page=guitars&action=add" class="btn btn-primary">+ Add First Guitar</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 65px;">ID</th>
                        <th style="width: 80px;">Photo</th>
                        <th>Name & Brand</th>
                        <th>Type</th>
                        <th>Condition</th>
                        <th>Daily Rate</th>
                        <th>Available / Stock</th>
                        <th>Status</th>
                        <th style="width: 140px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- list guitar records -->
                    <?php foreach ($guitars as $g): ?>
                        <tr>
                            <td><span class="id-tag">#<?= (int)$g['id'] ?></span></td>
                            <td>
                                <?php if (!empty($g['image_path']) && is_file(__DIR__ . '/../' . $g['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($g['image_path']) ?>" alt="<?= htmlspecialchars($g['name']) ?>" class="table-thumbnail">
                                <?php else: ?>
                                    <div class="no-photo-badge">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                        <span>No Photo</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong class="table-primary-text"><?= htmlspecialchars($g['name']) ?></strong>
                                <div class="text-muted"><?= htmlspecialchars($g['brand']) ?></div>
                            </td>
                            <td><span class="type-pill"><?= htmlspecialchars($g['type']) ?></span></td>
                            <td>
                                <span class="badge badge-condition"><?= htmlspecialchars($g['condition_status']) ?></span>
                            </td>
                            <td><strong class="rate-text">Rp <?= number_format((float)$g['daily_rate'], 0, ',', '.') ?></strong></td>
                            <td>
                                <span class="stock-indicator"><strong><?= (int)$g['available_stock'] ?></strong> / <?= (int)$g['stock'] ?></span>
                            </td>
                            <td>
                                <?php if ($g['availability_status'] === 'Available'): ?>
                                    <span class="badge badge-success"><span class="badge-dot"></span>Available</span>
                                <?php else: ?>
                                    <span class="badge badge-danger"><span class="badge-dot"></span>Unavailable</span>
                                <?php endif; ?>
                            </td>
                            <td class="table-actions">
                                <a href="index.php?page=guitars&action=edit&id=<?= (int)$g['id'] ?>" class="btn btn-sm btn-edit" title="Edit Guitar">
                                    Edit
                                </a>
                                <form method="POST" action="index.php?page=guitars" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this guitar?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-delete" title="Delete Guitar">
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
