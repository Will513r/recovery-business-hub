<?php
// performance.php - helper for measuring page render time and enabling output buffering

// Start output buffering to send the page in one chunk (helps with compression)
ob_start();

// Record start time for performance measurement
$performance_start = microtime(true);

// Register a shutdown function to log the elapsed time after the script finishes
register_shutdown_function(function() use ($performance_start) {
    $elapsed = microtime(true) - $performance_start;
    // Only log slow pages; logging every request floods the error log
    if ($elapsed > 1.0) {
        error_log('Slow page render: ' . number_format($elapsed, 4) . 's ' . ($_SERVER['REQUEST_URI'] ?? ''));
    }
    // Flush the output buffer (if still active)
    if (ob_get_length()) {
        ob_end_flush();
    }
});
?>
