<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Keeps API keys intact when the Settings API sanitizes the XDN options.
 * The visible API fields are intentionally blank for security, so a blank
 * field must never erase an existing key.
 */
final class XDN_AI_Settings_Fix {
    public static function init() {
        add_filter( 'pre_update_option_xdn_ai_settings', array( __CLASS__, 'preserve_api_keys' ), 10, 3 );
        add_action( 'admin_notices', array( __CLASS__, 'saved_notice' ) );
    }

    public static function preserve_api_keys( $new_value, $old_value, $option ) {
        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) return $new_value;
        if ( empty( $_POST['option_page'] ) || 'xdn_ai_settings_group' !== sanitize_key( wp_unslash( $_POST['option_page'] ) ) ) return $new_value;

        $posted = isset( $_POST['xdn_ai_settings'] ) && is_array( $_POST['xdn_ai_settings'] )
            ? wp_unslash( $_POST['xdn_ai_settings'] )
            : array();

        if ( ! is_array( $new_value ) ) $new_value = array();
        if ( ! is_array( $old_value ) ) $old_value = array();

        foreach ( array( 'openai_key', 'gemini_key' ) as $key ) {
            $value = isset( $posted[ $key ] ) ? trim( (string) $posted[ $key ] ) : '';
            if ( '' !== $value ) {
                $new_value[ $key ] = sanitize_text_field( $value );
            } elseif ( ! empty( $old_value[ $key ] ) ) {
                $new_value[ $key ] = $old_value[ $key ];
            }
        }

        return $new_value;
    }

    public static function saved_notice() {
        if ( ! isset( $_GET['page'], $_GET['settings-updated'] ) ) return;
        if ( 'xdn-ai-settings' !== sanitize_key( wp_unslash( $_GET['page'] ) ) || 'true' !== sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) ) return;

        $settings = get_option( 'xdn_ai_settings', array() );
        $openai = ! empty( $settings['openai_key'] );
        $gemini = ! empty( $settings['gemini_key'] );
        echo '<div class="notice notice-success is-dismissible"><p><strong>XDN AI:</strong> Đã lưu cài đặt. '; 
        echo $openai ? 'OpenAI API: <strong>Đã lưu</strong>. ' : 'OpenAI API: <strong>Chưa có</strong>. ';
        echo $gemini ? 'Gemini API: <strong>Đã lưu</strong>.' : 'Gemini API: <strong>Chưa có</strong>.';
        echo '</p></div>';
    }
}
XDN_AI_Settings_Fix::init();
