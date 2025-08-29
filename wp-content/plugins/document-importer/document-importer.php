<?php
/**
 * Plugin Name: Document Importer CLI
 * Description: Import documents as WooCommerce products via WP-CLI.
 * Version: 1.0
 * Author: Your Name
 */

if ( defined('WP_CLI') && WP_CLI ) {
    add_action('plugins_loaded', function() {
        require_once __DIR__ . '/document-importer-cli.php';
    });
}

