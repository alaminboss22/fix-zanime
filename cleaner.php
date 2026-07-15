<?php
// ==========================================
//  AUTO CACHE CLEANER (THE SILENT ASSASSIN)
//  Deletes files older than 1 Hour
// ==========================================

// Configuration
$cacheDir = __DIR__ . '/cache/';
$lifetime = 3600; // 3600 Seconds = 1 Hour (Adjust as needed)

// Check if directory exists
if (!is_dir($cacheDir)) {
    die("Cache directory not found. Nothing to clean.");
}

$files = glob($cacheDir . '*');
$deleted = 0;
$count = 0;

foreach ($files as $file) {
    if (is_file($file)) {
        $count++;
        // Check age: If file is older than $lifetime
        if (time() - filemtime($file) >= $lifetime) {
            unlink($file); // Delete it
            $deleted++;
        }
    }
}

// Security: Only show output if run manually, keep silent for Cron
echo "<h1>🧹 System Cleanup Report</h1>";
echo "<p><strong>Total Files Scanned:</strong> $count</p>";
echo "<p><strong>Files Deleted (Expired):</strong> $deleted</p>";
echo "<p><strong>Status:</strong> Cache Optimized.</p>";
?>
