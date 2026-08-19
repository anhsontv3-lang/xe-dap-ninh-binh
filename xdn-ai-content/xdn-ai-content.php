<?php
/**
 * Plugin Name: XDN AI Content Engine
 * Plugin URI: https://xedapninhbinh.com
 * Description: AI SEO research, source-driven content generation, weekly publishing and automatic logo watermarking for WordPress images.
 * Version: 0.2.0
 * Author: Xe Đạp Ninh Bình
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: xdn-ai-content
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'XDN_AI_VERSION', '0.2.0' );
define( 'XDN_AI_FILE', __FILE__ );
define( 'XDN_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'XDN_AI_URL', plugin_dir_url( __FILE__ ) );

require_once XDN_AI_DIR . 'includes/class-openai.php';
require_once XDN_AI_DIR . 'includes/class-gemini.php';
require_once XDN_AI_DIR . 'includes/class-image.php';
require_once XDN_AI_DIR . 'includes/class-admin.php';

final class XDN_AI_Content_Engine {
    public static function init() {
        XDN_AI_Admin::init();
        XDN_AI_Image::init();
        add_action( 'xdn_ai_daily_research', array( __CLASS__, 'scheduled_research' ) );
        add_action( 'xdn_ai_daily_content', array( __CLASS__, 'scheduled_content' ) );
    }

    public static function activate() {
        if ( ! wp_next_scheduled( 'xdn_ai_daily_research' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'xdn_ai_daily_research' );
        }
        if ( ! wp_next_scheduled( 'xdn_ai_daily_content' ) ) {
            wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', 'xdn_ai_daily_content' );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'xdn_ai_daily_research' );
        wp_clear_scheduled_hook( 'xdn_ai_daily_content' );
    }

    public static function scheduled_research() {
        $settings = get_option( 'xdn_ai_settings', array() );
        if ( empty( $settings['auto_research'] ) || empty( $settings['gemini_key'] ) ) return;
        $seed = ! empty( $settings['seed_topic'] ) ? $settings['seed_topic'] : 'xe đạp Ninh Bình';
        $result = XDN_AI_Gemini::research( $seed, $settings );
        if ( is_wp_error( $result ) ) { update_option( 'xdn_ai_last_error', $result->get_error_message(), false ); return; }
        update_option( 'xdn_ai_last_research', $result, false );
        update_option( 'xdn_ai_last_research_at', current_time( 'mysql' ), false );
    }

    public static function scheduled_content() {
        $settings = get_option( 'xdn_ai_settings', array() );
        if ( empty( $settings['auto_publish'] ) || empty( $settings['openai_key'] ) ) return;
        $week_target = max( 1, min( 7, (int) ( $settings['posts_per_week'] ?? 7 ) ) );
        $week_start = gmdate( 'Y-m-d', current_time( 'timestamp' ) - (int) gmdate( 'N', current_time( 'timestamp' ) ) * DAY_IN_SECONDS + DAY_IN_SECONDS );
        $count = (int) get_option( 'xdn_ai_week_count_' . $week_start, 0 );
        if ( $count >= $week_target ) return;

        $topics = ! empty( $settings['topic_pool'] ) && is_array( $settings['topic_pool'] ) ? $settings['topic_pool'] : self::default_topics();
        $index = (int) get_option( 'xdn_ai_topic_index', 0 );
        $topic = $topics[ $index % count( $topics ) ];
        update_option( 'xdn_ai_topic_index', $index + 1, false );

        $research = array(
            'topic' => $topic,
            'search_intent' => 'informational/local/commercial',
            'keyword_opportunities' => array( array( 'keyword' => $topic, 'intent' => 'informational', 'priority' => 'high', 'reason' => 'Chủ đề trong kế hoạch nội dung XDN' ) ),
            'content_gaps' => array(),
            'recommended_titles' => array( $topic ),
            'questions' => array(),
            'sources' => array(),
        );
        if ( ! empty( $settings['gemini_key'] ) ) {
            $fresh = XDN_AI_Gemini::research( $topic, $settings );
            if ( ! is_wp_error( $fresh ) ) $research = $fresh;
        }
        $result = XDN_AI_OpenAI::write_article( $research, $settings );
        if ( is_wp_error( $result ) ) { update_option( 'xdn_ai_last_error', $result->get_error_message(), false ); return; }
        $json = json_decode( trim( preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', trim( $result ) ) ), true );
        if ( ! is_array( $json ) ) return;
        $status = ! empty( $settings['publish_mode'] ) && $settings['publish_mode'] === 'publish' ? 'publish' : 'draft';
        $post_id = wp_insert_post( array(
            'post_title' => sanitize_text_field( $json['title'] ?? $topic ),
            'post_name' => sanitize_title( $json['slug'] ?? ( $json['title'] ?? $topic ) ),
            'post_excerpt' => wp_kses_post( $json['excerpt'] ?? '' ),
            'post_content' => wp_kses_post( $json['content_html'] ?? '' ),
            'post_status' => $status,
            'post_type' => 'post',
        ), true );
        if ( is_wp_error( $post_id ) ) { update_option( 'xdn_ai_last_error', $post_id->get_error_message(), false ); return; }
        update_post_meta( $post_id, '_xdn_ai_focus_keyword', sanitize_text_field( $json['focus_keyword'] ?? $topic ) );
        update_post_meta( $post_id, '_xdn_ai_meta_description', sanitize_text_field( $json['meta_description'] ?? '' ) );
        update_post_meta( $post_id, '_xdn_ai_source_data', wp_json_encode( $research, JSON_UNESCAPED_UNICODE ) );
        update_option( 'xdn_ai_week_count_' . $week_start, $count + 1, false );
        update_option( 'xdn_ai_last_post_id', $post_id, false );
    }

    public static function default_topics() {
        return array(
            'Sửa chữa phanh xe đạp', 'Sửa chữa đề xe đạp', 'Xích xe đạp bị tuột và cách xử lý',
            'Cách bảo dưỡng xe đạp đúng cách', 'Cách chọn xe đạp theo chiều cao', 'Xe đạp cho học sinh',
            'Các lỗi xe đạp thường gặp', 'Cách vệ sinh xe đạp', 'Khi nào cần thay má phanh xe đạp',
            'Cách căn chỉnh đề xe đạp', 'Bánh xe đạp bị đảo phải làm sao', 'Kinh nghiệm mua xe đạp cũ',
            'Phụ kiện xe đạp cần thiết', 'Xe đạp thể thao cho người mới', 'Xe đạp địa hình và cách chọn',
            'Bảo quản xe đạp trong mùa mưa', 'Sửa xích và líp xe đạp', 'Bảo dưỡng xe đạp điện',
            'Mua xe đạp tại Ninh Bình', 'Đạp xe và các cung đường đẹp ở Ninh Bình',
        );
    }
}

register_activation_hook( __FILE__, array( 'XDN_AI_Content_Engine', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'XDN_AI_Content_Engine', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'XDN_AI_Content_Engine', 'init' ) );
