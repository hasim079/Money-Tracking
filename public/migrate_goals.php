<?php
// Database migration utility for adding 'goals' enhancements
require_once __DIR__ . "/../config/database.php";

// Add 'title' column to the goals table
try {
    $pdo->exec("ALTER TABLE goals ADD COLUMN title VARCHAR(255) NULL AFTER user_id");
    echo "Added title column.\n";
} catch (PDOException $e) {
    echo "title: " . $e->getMessage() . "\n";
}

// Add 'deadline' column to the goals table
try {
    $pdo->exec("ALTER TABLE goals ADD COLUMN deadline DATE NULL AFTER target_amount");
    echo "Added deadline column.\n";
} catch (PDOException $e) {
    echo "deadline: " . $e->getMessage() . "\n";
}
