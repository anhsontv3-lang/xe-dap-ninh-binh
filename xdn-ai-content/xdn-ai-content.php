<?php
/**
 * Plugin Name: XDN AI Content Engine
 * Plugin URI: https://xedapninhbinh.com
 * Description: AI SEO research, content generation, source research, post management and WordPress scheduling for Xe Đạp Ninh Bình.
 * Version: 1.6.4-beta
 * Author: Xe Đạp Ninh Bình
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: xdn-ai-content
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'XDN_AI_VERSION', '1.6.4-beta' );
define( 'XDN_AI_FILE', __FILE__ );
define( 'XDN_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'XDN_AI_URL', plugin_dir_url( __FILE__ ) );

require_once XDN_AI_DIR . 'includes/class-openai.php';
require_once XDN_AI_DIR . 'includes/class-gemini.php';
require_once XDN_AI_DIR . 'includes/class-admin.php';
require_once XDN_AI_DIR . 'includes/class-content-hub.php';

final class XDN_AI_Content_Engine {
    public static function init() {
        XDN_AI_Admin::init();
        XDN_AI_Content_Hub::init();
        add_action( 'xdn_ai_daily_research', array( __CLASS__, 'scheduled_research' ) );
    }
    public static function activate() {
        if ( ! wp_next_scheduled( 'xdn_ai_daily_research' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'xdn_ai_daily_research' );
    }
    public static function deactivate() { wp_clear_scheduled_hook( 'xdn_ai_daily_research' ); }
    public static function scheduled_research() {
        $settings = get_option( 'xdn_ai_settings', array() );
        if ( empty( $settings['auto_research'] ) || empty( $settings['gemini_key'] ) ) return;
        $seed = ! empty( $settings['seed_topic'] ) ? $settings['seed_topic'] : 'xe đạp Ninh Bình';
        $result = XDN_AI_Gemini::research( $seed, $settings );
        if ( is_wp_error( $result ) ) { update_option( 'xdn_ai_last_error', $result->get_error_message(), false ); return; }
        update_option( 'xdn_ai_last_research', $result, false );
        update_option( 'xdn_ai_last_research_at', current_time( 'mysql' ), false );
    }
}
register_activation_hook( __FILE__, array( 'XDN_AI_Content_Engine', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'XDN_AI_Content_Engine', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'XDN_AI_Content_Engine', 'init' ) );
