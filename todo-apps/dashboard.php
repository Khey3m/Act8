<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

include 'db.php';

$user_id = $_SESSION['user_id'];

// Fetch user
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Count pending tasks
$pending = $pdo->prepare("SELECT COUNT(*) FROM todos WHERE user_id = ? AND status = 'pending'");
$pending->execute([$user_id]);
$pending_count = $pending->fetchColumn();

// Count overdue tasks
$overdue = $pdo->prepare("SELECT COUNT(*) FROM todos WHERE user_id = ? AND due_date < CURDATE() AND status != 'completed'");
$overdue->execute([$user_id]);
$overdue_count = $overdue->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            padding: 2.5rem;
            margin-top: 2rem;
            margin-bottom: 2rem;
        }

        h2 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 1.8rem;
            font-size: 1.8rem;
        }

        h2 strong {
            color: #4361ee;
        }

        .stats-card {
            border-radius: 14px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
        }

        .card-body {
            padding: 1.8rem;
            text-align: center;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 0.8rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-number {
            font-size: 2.8rem;
            font-weight: 700;
            margin: 0;
            line-height: 1;
        }

        .bg-warning {
            background: linear-gradient(135deg, #f4a261, #e76f51) !important;
        }

        .bg-danger {
            background: linear-gradient(135deg, #e63946, #c1121f) !important;
        }

        .btn-primary {
            background: #4361ee;
            border: none;
            border-radius: 12px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }

        .btn-primary:hover {
            background: #3a56d4;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
        }

        .mt-4 {
            text-align: center;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
                margin: 1rem;
            }
            h2 {
                font-size: 1.5rem;
            }
            .card-number {
                font-size: 2.2rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <h2>Welcome, <strong><?php echo htmlspecialchars($user['username']); ?></strong>!</h2>

        <div class="row g-4">
            <!-- Pending Tasks -->
            <div class="col-md-6">
                <div class="card text-white stats-card bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Pending Tasks</h5>
                        <h2 class="card-number"><?php echo $pending_count; ?></h2>
                    </div>
                </div>
            </div>

            <!-- Overdue Tasks -->
            <div class="col-md-6">
                <div class="card text-white stats-card bg-danger">
                    <div class="card-body">
                        <h5 class="card-title">Overdue</h5>
                        <h2 class="card-number"><?php echo $overdue_count; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-5 text-center">
            <a href="todos.php" class="btn btn-primary">
                Go to TODOs
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>