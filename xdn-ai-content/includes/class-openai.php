<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class XDN_AI_OpenAI {
    public static function generate( $instruction, $settings = array() ) {
        $key = ! empty( $settings['openai_key'] ) ? trim( $settings['openai_key'] ) : '';
        $model = ! empty( $settings['openai_model'] ) ? sanitize_text_field( $settings['openai_model'] ) : 'gpt-5.6-luna';
        if ( ! $key ) return new WP_Error( 'missing_openai_key', 'Chưa có OpenAI API key.' );

        $body = array('model' => $model, 'input' => $instruction);
        $response = wp_remote_post( 'https://api.openai.com/v1/responses', array(
            'timeout' => 120,
            'headers' => array('Content-Type' => 'application/json','Authorization' => 'Bearer ' . $key),
            'body' => wp_json_encode( $body ),
        ) );
        if ( is_wp_error( $response ) ) return $response;
        $code = wp_remote_retrieve_response_code( $response );
        $raw = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );
        if ( $code < 200 || $code >= 300 ) return new WP_Error( 'openai_http_error', 'OpenAI API lỗi: ' . $code . ' ' . wp_strip_all_tags( $raw ) );
        if ( ! empty( $data['output_text'] ) ) return $data['output_text'];
        $text = '';
        if ( ! empty( $data['output'] ) && is_array( $data['output'] ) ) {
            foreach ( $data['output'] as $item ) {
                if ( ! empty( $item['content'] ) && is_array( $item['content'] ) ) {
                    foreach ( $item['content'] as $content ) if ( isset( $content['text'] ) ) $text .= $content['text'];
                }
            }
        }
        return $text ? $text : new WP_Error( 'openai_empty', 'OpenAI không trả về nội dung.' );
    }

    public static function write_article( $research, $settings = array() ) {
        $site = home_url();
        $target = ! empty( $research['target_keyword'] ) ? sanitize_text_field( $research['target_keyword'] ) : '';
        $gap = ! empty( $research['target_content_gap'] ) ? sanitize_textarea_field( $research['target_content_gap'] ) : '';
        $focus = $target ? "\nTỪ KHÓA MỤC TIÊU CHO BÀI NÀY: {$target}\nCONTENT GAP CẦN GIẢI QUYẾT: {$gap}\n" : '';
        $prompt = "Bạn là biên tập viên SEO cho website {$site}, chuyên bán xe đạp tại Ninh Bình.\n\n" .
            "Dữ liệu nghiên cứu từ Gemini/Google Search:\n" . wp_json_encode( $research, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n" .
            $focus . "\n" .
            "Viết một bài tiếng Việt nguyên bản, hữu ích cho người đọc, không sao chép đối thủ. Nếu có từ khóa mục tiêu thì bài phải tập trung tự nhiên vào từ khóa đó và giải quyết content gap tương ứng. Không bịa giá, thông số, chính sách hoặc địa chỉ. Nếu thiếu dữ liệu thì ghi rõ cần bổ sung.\n\n" .
            "Trả về đúng JSON hợp lệ, không markdown fence:\n" .
            "{\"title\":string,\"slug\":string,\"excerpt\":string,\"focus_keyword\":string,\"meta_description\":string,\"content_html\":string,\"faq\":[{\"question\":string,\"answer\":string}]}";
        return self::generate( $prompt, $settings );
    }
}
