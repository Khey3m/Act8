<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
include 'db.php';

$user_id = $_SESSION['user_id'];

// Build WHERE clause from filters
$where = "WHERE t.user_id = ?";
$params = [$user_id];

if (!empty($_GET['search'])) {
    $s = "%" . trim($_GET['search']) . "%";
    $where .= " AND (t.title LIKE ? OR t.description LIKE ? OR t.tags LIKE ?)";
    $params[] = $s;
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

// Get filtered todos with category names
$sql = "SELECT t.*, c.name as category_name 
        FROM todos t 
        LEFT JOIN categories c ON t.category_id = c.id 
        $where 
        ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=todos_export_' . date('Y-m-d_His') . '.csv');

// Create CSV
$output = fopen('php://output', 'w');

// Headers row
fputcsv($output, [
    'ID',
    'Title',
    'Description',
    'Status',
    'Priority',
    'Category',
    'Tags',
    'Due Date',
    'Created At',
    'Updated At'
]);

// Data rows
foreach ($todos as $todo) {
    fputcsv($output, [
        $todo['id'],
        $todo['title'],
        $todo['description'],
        $todo['status'],
        $todo['priority'],
        $todo['category_name'],
        $todo['tags'],
        $todo['due_date'],
        $todo['created_at'],
        $todo['updated_at']
    ]);
}

fclose($output);
exit;