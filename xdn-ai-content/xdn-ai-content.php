<?php
/**
 * Plugin Name: XDN AI Content Engine
 * Description: AI SEO research, Gemini Google Search grounding, GPT content generation, Rank Math, WooCommerce, images and scheduling for Xe Đạp Ninh Bình.
 * Version: 0.9.0-beta
 * Author: Xe Đạp Ninh Bình
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: xdn-ai-content
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'XDN_AI_VERSION', '0.9.0-beta' );
define( 'XDN_AI_FILE', __FILE__ );
define( 'XDN_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'XDN_AI_URL', plugin_dir_url( __FILE__ ) );

require_once XDN_AI_DIR . 'includes/class-openai.php';
require_once XDN_AI_DIR . 'includes/class-gemini.php';
require_once XDN_AI_DIR . 'includes/class-rankmath.php';
require_once XDN_AI_DIR . 'includes/class-woocommerce.php';
require_once XDN_AI_DIR . 'includes/class-images.php';
require_once XDN_AI_DIR . 'includes/class-admin.php';

final class XDN_AI_Content_Engine {
    public static function init() {
        XDN_AI_Admin::init();
        add_action( 'xdn_ai_daily_research', array( __CLASS__, 'scheduled_research' ) );
        add_action( 'xdn_ai_publish_queue', array( __CLASS__, 'publish_queue' ) );
        if ( ! wp_next_scheduled( 'xdn_ai_publish_queue' ) ) {
            wp_schedule_event( time() + 300, 'hourly', 'xdn_ai_publish_queue' );
        }
    }
    public static function activate() {
        if ( ! wp_next_scheduled( 'xdn_ai_daily_research' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'xdn_ai_daily_research' );
        if ( ! wp_next_scheduled( 'xdn_ai_publish_queue' ) ) wp_schedule_event( time() + 300, 'hourly', 'xdn_ai_publish_queue' );
    }
    public static function deactivate() {
        wp_clear_scheduled_hook( 'xdn_ai_daily_research' );
        wp_clear_scheduled_hook( 'xdn_ai_publish_queue' );
    }
    public static function scheduled_research() {
        $s = get_option( 'xdn_ai_settings', array() );
        if ( empty( $s['auto_research'] ) || empty( $s['gemini_key'] ) ) return;
        $seed = ! empty( $s['seed_topic'] ) ? $s['seed_topic'] : 'xe đạp Ninh Bình';
        $result = XDN_AI_Gemini::research( $seed, $s );
        if ( is_wp_error( $result ) ) { update_option( 'xdn_ai_last_error', $result->get_error_message(), false ); return; }
        update_option( 'xdn_ai_last_research', $result, false );
        update_option( 'xdn_ai_last_research_at', current_time( 'mysql' ), false );
    }
    public static function publish_queue() {
        $s = get_option( 'xdn_ai_settings', array() );
        if ( empty( $s['auto_publish'] ) ) return;
        $now = current_time( 'timestamp' );
        $q = get_posts( array('post_type'=>'post','post_status'=>'future','posts_per_page'=>10,'orderby'=>'date','order'=>'ASC') );
        foreach ( $q as $post ) {
            if ( get_post_meta( $post->ID, '_xdn_ai_generated', true ) && get_post_time( 'U', false, $post ) <= $now ) wp_publish_post( $post->ID );
        }
    }
}
register_activation_hook( __FILE__, array( 'XDN_AI_Content_Engine', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'XDN_AI_Content_Engine', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'XDN_AI_Content_Engine', 'init' ) );
