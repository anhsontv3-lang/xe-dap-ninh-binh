<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class XDN_AI_Images {
    public static function recent_media( $limit = 20 ) {
        $ids = get_posts(array('post_type'=>'attachment','post_mime_type'=>'image','post_status'=>'inherit','posts_per_page'=>(int)$limit,'orderby'=>'date','order'=>'DESC','fields'=>'ids'));
        $out=array(); foreach($ids as $id) $out[] = array('id'=>$id,'url'=>wp_get_attachment_image_url($id,'large'),'alt'=>get_post_meta($id,'_wp_attachment_image_alt',true)); return $out;
    }
    public static function generate( $prompt, $settings = array() ) {
        $key = !empty($settings['openai_key']) ? trim($settings['openai_key']) : '';
        $model = !empty($settings['image_model']) ? sanitize_text_field($settings['image_model']) : 'gpt-image-2';
        if(!$key) return new WP_Error('missing_openai_key','Chưa có OpenAI API key.');
        $body=array('model'=>$model,'prompt'=>$prompt,'size'=>!empty($settings['image_size'])?$settings['image_size']:'1536x1024','quality'=>'medium','output_format'=>'png');
        $r=wp_remote_post('https://api.openai.com/v1/images',array('timeout'=>180,'headers'=>array('Content-Type'=>'application/json','Authorization'=>'Bearer '.$key),'body'=>wp_json_encode($body)));
        if(is_wp_error($r)) return $r;
        $code=wp_remote_retrieve_response_code($r); $data=json_decode(wp_remote_retrieve_body($r),true);
        if($code<200||$code>=300) return new WP_Error('openai_image_error','OpenAI Images API lỗi: '.$code.' '.wp_strip_all_tags(wp_remote_retrieve_body($r)));
        $b64=$data['data'][0]['b64_json']??''; if(!$b64) return new WP_Error('image_empty','OpenAI không trả ảnh.');
        $bytes=base64_decode($b64); if(!$bytes) return new WP_Error('image_decode','Không giải mã được ảnh.');
        $upload=wp_upload_bits(sanitize_file_name('xdn-ai-'.time().'.png'),null,$bytes); if(!empty($upload['error'])) return new WP_Error('image_upload',$upload['error']);
        require_once ABSPATH.'wp-admin/includes/image.php'; require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/media.php';
        $file=array('name'=>basename($upload['file']),'type'=>'image/png','tmp_name'=>$upload['file'],'error'=>0,'size'=>filesize($upload['file']));
        $id=media_handle_sideload($file,0,array('post_title'=>sanitize_text_field($prompt),'post_excerpt'=>'AI-generated image by XDN AI Content Engine'));
        if(is_wp_error($id)){@unlink($upload['file']);return $id;}
        return $id;
    }
    public static function set_alt( $id, $alt ) { if($id) update_post_meta($id,'_wp_attachment_image_alt',sanitize_text_field($alt)); }
}
