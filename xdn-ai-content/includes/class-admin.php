<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class XDN_AI_Admin {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'wp_ajax_xdn_ai_research', array( __CLASS__, 'ajax_research' ) );
        add_action( 'wp_ajax_xdn_ai_write', array( __CLASS__, 'ajax_write' ) );
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

    public static function dashboard() {
        $last = get_option( 'xdn_ai_last_research', array() );
        echo '<div class="wrap"><h1>XDN AI Content Engine</h1>';
        echo '<p>Trung tâm nghiên cứu SEO và tạo nội dung AI cho xedapninhbinh.com.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url( admin_url('admin.php?page=xdn-ai-research') ) . '">🔍 Tìm cơ hội SEO</a> <a class="button" href="' . esc_url( admin_url('admin.php?page=xdn-ai-settings') ) . '">⚙ Cài đặt API</a></p>';
        echo '<hr><h2>Nghiên cứu gần nhất</h2>';
        if ( $last ) {
            echo '<p><strong>Chủ đề:</strong> ' . esc_html( $last['topic'] ?? '' ) . '</p>';
            if ( ! empty( $last['keyword_opportunities'] ) ) {
                echo '<table class="widefat striped"><thead><tr><th>Keyword</th><th>Intent</th><th>Ưu tiên</th><th>Lý do</th></tr></thead><tbody>';
                foreach ( $last['keyword_opportunities'] as $row ) {
                    echo '<tr><td>' . esc_html($row['keyword'] ?? '') . '</td><td>' . esc_html($row['intent'] ?? '') . '</td><td>' . esc_html($row['priority'] ?? '') . '</td><td>' . esc_html($row['reason'] ?? '') . '</td></tr>';
                }
                echo '</tbody></table>';
            }
        } else echo '<p>Chưa có dữ liệu. Hãy chạy nghiên cứu đầu tiên.</p>';
        echo '</div>';
    }

    public static function research_page() {
        $settings = self::settings();
        $nonce = wp_create_nonce( 'xdn_ai_nonce' );
        echo '<div class="wrap"><h1>AI SEO Research</h1><p>Gemini sẽ dùng Google Search Grounding để nghiên cứu dữ liệu web hiện tại.</p>';
        echo '<table class="form-table"><tr><th><label for="xdn-topic">Chủ đề gốc</label></th><td><input id="xdn-topic" type="text" class="regular-text" value="' . esc_attr($settings['seed_topic'] ?? 'xe đạp Ninh Bình') . '"></td></tr></table>';
        echo '<p><button id="xdn-research" class="button button-primary">🔍 Tìm cơ hội SEO</button> <span id="xdn-status"></span></p>';
        echo '<div id="xdn-result"></div>';
        echo '<script>window.XDN_AI={ajax:"' . esc_js(admin_url('admin-ajax.php')) . '",nonce:"' . esc_js($nonce) . '"};</script>';
        echo '<script>(function(){const b=document.getElementById("xdn-research"),s=document.getElementById("xdn-status"),r=document.getElementById("xdn-result");b.addEventListener("click",function(){b.disabled=true;s.textContent=" Đang nghiên cứu Google...";const f=new FormData();f.append("action","xdn_ai_research");f.append("nonce",XDN_AI.nonce);f.append("topic",document.getElementById("xdn-topic").value);fetch(XDN_AI.ajax,{method:"POST",body:f}).then(x=>x.json()).then(j=>{b.disabled=false;if(!j.success){s.textContent=" Lỗi: "+j.data;return;}s.textContent=" Hoàn tất";let h="<h2>Keyword opportunities</h2><table class=\"widefat striped\"><thead><tr><th>Keyword</th><th>Intent</th><th>Priority</th><th>Reason</th></tr></thead><tbody>";(j.data.keyword_opportunities||[]).forEach(x=>{h+="<tr><td>"+(x.keyword||"")+"</td><td>"+(x.intent||"")+"</td><td>"+(x.priority||"")+"</td><td>"+(x.reason||"")+"</td></tr>"});h+="</tbody></table>";h+="<h2>Content gaps</h2><ul>";(j.data.content_gaps||[]).forEach(x=>h+="<li>"+x+"</li>");h+="</ul><h2>Tiêu đề đề xuất</h2><ol>";(j.data.recommended_titles||[]).forEach(x=>h+="<li>"+x+"</li>");h+="</ol>";r.innerHTML=h;}).catch(e=>{b.disabled=false;s.textContent=" Lỗi kết nối";});});})();</script></div>';
    }

    public static function settings_page() {
        $s = self::settings();
        echo '<div class="wrap"><h1>Cài đặt XDN AI</h1><form method="post" action="options.php">';
        settings_fields( 'xdn_ai_settings_group' );
        echo '<table class="form-table">';
        echo '<tr><th>OpenAI API Key</th><td><input type="password" name="xdn_ai_settings[openai_key]" class="regular-text" placeholder="Giữ trống để giữ key hiện tại"></td></tr>';
        echo '<tr><th>OpenAI Model</th><td><input name="xdn_ai_settings[openai_model]" class="regular-text" value="' . esc_attr($s['openai_model'] ?? 'gpt-5.6-luna') . '"></td></tr>';
        echo '<tr><th>Gemini API Key</th><td><input type="password" name="xdn_ai_settings[gemini_key]" class="regular-text" placeholder="Giữ trống để giữ key hiện tại"></td></tr>';
        echo '<tr><th>Gemini Model</th><td><input name="xdn_ai_settings[gemini_model]" class="regular-text" value="' . esc_attr($s['gemini_model'] ?? 'gemini-3.6-flash') . '"></td></tr>';
        echo '<tr><th>Chủ đề gốc</th><td><input name="xdn_ai_settings[seed_topic]" class="regular-text" value="' . esc_attr($s['seed_topic'] ?? 'xe đạp Ninh Bình') . '"></td></tr>';
        echo '<tr><th>Tự nghiên cứu hàng ngày</th><td><label><input type="checkbox" name="xdn_ai_settings[auto_research]" value="1" ' . checked(!empty($s['auto_research']),true,false) . '> Bật</label></td></tr>';
        echo '</table>'; submit_button('Lưu cài đặt'); echo '</form></div>';
    }

    public static function ajax_research() {
        if ( ! current_user_can('manage_options') || ! check_ajax_referer('xdn_ai_nonce','nonce',false) ) wp_send_json_error('Không có quyền.');
        $topic = sanitize_text_field( wp_unslash($_POST['topic'] ?? '') );
        if ( ! $topic ) wp_send_json_error('Vui lòng nhập chủ đề.');
        $result = XDN_AI_Gemini::research($topic, self::settings());
        if ( is_wp_error($result) ) wp_send_json_error($result->get_error_message());
        update_option('xdn_ai_last_research',$result,false);
        update_option('xdn_ai_last_research_at',current_time('mysql'),false);
        wp_send_json_success($result);
    }

    public static function ajax_write() {
        if ( ! current_user_can('publish_posts') || ! check_ajax_referer('xdn_ai_nonce','nonce',false) ) wp_send_json_error('Không có quyền.');
        $research = get_option('xdn_ai_last_research',array());
        if ( ! $research ) wp_send_json_error('Chưa có nghiên cứu.');
        $result = XDN_AI_OpenAI::write_article($research,self::settings());
        if ( is_wp_error($result) ) wp_send_json_error($result->get_error_message());
        $json = json_decode(trim($result),true);
        if ( ! is_array($json) ) wp_send_json_error('GPT trả về dữ liệu không hợp lệ.');
        $post_id = wp_insert_post(array(
            'post_title' => sanitize_text_field($json['title'] ?? 'Bài viết AI'),
            'post_name' => sanitize_title($json['slug'] ?? ($json['title'] ?? '')), 
            'post_excerpt' => wp_kses_post($json['excerpt'] ?? ''),
            'post_content' => wp_kses_post($json['content_html'] ?? ''),
            'post_status' => 'draft',
            'post_type' => 'post',
        ), true);
        if ( is_wp_error($post_id) ) wp_send_json_error($post_id->get_error_message());
        update_post_meta($post_id,'_xdn_ai_focus_keyword',sanitize_text_field($json['focus_keyword'] ?? ''));
        update_post_meta($post_id,'_xdn_ai_meta_description',sanitize_text_field($json['meta_description'] ?? ''));
        wp_send_json_success(array('post_id'=>$post_id,'edit_url'=>get_edit_post_link($post_id,''),'data'=>$json));
    }
}
