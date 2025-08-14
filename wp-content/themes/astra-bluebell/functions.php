<?php

function custom_read_more_text( $text, $product ) {
    if ( ! $product->is_purchasable() ) {
        return __( 'More Details', 'astra' ); // Change 'More Details' to your preferred text
    }
    return $text;
}
add_filter( 'woocommerce_product_add_to_cart_text', 'custom_read_more_text', 10, 2 );

// Remove unwanted sorting options
function custom_remove_sorting_options($options) {
    unset($options['price-desc']);
    unset($options['price']);
    unset($options['popularity']);
    unset($options['rating']);
    unset($options['menu_order']); // remove default, we’ll add custom title sort instead
    return $options;
}
add_filter('woocommerce_catalog_orderby', 'custom_remove_sorting_options');

// Add custom sorting options (latest + alphabetical)
function custom_add_sorting_options($options) {
    $options['date'] = __('Sort by recently added', 'astra');
    $options['title_asc'] = __('Sort by title (A–Z)', 'astra');
    return $options;
}
add_filter('woocommerce_catalog_orderby', 'custom_add_sorting_options');

// Add support for our custom "Sort by title"
function custom_sorting_query($query) {
    if (!is_admin() && $query->is_main_query() && (is_shop() || is_product_category() || is_product_tag())) {
        if (isset($_GET['orderby']) && $_GET['orderby'] === 'title_asc') {
            $query->set('orderby', 'title');
            $query->set('order', 'ASC');
        }
    }
}
add_action('pre_get_posts', 'custom_sorting_query');

// Set default sort to most recent
add_filter('woocommerce_default_catalog_orderby', function() {
    return 'date'; // newest products first
});

function filter_product_title_only( $search, $query ) {
    global $wpdb;
    if ( ! empty( $search ) && $query->is_main_query() && ! is_admin() && isset( $_GET['title'] ) ) {
        $like = '%' . $wpdb->esc_like( $query->get( 's' ) ) . '%';
        $search = $wpdb->prepare( " AND ({$wpdb->posts}.post_title LIKE %s)", $like );
    }
    return $search;
}

function acf_multi_field_filter_query( $query ) {
    if ( is_admin() || ! $query->is_main_query() || !( is_shop() || is_product_category() || is_product_tag() ) ) {
        return;
    }

    $fields = ['location', 'date', 'number', 'name', 'class']; // 'photographer' removed
    $meta_query = [];

    // Add ACF field filters (except photographer)
    foreach ( $fields as $field ) {
        if ( isset( $_GET[$field] ) && $_GET[$field] !== '' ) {
            $meta_query[] = array(
                'key'     => $field,
                'value'   => sanitize_text_field( $_GET[$field] ),
                'compare' => 'LIKE'
            );
        }
    }

    if ( ! empty( $meta_query ) ) {
        $query->set( 'meta_query', $meta_query );
    }

    // Add photographer taxonomy filter
    if ( isset( $_GET['photographer'] ) && $_GET['photographer'] !== '' ) {
        $tax_query = [
            [
                'taxonomy' => 'photographer',
                'field'    => 'slug',
                'terms'    => sanitize_text_field( $_GET['photographer'] ),
            ]
        ];

        // Merge if other tax_query already exists
        $existing_tax_query = $query->get( 'tax_query' );
        if ( $existing_tax_query ) {
            $tax_query = array_merge( $existing_tax_query, $tax_query );
        }

        $query->set( 'tax_query', $tax_query );
    }

    // Handle title search
    if ( isset( $_GET['title'] ) && $_GET['title'] !== '' ) {
        $query->set( 's', sanitize_text_field( $_GET['title'] ) );
        $query->set( 'search_fields', array( 'post_title' ) );
        add_filter( 'posts_search', 'filter_product_title_only', 10, 2 );
    }
}
add_action( 'pre_get_posts', 'acf_multi_field_filter_query' );

class ACF_Filter_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'acf_filter_widget',
            __('ACF Product Filter', 'your-theme'),
            ['description' => __('Filter products by ACF fields', 'your-theme')]
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];
        ?>
        <form method="get" action="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
            <?php
            $fields = ['title', 'location', 'date', 'number', 'name', 'class'];
            foreach ( $fields as $field ) {
                $label = ($field === 'title') ? 'Title' : ucfirst($field);
                $value = isset($_GET[$field]) ? esc_attr($_GET[$field]) : '';
                ?>
                <p>
                    <label for="<?php echo $field; ?>"><?php echo $label; ?>:</label><br>
                    <input type="text" name="<?php echo $field; ?>" id="<?php echo $field; ?>" value="<?php echo $value; ?>" placeholder=" " style="width:100%;">
                </p>
            <?php } ?>

            <?php
            $selected_photographer = isset($_GET['photographer']) ? sanitize_text_field($_GET['photographer']) : '';
            $photographers = get_terms([
                'taxonomy' => 'photographer',
                'hide_empty' => false,
            ]);
            if ( ! is_wp_error( $photographers ) && ! empty( $photographers ) ) :
            ?>
                <p>
                    <label for="photographer">Photographer:</label><br>
                    <select name="photographer" id="photographer" style="width:100%;">
                        <option value="">-- Any --</option>
                        <?php foreach ( $photographers as $term ) : ?>
                            <option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected_photographer, $term->slug ); ?>>
                                <?php echo esc_html( $term->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
            <?php endif; ?>

            <p style="display: flex; gap: 10px;">
              <button type="submit">Search</button>
          	</p>
            <p style="display: flex; gap: 10px;">
                <button type="button" onclick="window.location.href='<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>';">Reset All</button>
            </p>

        </form>
        <?php
        echo $args['after_widget'];
    }
}

function register_acf_filter_widget() {
    register_widget('ACF_Filter_Widget');
}
add_action('widgets_init', 'register_acf_filter_widget');

function register_photographer_taxonomy() {
    register_taxonomy(
        'photographer',
        'product',
        [
            'label' => 'Photographers',
            'rewrite' => ['slug' => 'photographer'],
            'hierarchical' => false,
            'show_ui' => true,
            'show_admin_column' => true,
        ]
    );
}
add_action('init', 'register_photographer_taxonomy');

add_filter( 'woocommerce_loop_product_link', 'open_product_links_in_new_tab' );
function open_product_links_in_new_tab( $link ) {
    // Add target="_blank" and rel="noopener noreferrer" for security
    $link = str_replace( '<a ', '<a target="_blank" rel="noopener noreferrer" ', $link );
    return $link;
}

function my_enqueue_open_links_new_tab_script() {
    wp_register_script( 'my-new-tab-script', false );
    wp_enqueue_script( 'my-new-tab-script' );
    wp_add_inline_script( 'my-new-tab-script', "
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.astra-shop-summary-wrap a, .astra-shop-thumbnail-wrap a').forEach(function (link) {
                link.setAttribute('target', '_blank');
                link.setAttribute('rel', 'noopener noreferrer');
            });
        });
    " );
}


add_action( 'wp_enqueue_scripts', 'my_enqueue_open_links_new_tab_script' );

add_filter( 'woocommerce_package_rates', function( $rates, $package ) {
    // Debug start
    error_log("\n--- Shipping Rates Filter Start ---");

    // Count total items in cart
    $cart_quantity = WC()->cart->get_cart_contents_count();
    error_log("Cart quantity: $cart_quantity");

    // Check if order meets minimum quantity requirement
    if ( $cart_quantity < 6 ) {
        error_log("Minimum order size not met (needs 6, has $cart_quantity).");
        return $rates; // Don't change shipping
    } else {
        error_log("Minimum order size met.");
    }

    // Assume product is physical unless proven otherwise
    $is_physical = false;

    // Loop through cart items
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $product = $cart_item['data'];

        // Log product details
        error_log("Checking product: " . $product->get_name());
        error_log("  Virtual? " . ($product->is_virtual() ? 'Yes' : 'No'));
        error_log("  Downloadable? " . ($product->is_downloadable() ? 'Yes' : 'No'));

        // If not virtual, we have a physical product
        if ( ! $product->is_virtual() ) {
            $is_physical = true;
            error_log("  → Found a physical product.");
            break;
        }
    }

    // If all products are digital, force free shipping
    if ( ! $is_physical ) {
        error_log("All products are digital → Forcing free shipping.");
        foreach ( $rates as $rate_id => $rate ) {
            if ( 'free_shipping' === $rate->method_id ) {
                $rates = array( $rate_id => $rate );
                break;
            }
        }
    } else {
        error_log("Order contains physical items → normal shipping applies.");
    }

    error_log("--- Shipping Rates Filter End ---\n");

    return $rates;
}, 10, 2 );
