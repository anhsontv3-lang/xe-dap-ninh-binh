<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class XDN_AI_Content_Hub {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'wp_ajax_xdn_ai_hub_research', array( __CLASS__, 'ajax_research' ) );
        add_action( 'wp_ajax_xdn_ai_hub_remix', array( __CLASS__, 'ajax_remix' ) );
    }

    public static function menu() {
        add_submenu_page(
            'xdn-ai-content',
            'Kho nội dung & Nguồn',
            'Kho nội dung & Nguồn',
            'manage_options',
            'xdn-ai-content-hub',
            array( __CLASS__, 'page' )
        );
    }

    private static function nonce() {
        return wp_create_nonce( 'xdn_ai_nonce' );
    }

    private static function settings() {
        return get_option( 'xdn_ai_settings', array() );
    }

    public static function page() {
        $settings = self::settings();
        $topic = ! empty( $settings['seed_topic'] ) ? $settings['seed_topic'] : 'xe đạp Ninh Bình';
        $saved = get_option( 'xdn_ai_content_hub', array() );
        echo '<div class="wrap"><h1>🧠 Kho nội dung & Nguồn <small style="font-size:13px;font-weight:400">v' . esc_html( XDN_AI_VERSION ) . '</small></h1>';
        echo '<p>Mở rộng chủ đề từ bán xe sang sửa chữa, bảo dưỡng, lỗi thường gặp, phụ kiện, tuyến đường và nội dung cập nhật. Gemini tìm nguồn mới; OpenAI chế tác lại thành bài nguyên bản.</p>';
        echo '<div class="notice notice-info inline"><p><strong>Nguyên tắc:</strong> nguồn chỉ dùng để tham khảo. Plugin không sao chép nguyên văn hoặc đổi vài từ rồi đăng lại.</p></div>';
        echo '<table class="form-table"><tr><th>Chủ đề chính</th><td><input id="xdn-hub-topic" type="text" class="regular-text" value="' . esc_attr( $topic ) . '"></td></tr>';
        echo '<tr><th>Nhóm nội dung</th><td><div id="xdn-hub-pills" style="display:flex;flex-wrap:wrap;gap:7px;max-width:1000px">';
        foreach ( self::content_pillars() as $key => $label ) {
            echo '<label style="border:1px solid #ccd0d4;border-radius:4px;padding:7px 10px;background:#fff"><input type="checkbox" value="' . esc_attr( $key ) . '" checked> ' . esc_html( $label ) . '</label>';
        }
        echo '</div></td></tr></table>';
        echo '<p><button id="xdn-hub-research" class="button button-primary">🔄 Cập nhật nguồn & tìm ý tưởng</button> <span id="xdn-hub-status"></span></p>';
        echo '<div id="xdn-hub-result">';
        if ( ! empty( $saved['source_updates'] ) ) self::render_results( $saved );
        echo '</div></div>';
        echo '<script>window.XDN_HUB=' . wp_json_encode( array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => self::nonce() ) ) . ';</script>';
        self::script();
    }

    private static function content_pillars() {
        return array(
            'repair'       => '🔧 Sửa chữa xe đạp',
            'brake'        => '🛑 Phanh: chỉnh/sửa/thay',
            'derailleur'   => '⚙️ Đề xe: chỉnh/sửa/thay',
            'drivetrain'   => '⛓️ Xích - líp - giò đĩa',
            'wheel'        => '🛞 Bánh - săm - lốp - vành',
            'maintenance'  => '🧽 Bảo dưỡng định kỳ',
            'troubleshoot' => '🩺 Chẩn đoán lỗi thường gặp',
            'buying'       => '🛒 Tư vấn chọn/mua xe',
            'comparison'   => '⚖️ So sánh dòng xe',
            'accessories'  => '🎒 Phụ kiện & đồ dùng',
            'safety'       => '🦺 An toàn khi đi xe',
            'routes'       => '📍 Đạp xe & cung đường Ninh Bình',
            'local'        => '🏪 Dịch vụ/cửa hàng tại Ninh Bình',
            'school'       => '🎒 Xe học sinh/trẻ em',
            'mtb'          => '⛰️ MTB/xe địa hình',
            'road'         => '🚴 Xe road/đường trường',
            'electric'     => '🔋 Xe đạp điện',
            'used'         => '♻️ Xe cũ/kiểm tra xe cũ',
            'seasonal'     => '🌦️ Nội dung theo mùa/thời tiết',
            'news'         => '📰 Tin mới & cập nhật ngành xe đạp',
        );
    }

    private static function render_results( $data ) {
        $sources = ! empty( $data['source_updates'] ) && is_array( $data['source_updates'] ) ? $data['source_updates'] : array();
        $ideas   = ! empty( $data['keyword_opportunities'] ) && is_array( $data['keyword_opportunities'] ) ? $data['keyword_opportunities'] : array();
        $clusters = ! empty( $data['content_clusters'] ) && is_array( $data['content_clusters'] ) ? $data['content_clusters'] : array();
        echo '<h2>🧩 Cụm nội dung mở rộng</h2><div style="display:flex;flex-wrap:wrap;gap:8px">';
        foreach ( $clusters as $cluster ) echo '<span style="display:inline-block;background:#f0f6fc;border:1px solid #c8d7e5;border-radius:14px;padding:6px 11px">' . esc_html( is_array($cluster) ? ($cluster['name'] ?? '') : $cluster ) . '</span>';
        echo '</div>';
        echo '<h2>📰 Nguồn mới để tham khảo</h2>';
        if ( ! $sources ) echo '<p>Chưa tìm thấy nguồn phù hợp.</p>';
        else {
            echo '<table class="widefat striped"><thead><tr><th>#</th><th>Nguồn</th><th>Ngày</th><th>Tóm tắt</th><th>Góc nên khai thác</th><th>Thao tác</th></tr></thead><tbody>';
            foreach ( $sources as $i => $source ) {
                $title = $source['title'] ?? 'Nguồn tham khảo'; $url = $source['url'] ?? '#';
                echo '<tr><td>' . absint($i+1) . '</td><td><strong><a target="_blank" rel="noopener noreferrer" href="' . esc_url($url) . '">' . esc_html($title) . '</a></strong><br><small>' . esc_html($source['domain'] ?? '') . '</small></td><td>' . esc_html($source['date'] ?? '') . '</td><td>' . esc_html($source['summary'] ?? '') . '</td><td>' . esc_html($source['local_angle'] ?? $source['angle'] ?? '') . '</td><td><button type="button" class="button xdn-hub-remix" data-index="' . absint($i) . '">✍️ Chế lại thành bài</button></td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '<h2>🎯 Ý tưởng SEO bổ sung</h2>';
        if ( $ideas ) {
            echo '<table class="widefat striped"><thead><tr><th>#</th><th>Keyword</th><th>Intent</th><th>Ưu tiên</th><th>Góc nội dung</th></tr></thead><tbody>';
            foreach ( $ideas as $i => $idea ) echo '<tr><td>' . absint($i+1) . '</td><td><strong>' . esc_html($idea['keyword'] ?? '') . '</strong></td><td>' . esc_html($idea['intent'] ?? '') . '</td><td>' . esc_html($idea['priority'] ?? '') . '</td><td>' . esc_html($idea['reason'] ?? $idea['content_gap'] ?? '') . '</td></tr>';
            echo '</tbody></table>';
        }
        echo '<p><strong>Cập nhật lần cuối:</strong> ' . esc_html( $data['updated_at'] ?? current_time('mysql') ) . '</p>';
    }

    private static function script() {
        echo '<style>.xdn-hub-ok{color:#168a16;font-weight:600}.xdn-hub-error{color:#c00;font-weight:600}.xdn-hub-remix{white-space:nowrap}</style><script>(function(){
        const X=window.XDN_HUB;
        async function api(data){const f=new FormData();Object.keys(data).forEach(k=>f.append(k,data[k]));const r=await fetch(X.ajax,{method:"POST",body:f});const t=await r.text();let j;try{j=JSON.parse(t)}catch(e){throw new Error("WordPress trả về dữ liệu không phải JSON: "+t.slice(0,250))}return j;}
        function status(t,ok){const e=document.getElementById("xdn-hub-status");if(e){e.textContent=t;e.className=ok?"xdn-hub-ok":"xdn-hub-error"}}
        const research=document.getElementById("xdn-hub-research");
        if(research)research.addEventListener("click",async()=>{const topic=document.getElementById("xdn-hub-topic").value;const cats=[...document.querySelectorAll("#xdn-hub-pills input:checked")].map(x=>x.value).join(",");research.disabled=true;status("⏳ Gemini đang cập nhật nguồn và mở rộng chủ đề...",true);try{const j=await api({action:"xdn_ai_hub_research",nonce:X.nonce,topic,categories:cats});if(!j.success)throw new Error(j.data||"Không thể cập nhật nguồn");document.getElementById("xdn-hub-result").innerHTML=j.data.html;status("✓ Đã cập nhật nguồn",true)}catch(e){status("✗ "+e.message,false)}finally{research.disabled=false}});
        document.addEventListener("click",async(e)=>{const b=e.target.closest(".xdn-hub-remix");if(!b)return;b.disabled=true;const old=b.textContent;b.textContent="⏳ Đang chế...";try{const j=await api({action:"xdn_ai_hub_remix",nonce:X.nonce,index:b.dataset.index});if(!j.success)throw new Error(j.data||"Không thể tạo bài");status("✓ Đã tạo Draft #"+j.data.post_id,true);b.textContent="✓ Đã tạo Draft"}catch(err){status("✗ "+err.message,false);b.textContent=old;b.disabled=false}});
        })();</script>';
    }

    public static function ajax_research() {
        if ( ! current_user_can('manage_options') || ! check_ajax_referer('xdn_ai_nonce','nonce',false) ) wp_send_json_error('Không có quyền.');
        $topic = sanitize_text_field( wp_unslash($_POST['topic'] ?? '') );
        $categories = sanitize_text_field( wp_unslash($_POST['categories'] ?? '') );
        if ( ! $topic ) wp_send_json_error('Vui lòng nhập chủ đề.');
        $category_labels = array(); $all = self::content_pillars();
        foreach ( array_filter(array_map('trim', explode(',', $categories))) as $key ) if ( isset($all[$key]) ) $category_labels[] = wp_strip_all_tags($all[$key]);
        if ( ! $category_labels ) $category_labels = array_values($all);
        $prompt_topic = $topic . "\n\nMỞ RỘNG NGHIÊN CỨU THEO CÁC NHÓM: " . implode(', ', $category_labels) . "\n\n" .
            "Đặc biệt tìm các chủ đề thực tế như sửa phanh xe đạp, chỉnh phanh, thay má phanh, phanh kêu; sửa/chỉnh đề trước và đề sau, sang số không mượt, xích nhảy; vệ sinh và tra dầu xích, líp, giò đĩa; thủng săm, lốp mòn, bánh đảo; bảo dưỡng định kỳ; lỗi xe đạp điện; tư vấn xe học sinh, MTB, road, xe cũ; phụ kiện; an toàn; cung đường đạp xe tại Ninh Bình; dịch vụ địa phương; và các tin/cập nhật mới có giá trị cho người đọc.\n\n" .
            "Ngoài keyword, hãy tìm 8-15 nguồn bài viết/tài liệu mới hoặc có uy tín trên web. Với mỗi nguồn, tóm tắt bằng lời của bạn và đề xuất một góc nội dung riêng cho website Ninh Bình. Không sao chép văn bản nguồn.";
        $result = XDN_AI_Gemini::research( $prompt_topic, self::settings() );
        if ( is_wp_error($result) ) wp_send_json_error($result->get_error_message());
        $sources = !empty($result['sources']) && is_array($result['sources']) ? $result['sources'] : array();
        $source_updates = array();
        foreach($sources as $s){
            if(!is_array($s)) continue;
            $source_updates[] = array(
                'title' => sanitize_text_field($s['title'] ?? ''),
                'url' => esc_url_raw($s['url'] ?? ''),
                'domain' => wp_parse_url($s['url'] ?? '', PHP_URL_HOST),
                'date' => sanitize_text_field($s['date'] ?? ''),
                'summary' => sanitize_textarea_field($s['summary'] ?? ''),
                'local_angle' => sanitize_textarea_field($s['local_angle'] ?? $s['angle'] ?? ''),
            );
        }
        $result['source_updates'] = $source_updates;
        $result['updated_at'] = current_time('mysql');
        update_option('xdn_ai_content_hub',$result,false);
        ob_start(); self::render_results($result); $html=ob_get_clean();
        wp_send_json_success(array('html'=>$html,'data'=>$result));
    }

    public static function ajax_remix() {
        if ( ! current_user_can('publish_posts') || ! check_ajax_referer('xdn_ai_nonce','nonce',false) ) wp_send_json_error('Không có quyền.');
        $index = absint($_POST['index'] ?? 0);
        $hub = get_option('xdn_ai_content_hub',array());
        $sources = !empty($hub['source_updates']) && is_array($hub['source_updates']) ? $hub['source_updates'] : array();
        if(!isset($sources[$index])) wp_send_json_error('Không tìm thấy nguồn. Hãy cập nhật nguồn lại.');
        $source = $sources[$index];
        $prompt = "Bạn là biên tập viên chuyên môn cho website bán và sửa xe đạp tại Ninh Bình.\n\n" .
            "NGUỒN THAM KHẢO:\n" . wp_json_encode($source,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n\n" .
            "DỮ LIỆU NGHIÊN CỨU:\n" . wp_json_encode($hub,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) . "\n\n" .
            "Hãy tạo một bài tiếng Việt nguyên bản từ thông tin tham khảo trên. Không dịch nguyên văn, không sao chép cấu trúc câu, không thay vài từ rồi đăng lại. Hãy kiểm tra logic, bỏ chi tiết không chắc chắn, bổ sung kinh nghiệm thực tế và tập trung vào nhu cầu người đọc ở Ninh Bình. Nếu nguồn là tin mới, nêu rõ ngày/nguồn khi cần. Nếu không đủ dữ liệu cho giá, địa chỉ, thông số hoặc chính sách thì không được bịa. Có thể thêm phần 'Kinh nghiệm thực tế', 'Khi nào nên mang xe đi sửa', 'Chi phí tham khảo cần hỏi trực tiếp' nhưng không tự đặt giá.\n\n" .
            "Bài nên có H2/H3, checklist, FAQ và internal-link placeholders dạng [LINK_NOI_BO: chủ đề]. Trả về đúng JSON, không markdown fence:\n" .
            "{\"title\":string,\"slug\":string,\"excerpt\":string,\"focus_keyword\":string,\"meta_description\":string,\"content_html\":string,\"faq\":[{\"question\":string,\"answer\":string}]}";
        $result = XDN_AI_OpenAI::generate($prompt,self::settings());
        if(is_wp_error($result)) wp_send_json_error($result->get_error_message());
        $json = self::normalize_article($result);
        if(is_wp_error($json)) wp_send_json_error($json->get_error_message());
        $post_id = wp_insert_post(array(
            'post_title'=>sanitize_text_field($json['title']??'Bài viết XDN'),
            'post_name'=>sanitize_title($json['slug']??($json['title']??'')),
            'post_excerpt'=>wp_kses_post($json['excerpt']??''),
            'post_content'=>wp_kses_post($json['content_html']??''),
            'post_status'=>'draft','post_type'=>'post'
        ),true);
        if(is_wp_error($post_id)) wp_send_json_error($post_id->get_error_message());
        update_post_meta($post_id,'_xdn_ai_created','1');
        update_post_meta($post_id,'_xdn_ai_focus_keyword',sanitize_text_field($json['focus_keyword']??''));
        update_post_meta($post_id,'_xdn_ai_source_url',esc_url_raw($source['url']??''));
        update_post_meta($post_id,'_xdn_ai_source_title',sanitize_text_field($source['title']??''));
        update_post_meta($post_id,'_xdn_ai_source_updated_at',current_time('mysql'));
        update_post_meta($post_id,'_xdn_ai_meta_description',sanitize_text_field($json['meta_description']??''));
        wp_send_json_success(array('post_id'=>$post_id,'edit_url'=>get_edit_post_link($post_id,'')));
    }

    private static function normalize_article($result) {
        if(is_array($result)){if(isset($result['title'])||isset($result['content_html']))return $result;return new WP_Error('invalid_article','AI trả về mảng nhưng thiếu dữ liệu bài viết.');}
        if(!is_string($result))return new WP_Error('invalid_article','Kết quả AI không phải chuỗi hoặc mảng.');
        $text=trim($result); $text=preg_replace('/^```(?:json)?\s*/i','',$text); $text=preg_replace('/\s*```$/','',$text);
        $json=json_decode($text,true); if(is_array($json))return $json;
        $start=strpos($text,'{'); $end=strrpos($text,'}'); if($start!==false&&$end!==false&&$end>$start){$json=json_decode(substr($text,$start,$end-$start+1),true);if(is_array($json))return $json;}
        return new WP_Error('invalid_article','AI trả về JSON không hợp lệ.');
    }
}
