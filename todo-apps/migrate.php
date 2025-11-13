<?php
include 'db.php';

try {
    // check if column exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS 
                          WHERE TABLE_SCHEMA = DATABASE() 
                          AND TABLE_NAME = 'todos' 
                          AND COLUMN_NAME = 'tags'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("ALTER TABLE todos ADD COLUMN tags VARCHAR(255) DEFAULT NULL");
        echo "Column 'tags' added.\n";
    } else {
        echo "Column 'tags' already exists.\n";
    }
} catch (PDOException $e) {
    echo "Migration error: " . $e->getMessage();
}