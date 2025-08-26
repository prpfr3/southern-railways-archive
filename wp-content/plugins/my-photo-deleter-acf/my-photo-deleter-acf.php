<?php
/**
 * Plugin Name: my-photo-deleter-acf
 * Description: A plugin to delete WooCommerce products with ACF fields from a CSV.
 * Version: 1.0
 * Author: Paul Frost
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Add the plugin to the WordPress admin menu
add_action('admin_menu', function () {
    add_submenu_page(
        'tools.php', // Parent menu
        'Delete Photo Products', // Page title
        'Delete Photo Products', // Menu title
        'manage_options', // Capability
        'myplugin-delete-data', // Menu slug
        'myplugin_delete_page' // Callback function
    );
});

// Render the admin page
function myplugin_delete_page() {
    ?>
    <div class="wrap">
        <h1>delete Photo Products</h1>
        <p>Click the button below to start the delete process.</p>
        
        <form method="post">
            <input type="submit" name="myplugin_delete_start" class="button button-primary" value="Run delete">
        </form>

        <?php
        // If the form is submitted, run the delete function
        if (isset($_POST['myplugin_delete_start'])) {
            myplugin_run_delete();
        }
        ?>
    </div>
    <?php
}

// Function to delete all WooCommerce products and variations
function myplugin_run_delete() {
    $deleted = 0;

    // Get all product IDs (including parents and variations)
    $args = [
        'post_type'      => ['product', 'product_variation'],
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ];
    
    $products = get_posts($args);

    if (!empty($products)) {
        global $wpdb;

        foreach ($products as $product_id) {
            // Delete variations linked to the product
            $variation_ids = get_posts([
                'post_type'      => 'product_variation',
                'posts_per_page' => -1,
                'post_parent'    => $product_id,
                'fields'         => 'ids',
            ]);

            foreach ($variation_ids as $variation_id) {
                wp_delete_post($variation_id, true); // Delete variation
            }

            // Delete parent product
            wp_delete_post($product_id, true); // Permanently delete product
            $deleted++;
        }

        // ✅ Clean up orphaned meta lookup entries directly
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}wc_product_meta_lookup
            WHERE product_id NOT IN (
                SELECT ID FROM {$wpdb->prefix}posts
            )
        ");

        // ✅ Force WooCommerce to refresh lookup table
        wc_update_product_lookup_tables(true);

        echo "<div class='updated'><p>Delete completed. Total products deleted: $deleted.</p></div>";
    } else {
        echo "<div class='notice notice-warning'><p>No products found to delete.</p></div>";
    }
}


