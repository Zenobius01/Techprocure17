<?php
/**
 * Check Users in Database
 * File: check_users.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

echo "<h1>Check Users</h1>";

try {
    $db = getDB();
    echo "<p style='color:green;'>✅ Database connected</p>";
    
    // Check users table
    $users = $db->query("SELECT id, username, email, user_type, status, password_hash FROM users");
    
    if ($users->rowCount() > 0) {
        echo "<h2>Users Found:</h2>";
        echo "<table border='1' cellpadding='8'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Type</th><th>Status</th><th>Password Hash</th></tr>";
        while ($user = $users->fetch()) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . htmlspecialchars($user['user_type']) . "</td>";
            echo "<td>" . htmlspecialchars($user['status']) . "</td>";
            echo "<td>" . substr($user['password_hash'], 0, 30) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red;'>❌ No users found in database!</p>";
        echo "<p>Run the create_test_user.php script to create users.</p>";
    }
    
    // Test password verification
    echo "<h2>Test Password Verification</h2>";
    $testUser = $db->query("SELECT email, password_hash FROM users LIMIT 1")->fetch();
    if ($testUser) {
        $testPassword = "demo123";
        echo "<p>Testing password: <strong>'demo123'</strong> for: <strong>" . $testUser['email'] . "</strong></p>";
        if (password_verify($testPassword, $testUser['password_hash'])) {
            echo "<p style='color:green;'>✅ Password verification SUCCESSFUL!</p>";
        } else {
            echo "<p style='color:red;'>❌ Password verification FAILED!</p>";
            // Generate new hash
            $newHash = password_hash('demo123', PASSWORD_DEFAULT);
            echo "<p>New hash for 'demo123': <code>" . $newHash . "</code></p>";
            echo "<p>Run this SQL to fix:</p>";
            echo "<pre>UPDATE users SET password_hash = '$newHash' WHERE email = '" . $testUser['email'] . "';</pre>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>