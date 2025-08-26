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
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }

    // Only run filter on Photos category archive
    if ( is_product_category( 'photos' ) ) {

        $fields = ['location', 'date', 'number', 'name', 'class']; 
        $meta_query = [];

        foreach ( $fields as $field ) {
            if ( isset( $_GET[$field] ) && $_GET[$field] !== '' ) {
                $meta_query[] = [
                    'key'     => $field,
                    'value'   => sanitize_text_field( $_GET[$field] ),
                    'compare' => 'LIKE'
                ];
            }
        }

        if ( ! empty( $meta_query ) ) {
            $query->set( 'meta_query', $meta_query );
        }

        if ( isset( $_GET['photographer'] ) && $_GET['photographer'] !== '' ) {
            $tax_query = [
                [
                    'taxonomy' => 'photographer',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field( $_GET['photographer'] ),
                ]
            ];

            $existing_tax_query = $query->get( 'tax_query' );
            if ( $existing_tax_query ) {
                $tax_query = array_merge( $existing_tax_query, $tax_query );
            }

            $query->set( 'tax_query', $tax_query );
        }

        if ( isset( $_GET['title'] ) && $_GET['title'] !== '' ) {
            $query->set( 's', sanitize_text_field( $_GET['title'] ) );
            $query->set( 'search_fields', ['post_title'] );
            add_filter( 'posts_search', 'filter_product_title_only', 10, 2 );
        }
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

        // Only show widget on Photos category
        if ( ! is_product_category( 'photos' ) ) {
            return;
        }

        echo $args['before_widget'];
        ?>
            <form method="get" action="<?php echo esc_url( get_term_link( 'photos', 'product_cat' ) ); ?>">
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

add_filter( 'astra_page_layout', function( $layout ) {
    // Only on Photos category page → show sidebar
    if ( is_product_category( 'photos' ) ) {
        return 'left-sidebar'; // or 'right-sidebar', depending on your Astra settings
    }

    // Everywhere else → full width (no sidebar)
    if ( is_shop() || is_product_category() || is_product_tag() ) {
        return 'no-sidebar';
    }

    return $layout;
});


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

add_action('woocommerce_check_cart_items', 'custom_minimum_order_for_selected_sizes');
add_action('woocommerce_before_checkout_form', 'custom_minimum_order_for_selected_sizes');
add_action('woocommerce_checkout_process', 'custom_minimum_order_for_selected_sizes');

function custom_minimum_order_for_selected_sizes() {
    $minimum_total    = 6.00;
    $attribute        = 'pa_size_or_type';
    $triggering_sizes = ['6x4', '9x6', '12x8', '15x10', '18x12'];
    $physical_total = 0;
    $match_found    = false;

    foreach ( WC()->cart->get_cart() as $item_key => $item ) {
        $product = $item['data']; // WC_Product object
        $qty     = $item['quantity'];

        // Check variation attributes for the selected size
        $size = '';
        if ( isset( $item['variation']["attribute_{$attribute}"] ) ) {
            $size = $item['variation']["attribute_{$attribute}"];
        }

        // Skip if size not in triggering list
        if ( ! in_array( $size, $triggering_sizes, true ) ) {
            error_log(" → Skipping, size not in triggering list.");
            continue;
        }

        $match_found = true;

        // Add this line subtotal (pre-tax) to the running total
        $physical_total += $item['line_subtotal'];
    }

    // If rule is triggered but total is below minimum, block checkout
    if ( $match_found && $physical_total < $minimum_total ) {
        $message = sprintf(
            __( 'The minimum order amount for products requiring postage is %s. Your current subtotal is %s.', 'woocommerce' ),
            wc_price( $minimum_total ),
            wc_price( $physical_total )
        );

        wc_add_notice( $message, 'error' );
    }
}

