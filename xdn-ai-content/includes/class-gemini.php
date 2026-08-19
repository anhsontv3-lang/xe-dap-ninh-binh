<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class XDN_AI_Gemini {
    public static function research( $topic, $settings = array() ) {
        $key = ! empty( $settings['gemini_key'] ) ? trim( $settings['gemini_key'] ) : '';
        $model = ! empty( $settings['gemini_model'] ) ? sanitize_text_field( $settings['gemini_model'] ) : 'gemini-3.6-flash';
        if ( ! $key ) return new WP_Error( 'missing_gemini_key', 'Chưa có Gemini API key.' );

        $schema = array(
            'type' => 'object',
            'properties' => array(
                'topic' => array('type'=>'string'),
                'search_intent' => array('type'=>'string'),
                'keyword_opportunities' => array('type'=>'array','items'=>array(
                    'type'=>'object','properties'=>array(
                        'keyword'=>array('type'=>'string'), 'intent'=>array('type'=>'string'),
                        'priority'=>array('type'=>'string','enum'=>array('high','medium','low')),
                        'opportunity_score'=>array('type'=>'integer','minimum'=>0,'maximum'=>100),
                        'reason'=>array('type'=>'string')
                    ), 'required'=>array('keyword','intent','priority','opportunity_score','reason'), 'additionalProperties'=>false
                )),
                'serp_observations' => array('type'=>'array','items'=>array('type'=>'string')),
                'content_gaps' => array('type'=>'array','items'=>array('type'=>'string')),
                'recommended_titles' => array('type'=>'array','items'=>array('type'=>'string')),
                'questions' => array('type'=>'array','items'=>array('type'=>'string')),
                'sources' => array('type'=>'array','items'=>array(
                    'type'=>'object','properties'=>array('title'=>array('type'=>'string'),'url'=>array('type'=>'string')),
                    'required'=>array('title','url'),'additionalProperties'=>false
                ))
            ),
            'required'=>array('topic','search_intent','keyword_opportunities','serp_observations','content_gaps','recommended_titles','questions','sources'),
            'additionalProperties'=>false
        );

        $prompt = "Bạn là chuyên gia SEO local cho website bán xe đạp tại Ninh Bình.\n\n" .
            "Chủ đề gốc: {$topic}\n\n" .
            "Dùng Google Search để nghiên cứu SERP hiện tại. Tìm cơ hội nội dung có khả năng cạnh tranh thực tế, ưu tiên ý định mua hàng, địa phương Ninh Bình và các truy vấn dài. Phân tích điểm mạnh/yếu của kết quả đang xuất hiện, tìm content gap và không sao chép nội dung đối thủ. Chỉ đưa ra nhận định có cơ sở từ kết quả tìm kiếm. opportunity_score là điểm cơ hội do bạn ước lượng (0-100), không phải điểm của Google. Trả về đúng JSON schema.";

        $body = array(
            'contents' => array(array('parts' => array(array('text' => $prompt)))),
            'tools' => array(array('google_search' => new stdClass())),
            'generationConfig' => array(
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema
            )
        );

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent';
        $response = wp_remote_post( $url, array(
            'timeout' => 90,
            'headers' => array('Content-Type'=>'application/json','x-goog-api-key'=>$key),
            'body' => wp_json_encode( $body ),
        ) );
        if ( is_wp_error( $response ) ) return $response;
        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );
        if ( $code < 200 || $code >= 300 ) return new WP_Error( 'gemini_http_error', 'Gemini API lỗi: ' . $code . ' ' . wp_strip_all_tags( $raw ) );

        $text = '';
        if ( ! empty( $data['candidates'][0]['content']['parts'] ) ) {
            foreach ( $data['candidates'][0]['content']['parts'] as $part ) if ( isset($part['text']) ) $text .= $part['text'];
        }
        if ( ! $text ) return new WP_Error( 'gemini_empty', 'Gemini không trả về nội dung nghiên cứu.' );
        $json = json_decode( trim($text), true );
        if ( ! is_array($json) ) return new WP_Error( 'gemini_invalid_json', 'Gemini trả về JSON không hợp lệ.' );

        $json['_grounding_metadata'] = $data['candidates'][0]['groundingMetadata'] ?? array();
        return $json;
    }
}
