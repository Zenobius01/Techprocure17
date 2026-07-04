<?php
/**
 * Check Table Structure
 * File: check_table_structure.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

echo "<h1>Check Table Structure</h1>";

try {
    $db = getDB();
    echo "<p style='color:green;'>✅ Database connected</p>";
    
    // Check users table columns
    $columns = $db->query("DESCRIBE users");
    echo "<h2>Users Table Columns:</h2>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($col = $columns->fetch()) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . $col['Default'] . "</td>";
        echo "<td>" . $col['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if username column exists
    $has_username = false;
    $columns->execute();
    while ($col = $columns->fetch()) {
        if ($col['Field'] === 'username') {
            $has_username = true;
        }
    }
    
    if (!$has_username) {
        echo "<p style='color:orange;'>⚠️ 'username' column does NOT exist in users table</p>";
        echo "<p>You may have 'name' or 'full_name' instead.</p>";
    }
    
    // Show actual data
    echo "<h2>Sample Users:</h2>";
    $users = $db->query("SELECT * FROM users LIMIT 5");
    if ($users->rowCount() > 0) {
        echo "<table border='1' cellpadding='8'>";
        echo "<tr>";
        $row = $users->fetch(PDO::FETCH_ASSOC);
        foreach (array_keys($row) as $col) {
            echo "<th>" . $col . "</th>";
        }
        echo "</tr>";
        // Reset and show data
        $users->execute();
        while ($user = $users->fetch()) {
            echo "<tr>";
            foreach ($user as $value) {
                echo "<td>" . htmlspecialchars(substr($value, 0, 30)) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>❌ No users found</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>