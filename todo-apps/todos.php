<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
include 'db.php';
include 'utils.php';
$user_id = $_SESSION['user_id'];

// CRUD Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $desc = $_POST['description'] ?? '';
    $status = $_POST['status'];
    $priority = $_POST['priority'];
    $notifications = isset($_POST['notifications']) ? 1 : 0;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $tags = !empty($_POST['tags']) ? implode(',', array_map('trim', explode(',', $_POST['tags']))) : null;

    if ($_POST['action'] === 'add') {
        // include due_date and tags in INSERT
        $stmt = $pdo->prepare("INSERT INTO todos (user_id, category_id, title, description, status, priority, notifications, due_date, attachment, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $category_id, $title, $desc, $status, $priority, $notifications, $due_date, $attachment, $tags]);
        $result = ['success' => true, 'message' => 'Added'];
    } elseif ($_POST['action'] === 'edit') {
        $id = $_POST['id'];

        // FIX 1: Use statement, not $pdo
        $old_stmt = $pdo->prepare("SELECT status, notifications, attachment FROM todos WHERE id = ?");
        $old_stmt->execute([$id]);
        $old = $old_stmt->fetch();

        if ($status == 'completed' && $old['status'] != 'completed' && $notifications) {
            // FIX 2: Use statement for email
            $email_stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $email_stmt->execute([$user_id]);
            $email = $email_stmt->fetchColumn();
            simulate_email($email, "Task Done!", "Task '$title' completed!");
        }

        // include due_date and tags in UPDATE (preserve old attachment if needed)
        $stmt = $pdo->prepare("UPDATE todos SET category_id=?, title=?, description=?, status=?,
            priority=?, notifications=?, due_date=?, attachment=?, tags=? WHERE id=? AND user_id=?");
        $stmt->execute([
            $category_id, $title, $desc, $status, $priority, $notifications, $due_date,
            $attachment ?: ($old['attachment'] ?? ''), $tags, $id, $user_id
        ]);
        $result = ['success' => true, 'message' => 'Updated'];
    }
    header("Location: todos.php");
    exit;
}

// Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM todos WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    header("Location: todos.php");
    exit;
}

// Pagination & Filter
// === FIXED: PAGINATION & FILTER ===
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 5;
$offset = ($page - 1) * $limit;
$where = "WHERE t.user_id = ?";
$params = [$user_id];

if (!empty($_GET['search'])) {
    $s = "%" . trim($_GET['search']) . "%";
    $where .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $params[] = $s;
    $params[] = $s;
}
if (!empty($_GET['status'])) {
    $where .= " AND t.status = ?";
    $params[] = $_GET['status'];
}
if (!empty($_GET['category'])) {
    $where .= " AND t.category_id = ?";
    $params[] = (int)$_GET['category'];
}

// Count total
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM todos t $where");
$count_stmt->execute($params);
$total = $count_stmt->fetchColumn();
$pages = $total > 0 ? ceil($total / $limit) : 1;

// Main query with explicit INT binding
$sql = "
    SELECT t.*, c.name as cat_name 
    FROM todos t 
    LEFT JOIN categories c ON t.category_id = c.id 
    $where 
    ORDER BY t.created_at DESC 
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($sql);

// Bind parameters one by one with type
for ($i = 0; $i < count($params); $i++) {
    $stmt->bindValue($i + 1, $params[$i]);
}

// Bind LIMIT and OFFSET as integers
$stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);

$stmt->execute();
$todos = $stmt->fetchAll();

// Categories
$cat_stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
$cat_stmt->execute([$user_id]);
$cats = $cat_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TODOs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    .tag {
        font-size: 0.8rem;
        margin-right: 0.3rem;
    }
    .countdown {
        font-size: 0.9rem;
        font-weight: 500;
    }
    .countdown.text-danger {
        animation: blink 2s infinite;
    }
    @keyframes blink {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-5">
        <h3>My TODOs</h3>

        <!-- Filter Form -->
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control"
                       placeholder="Search..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="in_progress" <?= ($_GET['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="completed" <?= ($_GET['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($_GET['category'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-2">
                <a href="export_csv.php?<?= http_build_query([
                    'search' => $_GET['search'] ?? '',
                    'status' => $_GET['status'] ?? '',
                    'category' => $_GET['category'] ?? ''
                ]) ?>" class="btn btn-outline-secondary w-100">
                    Export CSV
                </a>
            </div>
        </form>

        <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addModal">+ Add Task</button>

        <!-- Tasks Table -->
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Tags</th>
                    <th>Status</th>
                    <th>Due Date</th>  <!-- Changed from "Due" to "Due Date" -->
                    <th>File</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($todos)): ?>
                    <tr><td colspan="6" class="text-center text-muted">No tasks found.</td></tr>
                <?php else: ?>
                    <?php foreach ($todos as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['title']) ?></td>
                        <td>
                            <?php
                            if (!empty($t['tags'])) {
                                $parts = array_filter(array_map('trim', explode(',', $t['tags'])));
                                foreach ($parts as $p) {
                                    echo '<span class="badge bg-secondary me-1">' . htmlspecialchars($p) . '</span>';
                                }
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= 
                                $t['status'] === 'completed' ? 'success' : 
                                ($t['status'] === 'in_progress' ? 'info' : 'warning') 
                            ?>">
                                <?= ucfirst(str_replace('_', ' ', $t['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($t['due_date'])): ?>
                                <span class="countdown" data-due="<?= htmlspecialchars($t['due_date']) ?>"></span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $t['attachment'] 
                                ? '<a href="' . htmlspecialchars($t['attachment']) . '" target="_blank">View</a>' 
                                : '—' 
                            ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" 
                                    onclick='editTodo(<?= json_encode($t) ?>)' 
                                    data-bs-toggle="modal" data-bs-target="#editModal">
                                Edit
                            </button>
                            <a href="?delete=<?= $t['id'] ?>" 
                               class="btn btn-sm btn-danger" 
                               onclick="return confirm('Delete this task?')">
                                Del
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <nav>
            <ul class="pagination">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query([
                            'search' => $_GET['search'] ?? '',
                            'status' => $_GET['status'] ?? '',
                            'category' => $_GET['category'] ?? ''
                        ]) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <?php include 'todo_form.php'; ?>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Save</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <?php include 'todo_form.php'; ?>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Update</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function editTodo(t) {
        document.getElementById('edit_id').value = t.id;
        document.querySelector('#editModal [name="title"]').value = t.title;
        document.querySelector('#editModal [name="description"]').value = t.description || '';
        document.querySelector('#editModal [name="status"]').value = t.status;
        document.querySelectorAll('#editModal [name="priority"]').forEach(r => {
            r.checked = r.value === t.priority;
        });
        document.querySelector('#editModal [name="notifications"]').checked = t.notifications == 1;
        document.querySelector('#editModal [name="due_date"]').value = t.due_date || '';
        document.querySelector('#editModal [name="category_id"]').value = t.category_id || '';
        // ADDED: populate tags field
        document.querySelector('#editModal [name="tags"]').value = t.tags || '';
    }

    function updateCountdowns() {
        document.querySelectorAll('.countdown').forEach(el => {
            const due = el.dataset.due;
            if (!due) return;
            
            const dueDate = new Date(due + 'T23:59:59');  // End of due date
            const now = new Date();
            const diff = dueDate - now;
            
            if (diff < 0) {
                // Overdue
                el.textContent = 'Overdue';
                el.className = 'countdown text-danger fw-bold';
            } else {
                // Calculate remaining time
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                
                let text = '';
                if (days > 0) text += `${days}d `;
                if (hours > 0) text += `${hours}h `;
                if (minutes > 0) text += `${minutes}m`;
                
                el.textContent = text || 'Less than 1m';
                
                // Style based on urgency
                if (days === 0) {
                    el.className = 'countdown text-danger';  // Due today
                } else if (days <= 3) {
                    el.className = 'countdown text-warning';  // Due soon
                } else {
                    el.className = 'countdown text-muted';  // Due later
                }
            }
        });
    }

    // Update every minute
    setInterval(updateCountdowns, 60000);
    updateCountdowns(); // Initial update
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>