<?php
session_start();
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit; 
}

include 'db.php';
include 'utils.php';

$user_id = $_SESSION['user_id'];

// === CSRF TOKEN ===
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// === CATEGORIES ===
$cat_stmt = $pdo->prepare("SELECT id, name FROM categories WHERE user_id = ? ORDER BY name");
$cat_stmt->execute([$user_id]);
$categories = $cat_stmt->fetchAll(PDO::FETCH_KEY_PAIR); // id => name

// === FILE UPLOAD HANDLER ===
function handle_upload($file, $old_path = null) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return $old_path;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'txt']) || $file['size'] > 5_000_000) {
        return $old_path;
    }

    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $name = 'upload_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $path = $dir . '/' . $name;

    if (move_uploaded_file($file['tmp_name'], $path)) {
        if ($old_path && file_exists(__DIR__ . '/' . $old_path)) {
            @unlink(__DIR__ . '/' . $old_path);
        }
        return 'uploads/' . $name;
    }
    return $old_path;
}

// === ADD / EDIT TASK ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = "Invalid request.";
        header("Location: todos.php?" . http_build_query($_GET));
        exit;
    }

    $action = $_POST['action'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $desc = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? 'pending';
    $priority = $_POST['priority'] ?? 'medium';
    $notifications = isset($_POST['notifications']) ? 1 : 0;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $tags = !empty($_POST['tags']) ? implode(',', array_map('trim', explode(',', $_POST['tags']))) : null;

    if (empty($title)) {
        $_SESSION['flash'] = "Title is required.";
    } else {
        if ($action === 'add') {
            $attachment = handle_upload($_FILES['attachment'] ?? null);
            $stmt = $pdo->prepare("INSERT INTO todos (user_id, category_id, title, description, status, priority, notifications, due_date, attachment, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $category_id, $title, $desc, $status, $priority, $notifications, $due_date, $attachment, $tags]);
            $_SESSION['flash'] = "Task added successfully!";
        }

        elseif ($action === 'edit' && !empty($_POST['id'])) {
            $id = (int)$_POST['id'];

            $old_stmt = $pdo->prepare("SELECT status, notifications, attachment FROM todos WHERE id = ? AND user_id = ?");
            $old_stmt->execute([$id, $user_id]);
            $old = $old_stmt->fetch();

            if (!$old) {
                $_SESSION['flash'] = "Task not found.";
            } else {
                $attachment = handle_upload($_FILES['attachment'] ?? null, $old['attachment']);

                if ($status === 'completed' && $old['status'] !== 'completed' && $notifications) {
                    $email_stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
                    $email_stmt->execute([$user_id]);
                    $email = $email_stmt->fetchColumn();
                    if ($email) {
                        simulate_email($email, "Task Completed!", "Great job! '$title' is done.");
                    }
                }

                $stmt = $pdo->prepare("UPDATE todos SET category_id=?, title=?, description=?, status=?, priority=?, notifications=?, due_date=?, attachment=?, tags=? WHERE id=? AND user_id=?");
                $stmt->execute([$category_id, $title, $desc, $status, $priority, $notifications, $due_date, $attachment, $tags, $id, $user_id]);
                $_SESSION['flash'] = "Task updated!";
            }
        }
    }

    header("Location: todos.php?" . http_build_query($_GET));
    exit;
}

// === DELETE TASK ===
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT attachment FROM todos WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $file = $stmt->fetchColumn();
    if ($file && file_exists(__DIR__ . '/' . $file)) @unlink(__DIR__ . '/' . $file);

    $stmt = $pdo->prepare("DELETE FROM todos WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $_SESSION['flash'] = "Task deleted.";
    header("Location: todos.php?" . http_build_query($_GET));
    exit;
}

// === PAGINATION & FILTERING ===
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 5;
$offset = ($page - 1) * $limit;

$where = "WHERE t.user_id = ?";
$params = [$user_id];

if (!empty($_GET['search'])) {
    $s = "%" . trim($_GET['search']) . "%";
    $where .= " AND (t.title LIKE ? OR t.description LIKE ? OR t.tags LIKE ?)";
    $params[] = $s; $params[] = $s; $params[] = $s;
}
if (!empty($_GET['status']) && in_array($_GET['status'], ['pending', 'in_progress', 'completed'])) {
    $where .= " AND t.status = ?";
    $params[] = $_GET['status'];
}
if (!empty($_GET['category']) && isset($categories[(int)$_GET['category']])) {
    $where .= " AND t.category_id = ?";
    $params[] = (int)$_GET['category'];
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM todos t $where");
$count_stmt->execute($params);
$total = $count_stmt->fetchColumn();
$pages = max(1, ceil($total / $limit));

$sql = "SELECT t.*, c.name as cat_name FROM todos t LEFT JOIN categories c ON t.category_id = c.id $where ORDER BY t.created_at DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
foreach ($params as $i => $p) $stmt->bindValue($i + 1, $p);
$stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$todos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My TODOs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --success: #06d6a0;
            --warning: #f4a261;
            --danger: #e63946;
            --light: #f8f9fa;
        }
        body {
            background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 2rem;
            margin: 2rem auto;
        }
        h3 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        .filter-form .form-control, .filter-form .form-select {
            border-radius: 10px;
            padding: 0.6rem 1rem;
        }
        .btn-add {
            border-radius: 12px;
            padding: 0.7rem 1.5rem;
            font-weight: 500;
        }
        .task-card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }
        .task-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .task-title {
            font-weight: 600;
            color: #2c3e50;
            margin: 0 0 0.5rem;
        }
        .task-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .tag {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 50px;
        }
        .countdown {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .countdown.overdue {
            color: var(--danger);
            animation: blink 1.5s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        .priority-high { color: var(--danger); }
        .priority-medium { color: var(--warning); }
        .priority-low { color: var(--success); }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        .modal-header {
            background: var(--primary);
            color: white;
            border-radius: 14px 14px 0 0;
        }
        .modal-title i { margin-right: 0.5rem; }
        .form-control, .form-select {
            border-radius: 10px;
        }
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .btn-close-white {
            filter: invert(1);
        }
        @media (max-width: 768px) {
            .container { padding: 1.5rem; margin: 1rem; }
            .filter-form .col-md-2 { margin-top: 0.5rem; }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>My TODOs</h3>
            <button class="btn btn-success btn-add" data-bs-toggle="modal" data-bs-target="#addModal">
                Add Task
            </button>
        </div>

        <!-- Flash Message -->
        <?php if ($msg = $_SESSION['flash'] ?? ''): unset($_SESSION['flash']); ?>
            <div class="alert alert-<?= strpos($msg, 'deleted') !== false || strpos($msg, 'added') || strpos($msg, 'updated') ? 'success' : 'danger' ?> alert-dismissible fade show">
                <?= htmlspecialchars($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Form -->
        <form method="GET" class="row g-2 mb-4 filter-form">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search tasks..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="in_progress" <?= ($_GET['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="completed" <?= ($_GET['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $id => $name): ?>
                        <option value="<?= $id ?>" <?= ($_GET['category'] ?? '') == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-3">
                <a href="export_csv.php?<?= http_build_query($_GET) ?>" class="btn btn-outline-secondary w-100">
                    Export CSV
                </a>
            </div>
        </form>

        <!-- Tasks List -->
        <?php if (empty($todos)): ?>
            <div class="empty-state">
                <h5>No tasks found</h5>
                <p>Add your first task using the button above!</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($todos as $t): ?>
                    <div class="col-lg-6">
                        <div class="card task-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="task-title"><?= htmlspecialchars($t['title']) ?></h5>
                                    <span class="badge bg-<?= $t['status'] === 'completed' ? 'success' : ($t['status'] === 'in_progress' ? 'info' : 'warning') ?>">
                                        <?= ucfirst(str_replace('_', ' ', $t['status'])) ?>
                                    </span>
                                </div>

                                <?php if ($t['description']): ?>
                                    <p class="text-muted mb-2" style="font-size:0.9rem;">
                                        <?= nl2br(htmlspecialchars(substr($t['description'], 0, 120))) ?>
                                        <?= strlen($t['description']) > 120 ? '...' : '' ?>
                                    </p>
                                <?php endif; ?>

                                <div class="task-meta d-flex flex-wrap gap-2 align-items-center">
                                    <?php if ($t['cat_name']): ?>
                                        <span class="badge bg-light text-dark"><?= htmlspecialchars($t['cat_name']) ?></span>
                                    <?php endif; ?>

                                    <span class="priority-<?= $t['priority'] ?>">
                                        <?= ucfirst($t['priority']) ?>
                                    </span>

                                    <?php if ($t['due_date']): ?>
                                        <span class="countdown" data-due="<?= $t['due_date'] ?>"></span>
                                    <?php endif; ?>

                                    <?php if ($t['attachment']): ?>
                                        <a href="<?= htmlspecialchars($t['attachment']) ?>" target="_blank" class="text-decoration-none">
                                            File
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <?php if ($t['tags']): ?>
                                    <div class="mt-2">
                                        <?php foreach (array_filter(explode(',', $t['tags'])) as $tag): ?>
                                            <span class="badge tag bg-secondary"><?= htmlspecialchars(trim($tag)) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-3 text-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick='editTask(<?= json_encode($t) ?>)' data-bs-toggle="modal" data-bs-target="#editModal">
                                        Edit
                                    </button>
                                    <a href="?delete=<?= $t['id'] ?>&<?= http_build_query($_GET) ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Delete this task?')">
                                        Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Add Modal -->
    <?php 
    $modal_id = 'addModal';
    $form_action = 'add';
    $todo = null;
    include 'todo_form.php'; 
    ?>

    <!-- Edit Modal -->
    <?php 
    $modal_id = 'editModal';
    $form_action = 'edit';
    include 'todo_form.php'; 
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editTask(t) {
        document.getElementById('edit_id').value = t.id;
        document.querySelector('#editModal [name="title"]').value = t.title;
        document.querySelector('#editModal [name="description"]').value = t.description || '';
        document.querySelector('#editModal [name="status"]').value = t.status;
        document.querySelector('#editModal [name="priority"][value="' + t.priority + '"]').checked = true;
        document.querySelector('#editModal [name="notifications"]').checked = t.notifications == 1;
        document.querySelector('#editModal [name="due_date"]').value = t.due_date || '';
        document.querySelector('#editModal [name="category_id"]').value = t.category_id || '';
        document.querySelector('#editModal [name="tags"]').value = t.tags || '';
    }

    function updateCountdowns() {
        document.querySelectorAll('.countdown').forEach(el => {
            const due = el.dataset.due;
            if (!due) return;
            const dueDate = new Date(due + 'T23:59:59');
            const now = new Date();
            const diff = dueDate - now;

            if (diff < 0) {
                el.textContent = 'Overdue';
                el.className = 'countdown overdue';
            } else {
                const days = Math.floor(diff / 86400000);
                const hours = Math.floor((diff % 86400000) / 3600000);
                const mins = Math.floor((diff % 3600000) / 60000);
                let text = '';
                if (days) text += days + 'd ';
                if (hours) text += hours + 'h ';
                if (mins) text += mins + 'm';
                el.textContent = text.trim() || '<1m';
                el.className = 'countdown ' + (days === 0 ? 'text-danger' : (days <= 2 ? 'text-warning' : 'text-success'));
            }
        });
    }

    setInterval(updateCountdowns, 60000);
    updateCountdowns();
    </script>
</body>
</html>