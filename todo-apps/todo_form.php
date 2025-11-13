
<?php
// todo_form.php - task modal form used for both Add and Edit
// Expects $todo (optional) and $cats from including page
$todo = $todo ?? [];
?>
<div class="modal-header">
    <h5 class="modal-title">Task</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
    <div class="mb-3">
        <label>Title *</label>
        <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($todo['title'] ?? ''); ?>">
    </div>

    <div class="mb-3">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($todo['description'] ?? ''); ?></textarea>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label>Status</label>
            <select name="status" class="form-select">
                <option value="pending" <?php echo (($todo['status'] ?? '') === 'pending') ? 'selected' : ''; ?>>Pending</option>
                <option value="in_progress" <?php echo (($todo['status'] ?? '') === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                <option value="completed" <?php echo (($todo['status'] ?? '') === 'completed') ? 'selected' : ''; ?>>Completed</option>
            </select>
        </div>

        <div class="col-md-6 mb-3">
            <label>Priority</label><br>
            <div class="form-check form-check-inline">
                <input type="radio" name="priority" value="low" class="form-check-input" <?php echo (($todo['priority'] ?? '') === 'low') ? 'checked' : ''; ?>>
                <label class="form-check-label">Low</label>
            </div>
            <div class="form-check form-check-inline">
                <input type="radio" name="priority" value="medium" class="form-check-input" <?php echo (($todo['priority'] ?? '') === 'medium' || empty($todo['priority'])) ? 'checked' : ''; ?>>
                <label class="form-check-label">Medium</label>
            </div>
            <div class="form-check form-check-inline">
                <input type="radio" name="priority" value="high" class="form-check-input" <?php echo (($todo['priority'] ?? '') === 'high') ? 'checked' : ''; ?>>
                <label class="form-check-label">High</label>
            </div>
        </div>
    </div>

    <div class="mb-3 form-check">
        <input type="checkbox" name="notifications" class="form-check-input" id="notifyCheckbox" <?php echo (!empty($todo['notifications'])) ? 'checked' : ''; ?>>
        <label class="form-check-label" for="notifyCheckbox">Email when done</label>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label>Due Date</label>
            <input type="date" name="due_date" class="form-control" value="<?php echo htmlspecialchars($todo['due_date'] ?? ''); ?>">
        </div>

        <div class="col-md-6 mb-3">
            <label>Category</label>
            <select name="category_id" class="form-select">
                <option value="">None</option>
                <?php
                if (!empty($cats) && is_array($cats)) {
                    foreach ($cats as $c) {
                        $sel = (isset($todo['category_id']) && $todo['category_id'] == $c['id']) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($c['id']) . "\" $sel>" . htmlspecialchars($c['name']) . "</option>";
                    }
                }
                ?>
            </select>
        </div>
    </div>

    <!-- Tags input -->
    <div class="mb-3">
        <label>Tags (comma separated)</label>
        <input type="text" name="tags" class="form-control" placeholder="work,urgent,home" value="<?php echo htmlspecialchars($todo['tags'] ?? ''); ?>">
    </div>

    <div class="mb-3">
        <label>Attachment</label>
        <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        <?php if (!empty($todo['attachment'])): ?>
            <small class="form-text text-muted">Current: <a href="<?php echo htmlspecialchars($todo['attachment']); ?>" target="_blank">View</a></small>
        <?php endif; ?>
    </div>
</div>