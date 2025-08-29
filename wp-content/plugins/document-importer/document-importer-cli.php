<?php

// Ensure this function is unique to avoid redeclaration
function di_set_external_image_for_product($product_id, $image_url) {
    $mime_types = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'];
    $file_ext = strtolower(pathinfo($image_url, PATHINFO_EXTENSION));
    $mime_type = $mime_types[$file_ext] ?? 'image/jpeg';
    list($width,$height) = @getimagesize($image_url) ?: [0,0];

    $existing_attachment = get_posts([
        'post_type'=>'attachment',
        'meta_key'=>'_wp_attached_file',
        'meta_value'=>$image_url,
        'posts_per_page'=>1
    ]);

    if($existing_attachment){
        $attachment_id = $existing_attachment[0]->ID;
    } else {
        $attachment_id = wp_insert_post([
            'post_title'=>'External Image - '.basename($image_url),
            'post_status'=>'inherit',
            'post_type'=>'attachment',
            'guid'=>esc_url_raw($image_url),
            'post_mime_type'=>$mime_type
        ]);
        if(!$attachment_id) return false;

        update_post_meta($attachment_id,'_exmage_external_url',esc_url_raw($image_url));
        update_post_meta($attachment_id,'_wp_attached_file',esc_url_raw($image_url));

        $metadata = [
            'file'=>esc_url_raw($image_url),
            'width'=>$width,
            'height'=>$height,
            'sizes'=>[],
            'image_meta'=>[]
        ];

        if($width>0 && $height>0){
            $metadata['sizes'] = [
                'thumbnail'=>['file'=>basename($image_url),'width'=>150,'height'=>150,'mime-type'=>$mime_type],
                'medium'=>['file'=>basename($image_url),'width'=>min(300,$width),'height'=>min(300,$height),'mime-type'=>$mime_type],
                'large'=>['file'=>basename($image_url),'width'=>min(1024,$width),'height'=>min(1024,$height),'mime-type'=>$mime_type],
                'woocommerce_thumbnail'=>['file'=>basename($image_url),'width'=>$width,'height'=>$height,'mime-type'=>$mime_type],
                'woocommerce_single'=>['file'=>basename($image_url),'width'=>$width,'height'=>$height,'mime-type'=>$mime_type],
                'woocommerce_gallery_thumbnail'=>['file'=>basename($image_url),'width'=>150,'height'=>150,'mime-type'=>$mime_type]
            ];
        }
        update_post_meta($attachment_id,'_wp_attachment_metadata',$metadata);
    }

    update_post_meta($attachment_id,'image_url','');
    update_post_meta($attachment_id,'_image_url','field_67b9c31f19be2');
    update_post_meta($product_id,'_thumbnail_id',$attachment_id);

    return $attachment_id;
}

class Document_Importer_CLI {

    public function __invoke($args){
        list($file) = $args;

        if(!file_exists($file)) WP_CLI::error("File not found: $file");
        if(($handle = fopen($file,'r'))===false) WP_CLI::error("Unable to open file: $file");

        $header = fgetcsv($handle, 0, '|');
        if(!$header) WP_CLI::error("Empty or invalid CSV file.");

        $pricing_map = ['A'=>3.00,'B'=>4.50,'C'=>6.00];
        $count=0;

        while(($row=fgetcsv($handle,0,'|'))!==false){
            $count++;
            if(count($row)!==count($header)){
                WP_CLI::warning("Skipping row ".($count+1).": column count mismatch");
                continue;
            }

            $data = array_combine($header,$row);

            $title = sanitize_text_field($data['document_title'] ?? '');
            $year = sanitize_text_field($data['year'] ?? '');
            $region = sanitize_text_field($data['region'] ?? '');
            $pricing_cat = strtoupper(trim($data['pricing_category'] ?? ''));
            $doc_id_raw = trim($data['document_id'] ?? '');

            // Description and title with brackets kept
            $desc = $title . ' ' . $doc_id_raw;

            // SKU without brackets
            $doc_id_clean = trim($doc_id_raw,'[]');

            // Price
            $price = $pricing_map[$pricing_cat] ?? 0;

            // Build thumbnail URL (numeric part only)
            $numeric_part = substr($doc_id_clean,2);
            $thumb_url = "https://www.bluebell-railway-museum.co.uk/archive/docs/EW/EW{$numeric_part}.jpg";

            // Check for duplicate SKU
            $existing = wc_get_product_id_by_sku($doc_id_clean);
            if($existing){
                $product = wc_get_product($existing);
                WP_CLI::log("Updating existing product (ID: {$existing}, SKU: {$doc_id_clean})");
            } else {
                $product = new WC_Product_Simple();
                $product->set_status('publish');
                $product->set_manage_stock(false);
                $product->set_catalog_visibility('visible');
                $product->set_virtual(true);
                $product->set_downloadable(false);
                $product->set_stock_status('instock');
            }

            $product->set_name($desc);
            $product->set_sku($doc_id_clean);
            $product->set_regular_price($price);

            $product_id = $product->save();

            // Save ACF fields
            if($region) update_field('document_region',$region,$product_id);
            if($year) update_field('document_year',$year,$product_id);

            // Thumbnail
            if(!empty($thumb_url)) di_set_external_image_for_product($product_id,$thumb_url);

            // Assign to Documents category
            $category = get_term_by('slug','documents','product_cat');
            if(!$category) $category = wp_insert_term('Documents','product_cat',['slug'=>'documents']);
            if($category && !is_wp_error($category)){
                $cat_id = is_array($category)? $category['term_id'] : $category->term_id;
                wp_set_object_terms($product_id,(int)$cat_id,'product_cat',false);
            }

            WP_CLI::log("Imported: {$desc} (SKU: {$doc_id_clean})");
        }

        fclose($handle);
        WP_CLI::success("Imported {$count} documents.");
    }
}

WP_CLI::add_command('import:documents','Document_Importer_CLI');
