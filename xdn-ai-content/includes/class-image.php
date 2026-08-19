<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class XDN_AI_Image {
    public static function init() {
        add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'watermark_attachment' ), 20, 2 );
    }

    public static function watermark_attachment( $metadata, $attachment_id ) {
        $settings = get_option( 'xdn_ai_settings', array() );
        if ( empty( $settings['logo_id'] ) || empty( $settings['watermark_enabled'] ) ) return $metadata;
        if ( (int) $settings['logo_id'] === (int) $attachment_id ) return $metadata;
        if ( get_post_meta( $attachment_id, '_xdn_ai_logo_processed', true ) ) return $metadata;

        $file = get_attached_file( $attachment_id );
        if ( $file && self::apply_logo( $file, $settings ) ) {
            if ( ! empty( $metadata['sizes'] ) ) {
                $base = trailingslashit( dirname( $file ) );
                foreach ( $metadata['sizes'] as $size ) {
                    if ( empty( $size['file'] ) ) continue;
                    self::apply_logo( $base . $size['file'], $settings );
                }
            }
            update_post_meta( $attachment_id, '_xdn_ai_logo_processed', 1 );
            if ( ! get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) {
                $title = get_the_title( $attachment_id );
                if ( $title ) update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $title ) );
            }
        }
        return $metadata;
    }

    public static function apply_logo( $image_path, $settings ) {
        if ( ! $image_path || ! file_exists( $image_path ) || ! function_exists( 'imagecreatefrompng' ) ) return false;
        $info = @getimagesize( $image_path );
        if ( ! $info || empty( $info[2] ) ) return false;
        $logo_path = get_attached_file( (int) $settings['logo_id'] );
        if ( ! $logo_path || ! file_exists( $logo_path ) ) return false;
        $img = self::load_image( $image_path, $info[2] );
        if ( ! $img ) return false;
        $li = @getimagesize( $logo_path );
        if ( ! $li || ! $li[0] || ! $li[1] ) { imagedestroy($img); return false; }
        $logo = self::load_image( $logo_path, $li[2] );
        if ( ! $logo ) { imagedestroy($img); return false; }

        $w = imagesx($img); $h = imagesy($img);
        $ratio = max( 0.05, min( 0.30, (float) ($settings['logo_size'] ?? 12) / 100 ) );
        $lw = max( 20, (int) round( $w * $ratio ) );
        $lh = max( 20, (int) round( $lw * $li[1] / $li[0] ) );
        $resized = imagecreatetruecolor($lw,$lh);
        imagealphablending($resized,false); imagesavealpha($resized,true);
        $transparent=imagecolorallocatealpha($resized,0,0,0,127); imagefill($resized,0,0,$transparent);
        imagecopyresampled($resized,$logo,0,0,0,0,$lw,$lh,$li[0],$li[1]);

        $margin = max( 5, (int) round($w * ((float)($settings['logo_margin'] ?? 2) / 100)) );
        $pos = $settings['logo_position'] ?? 'bottom-right';
        $x=$margin; $y=$h-$lh-$margin;
        if($pos==='top-left'){ $x=$margin; $y=$margin; }
        elseif($pos==='top-right'){ $x=$w-$lw-$margin; $y=$margin; }
        elseif($pos==='bottom-left'){ $x=$margin; $y=$h-$lh-$margin; }
        elseif($pos==='center'){ $x=(int)(($w-$lw)/2); $y=(int)(($h-$lh)/2); }
        else { $x=$w-$lw-$margin; $y=$h-$lh-$margin; }
        $opacity=max(0,min(100,(int)($settings['logo_opacity'] ?? 85)));
        imagecopymerge($img,$resized,$x,$y,0,0,$lw,$lh,$opacity);
        $ok=self::save_image($img,$image_path,$info[2]);
        imagedestroy($resized); imagedestroy($logo); imagedestroy($img);
        return $ok;
    }

    private static function load_image($path,$type){
        if($type===IMAGETYPE_JPEG) return @imagecreatefromjpeg($path);
        if($type===IMAGETYPE_PNG) return @imagecreatefrompng($path);
        if(defined('IMAGETYPE_WEBP') && $type===IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) return @imagecreatefromwebp($path);
        return false;
    }
    private static function save_image($img,$path,$type){
        if($type===IMAGETYPE_JPEG) return @imagejpeg($img,$path,90);
        if($type===IMAGETYPE_PNG) return @imagepng($img,$path,6);
        if(defined('IMAGETYPE_WEBP') && $type===IMAGETYPE_WEBP && function_exists('imagewebp')) return @imagewebp($img,$path,90);
        return false;
    }

    public static function process_existing_images(){
        $settings=get_option('xdn_ai_settings',array());
        if(empty($settings['logo_id']) || empty($settings['watermark_enabled'])) return 0;
        $ids=get_posts(array('post_type'=>'attachment','post_mime_type'=>'image','post_status'=>'inherit','posts_per_page'=>-1,'fields'=>'ids','meta_query'=>array(array('key'=>'_xdn_ai_logo_processed','compare'=>'NOT EXISTS'))));
        $count=0;
        foreach($ids as $id){
            if((int)$id===(int)$settings['logo_id']) continue;
            $file=get_attached_file($id);
            if($file && self::apply_logo($file,$settings)){
                update_post_meta($id,'_xdn_ai_logo_processed',1);
                $meta=wp_generate_attachment_metadata($id,$file);
                if($meta) wp_update_attachment_metadata($id,$meta);
                $count++;
            }
        }
        return $count;
    }
}
