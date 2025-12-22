<?php

/**
 * SputnikIP Integration Test Script
 *
 * Usage:
 *   php test_sputnikip.php <device_url> <login:password>
 *   php test_sputnikip.php <device_url> <login> <password>
 *
 * Examples:
 *   php test_sputnikip.php http://192.168.1.100 admin:adminpass
 *   php test_sputnikip.php http://192.168.1.100 admin adminpass
 */

if ($argc < 3) {
    echo "Usage:\n";
    echo "  php test_sputnikip.php <device_url> <login:password>\n";
    echo "  php test_sputnikip.php <device_url> <login> <password>\n";
    echo "\nExamples:\n";
    echo "  php test_sputnikip.php http://192.168.1.100 admin:adminpass\n";
    echo "  php test_sputnikip.php http://192.168.1.100 admin adminpass\n";
    exit(1);
}

$deviceUrl = $argv[1];

// Support both formats: "login:password" or "login password"
if ($argc >= 4) {
    $credentials = $argv[2] . ':' . $argv[3];
} else {
    $credentials = $argv[2];
}

// Parse for display
$credParts = explode(':', $credentials, 2);
$login = $credParts[0];
$password = $credParts[1] ?? '';

echo "=== SputnikIP Integration Test ===\n\n";
echo "Device: $deviceUrl\n";
echo "Login: $login\n\n";

// Load SmartYard
require_once __DIR__ . '/server/utils/loader.php';

try {
    // Create domophone instance
    echo "[1] Creating domophone instance...\n";
    $domophone = loadDevice('domophone', 'sputnikip.json', $deviceUrl, $credentials);
    echo "    OK\n\n";

    // Test ping
    echo "[2] Testing ping (getSysinfo)...\n";
    $sysinfo = $domophone->getSysinfo();
    if (!empty($sysinfo)) {
        echo "    OK - Device ID: " . ($sysinfo['DeviceID'] ?? 'N/A') . "\n";
        echo "    Model: " . ($sysinfo['DeviceModel'] ?? 'N/A') . "\n";
        echo "    HW: " . ($sysinfo['HardwareVersion'] ?? 'N/A') . "\n";
        echo "    SW: " . ($sysinfo['SoftwareVersion'] ?? 'N/A') . "\n";
    } else {
        echo "    FAIL - Empty response\n";
    }
    echo "\n";

    // Test SIP config
    echo "[3] Getting SIP config...\n";
    $sip = $domophone->getSipConfig();
    echo "    Server: " . ($sip['server'] ?? 'N/A') . ":" . ($sip['port'] ?? 'N/A') . "\n";
    echo "    Login: " . ($sip['login'] ?? 'N/A') . "\n";
    echo "\n";

    // Test apartments
    echo "[4] Getting apartments...\n";
    $apartments = $domophone->getApartments();
    echo "    Found: " . count($apartments) . " apartments\n";
    if (count($apartments) > 0) {
        $first = reset($apartments);
        echo "    First: #" . $first['apartment'] . " - SIP: " . implode(',', $first['sipNumbers'] ?? []) . "\n";
    }
    echo "\n";

    // Test RFID keys
    echo "[5] Getting RFID keys...\n";
    $rfids = $domophone->getRfids();
    echo "    Found: " . count($rfids) . " keys\n";
    if (count($rfids) > 0) {
        $firstKey = array_key_first($rfids);
        echo "    First: $firstKey\n";
    }
    echo "\n";

    // Test audio levels
    echo "[6] Getting audio levels...\n";
    $audio = $domophone->getAudioLevels();
    echo "    System: " . ($audio[0] ?? 'N/A') . "\n";
    echo "    Mic: " . ($audio[1] ?? 'N/A') . "\n";
    echo "    Voice: " . ($audio[2] ?? 'N/A') . "\n";
    echo "    SIP: " . ($audio[3] ?? 'N/A') . "\n";
    echo "\n";

    // Test CMS model
    echo "[7] Getting CMS model...\n";
    $cms = $domophone->getCmsModel();
    echo "    Model: $cms\n";
    echo "\n";

    // Test CMS levels
    echo "[8] Getting CMS levels...\n";
    $levels = $domophone->getCmsLevels();
    echo "    Call threshold: " . ($levels[0] ?? 'N/A') . "\n";
    echo "    Door threshold: " . ($levels[1] ?? 'N/A') . "\n";
    echo "\n";

    // Test gate mode
    echo "[9] Getting gate mode...\n";
    $gateMode = $domophone->isGateModeEnabled();
    echo "    Enabled: " . ($gateMode ? 'Yes' : 'No') . "\n";
    echo "\n";

    // Test DTMF config
    echo "[10] Getting DTMF config...\n";
    $dtmf = $domophone->getDtmfConfig();
    echo "    Code1: " . ($dtmf['code1'] ?? 'N/A') . "\n";
    echo "\n";

    echo "=== All tests completed ===\n";
    echo "\n";
    echo "To test door opening (uncomment if ready):\n";
    echo "// \$domophone->openLock(0); // Main door\n";
    echo "// \$domophone->openLock(1); // Second door\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
