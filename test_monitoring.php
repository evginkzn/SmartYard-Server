<?php

/**
 * Test monitoring status for SputnikIP
 */

require_once __DIR__ . '/server/utils/loader.php';

$url = $argv[1] ?? 'http://localhost:8080';
$credentials = $argv[2] ?? 'admin:adminpass';

echo "=== Monitoring Test ===\n\n";
echo "URL: $url\n";
echo "Credentials: $credentials\n\n";

// Test 1: Direct device connection
echo "[1] Testing direct device connection...\n";
try {
    $domophone = loadDevice('domophone', 'sputnikip.json', $url, $credentials);
    $sysinfo = $domophone->getSysinfo();
    echo "    OK - DeviceID: " . ($sysinfo['DeviceID'] ?? 'N/A') . "\n";
    echo "    Model: " . ($sysinfo['DeviceModel'] ?? 'N/A') . "\n";
    echo "    SW: " . ($sysinfo['SoftwareVersion'] ?? 'N/A') . "\n";
} catch (Exception $e) {
    echo "    FAIL: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 2: Simple monitoring backend
echo "[2] Testing simple monitoring backend...\n";
try {
    $monitoring = loadBackend('monitoring');
    echo "    Backend class: " . get_class($monitoring) . "\n";

    $testHost = [
        'hostId' => 999,
        'enabled' => true,
        'ip' => parse_url($url, PHP_URL_HOST),
        'url' => $url,
    ];

    $status = $monitoring->deviceStatus('domophone', $testHost);
    echo "    Status: " . json_encode($status) . "\n";
} catch (Exception $e) {
    echo "    FAIL: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: TCP ping directly
echo "[3] Testing TCP ping directly...\n";
$parsed = parse_url($url);
$host = $parsed['host'] ?? 'localhost';
$port = $parsed['port'] ?? 80;
echo "    Host: $host, Port: $port\n";

$fp = @stream_socket_client("$host:$port", $errno, $errstr, 2);
if ($fp) {
    fclose($fp);
    echo "    OK - TCP connection successful\n";
} else {
    echo "    FAIL - $errstr ($errno)\n";
}
echo "\n";

// Test 4: Batch status check
echo "[4] Testing batch devicesStatus...\n";
try {
    $hosts = [
        [
            'hostId' => 1,
            'enabled' => true,
            'ip' => $host,
            'url' => $url,
        ],
    ];

    $statuses = $monitoring->devicesStatus('domophone', $hosts);
    echo "    Result: " . json_encode($statuses, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "    FAIL: " . $e->getMessage() . "\n";
}

echo "\n=== Done ===\n";
