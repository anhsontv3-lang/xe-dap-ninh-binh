<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class XDN_AI_Image_Manager {
    public static function sources() {
        return array(
            'generated' => 'Tạo ảnh bằng OpenAI Image API',
            'site'      => 'Ưu tiên ảnh có sẵn trên website/WooCommerce',
            'licensed' => 'Ảnh từ nguồn được cấp phép/cho phép sử dụng',
        );
    }

    public static function build_prompt( $title, $topic, $products = array() ) {
        $product_names = array();
        foreach ( (array) $products as $product ) {
            if ( is_object( $product ) && method_exists( $product, 'get_name' ) ) {
                $product_names[] = $product->get_name();
            } elseif ( is_array( $product ) && ! empty( $product['name'] ) ) {
                $product_names[] = $product['name'];
            }
        }

        $prompt = 'Tạo ảnh hero/blog chuyên nghiệp cho website bán xe đạp tại Ninh Bình. ' .
            'Chủ đề: ' . sanitize_text_field( $topic ) . '. ' .
            'Tiêu đề: ' . sanitize_text_field( $title ) . '. ' .
            'Phong cách ảnh chân thực, sáng, sạch, thương mại, phù hợp website Việt Nam; không chèn chữ, logo, watermark hoặc tên thương hiệu giả. ' .
            'Không tạo thông số sản phẩm giả trong ảnh.';

        if ( $product_names ) {
            $prompt .= ' Sản phẩm liên quan: ' . implode( ', ', array_map( 'sanitize_text_field', $product_names ) ) . '.';
        }
        return $prompt;
    }

    public static function choose_source( $preferred = 'auto' ) {
        if ( $preferred === 'site' || $preferred === 'generated' || $preferred === 'licensed' ) return $preferred;
        return 'site';
    }
}
