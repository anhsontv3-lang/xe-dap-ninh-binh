<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class XDN_AI_OpenAI {
    public static function generate( $instruction, $settings = array() ) {
        $key = ! empty( $settings['openai_key'] ) ? trim( $settings['openai_key'] ) : '';
        $model = ! empty( $settings['openai_model'] ) ? sanitize_text_field( $settings['openai_model'] ) : 'gpt-5.6-luna';
        if ( ! $key ) return new WP_Error( 'missing_openai_key', 'Chưa có OpenAI API key.' );
        $response = wp_remote_post( 'https://api.openai.com/v1/responses', array(
            'timeout' => 120,
            'headers' => array('Content-Type'=>'application/json','Authorization'=>'Bearer '.$key),
            'body' => wp_json_encode(array('model'=>$model,'input'=>$instruction)),
        ) );
        if ( is_wp_error($response) ) return $response;
        $code=wp_remote_retrieve_response_code($response); $raw=wp_remote_retrieve_body($response); $data=json_decode($raw,true);
        if($code<200 || $code>=300) return new WP_Error('openai_http_error','OpenAI API lỗi: '.$code.' '.wp_strip_all_tags($raw));
        if(!empty($data['output_text'])) return $data['output_text'];
        $text='';
        if(!empty($data['output']) && is_array($data['output'])) foreach($data['output'] as $item) if(!empty($item['content']) && is_array($item['content'])) foreach($item['content'] as $content) if(isset($content['text'])) $text.=$content['text'];
        return $text ? $text : new WP_Error('openai_empty','OpenAI không trả về nội dung.');
    }

    public static function collect_sources( $settings ) {
        $urls = ! empty($settings['source_urls']) && is_array($settings['source_urls']) ? $settings['source_urls'] : array();
        $out=array();
        foreach($urls as $url){
            $url=esc_url_raw(trim($url)); if(!$url) continue;
            $r=wp_remote_get($url,array('timeout'=>20,'redirection'=>3,'user-agent'=>'XDN-AI-Content/0.2'));
            if(is_wp_error($r)) continue;
            $html=wp_remote_retrieve_body($r); if(!$html) continue;
            $title=''; if(preg_match('/<title[^>]*>(.*?)<\/title>/is',$html,$m)) $title=wp_strip_all_tags(html_entity_decode($m[1],ENT_QUOTES,'UTF-8'));
            $text=wp_strip_all_tags($html); $text=preg_replace('/\s+/u',' ',html_entity_decode($text,ENT_QUOTES,'UTF-8')); $text=trim($text);
            $out[]=array('url'=>$url,'title'=>sanitize_text_field($title),'excerpt'=>mb_substr($text,0,3500));
            if(count($out)>=5) break;
        }
        return $out;
    }

    public static function write_article( $research, $settings = array() ) {
        $site=home_url();
        $sources=self::collect_sources($settings);
        $prompt="Bạn là biên tập viên SEO cho website {$site}, chuyên xe đạp tại Ninh Bình.\n\n".
            "Dữ liệu nghiên cứu:\n".wp_json_encode($research,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n\n".
            "Nguồn tham khảo bên ngoài (chỉ dùng để nghiên cứu, không sao chép):\n".wp_json_encode($sources,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n\n".
            "Viết bài tiếng Việt nguyên bản, hữu ích, tự nhiên, có local SEO. Không sao chép câu dài từ nguồn. Không bịa giá, thông số, chính sách, địa chỉ. Nếu nguồn mâu thuẫn, ưu tiên thông tin có thể kiểm chứng và diễn đạt thận trọng.\n\n".
            "Hãy tự xác định những đoạn cần hình minh họa. Tại vị trí phù hợp chèn marker dạng <!-- XDN_IMAGE: mô tả hình ảnh -->. Không chèn marker ở mọi đoạn; chỉ dùng khi hình giúp người đọc hiểu rõ hơn.\n\n".
            "Trả về JSON hợp lệ: {\"title\":string,\"slug\":string,\"excerpt\":string,\"focus_keyword\":string,\"meta_description\":string,\"content_html\":string,\"faq\":[{\"question\":string,\"answer\":string}],\"image_prompts\":[string]}";
        return self::generate($prompt,$settings);
    }
}
