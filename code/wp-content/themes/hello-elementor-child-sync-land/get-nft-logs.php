<?php
/**
 * Get NFT logs for NMKR support
 * Run via: wp eval-file get-nft-logs.php
 */

$log_dir = WP_CONTENT_DIR . '/nft-logs';
$log_file = $log_dir . '/nft-2026-03-13.log';

echo "=== NFT Mint Logs for NMKR Support ===\n\n";

if (!file_exists($log_file)) {
    echo "Log file not found at: $log_file\n";

    // Check if directory exists
    if (!is_dir($log_dir)) {
        echo "Log directory does not exist: $log_dir\n";
    } else {
        echo "Available log files:\n";
        $files = glob($log_dir . '/nft-*.log');
        foreach ($files as $file) {
            echo "  - " . basename($file) . "\n";
        }
    }
    exit(1);
}

echo "Log file: $log_file\n";
echo "Size: " . filesize($log_file) . " bytes\n";
echo "Modified: " . date('Y-m-d H:i:s', filemtime($log_file)) . "\n\n";

echo "=== LOG CONTENTS ===\n\n";
echo file_get_contents($log_file);
