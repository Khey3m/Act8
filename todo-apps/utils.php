<?php

function simulate_email($to, $subject, $message)
{
    $log = "To: $to\nSubject: $subject\nMessage: $message\n\n";
    file_put_contents('email_log.txt', $log, FILE_APPEND | LOCK_EX);
}

function run_migration($filename)
{
    global $pdo;

    $migrationsDir = __DIR__ . '/migrations';
    $path = $migrationsDir . '/' . $filename;

    if (!is_dir($migrationsDir)) {
        if (!@mkdir($migrationsDir, 0755, true)) {
            echo "Failed to create migrations directory: {$migrationsDir}";
            return false;
        }
    }

    if (!is_readable($path)) {
        echo "Migration file not found: {$path}";
        return false;
    }

    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        echo "Migration file is empty: {$path}";
        return false;
    }

    try {
        $pdo->exec($sql);
        echo "Migration {$filename} applied successfully.";
        return true;
    } catch (PDOException $e) {
        echo "Migration failed: " . $e->getMessage();
        return false;
    }
}
?>
