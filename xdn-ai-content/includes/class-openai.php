<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class XDN_AI_OpenAI {
    public static function generate( $instruction, $settings = array(), $schema = null ) {
        $key = ! empty( $settings['openai_key'] ) ? trim( $settings['openai_key'] ) : '';
        $model = ! empty( $settings['openai_model'] ) ? sanitize_text_field( $settings['openai_model'] ) : 'gpt-5.6';
        if ( ! $key ) return new WP_Error( 'missing_openai_key', 'Chưa có OpenAI API key.' );

        $body = array(
            'model' => $model,
            'input' => $instruction,
        );
        if ( is_array( $schema ) ) {
            $body['text'] = array(
                'format' => array(
                    'type' => 'json_schema',
                    'name' => 'xdn_article',
                    'strict' => true,
                    'schema' => $schema,
                ),
            );
        }

        $response = wp_remote_post( 'https://api.openai.com/v1/responses', array(
            'timeout' => 120,
            'headers' => array('Content-Type'=>'application/json','Authorization'=>'Bearer ' . $key),
            'body' => wp_json_encode( $body ),
        ) );
        if ( is_wp_error( $response ) ) return $response;
        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );
        if ( $code < 200 || $code >= 300 ) return new WP_Error( 'openai_http_error', 'OpenAI API lỗi: ' . $code . ' ' . wp_strip_all_tags( $raw ) );

        if ( ! empty( $data['output_text'] ) ) return $data['output_text'];
        $text = '';
        if ( ! empty( $data['output'] ) && is_array( $data['output'] ) ) {
            foreach ( $data['output'] as $item ) if ( ! empty($item['content']) && is_array($item['content']) ) foreach ($item['content'] as $content) if (isset($content['text'])) $text .= $content['text'];
        }
        return $text ? $text : new WP_Error( 'openai_empty', 'OpenAI không trả về nội dung.' );
    }

    public static function write_article( $research, $settings = array() ) {
        $site = home_url();
        $schema = array(
            'type'=>'object','additionalProperties'=>false,
            'properties'=>array(
                'title'=>array('type'=>'string'), 'slug'=>array('type'=>'string'), 'excerpt'=>array('type'=>'string'),
                'focus_keyword'=>array('type'=>'string'), 'meta_description'=>array('type'=>'string'),
                'content_html'=>array('type'=>'string'),
                'faq'=>array('type'=>'array','items'=>array('type'=>'object','additionalProperties'=>false,'properties'=>array('question'=>array('type'=>'string'),'answer'=>array('type'=>'string')),'required'=>array('question','answer')))
            ),
            'required'=>array('title','slug','excerpt','focus_keyword','meta_description','content_html','faq')
        );
        $prompt = "Bạn là biên tập viên SEO cho website {$site}, chuyên bán xe đạp tại Ninh Bình.\n\n" .
            "Dữ liệu nghiên cứu từ Gemini/Google Search:\n" . wp_json_encode( $research, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n\n" .
            "Viết một bài tiếng Việt nguyên bản, hữu ích cho người đọc, không sao chép đối thủ. Ưu tiên trải nghiệm thực tế, thông tin địa phương và ý định tìm kiếm. Không bịa giá, thông số, chính sách hoặc địa chỉ. Nếu thiếu dữ liệu thì không tự suy đoán. Content HTML phải có H2/H3 hợp lý, đoạn văn dễ đọc và FAQ. Trả về đúng schema JSON.";
        return self::generate( $prompt, $settings, $schema );
    }
}
