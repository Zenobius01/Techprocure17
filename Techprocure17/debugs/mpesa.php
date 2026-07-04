<?php
require_once '../includes/functions.php';

echo "<h1>🧪 M-Pesa Simulation Test</h1>";

// Check if simulation mode is active
echo "<h3>🔬 Simulation Mode: " . (MPESA_SIMULATION_MODE ? '✅ ACTIVE' : '❌ INACTIVE') . "</h3>";

// Test 1: Get Config
echo "<h3>📋 Current Configuration:</h3>";
echo "<pre>";
print_r(getMpesaConfig());
echo "</pre>";

// Test 2: Send STK Push
echo "<h3>📱 Sending STK Push...</h3>";
$phone = '255760211221';
$amount = 1000;
$reference = 'TEST-' . time();

echo "Phone: $phone<br>";
echo "Amount: $amount<br>";
echo "Reference: $reference<br><br>";

$result = mpesaStkPush($phone, $amount, $reference, 'Test Payment');

echo "<pre>";
print_r($result);
echo "</pre>";

// Test 3: Query Status
if (isset($result['checkoutRequestID'])) {
    echo "<h3>🔍 Querying Transaction Status...</h3>";
    $status = mpesaQueryStatus($result['checkoutRequestID']);
    echo "<pre>";
    print_r($status);
    echo "</pre>";
}

// Show how to switch modes
echo "<hr>";
echo "<h3>⚙️ How to Switch Modes:</h3>";
echo "<p>In <code>includes/functions.php</code>, change:</p>";
echo "<code>define('MPESA_SIMULATION_MODE', true);</code> → For simulation<br>";
echo "<code>define('MPESA_SIMULATION_MODE', false);</code> → For real API<br>";

echo "<h3>📚 Learning Resources:</h3>";
echo "<ul>";
echo "<li>✅ Payment flow works without real credentials</li>";
echo "<li>✅ Test different scenarios (success/failure)</li>";
echo "<li>✅ Understand the full payment lifecycle</li>";
echo "<li>✅ When ready, get real credentials from Safaricom</li>";
echo "</ul>";
?>