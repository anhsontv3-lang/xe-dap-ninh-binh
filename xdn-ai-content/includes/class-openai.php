<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class XDN_AI_OpenAI {
    public static function generate( $instruction, $settings = array() ) {
        $key=!empty($settings['openai_key'])?trim($settings['openai_key']):'';$model=!empty($settings['openai_model'])?sanitize_text_field($settings['openai_model']):'gpt-5.6-luna';
        if(!$key)return new WP_Error('missing_openai_key','Chưa có OpenAI API key.');
        $body=array('model'=>$model,'input'=>$instruction);
        $r=wp_remote_post('https://api.openai.com/v1/responses',array('timeout'=>150,'headers'=>array('Content-Type'=>'application/json','Authorization'=>'Bearer '.$key),'body'=>wp_json_encode($body)));
        if(is_wp_error($r))return $r;$code=wp_remote_retrieve_response_code($r);$raw=wp_remote_retrieve_body($r);$data=json_decode($raw,true);
        if($code<200||$code>=300)return new WP_Error('openai_http_error','OpenAI API lỗi: '.$code.' '.wp_strip_all_tags($raw));
        if(!empty($data['output_text']))return $data['output_text'];$text='';
        foreach(($data['output']??array()) as $item)foreach(($item['content']??array()) as $c)if(isset($c['text']))$text.=$c['text'];
        return $text? $text:new WP_Error('openai_empty','OpenAI không trả về nội dung.');
    }
    public static function write_article($research,$settings=array(),$opportunity=array()){
        $site=home_url();$kw=$opportunity['keyword']??($research['keyword_opportunities'][0]['keyword']??'xe đạp Ninh Bình');
        $products=XDN_AI_WooCommerce::products($kw,8);$recent=get_posts(array('post_type'=>'post','post_status'=>'publish','posts_per_page'=>12,'fields'=>'ids'));
        $prompt="Bạn là trưởng biên tập SEO cho {$site}, chuyên xe đạp tại Ninh Bình.\nKeyword mục tiêu: {$kw}\nOpportunity: ".wp_json_encode($opportunity,JSON_UNESCAPED_UNICODE)."\nNghiên cứu Gemini/Google: ".wp_json_encode($research,JSON_UNESCAPED_UNICODE)."\nSản phẩm WooCommerce thật: ".wp_json_encode($products,JSON_UNESCAPED_UNICODE)."\nCác bài cũ để tránh trùng: ".wp_json_encode($recent)."\n\nViết bài tiếng Việt nguyên bản, people-first, hữu ích, local SEO. Không copy/viết lại từng đoạn của đối thủ. Không bịa giá, thông số, địa chỉ, bảo hành. Chỉ dùng dữ liệu sản phẩm được cung cấp. Có H2/H3 rõ ràng, đoạn ngắn, bullet khi hữu ích, FAQ và CTA tự nhiên. Không nhồi keyword.\n\nTrả JSON duy nhất với schema: {\"title\":string,\"seo_title\":string,\"slug\":string,\"excerpt\":string,\"focus_keyword\":string,\"meta_description\":string,\"canonical\":string,\"image_alt\":string,\"content_html\":string,\"faq\":[{\"question\":string,\"answer\":string}]}";
        return self::generate($prompt,$settings);
    }
}
