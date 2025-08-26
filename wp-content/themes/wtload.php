<?php
/**
 * Plugin Name: my-document-importer-acf
 * Description: A plugin to import WooCommerce products with ACF fields from a CSV.
 * Version: 1.0
 * Author: Paul Frost
 */

function set_external_image_for_product($product_id, $image_url) {
    // Determine MIME type based on file extension
    $mime_types = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp'
    ];
    $file_ext = strtolower(pathinfo($image_url, PATHINFO_EXTENSION));
    $mime_type = $mime_types[$file_ext] ?? 'image/jpeg'; // Default to JPEG if unknown

    // Try to fetch the actual image dimensions
    list($width, $height) = @getimagesize($image_url) ?: [0, 0];

    // Check if an attachment already exists for this image URL
    $existing_attachment = get_posts([
        'post_type'  => 'attachment',
        'meta_key'   => '_wp_attached_file',
        'meta_value' => $image_url,
        'posts_per_page' => 1
    ]);

    if ($existing_attachment) {
        $attachment_id = $existing_attachment[0]->ID;
    } else {
        // Create a new attachment post
        $attachment = [
            'post_title'     => 'External Image - ' . basename($image_url),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_type'      => 'attachment',
            'guid'           => esc_url_raw($image_url),
            'post_mime_type' => $mime_type
        ];

        $attachment_id = wp_insert_post($attachment);

        if (!$attachment_id) {
            return false; // If insertion failed, return false
        }

        // Add EXMAGE-specific metadata
        update_post_meta($attachment_id, '_exmage_external_url', esc_url_raw($image_url));

        // Store image URL in `_wp_attached_file`
        update_post_meta($attachment_id, '_wp_attached_file', esc_url_raw($image_url));

        // Add metadata for WooCommerce compatibility using actual dimensions
        $metadata = [
            'file'   => esc_url_raw($image_url),
            'width'  => $width,
            'height' => $height,
            'sizes'  => [],
            'image_meta' => [
                'aperture'         => '0',
                'credit'           => '',
                'camera'           => '',
                'caption'          => '',
                'created_timestamp'=> '0',
                'copyright'        => '',
                'focal_length'     => '0',
                'iso'              => '0',
                'shutter_speed'    => '0',
                'title'            => '',
                'orientation'      => '0',
                'keywords'         => []
            ]
        ];

        // Update metadata with detected dimensions
        if ($width > 0 && $height > 0) {
            $metadata['sizes'] = [
                'thumbnail' => ['file' => basename($image_url), 'width' => 150, 'height' => 150, 'mime-type' => $mime_type],
                'medium'    => ['file' => basename($image_url), 'width' => min(300, $width), 'height' => min(300, $height), 'mime-type' => $mime_type],
                'large'     => ['file' => basename($image_url), 'width' => min(1024, $width), 'height' => min(1024, $height), 'mime-type' => $mime_type],
                'woocommerce_thumbnail'      => ['file' => basename($image_url), 'width' => $width, 'height' => $height, 'mime-type' => $mime_type],
                'woocommerce_single'         => ['file' => basename($image_url), 'width' => $width, 'height' => $height, 'mime-type' => $mime_type],
                'woocommerce_gallery_thumbnail' => ['file' => basename($image_url), 'width' => 150, 'height' => 150, 'mime-type' => $mime_type]
            ];
        }

        update_post_meta($attachment_id, '_wp_attachment_metadata', $metadata);
    }

    // Additional metadata fields for consistency with EXMAGE
    update_post_meta($attachment_id, 'image_url', ''); // Empty value like manually uploaded images
    update_post_meta($attachment_id, '_image_url', 'field_67b9c31f19be2'); // ACF field identifier
    // Set as the product's featured image
    update_post_meta($product_id, '_thumbnail_id', $attachment_id);
    return $attachment_id;
}

function convert_date_format($date) {
    $converted_date = false;

    // First, check for DD/MM/YYYY format
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
        // Reformat to YYYY-MM-DD
        $converted_date = "{$matches[3]}-{$matches[2]}-{$matches[1]}"; // '1969-04-12'
    }
    // Then check for original DD MMM YYYY format (e.g., 12 Apr 1969)
    elseif (preg_match('/^(\d{2})\s([a-zA-Z]{3})\s(\d{4})$/', $date, $matches)) {
        $month = date('m', strtotime($matches[2])); // Convert 'Apr' to '04'
        $converted_date = "{$matches[3]}-{$month}-{$matches[1]}"; // '1969-04-12'
    }

    return $converted_date;
}


// Automatically set WooCommerce product slug to SKU
add_action('save_post_product', 'set_product_slug_to_sku', 20, 3);

function set_product_slug_to_sku($post_ID, $post, $update) {
    // Only proceed if it's a product and it's not an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if ($post->post_type !== 'product') return;

    // Get the product object
    $product = wc_get_product($post_ID);
    if (!$product) return;

    $sku = $product->get_sku();

    // If SKU exists and the current slug is not equal to the SKU
    if ($sku && $post->post_name !== sanitize_title($sku)) {
        // Remove this hook temporarily to avoid infinite loop
        remove_action('save_post_product', 'set_product_slug_to_sku', 20);

        // Update post slug
        wp_update_post([
            'ID' => $post_ID,
            'post_name' => sanitize_title($sku)
        ]);

        // Re-add the hook
        add_action('save_post_product', 'set_product_slug_to_sku', 20, 3);
    }
}


// Function to run the import process
function myplugin_run_import() {

    echo "\nImport Running";
    $file = 'D:\OneDrive\Documents\Bluebell Railway\Archive documentdatabase\BRMdocuments.csv';
    $file = __DIR__ . '/BRMdocuments.csv';
    $row = 0;
    $created = 0;
    $skipped = 0;

    // Define your starting point and limit
    $start = 347; // Replace $n with your desired starting row number (1-based)
    $limit = 100000;
    $end = $start + $limit;

    $handle = fopen($file, 'r');

    if ($handle === false) {
        die("Failed to open CSV file.");
    }

    // Read header line manually and strip BOM
    $header_line = fgets($handle);
    $header_line = preg_replace('/^\xEF\xBB\xBF/', '', $header_line); // Remove BOM
    $headers = str_getcsv(trim($header_line), '|');

    $line_num = 0; // Tracks number of data lines read (excluding header)

    while (($row_data = fgetcsv($handle, 0, '|')) !== false) {
        $line_num++;

        // Skip rows before $start
        if ($line_num < $start) {
            continue;
        }

        // Stop after $end - 1
        if ($line_num >= $end) {
            break;
        }

        // Row length check
        if (count($row_data) !== count($headers)) {
            $skipped++;
            continue;
        }

        $data = array_combine($headers, $row_data);
        $reference_number = $data['Reference Number'];
        $title = $data['Title'];
        $description = $data['Description'];
        $tags = $data['Tags'];
        $sort_date = $data['Sort Date'];
        $company = $data['Company'];
        $class = $data['Class'];
        $date = $data['Date'];
        $number = $data['Number'];
        $name = $data['Name'];
        $location = $data['Location'];
        $train_working = $data['Train Working'];
        $other_information = $data['OtherInformation'];
        $documentgrapher = $data['documentgrapher'];
        $reference_number = $data['Reference Number'];
        $day_of_week = $data['Day of Week'];
        $holiday = $data['Holiday'];

      echo "\n Now on Product: {$reference_number}";        
        
        global $wpdb;
        $existing_product_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT pm.post_id
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_sku'
               AND pm.meta_value = %s
               AND p.post_status = 'publish'
               AND (p.post_type = 'product' OR p.post_type = 'product_variation')
             LIMIT 1",
            $reference_number
        ));      


      echo "\n Deleting Product id: {$existing_product_id}";   

        if ($existing_product_id) {
            // Force delete = true skips the trash and permanently removes
            wp_delete_post($existing_product_id, true);
            echo "\n🗑️ Deleted existing product with ID: {$existing_product_id}";
        }

        /** Attribute configuration **/
        $attribute_tax   = 'pa_size_or_type';          // DB taxonomy name for the attribute
        $attribute_slug  = 'size_or_type';             // attribute slug without 'pa_' - sometimes used in UI/DB
        //$values          = ['6x4', '9x6', '12x8', '15x10', '18x12', 'jpg_personal', 'jpg_commercial'];
        $values          = [
          					'6” x 4” document Print', 
          					'9” x 6” document Print', 
          					'12” x 8” document Print', 
          					'15” x 10” document Print', 
          					'18” x 12” document Print', 
          					'Digital Download – Personal use', 
          					'Digital Download – Commercial use'
        					];
        $variation_prices = ['1.25', '4.00', '9.60', '18.00', '29.00', '2.00', '8.00']; // index-aligned with $values

        // --- 2) Create parent product post ---
        $product_id = wp_insert_post([
          'post_title'   => $title,
          'post_content' => $description,
          'post_status'  => 'publish',
          'post_type'    => 'product',
          'meta_input'   => [
            '_sku' => $reference_number,
          ],
        ]);

        if (is_wp_error($product_id)) {
          echo "\n<div class='error'>Failed to create product for Reference Number: {$reference_number}</div>";
          return;
        }
        echo "\n✅ Created product with ID: {$product_id}";
        $created++;

        $tags_array = !empty($tags) ? array_map('trim', explode(', ', $tags)) : [];
        if (!empty($tags_array)) {
          wp_set_post_terms($product_id, $tags_array, 'product_tag');
          // echo "\n✅ Tags assigned: " . implode(', ', $tags_array) . "";
        }

        $documents_category = get_term_by('name', 'documents', 'product_cat');
        if ($documents_category) {
          wp_set_object_terms($product_id, [$documents_category->term_id], 'product_cat');
        } else {
          echo "\n⚠️ Category 'documents' not found";
        }

        // --- 5) Ensure attribute terms exist in taxonomy (create if missing) ---
        foreach ($values as $size) {
          // Use term_exists to check by name (case-insensitive)
          if (! term_exists($size, $attribute_tax) ) {
            $insert = wp_insert_term($size, $attribute_tax);
            if (is_wp_error($insert)) {
              echo "\n❌ Failed to insert term '{$size}' into {$attribute_tax}: " . $insert->get_error_message() . "";
            } else {
              echo "\n✅ Inserted missing term '{$size}' into {$attribute_tax}";
            }
          } else {
            //echo "\nℹ️ Term '{$size}' already exists in {$attribute_tax}";
          }
        }

        //Attach attribute terms to parent product (must be done BEFORE variations) ---
        wp_set_object_terms($product_id, $values, $attribute_tax);

        //Set _product_attributes meta so WooCommerce knows attribute is used for variations ---
        $product_attributes = [
          $attribute_tax => [
            'name'         => $attribute_tax,
            'value'        => '',   // empty for taxonomy-based attributes
            'position'     => 0,
            'is_visible'   => 1,
            'is_variation' => 1,
            'is_taxonomy'  => 1,
          ],
        ];
        update_post_meta($product_id, '_product_attributes', $product_attributes);

        // Also set product type taxonomy (product_type) and meta (robustness)
        wp_set_object_terms($product_id, 'variable', 'product_type');
        update_post_meta($product_id, '_product_type', 'variable');

        //Other product-level meta / ACF updates BEFORE saving parent (so WC picks them up) ---
        foreach (['image_url' => '', '_image_url' => 'field_67b9c31f19be2'] as $key => $value) {
          update_post_meta($product_id, $key, $value);
        }

        // External image
        $image_url = "https://www.bluebell-railway-museum.co.uk/archive/documents2/" .
          substr($reference_number, 0, 3) . "/" .
          substr($reference_number, -3) . ".jpg";
        set_external_image_for_product($product_id, $image_url);

        // ACF fields (guarded updates)
        if (!empty($sort_date)) {
          $converted_date = convert_date_format($sort_date);
          if ($converted_date) {
            $ok = update_field('sort_date', $converted_date, $product_id);
            echo $ok ? "✅ sort_date set" : "❌ sort_date update failed";
          } else {
            echo "\n❌ Date conversion failed for sort_date: {$sort_date}";
          }
        } else {
          echo "\nℹ️ sort_date is not set or empty";
        }

        // Generic ACF updates
        $acf_map = [
          'description'       => $description,
          'company'           => $company,
          'class'             => $class,
          'date'              => $date,
          'number'            => $number,
          'name'              => $name,
          'location'          => $location,
          'train_working'     => $train_working,
          'other_information' => $other_information,
          'reference_number'  => $reference_number,
          'day_of_week'       => $day_of_week,
          'holiday'           => $holiday,
        ];

        foreach ($acf_map as $field_key => $field_value) {
          if (!empty($field_value)) {
            $res = update_field($field_key, $field_value, $product_id);
            echo $res ? "✅ ACF '{$field_key}' updated" : "❌ ACF '{$field_key}' update failed";
          }
        }

        // documentgrapher taxonomy handling
        if (!empty($documentgrapher)) {
          $documentgrapher = sanitize_text_field($documentgrapher);
          $term = term_exists($documentgrapher, 'documentgrapher');
          if (!$term) {
            $term = wp_insert_term($documentgrapher, 'documentgrapher');
          }
          if (!is_wp_error($term)) {
            $term_id = is_array($term) ? $term['term_id'] : $term;
            wp_set_object_terms($product_id, intval($term_id), 'documentgrapher', false);
          } else {
            echo "\n❌ documentgrapher term error: " . $term->get_error_message() . "";
          }
        }

        try {
          foreach ($values as $index => $size) {

            // --- Ensure term exists and get its actual slug ---
            $term = term_exists($size, $attribute_tax);
            if (!$term) {
                $term = wp_insert_term($size, $attribute_tax);
            }

            if (is_wp_error($term)) {
                echo "\n❌ Failed to ensure term for size '{$size}': " . $term->get_error_message();
                continue; // Skip this variation if term creation failed
            }

            // Get term_id and slug
            $term_id = is_array($term) ? $term['term_id'] : $term;
            $term_obj = get_term($term_id, $attribute_tax);
            if (!$term_obj || is_wp_error($term_obj)) {
                echo "\n❌ Could not retrieve term object for size '{$size}'";
                continue;
            }
            $term_slug = $term_obj->slug;

            // --- Create variation ---
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            $variation->set_regular_price($variation_prices[$index] ?? '0.00');
            $variation->set_sku($reference_number . '-' . $term_slug);
            $variation->set_manage_stock(false);
            $variation->set_stock_status('instock');
            $variation->set_status('publish');
            $variation_id = $variation->save();

            if (!$variation_id) {
                echo "\n❌ Variation save returned falsy for size: {$size}";
                continue;
            }

            // --- Assign attribute slug to variation ---
            $attribute_meta_key = 'attribute_' . $attribute_tax; // e.g. attribute_pa_size_or_type
            update_post_meta($variation_id, $attribute_meta_key, $term_slug);
            update_post_meta($variation_id, '_sku', $reference_number . '-' . $term_slug);
            update_post_meta($variation_id, '_stock_status', 'instock');

            //echo "\n✅ Created variation ID {$variation_id} for '{$size}' (slug: {$term_slug})";
        }
    } catch (Exception $e) {
        echo "\n<div class='notice notice-error'>❌ Variation creation error: {$e->getMessage()}</div>";
    }

        //Final housekeeping: clear transients / regenerate lookup tables ---
        wc_delete_product_transients($product_id);
        wc_delete_shop_order_transients(); // optional, but safe if you want to clear caches

        // If you use lookup tables (WC 3.6+), prime them (optional)
        if (function_exists('wc_update_product_lookup')) {
          try {
            wc_update_product_lookup($product_id);
          } catch (Exception $e) {
            // non-fatal
          }
        }
        echo "\n🎉 Done. Parent product and variations should now be present for product ID: {$product_id}";
    }

    fclose($handle);
    echo "\n\nCreated: $created\nSkipped: $skipped\n";
  }