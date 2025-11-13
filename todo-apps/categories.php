<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

include 'db.php';

$user_id = $_SESSION['user_id'];

// Initialize $categories safely
$categories = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty(trim($_POST['name'] ?? ''))) {
    $name = trim($_POST['name']);
    $stmt = $pdo->prepare("INSERT INTO categories (name, user_id) VALUES (?, ?)");
    $stmt->execute([$name, $user_id]);
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    header("Location: categories.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Categories</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            color: #333;
        }
        .container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-top: 2rem;
            margin-bottom: 2rem;
        }
        h3 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 1.5rem;
            font-size: 1.6rem;
        }
        .form-control {
            border-radius: 8px;
            padding: 0.65rem 1rem;
        }
        .btn-success {
            border-radius: 8px;
            padding: 0.65rem 1.5rem;
            font-weight: 500;
        }
        .table {
            margin-top: 1rem;
            border-radius: 10px;
            overflow: hidden;
        }
        .table thead {
            background-color: #4361ee;
            color: white;
        }
        .table th {
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table tbody tr:hover {
            background-color: #f1f3f5;
        }
        .btn-danger {
            border-radius: 6px;
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        .empty-state h5 {
            margin-top: 1rem;
            color: #495057;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <h3>Manage Categories</h3>

        <!-- Add Form -->
        <form method="POST" class="row g-3 mb-4 align-items-center">
            <div class="col-auto flex-grow-1">
                <input type="text" name="name" class="form-control" placeholder="New category name" required maxlength="50">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-success">Add</button>
            </div>
        </form>

        <!-- Categories Table -->
        <?php if (count($categories) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $i => $cat): ?>
                            <tr>
                                <td><strong><?php echo $i + 1; ?></strong></td>
                                <td><?php echo htmlspecialchars($cat['name']); ?></td>
                                <td>
                                    <a href="?delete=<?php echo $cat['id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Delete &quot;<?php echo htmlspecialchars($cat['name']); ?>&quot;?')">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 3rem; color: #dee2e6;">(empty folder icon)</div>
                <h5>No categories yet</h5>
                <p class="text-muted">Add your first category using the form above.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>