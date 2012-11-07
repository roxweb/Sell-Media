<?php

/**
 * Filter: Add Sell option to media uploader
 *
 * @param $form_fields array, fields to include in attachment form
 * @param $post object, attachment record in database
 * @return $form_fields, modified form fields
 * @author Thad Allender
 * @uses add_filter( 'attachment_fields_to_edit', 'sell_media_attachment_fields_to_edit', 10, 2 );
 * @since 0.1
 */
function sell_media_attachment_fields_to_edit( $form_fields, $post ) {

    $image_meta_a = wp_get_attachment_metadata( $post->ID );
    $upload_url_a = wp_upload_dir();
    ?>
    <script type="text/javascript">
    jQuery( document ).ready(function( $ ){
        if ( $('.media-item input[type="checkbox"]').attr('checked') != undefined ) {
            $('.urlfield').val("<?php print $upload_url_a['baseurl'] . SellMedia::upload_dir . '/'.$image_meta_a['file']; ?>");
        }
    });
    </script>
    <?php
    $sell = (bool) get_post_meta($post->ID, '_sell_media_for_sale', true);

    $form_fields['sell'] = array(
        'label' => __('Sell this?'), 'sell_media',
        'input' => 'html',
        'html' => '<label for="attachments-'.$post->ID.'-sell"> '.
            '<input type="checkbox" id="attachments-'.$post->ID.'-sell" name="attachments['.$post->ID.'][sell]" value="1"'.($sell ? ' checked="checked"' : '').' /> Yes</label>  ',
        'value' => $sell,
        'helps' => __('If you select yes, this image will be added as a Product entry. You can modify the price and available licenses on the Products -> Edit Products tab. By default, the newly created Product will inherit the prices and licenses that you chose on the settings page.'), 'sell_media'
    );

    return $form_fields;
}
add_filter( 'attachment_fields_to_edit', 'sell_media_attachment_fields_to_edit', 10, 2 );


/**
 * Delete a product post type based on the ID
 *
 * @since 0.1
 */
function sell_media_delete_item( $post_id=null ){

    $product_id = get_post_meta( $post_id, '_sell_media_for_sale_product_id', true );

    delete_post_meta( $post_id, '_sell_media_for_sale_product_id' );
    delete_post_meta( $post_id, '_sell_media_for_sale' );

    wp_trash_post( $product_id );
}


/**
 * Filter: Save/Edit value of "Sell This?" selection in media uploader
 *
 * @param $post array, the post data for database
 * @param $attachment array, attachment fields from $_POST form
 * @return $post array, modified post data
 * @author Thad Allender
 * @uses add_filter( 'attachment_fields_to_save', 'sell_media_attachment_field_sell_save', 10, 2 );
 * @todo make wp_delete_post work
 * @todo add conditional for save vs. edit
 * @since 0.1
 */
function sell_media_attachment_field_sell_save( $post, $attachment ) {
    $for_sale = get_post_meta( $post['ID'], '_sell_media_for_sale', $attachment['sell'], true );

    // Attachment was once marked for sale, but no longer is for sale.
    if ( is_null( $attachment['sell'] ) && $for_sale ){
        sell_media_delete_item( $post['ID'] );

        return $post;

    // Attachment is now marked for sale
    } elseif( $attachment['sell'] ) {

        // Create post object, we use later on
        $product = array(
            'post_title' => $post['post_title'],
            'post_content' => $post['post_content'],
            'post_status' => 'publish',
            'post_type' => 'sell_media_item',
            'post_date_gmt' => date('Y-m-d H:i:s')
        );

        // Insert the post into the database
        $product_id = wp_insert_post( $product );

        // If wp_insert_post fails, lets just leave
        // if needed we can debug, but no need to hang
        // around with over complicated conditionals
        if ( is_wp_error( $product_id ) ) {
            return;
        }

        // From here we can asusme that the image has been upload
        // and a post type has been created. We update post meta
        // read some meta data from the image, build paths, save
        // iptc data, set default license and finally copy the
        // image to our products directory.

        update_post_meta( $product_id, '_thumbnail_id', $post['ID'] );
        update_post_meta( $product_id, 'sell_media_description', $post['post_content'] );
        update_post_meta( $post['ID'], '_sell_media_for_sale', true );
        update_post_meta( $post['ID'], '_sell_media_for_sale_product_id', $product_id );

        // Read our meta data from the original post
        $meta = wp_get_attachment_metadata( $post['ID'] );

        sell_media_set_default_terms( $product_id );

        // Build paths to the original file and the destination
        $dir = wp_upload_dir();
        $original_file = $dir['basedir'] . '/' . $meta['file'];

        $destination_file = $dir['basedir'] . SellMedia::upload_dir . '/' . $meta['file'];

        $mime_type = wp_check_filetype( $original_file );

        $image_mimes = array(
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/bmp',
            'image/tiff'
            );

        // Image mime type support
        if ( in_array( $mime_type['type'], $image_mimes ) ){
            $destination_file = sell_media_image_attachment( $original_file );
        }
        // Support for different mime types here

        return $post;

    // Attachment is not marked for sale and never was,
    // just return what if anything fields
    // were updated
    } else {
        return $post;
    }
}
add_filter( 'attachment_fields_to_save', 'sell_media_attachment_field_sell_save', 10, 2 );