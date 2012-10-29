<?php

/**
 *  Resizes an image, and returns an array containing the resized URL, width, height and file type.
 *  Because Wordpress 3.5 has added the 'WP_Image_Editor' class, and depreciated some of the functions
 *  we would normally rely on (such as wp_load_image), we've had to create two separate functions.
 *  
 *  The first function is for Wordpress 3.5+ and uses the WP_Image_Editor class,
 *  and the second function uses various GD Library functions to resize the image.
 *  Both produce the same result.
 *
 *  This might seem like overkill, but because some old image-based functions (such as the one mentioned above) have been depreciated,
 *  we've had to do this to avoid users with WP_Debug enabled from receiving error messages.
 *
 *  This is future proof as well, and means we don't have set Wordpress 3.5 as the minimum requirement!
 *
 *  Lastly, Wordpress 3.5+ can handle resizing with either ImageMagik or GD Library.
 *  However, our pre-Wordpress 3.5 function can only use GD Library. This shouldn't be a problem,
 *  but if you don't have GD Library installed and aren't using Wordpress 3.5, you won't be able to avail of image resizing at all.
 *  
 *  @author Matthew Ruddy (http://rivaslider.com)
 *  @return array   An array containing the resized image URL, width, height and file type.
 */
if ( class_exists( 'WP_Image_Editor' ) && isset( $wp_version ) && version_compare( '3.5', $wp_version, '>=' ) ) {
    function matthewruddy_image_resize( $url, $width = 150, $height = 150, $crop = true, $retina = false ) {

        if ( empty( $url ) )
            return new WP_Error( 'no_image_url', __( 'No image URL has been entered.' ), $url );

        // Get the image file path
        $file_path = parse_url( $url );
        $file_path = $_SERVER['DOCUMENT_ROOT'] . $file_path['path'];
        
        // Check for Multisite
        if ( is_multisite() ) {
            global $blog_id;
            $blog_details = get_blog_details( $blog_id );
            $file_path = str_replace( $blog_details->path . 'files/', '/wp-content/blogs.dir/'. $blog_id .'/files/', $file_path );
        }

        // Destination width and height variables
        $dest_width = ( $retina ) ? ( $width * 2 ) : $width;
        $dest_height = ( $retina ) ? ( $height * 2 ) : $height;

        // File name suffix (appended to original file name)
        $suffix = "{$dest_width}x{$dest_height}";

        // Some additional info about the image
        $info = pathinfo( $file_path );
        $dir = $info['dirname'];
        $ext = $info['extension'];
        $name = wp_basename( $file_path, ".$ext" );

        // Suffix applied to filename
        $suffix = "{$dest_width}x{$dest_height}";

        // Get the destination file name
        $dest_file_name = "{$dir}/{$name}-{$suffix}.{$ext}";

        if ( !file_exists( $dest_file_name ) ) {

            // Load Wordpress Image Editor instance
            $editor = WP_Image_Editor::get_instance( $file_path );
            if ( is_wp_error( $editor ) )
                return array( 'url' => $url, 'width' => $width, 'height' => $height );

            // Get the original image size
            $size = $editor->get_size();
            $orig_width = $size['width'];
            $orig_height = $size['height'];

            $src_x = $src_y = 0;
            $src_w = $orig_width;
            $src_h = $orig_height;

            if ( $crop ) {

                $cmp_x = $orig_width / $dest_width;
                $cmp_y = $orig_height / $dest_height;

                // Calculate x or y coordinate, and width or height of source
                if ( $cmp_x > $cmp_y ) {
                    $src_w = round( $orig_width / $cmp_x * $cmp_y );
                    $src_x = round( ( $orig_width - ( $orig_width / $cmp_x * $cmp_y ) ) / 2 );
                }
                else if ( $cmp_y > $cmp_x ) {
                    $src_h = round( $orig_height / $cmp_y * $cmp_x );
                    $src_y = round( ( $orig_height - ( $orig_height / $cmp_y * $cmp_x ) ) / 2 );
                }

            }

            // Time to crop the image!
            $editor->crop( $src_x, $src_y, $src_w, $src_h, $dest_width, $dest_height );

            // Now let's save the image
            $saved = $editor->save( $dest_file_name );

            // Create the image array
            $image_array = array(
                'url' => str_replace( basename( $url ), basename( $saved['path'] ), $url ),
                'width' => $saved['width'],
                'height' => $saved['height'],
                'type' => $saved['mime-type']
            );

        }
        else {
            $image_array = array(
                'url' => str_replace( basename( $url ), basename( $dest_file_name ), $url ),
                'width' => $dest_height,
                'height' => $dest_height,
                'type' => $ext
            );
        }

        // Return image array
        return $image_array;

    }
}
else {
    function matthewruddy_image_resize( $url, $width = 150, $height = 150, $crop = true, $retina = false ) {

        if ( empty( $url ) )
            return new WP_Error( 'no_image_url', __( 'No image URL has been entered.' ), $url );

        // Bail if GD Library doesn't exist
        if ( !extension_loaded('gd') || !function_exists('gd_info') )
            return array( 'url' => $url, 'width' => $width, 'height' => $height );

        // Destination width and height variables
        $dest_width = ( $retina ) ? ( $width * 2 ) : $width;
        $dest_height = ( $retina ) ? ( $height * 2 ) : $height;

        // Get image file path
        $file_path = parse_url( $url );
        $file_path = $_SERVER['DOCUMENT_ROOT'] . $file_path['path'];
        
        // Check for Multisite
        if ( is_multisite() ) {
            global $blog_id;
            $blog_details = get_blog_details( $blog_id );
            $file_path = str_replace( $blog_details->path . 'files/', '/wp-content/blogs.dir/'. $blog_id .'/files/', $file_path );
        }

        // Some additional info about the image
        $info = pathinfo( $file_path );
        $dir = $info['dirname'];
        $ext = $info['extension'];
        $name = wp_basename( $file_path, ".$ext" );

        // Suffix applied to filename
        $suffix = "{$dest_width}x{$dest_height}";

        // Get the destination file name
        $dest_file_name = "{$dir}/{$name}-{$suffix}.{$ext}";

        // No need to resize & create a new image if it already exists!
        if ( !file_exists( $dest_file_name ) ) {

            $image = wp_load_image( $file_path );
            if ( !is_resource( $image ) )
                return new WP_Error( 'error_loading_image_as_resource', $image, $file_path );

            // Get the current image dimensions and type
            $size = @getimagesize( $file_path );
            if ( !$size )
                return new WP_Error( 'file_path_getimagesize_failed', __( 'Failed to get $file_path information using "@getimagesize".', 'rivasliderpro'), $file_path );
            list( $orig_width, $orig_height, $orig_type ) = $size;
            
            // Create new image
            $new_image = wp_imagecreatetruecolor( $dest_width, $dest_height );

            // Do some proportional cropping if enabled
            if ( $crop ) {

                $src_x = $src_y = 0;
                $src_w = $orig_width;
                $src_h = $orig_height;

                $cmp_x = $orig_width / $dest_width;
                $cmp_y = $orig_height / $dest_height;

                // Calculate x or y coordinate, and width or height of source
                if ( $cmp_x > $cmp_y ) {
                    $src_w = round( $orig_width / $cmp_x * $cmp_y );
                    $src_x = round( ( $orig_width - ( $orig_width / $cmp_x * $cmp_y ) ) / 2 );
                }
                else if ( $cmp_y > $cmp_x ) {
                    $src_h = round( $orig_height / $cmp_y * $cmp_x );
                    $src_y = round( ( $orig_height - ( $orig_height / $cmp_y * $cmp_x ) ) / 2 );
                }

                // Create the resampled image
                imagecopyresampled( $new_image, $image, 0, 0, $src_x, $src_y, $dest_width, $dest_height, $src_w, $src_h );

            }
            else
                imagecopyresampled( $new_image, $image, 0, 0, 0, 0, $dest_width, $dest_height, $orig_width, $orig_height );

            // Convert from full colors to index colors, like original PNG.
            if ( IMAGETYPE_PNG == $orig_type && function_exists('imageistruecolor') && !imageistruecolor( $image ) )
                imagetruecolortopalette( $new_image, false, imagecolorstotal( $image ) );

            // Remove the original image from memory (no longer needed)
            imagedestroy( $image );

            // Check the image is the correct file type
            if ( IMAGETYPE_GIF == $orig_type ) {
                if ( !imagegif( $new_image, $dest_file_name ) )
                    return new WP_Error( 'resize_path_invalid', __( 'Resize path invalid (GIF)' ) );
            }
            elseif ( IMAGETYPE_PNG == $orig_type ) {
                if ( !imagepng( $new_image, $dest_file_name ) )
                    return new WP_Error( 'resize_path_invalid', __( 'Resize path invalid (PNG).' ) );
            }
            else {

                // All other formats are converted to jpg
                if ( 'jpg' != $ext && 'jpeg' != $ext )
                    $dest_file_name = "{$dir}/{$name}-{$suffix}.jpg";
                if ( !imagejpeg( $new_image, $dest_file_name, apply_filters( 'resize_jpeg_quality', 90 ) ) )
                    return new WP_Error( 'resize_path_invalid', __( 'Resize path invalid (JPG).' ) );

            }

            // Remove new image from memory (no longer needed as well)
            imagedestroy( $new_image );

            // Set correct file permissions
            $stat = stat( dirname( $dest_file_name ));
            $perms = $stat['mode'] & 0000666;
            @chmod( $dest_file_name, $perms );

        }

        // Get some information about the resized image
        $new_size = @getimagesize( $dest_file_name );
        if ( !$new_size )
            return new WP_Error( 'resize_path_getimagesize_failed', __( 'Failed to get $dest_file_name (resized image) info via @getimagesize' ), $dest_file_name );
        list( $resized_width, $resized_height, $resized_type ) = $new_size;

        // Get the new image URL
        $resized_url = str_replace( basename( $url ), basename( $dest_file_name ), $url );

        // Return array with resized image information
        $image_array = array(
            'url' => $resized_url,
            'width' => $resized_width,
            'height' => $resized_height,
            'type' => $resized_type
        );

        return $image_array;

    }
}