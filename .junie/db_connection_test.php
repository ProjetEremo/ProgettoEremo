<?php
// Simple test to verify database connection
require_once __DIR__ . '/../eremo/src/public/api/db_config.php';

// If we reach this point without errors, the connection was successful
echo "Database connection test: ";
if ($conn && $conn->ping()) {
    echo "SUCCESS - Connected to database '{$dbname}' as user '{$user}'";
} else {
    echo "FAILED - Could not connect to database";
}

// Close the connection
$conn->close();
echo "\nTest completed.";
?>