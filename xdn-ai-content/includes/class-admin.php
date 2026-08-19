<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class XDN_AI_Admin {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'wp_ajax_xdn_ai_research', array( __CLASS__, 'ajax_research' ) );
        add_action( 'wp_ajax_xdn_ai_write', array( __CLASS__, 'ajax_write' ) );
        add_action( 'wp_ajax_xdn_ai_publish', array( __CLASS__, 'ajax_publish' ) );
        add_action( 'wp_ajax_xdn_ai_schedule', array( __CLASS__, 'ajax_schedule' ) );
        add_action( 'wp_ajax_xdn_ai_posts', array( __CLASS__, 'ajax_posts' ) );
    }

    public static function menu() {
        add_menu_page( 'XDN AI Content', 'XDN AI Content', 'manage_options', 'xdn-ai-content', array( __CLASS__, 'dashboard' ), 'dashicons-edit-page', 58 );
        add_submenu_page( 'xdn-ai-content', 'AI SEO Research', 'AI SEO Research', 'manage_options', 'xdn-ai-research', array( __CLASS__, 'research_page' ) );
        add_submenu_page( 'xdn-ai-content', 'Cài đặt', 'Cài đặt', 'manage_options', 'xdn-ai-settings', array( __CLASS__, 'settings_page' ) );
    }

    public static function register_settings() {
        register_setting( 'xdn_ai_settings_group', 'xdn_ai_settings', array( __CLASS__, 'sanitize_settings' ) );
    }

    public static function sanitize_settings( $input ) {
        $old = get_option( 'xdn_ai_settings', array() );
        $input = is_array( $input ) ? $input : array();
        $out = array();
        $out['openai_key'] = isset( $input['openai_key'] ) && $input['openai_key'] !== '' ? sanitize_text_field( $input['openai_key'] ) : ( $old['openai_key'] ?? '' );
        $out['gemini_key'] = isset( $input['gemini_key'] ) && $input['gemini_key'] !== '' ? sanitize_text_field( $input['gemini_key'] ) : ( $old['gemini_key'] ?? '' );
        $out['openai_model'] = sanitize_text_field( $input['openai_model'] ?? 'gpt-5.6-luna' );
        $out['gemini_model'] = sanitize_text_field( $input['gemini_model'] ?? 'gemini-3.6-flash' );
        $out['seed_topic'] = sanitize_text_field( $input['seed_topic'] ?? 'xe đạp Ninh Bình' );
        $out['auto_research'] = ! empty( $input['auto_research'] ) ? 1 : 0;
        return $out;
    }

    private static function settings() { return get_option( 'xdn_ai_settings', array() ); }

    private static function nonce() { return wp_create_nonce( 'xdn_ai_nonce' ); }

    public static function dashboard() {
        echo '<div class="wrap"><h1>🚲 XDN AI Content Engine <small style="font-size:13px;font-weight:400;">v' . esc_html( XDN_AI_VERSION ) . '</small></h1>';
        echo '<p>Trung tâm nghiên cứu SEO, tạo bài, quản lý bài viết và đặt lịch đăng cho xedapninhbinh.com.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url( admin_url('admin.php?page=xdn-ai-research') ) . '">🔍 AI SEO Research</a> <a class="button" href="' . esc_url( admin_url('admin.php?page=xdn-ai-settings') ) . '">⚙ Cài đặt API</a></p>';
        echo '<hr><h2>📚 Bài viết XDN đã tạo</h2>';
        self::render_posts_table( 30 );
        echo '</div>';
    }

    private static function render_posts_table( $limit = 30 ) {
        $posts = self::get_xdn_posts( $limit );
        if ( empty( $posts ) ) {
            echo '<p>Chưa có bài viết nào được tạo bởi XDN AI.</p>';
            return;
        }
        echo '<table class="widefat striped" id="xdn-posts-table"><thead><tr><th style="width:45px">#</th><th>Tiêu đề</th><th>Keyword</th><th>Trạng thái</th><th>Ngày</th><th style="width:330px">Thao tác</th></tr></thead><tbody>';
        foreach ( $posts as $i => $post ) {
            echo self::post_row_html( $post, $i + 1 );
        }
        echo '</tbody></table>';
    }

    private static function get_xdn_posts( $limit = 30 ) {
        return get_posts( array(
            'post_type'      => 'post',
            'post_status'    => array( 'draft', 'future', 'publish', 'pending', 'private' ),
            'posts_per_page' => absint( $limit ),
            'meta_key'       => '_xdn_ai_created',
            'meta_value'     => '1',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
    }

    private static function post_row_html( $post, $number ) {
        $status = get_post_status( $post );
        $status_labels = array(
            'draft'   => 'Bản nháp',
            'future'  => 'Đã đặt lịch',
            'publish' => 'Đã đăng',
            'pending' => 'Chờ duyệt',
            'private' => 'Riêng tư',
        );
        $status_label = $status_labels[ $status ] ?? $status;
        $keyword = get_post_meta( $post->ID, '_xdn_ai_focus_keyword', true );
        $edit = get_edit_post_link( $post->ID, '' );
        $date = get_post_time( 'd/m/Y H:i', true, $post );
        $row = '<tr data-post-id="' . absint( $post->ID ) . '">';
        $row .= '<td>' . absint( $number ) . '</td>';
        $row .= '<td><strong><a href="' . esc_url( $edit ) . '">' . esc_html( get_the_title( $post ) ?: '(Chưa có tiêu đề)' ) . '</a></strong></td>';
        $row .= '<td>' . esc_html( $keyword ) . '</td>';
        $row .= '<td>' . esc_html( $status_label ) . '</td>';
        $row .= '<td>' . esc_html( $date ) . '</td>';
        $row .= '<td><a class="button" href="' . esc_url( $edit ) . '">✏ Sửa</a> ';
        if ( 'publish' !== $status ) {
            $row .= '<button type="button" class="button xdn-publish" data-id="' . absint( $post->ID ) . '">🚀 Đăng luôn</button> ';
            $row .= '<input type="datetime-local" class="xdn-schedule-date" style="width:185px" value=""> ';
            $row .= '<button type="button" class="button xdn-schedule" data-id="' . absint( $post->ID ) . '">📅 Đặt lịch</button>';
        }
        $row .= '</td></tr>';
        return $row;
    }

    public static function research_page() {
        $settings = self::settings();
        $nonce = self::nonce();
        echo '<div class="wrap"><h1>🔎 AI SEO Research <small style="font-size:13px;font-weight:400;">v' . esc_html( XDN_AI_VERSION ) . '</small></h1>';
        echo '<p>Gemini nghiên cứu dữ liệu web; mỗi cơ hội SEO có thể tạo bài, đăng ngay hoặc đặt lịch.</p>';
        echo '<table class="form-table"><tr><th><label for="xdn-topic">Chủ đề gốc</label></th><td><input id="xdn-topic" type="text" class="regular-text" value="' . esc_attr($settings['seed_topic'] ?? 'xe đạp Ninh Bình') . '"></td></tr></table>';
        echo '<p><button id="xdn-research" class="button button-primary">🔍 Tìm cơ hội SEO</button> <span id="xdn-status"></span></p>';
        echo '<div id="xdn-result"></div>';
        echo '<hr><h2>📚 Danh sách bài XDN đã tạo</h2><div id="xdn-post-list">';
        self::render_posts_table( 30 );
        echo '</div>';
        echo '<script>window.XDN_AI=' . wp_json_encode( array( 'ajax'=>admin_url('admin-ajax.php'), 'nonce'=>$nonce, 'version'=>XDN_AI_VERSION ) ) . ';</script>';
        self::inline_js();
        echo '</div>';
    }

    private static function inline_js() {
        echo '<style>
        #xdn-result table td,#xdn-result table th{vertical-align:top}.xdn-actions{min-width:330px}.xdn-actions button{margin:2px}.xdn-actions input{margin:2px}.xdn-ok{color:#168a16;font-weight:600}.xdn-error{color:#c00;font-weight:600}
        </style>';
        echo '<script>(function(){
        const X=window.XDN_AI;
        const esc=v=>String(v==null?"":v).replace(/[&<>\"]/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;","":