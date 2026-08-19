<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class XDN_AI_Gemini {
    public static function research( $topic, $settings = array() ) {
        $key = ! empty( $settings['gemini_key'] ) ? trim( $settings['gemini_key'] ) : '';
        $model = ! empty( $settings['gemini_model'] ) ? sanitize_text_field( $settings['gemini_model'] ) : 'gemini-3.6-flash';
        if ( ! $key ) return new WP_Error( 'missing_gemini_key', 'Chưa có Gemini API key.' );

        $prompt = "Bạn là chuyên gia SEO local và biên tập viên nội dung xe đạp cho website tại Ninh Bình.\n\n" .
            "Chủ đề gốc: {$topic}\n\n" .
            "Hãy nghiên cứu Google Search bằng dữ liệu web hiện tại và trả về JSON hợp lệ, không markdown, theo cấu trúc:\n" .
            "{\n  \"topic\": string,\n  \"search_intent\": string,\n  \"keyword_opportunities\": [{\"keyword\": string, \"intent\": string, \"priority\": \"high|medium|low\", \"reason\": string}],\n  \"serp_observations\": [string],\n  \"content_gaps\": [string],\n  \"recommended_titles\": [string],\n  \"questions\": [string],\n  \"content_clusters\": [{\"name\": string, \"keywords\": [string], \"angle\": string}],\n  \"sources\": [{\"title\": string, \"url\": string, \"date\": string, \"domain\": string, \"summary\": string, \"local_angle\": string}]\n}\n\n" .
            "Mở rộng mạnh sang: sửa phanh xe đạp, chỉnh phanh, thay má phanh, phanh kêu; sửa/chỉnh đề trước và đề sau, sang số không mượt, xích nhảy; xích-líp-giò đĩa; săm-lốp-vành-bánh đảo; bảo dưỡng; chẩn đoán lỗi; xe đạp điện; xe học sinh/trẻ em; MTB; road; xe cũ; phụ kiện; an toàn; cung đường đạp xe Ninh Bình; dịch vụ địa phương; tư vấn mua xe; so sánh xe; và tin/cập nhật ngành xe đạp.\n\n" .
            "Hãy tìm 8-15 nguồn mới hoặc có uy tín trên web. Ưu tiên tài liệu kỹ thuật, hãng xe/phụ tùng, chuyên trang xe đạp và nguồn địa phương đáng tin. Với mỗi nguồn, tóm tắt bằng lời của bạn và đề xuất một local_angle riêng cho website Ninh Bình. Không sao chép câu chữ nguồn. Nếu không có ngày xuất bản thì để chuỗi rỗng. Không bịa URL.";

        $body = array(
            'contents' => array(array('parts' => array(array('text' => $prompt)))),
            'tools' => array(array('google_search' => new stdClass())),
            'generationConfig' => array('temperature' => 0.2, 'responseMimeType' => 'application/json')
        );
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent';
        $response = wp_remote_post( $url, array(
            'timeout' => 90,
            'headers' => array('Content-Type' => 'application/json','x-goog-api-key' => $key),
            'body' => wp_json_encode( $body ),
        ) );
        if ( is_wp_error( $response ) ) return $response;
        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );
        if ( $code < 200 || $code >= 300 ) return new WP_Error( 'gemini_http_error', 'Gemini API lỗi: ' . $code . ' ' . wp_strip_all_tags( $raw ) );

        $text = '';
        if ( ! empty( $data['candidates'][0]['content']['parts'] ) ) foreach ( $data['candidates'][0]['content']['parts'] as $part ) if ( isset( $part['text'] ) ) $text .= $part['text'];
        if ( ! $text ) return new WP_Error( 'gemini_empty', 'Gemini không trả về nội dung nghiên cứu.' );
        $text = trim( $text );
        $json = json_decode( $text, true );
        if ( ! is_array( $json ) ) {
            $text = preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $text );
            $json = json_decode( $text, true );
        }
        if ( ! is_array( $json ) ) return new WP_Error( 'gemini_invalid_json', 'Gemini trả về JSON không hợp lệ.' );
        if ( isset( $data['groundingMetadata'] ) ) $json['_grounding_metadata'] = $data['groundingMetadata'];
        return $json;
    }
}
