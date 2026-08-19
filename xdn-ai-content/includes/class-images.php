<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * XDN AI image pipeline.
 * - Inserts images where AI places <!-- XDN_IMAGE: description --> markers.
 * - Can use Google Custom Search Images or OpenAI image generation.
 * - Watermarks article/body images and WooCommerce gallery images.
 * - Never watermarks a WordPress featured thumbnail when the option is enabled.
 */
class XDN_AI_Images {
    public static function init() {
        add_action( 'save_post_product', array( __CLASS__, 'watermark_product_gallery' ), 30, 3 );
    }

    private static function settings() { return get_option( 'xdn_ai_settings', array() ); }

    public static function prepare_article( $post_id, $content, $title = '' ) {
        if ( ! is_string( $content ) ) return $content;
        if ( ! preg_match_all( '/<!--\s*XDN_IMAGE\s*:\s*(.*?)\s*-->/is', $content, $matches, PREG_OFFSET_CAPTURE ) ) return $content;
        $settings = self::settings();
        if ( empty( $settings['auto_images'] ) ) return preg_replace( '/<!--\s*XDN_IMAGE\s*:\s*(.*?)\s*-->/is', '', $content );

        $limit = max( 1, min( 8, absint( $settings['image_limit'] ?? 5 ) ) );
        $done = 0;
        foreach ( $matches[1] as $match ) {
            if ( $done >= $limit ) break;
            $description = sanitize_text_field( $match[0] );
            if ( ! $description ) continue;
            $attachment_id = self::find_or_create_image( $description, $post_id, $title );
            if ( is_wp_error( $attachment_id ) ) continue;
            $url = wp_get_attachment_image_url( $attachment_id, 'large' );
            if ( ! $url ) continue;
            $alt = sanitize_text_field( $description );
            $img = '<figure class="xdn-ai-image"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" /><figcaption>' . esc_html( $description ) . '</figcaption></figure>';
            $content = preg_replace( '/<!--\s*XDN_IMAGE\s*:\s*' . preg_quote( $description, '/' ) . '\s*-->/is', $img, $content, 1 );
            $done++;
        }
        $content = preg_replace( '/<!--\s*XDN_IMAGE\s*:\s*(.*?)\s*-->/is', '', $content );
        return $content;
    }

    private static function find_or_create_image( $description, $post_id, $title ) {
        $settings = self::settings();
        $source = sanitize_key( $settings['image_source'] ?? 'ai' );
        if ( 'google' === $source ) {
            $id = self::google_image( $description, $post_id );
            if ( ! is_wp_error( $id ) && $id ) return $id;
            if ( empty( $settings['image_google_fallback_ai'] ) ) return $id instanceof WP_Error ? $id : new WP_Error( 'image_not_found', 'Không tìm thấy ảnh Google phù hợp.' );
        }
        return self::ai_image( $description, $post_id, $title );
    }

    private static function google_image( $description, $post_id ) {
        $s = self::settings();
        $key = trim( $s['google_image_key'] ?? '' );
        $cx  = trim( $s['google_image_cx'] ?? '' );
        if ( ! $key || ! $cx ) return new WP_Error( 'missing_google_image_key', 'Chưa cấu hình Google Image Search API/CX.' );
        $url = add_query_arg( array(
            'key' => $key,
            'cx' => $cx,
            'q' => $description . ' xe đạp',
            'searchType' => 'image',
            'num' => 5,
            'safe' => 'active',
            'imgSize' => 'large',
        ), 'https://www.googleapis.com/customsearch/v1' );
        $response = wp_remote_get( $url, array( 'timeout' => 30 ) );
        if ( is_wp_error( $response ) ) return $response;
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['items'] ) || ! is_array( $data['items'] ) ) return new WP_Error( 'google_image_empty', 'Google không trả về ảnh.' );
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        foreach ( $data['items'] as $item ) {
            $image_url = ! empty( $item['link'] ) ? esc_url_raw( $item['link'] ) : '';
            if ( ! $image_url ) continue;
            $id = media_sideload_image( $image_url, $post_id, sanitize_text_field( $item['title'] ?? $description ), 'id' );
            if ( is_wp_error( $id ) ) continue;
            update_post_meta( $id, '_xdn_ai_image_source', 'google' );
            update_post_meta( $id, '_xdn_ai_source_url', $image_url );
            self::watermark_attachment( $id, false );
            return $id;
        }
        return new WP_Error( 'google_image_download_failed', 'Không thể tải ảnh Google về Media Library.' );
    }

    private static function ai_image( $description, $post_id, $title ) {
        $s = self::settings();
        $key = trim( $s['openai_key'] ?? '' );
        if ( ! $key ) return new WP_Error( 'missing_openai_key', 'Chưa có OpenAI API key để tạo ảnh.' );
        $prompt = 'Tạo ảnh minh họa biên tập cho bài viết website xe đạp tại Ninh Bình. Chủ đề: ' . $description . '. ' .
            'Ảnh thực tế/biên tập sạch, không chữ, không logo, không watermark, bố cục ngang 16:9, ánh sáng tự nhiên, phù hợp website chuyên nghiệp. Không tạo người nổi tiếng hay thương hiệu giả.';
        $body = array(
            'model' => 'gpt-image-1',
            'prompt' => $prompt,
            'size' => '1536x1024',
            'quality' => 'medium',
        );
        $response = wp_remote_post( 'https://api.openai.com/v1/images/generations', array(
            'timeout' => 180,
            'headers' => array( 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $key ),
            'body' => wp_json_encode( $body ),
        ) );
        if ( is_wp_error( $response ) ) return $response;
        $code = wp_remote_retrieve_response_code( $response );
        $raw = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );
        if ( $code < 200 || $code >= 300 ) return new WP_Error( 'openai_image_error', 'OpenAI Image lỗi: ' . $code . ' ' . wp_strip_all_tags( $raw ) );
        $b64 = $data['data'][0]['b64_json'] ?? '';
        if ( ! $b64 ) return new WP_Error( 'openai_image_empty', 'OpenAI không trả về ảnh.' );
        $binary = base64_decode( $b64, true );
        if ( false === $binary ) return new WP_Error( 'openai_image_decode', 'Không giải mã được ảnh AI.' );
        $tmp = wp_tempnam( 'xdn-ai-image' );
        if ( ! $tmp || false === file_put_contents( $tmp, $binary ) ) return new WP_Error( 'openai_image_temp', 'Không lưu được ảnh AI tạm thời.' );
        $name = sanitize_file_name( ( $title ?: 'xdn-ai' ) . '-' . sanitize_title( $description ) . '.png' );
        $file = array( 'name' => $name, 'tmp_name' => $tmp, 'type' => 'image/png', 'error' => 0, 'size' => filesize( $tmp ) );
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $id = media_handle_sideload( $file, $post_id, sanitize_text_field( $description ) );
        if ( is_wp_error( $id ) ) { @unlink( $tmp ); return $id; }
        update_post_meta( $id, '_xdn_ai_image_source', 'openai' );
        update_post_meta( $id, '_xdn_ai_image_prompt', $prompt );
        self::watermark_attachment( $id, false );
        return $id;
    }

    public static function watermark_product_gallery( $post_id, $post, $update ) {
        if ( 'product' !== $post->post_type || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
        $s = self::settings();
        if ( empty( $s['watermark_products'] ) ) return;
        $ids = array();
        $gallery = get_post_meta( $post_id, '_product_image_gallery', true );
        if ( $gallery ) $ids = array_filter( array_map( 'absint', explode( ',', $gallery ) ) );
        foreach ( $ids as $id ) self::watermark_attachment( $id, true );
    }

    public static function watermark_attachment( $attachment_id, $is_product = false ) {
        $s = self::settings();
        if ( empty( $s['watermark_enabled'] ) ) return false;
        if ( get_post_meta( $attachment_id, '_xdn_ai_watermarked', true ) ) return true;
        $thumb = get_post_meta( $attachment_id, '_xdn_ai_skip_watermark', true );
        if ( $thumb ) return false;
        if ( self::is_featured_thumbnail( $attachment_id ) && ! empty( $s['watermark_skip_thumbnails'] ) ) return false;
        if ( $is_product && empty( $s['watermark_products'] ) ) return false;
        if ( ! $is_product && empty( $s['watermark_articles'] ) ) return false;
        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! file_exists( $file ) ) return false;
        $logo = self::logo_file();
        if ( is_wp_error( $logo ) || ! $logo ) return false;
        $ok = self::apply_gd_watermark( $file, $logo, $s );
        @unlink( $logo );
        if ( ! $ok ) return false;
        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file ) );
        update_post_meta( $attachment_id, '_xdn_ai_watermarked', current_time( 'mysql' ) );
        return true;
    }

    private static function is_featured_thumbnail( $attachment_id ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_thumbnail_id' AND meta_value=%d LIMIT 1", $attachment_id ) );
    }

    private static function logo_file() {
        $s = self::settings();
        $id = absint( $s['watermark_logo_id'] ?? 0 );
        if ( $id ) {
            $file = get_attached_file( $id );
            if ( $file && file_exists( $file ) ) return $file;
        }
        $url = trim( $s['watermark_logo_url'] ?? '' );
        if ( ! $url ) return new WP_Error( 'missing_logo', 'Chưa cấu hình logo watermark.' );
        $tmp = download_url( $url, 30 );
        return is_wp_error( $tmp ) ? $tmp : $tmp;
    }

    private static function apply_gd_watermark( $file, $logo_file, $s ) {
        if ( ! function_exists( 'imagecreatefrompng' ) ) return false;
        $info = @getimagesize( $file );
        $li = @getimagesize( $logo_file );
        if ( ! $info || ! $li ) return false;
        $src = self::gd_load( $file, $info['mime'] );
        $logo = self::gd_load( $logo_file, $li['mime'] );
        if ( ! $src || ! $logo ) return false;
        $sw = imagesx( $src ); $sh = imagesy( $src );
        $lw = imagesx( $logo ); $lh = imagesy( $logo );
        $scale = max( 0.05, min( 0.5, (float) ( $s['watermark_scale'] ?? 0.18 ) ) );
        $nw = max( 30, (int) ( $sw * $scale ) ); $nh = max( 20, (int) ( $lh * ( $nw / max(1,$lw) ) ) );
        $wm = imagecreatetruecolor( $nw, $nh ); imagealphablending($wm,false); imagesavealpha($wm,true);
        $transparent = imagecolorallocatealpha($wm,0,0,0,127); imagefill($wm,0,0,$transparent);
        imagecopyresampled( $wm, $logo, 0, 0, 0, 0, $nw, $nh, $lw, $lh );
        $opacity = max( 0, min( 100, absint( $s['watermark_opacity'] ?? 75 ) ) );
        if ( $opacity < 100 ) { $tmpwm=imagecreatetruecolor($nw,$nh); imagealphablending($tmpwm,false); imagesavealpha($tmpwm,true); $tr=imagecolorallocatealpha($tmpwm,0,0,0,127); imagefill($tmpwm,0,0,$tr); imagecopy($tmpwm,$wm,0,0,0,0,$nw,$nh); imagefilter($tmpwm,IMG_FILTER_COLORIZE,0,0,0,127-$opacity); imagedestroy($wm); $wm=$tmpwm; }
        $margin=max(5,absint($s['watermark_margin']??18)); $pos=sanitize_key($s['watermark_position']??'bottom-right');
        $x=$sw-$nw-$margin; $y=$sh-$nh-$margin;
        if('bottom-left'===$pos){$x=$margin;}elseif('top-right'===$pos){$y=$margin;}elseif('top-left'===$pos){$x=$margin;$y=$margin;}elseif('center'===$pos){$x=(int)(($sw-$nw)/2);$y=(int)(($sh-$nh)/2);}
        imagealphablending($src,true); imagecopy($src,$wm,$x,$y,0,0,$nw,$nh);
        $ok=self::gd_save($src,$file,$info['mime']); imagedestroy($src);imagedestroy($logo);imagedestroy($wm); return $ok;
    }

    private static function gd_load($file,$mime){
        switch($mime){case 'image/jpeg':return @imagecreatefromjpeg($file);case 'image/png':return @imagecreatefrompng($file);case 'image/gif':return @imagecreatefromgif($file);case 'image/webp':return function_exists('imagecreatefromwebp')?@imagecreatefromwebp($file):false;default:return false;}
    }
    private static function gd_save($img,$file,$mime){
        switch($mime){case 'image/jpeg':return imagejpeg($img,$file,90);case 'image/png':imagealphablending($img,false);imagesavealpha($img,true);return imagepng($img,$file,6);case 'image/gif':return imagegif($img,$file);case 'image/webp':return function_exists('imagewebp')?imagewebp($img,$file,90):false;default:return false;}
    }
}
