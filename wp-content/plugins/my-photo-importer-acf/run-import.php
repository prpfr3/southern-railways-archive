<?php
// Load WordPress core
require_once dirname(__DIR__, 3) . '/wp-load.php'; // Adjusts path to root

// Only allow running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Import your plugin's main import function
require_once __DIR__ . '/my-photo-importer-acf.php';

// Optional: disable output buffering
while (ob_get_level()) ob_end_clean();

// Run the import
echo "Starting import...\n";
myplugin_run_import();
echo "Import completed.\n";
