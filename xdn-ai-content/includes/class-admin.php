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
        return array(
            'openai_key'    => isset($input['openai_key']) && $input['openai_key'] !== '' ? sanitize_text_field($input['openai_key']) : ($old['openai_key'] ?? ''),
            'gemini_key'    => isset($input['gemini_key']) && $input['gemini_key'] !== '' ? sanitize_text_field($input['gemini_key']) : ($old['gemini_key'] ?? ''),
            'openai_model'  => sanitize_text_field($input['openai_model'] ?? 'gpt-5.6-luna'),
            'gemini_model'  => sanitize_text_field($input['gemini_model'] ?? 'gemini-3.6-flash'),
            'seed_topic'    => sanitize_text_field($input['seed_topic'] ?? 'xe đạp Ninh Bình'),
            'auto_research' => !empty($input['auto_research']) ? 1 : 0,
        );
    }

    private static function settings() { return get_option('xdn_ai_settings', array()); }
    private static function nonce() { return wp_create_nonce('xdn_ai_nonce'); }

    public static function dashboard() {
        echo '<div class="wrap"><h1>🚲 XDN AI Content Engine <small style="font-size:13px;font-weight:400">v' . esc_html(XDN_AI_VERSION) . '</small></h1>';
        echo '<p>Trung tâm nghiên cứu SEO, tạo bài, quản lý bài viết và đặt lịch đăng.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=xdn-ai-research')) . '">🔎 AI SEO Research</a> <a class="button" href="' . esc_url(admin_url('admin.php?page=xdn-ai-settings')) . '">⚙ Cài đặt API</a></p><hr>';
        echo '<h2>📚 Bài viết XDN đã tạo</h2>';
        self::render_posts_table(30);
        echo '</div>';
    }

    private static function get_xdn_posts($limit = 30) {
        return get_posts(array(
            'post_type' => 'post',
            'post_status' => array('draft','future','publish','pending','private'),
            'posts_per_page' => absint($limit),
            'meta_key' => '_xdn_ai_created',
            'meta_value' => '1',
            'orderby' => 'date',
            'order' => 'DESC',
        ));
    }

    private static function render_posts_table($limit = 30) {
        $posts = self::get_xdn_posts($limit);
        if (!$posts) { echo '<p>Chưa có bài viết nào được tạo bởi XDN AI.</p>'; return; }
        echo '<table class="widefat striped xdn-posts-table"><thead><tr><th style="width:45px">#</th><th>Tiêu đề</th><th>Keyword</th><th>Trạng thái</th><th>Ngày</th><th style="width:360px">Thao tác</th></tr></thead><tbody>';
        foreach ($posts as $i => $post) echo self::post_row_html($post, $i + 1);
        echo '</tbody></table>';
    }

    private static function post_row_html($post, $number) {
        $status = get_post_status($post);
        $labels = array('draft'=>'Bản nháp','future'=>'Đã đặt lịch','publish'=>'Đã đăng','pending'=>'Chờ duyệt','private'=>'Riêng tư');
        $keyword = get_post_meta($post->ID, '_xdn_ai_focus_keyword', true);
        $edit = get_edit_post_link($post->ID, '');
        $date = get_post_time('d/m/Y H:i', true, $post);
        $html = '<tr data-post-id="' . absint($post->ID) . '">';
        $html .= '<td>' . absint($number) . '</td>';
        $html .= '<td><strong><a href="' . esc_url($edit) . '">' . esc_html(get_the_title($post) ?: '(Chưa có tiêu đề)') . '</a></strong></td>';
        $html .= '<td>' . esc_html($keyword) . '</td><td>' . esc_html($labels[$status] ?? $status) . '</td><td>' . esc_html($date) . '</td><td class="xdn-actions">';
        $html .= '<a class="button" href="' . esc_url($edit) . '">✏ Sửa</a> ';
        if ('publish' !== $status) {
            $html .= '<button type="button" class="button xdn-publish" data-id="' . absint($post->ID) . '">🚀 Đăng luôn</button> ';
            $html .= '<input type="datetime-local" class="xdn-schedule-date" style="width:185px"> ';
            $html .= '<button type="button" class="button xdn-schedule" data-id="' . absint($post->ID) . '">📅 Đặt lịch</button>';
        }
        return $html . '</td></tr>';
    }

    public static function research_page() {
        $settings = self::settings();
        echo '<div class="wrap"><h1>🔎 AI SEO Research <small style="font-size:13px;font-weight:400">v' . esc_html(XDN_AI_VERSION) . '</small></h1>';
        echo '<p>Gemini nghiên cứu dữ liệu web. Mỗi cơ hội SEO có thể tạo Draft, đăng ngay hoặc đặt lịch.</p>';
        echo '<table class="form-table"><tr><th><label for="xdn-topic">Chủ đề gốc</label></th><td><input id="xdn-topic" type="text" class="regular-text" value="' . esc_attr($settings['seed_topic'] ?? 'xe đạp Ninh Bình') . '"></td></tr></table>';
        echo '<p><button id="xdn-research" class="button button-primary">🔎 Tìm cơ hội SEO</button> <span id="xdn-status"></span></p><div id="xdn-result"></div>';
        echo '<hr><h2>📚 Danh sách bài XDN đã tạo</h2><div id="xdn-post-list">'; self::render_posts_table(30); echo '</div>';
        echo '<script>window.XDN_AI=' . wp_json_encode(array('ajax'=>admin_url('admin-ajax.php'),'nonce'=>self::nonce(),'version'=>XDN_AI_VERSION)) . ';</script>';
        self::inline_js();
        echo '</div>';
    }

    private static function inline_js() {
        echo '<style>.xdn-actions{min-width:360px}.xdn-actions button,.xdn-actions input{margin:2px}.xdn-ok{color:#168a16;font-weight:600}.xdn-error{color:#c00;font-weight:600}.xdn-loading{opacity:.6;pointer-events:none}</style>';
        echo '<script>(function(){
        const X=window.XDN_AI;
        async function api(data){
            const f=new FormData();
            Object.keys(data).forEach(k=>f.append(k,data[k]));
            const r=await fetch(X.ajax,{method:"POST",body:f});
            const t=await r.text();
            let j; try{j=JSON.parse(t)}catch(e){throw new Error("WordPress trả về dữ liệu không phải JSON: "+t.slice(0,200))}
            return j;
        }
        function msg(text,ok){const s=document.getElementById("xdn-status");if(s){s.textContent=text;s.className=ok?"xdn-ok":"xdn-error"}}
        function cell(text){const td=document.createElement("td");td.textContent=text==null?"":String(text);return td}
        function addButton(td,label,cls,attrs){const b=document.createElement("button");b.type="button";b.className="button "+cls;b.textContent=label;Object.keys(attrs||{}).forEach(k=>b.dataset[k]=attrs[k]);td.appendChild(b);return b}
        async function refreshPosts(){const box=document.getElementById("xdn-post-list");if(!box)return;try{const j=await api({action:"xdn_ai_posts",nonce:X.nonce});if(j.success)box.innerHTML=j.data.html}catch(e){console.error(e)}}

        document.addEventListener("click",async function(ev){
            const pub=ev.target.closest(".xdn-publish");
            if(pub){ev.preventDefault();if(!confirm("Đăng bài này ngay?"))return;pub.disabled=true;try{const j=await api({action:"xdn_ai_publish",nonce:X.nonce,post_id:pub.dataset.id});if(!j.success)throw new Error(j.data||"Không thể đăng");await refreshPosts();alert("✓ Đã đăng bài.")}catch(e){alert("✗ "+e.message)}finally{pub.disabled=false}return}
            const sch=ev.target.closest(".xdn-schedule");
            if(sch){ev.preventDefault();const row=sch.closest("tr"),input=row&&row.querySelector(".xdn-schedule-date");if(!input||!input.value){alert("Vui lòng chọn ngày và giờ đặt lịch.");return}sch.disabled=true;try{const j=await api({action:"xdn_ai_schedule",nonce:X.nonce,post_id:sch.dataset.id,datetime:input.value});if(!j.success)throw new Error(j.data||"Không thể đặt lịch");await refreshPosts();alert("✓ Đã đặt lịch: "+j.data.date)}catch(e){alert("✗ "+e.message)}finally{sch.disabled=false}return}
            const draft=ev.target.closest(".xdn-draft");
            if(draft){ev.preventDefault();const old=draft.textContent;draft.disabled=true;draft.textContent="⏳ Đang tạo...";try{const j=await api({action:"xdn_ai_write",nonce:X.nonce,keyword:draft.dataset.keyword||"",content_gap:draft.dataset.gap||""});if(!j.success)throw new Error(j.data||"Không thể tạo Draft");await refreshPosts();msg("✓ Đã tạo Draft #"+j.data.post_id,true)}catch(e){msg("✗ "+e.message,false)}finally{draft.disabled=false;draft.textContent=old}return}
            const dp=ev.target.closest(".xdn-draft-publish");
            if(dp){ev.preventDefault();const old=dp.textContent;dp.disabled=true;dp.textContent="⏳ Đang tạo...";try{const j=await api({action:"xdn_ai_write",nonce:X.nonce,keyword:dp.dataset.keyword||"",publish_now:"1"});if(!j.success)throw new Error(j.data||"Không thể đăng");await refreshPosts();alert("✓ Đã tạo và đăng bài #"+j.data.post_id)}catch(e){alert("✗ "+e.message)}finally{dp.disabled=false;dp.textContent=old}return}
            const ns=ev.target.closest(".xdn-new-schedule");
            if(ns){ev.preventDefault();const row=ns.closest("tr"),input=row&&row.querySelector(".xdn-new-date");if(!input||!input.value){alert("Vui lòng chọn ngày và giờ.");return}ns.disabled=true;try{const j=await api({action:"xdn_ai_write",nonce:X.nonce,keyword:ns.dataset.keyword||"",schedule_at:input.value});if(!j.success)throw new Error(j.data||"Không thể đặt lịch");await refreshPosts();alert("✓ Đã tạo bài và đặt lịch: "+j.data.date)}catch(e){alert("✗ "+e.message)}finally{ns.disabled=false}}
        });

        const research=document.getElementById("xdn-research");
        if(research)research.addEventListener("click",async function(){
            const topic=document.getElementById("xdn-topic"),result=document.getElementById("xdn-result");
            research.disabled=true;msg("⏳ Đang nghiên cứu...",true);
            try{
                const j=await api({action:"xdn_ai_research",nonce:X.nonce,topic:topic.value});
                if(!j.success)throw new Error(j.data||"Nghiên cứu thất bại");
                msg("✓ Hoàn tất",true);
                const rows=j.data.keyword_opportunities||[];
                const table=document.createElement("table");table.className="widefat striped";
                const thead=document.createElement("thead"),hr=document.createElement("tr");
                ["#","Keyword","Intent","Score","Priority","Content gap","Thao tác"].forEach(x=>hr.appendChild(cell(x)));thead.appendChild(hr);table.appendChild(thead);
                const tbody=document.createElement("tbody");
                rows.forEach(function(x,i){
                    const tr=document.createElement("tr"),kw=x.keyword||"",gap=x.content_gap||x.reason||"";
                    tr.appendChild(cell(i+1));tr.appendChild(cell(kw));tr.appendChild(cell(x.intent||""));tr.appendChild(cell(x.score||""));tr.appendChild(cell(x.priority||""));tr.appendChild(cell(gap));
                    const td=document.createElement("td");td.className="xdn-actions";
                    addButton(td,"📝 Draft","xdn-draft",{keyword:kw,gap:gap});addButton(td,"🚀 Đăng luôn","xdn-draft-publish",{keyword:kw});
                    td.appendChild(document.createElement("br"));const input=document.createElement("input");input.type="datetime-local";input.className="xdn-new-date";input.style.width="185px";td.appendChild(input);addButton(td,"📅 Đặt lịch","xdn-new-schedule",{keyword:kw});
                    tr.appendChild(td);tbody.appendChild(tr);
                });
                table.appendChild(tbody);result.innerHTML="<h2>Keyword opportunities</h2>";result.appendChild(table);
                const gaps=j.data.content_gaps||[];if(gaps.length){const h=document.createElement("h2");h.textContent="Content gaps";result.appendChild(h);const ul=document.createElement("ul");gaps.forEach(g=>{const li=document.createElement("li");li.textContent=g;ul.appendChild(li)});result.appendChild(ul)}
                await refreshPosts();
            }catch(e){msg("✗ "+e.message,false)}finally{research.disabled=false}
        });
        })();</script>';
    }

    public static function settings_page() {
        $s=self::settings();
        echo '<div class="wrap"><h1>Cài đặt XDN AI <small style="font-size:13px;font-weight:400">v' . esc_html(XDN_AI_VERSION) . '</small></h1><form method="post" action="options.php">';
        settings_fields('xdn_ai_settings_group');
        echo '<table class="form-table">';
        echo '<tr><th>OpenAI API Key</th><td><input type="password" name="xdn_ai_settings[openai_key]" class="regular-text" placeholder="Giữ trống để giữ key hiện tại"></td></tr>';
        echo '<tr><th>OpenAI Model</th><td><input name="xdn_ai_settings[openai_model]" class="regular-text" value="' . esc_attr($s['openai_model'] ?? 'gpt-5.6-luna') . '"></td></tr>';
        echo '<tr><th>Gemini API Key</th><td><input type="password" name="xdn_ai_settings[gemini_key]" class="regular-text" placeholder="Giữ trống để giữ key hiện tại"></td></tr>';
        echo '<tr><th>Gemini Model</th><td><input name="xdn_ai_settings[gemini_model]" class="regular-text" value="' . esc_attr($s['gemini_model'] ?? 'gemini-3.6-flash') . '"></td></tr>';
        echo '<tr><th>Chủ đề gốc</th><td><input name="xdn_ai_settings[seed_topic]" class="regular-text" value="' . esc_attr($s['seed_topic'] ?? 'xe đạp Ninh Bình') . '"></td></tr>';
        echo '<tr><th>Tự nghiên cứu hàng ngày</th><td><label><input type="checkbox" name="xdn_ai_settings[auto_research]" value="1" ' . checked(!empty($s['auto_research']),true,false) . '> Bật</label></td></tr>';
        echo '</table>';submit_button('Lưu cài đặt');echo '</form></div>';
    }

    public static function ajax_research() {
        if(!current_user_can('manage_options') || !check_ajax_referer('xdn_ai_nonce','nonce',false)) wp_send_json_error('Không có quyền.');
        $topic=sanitize_text_field(wp_unslash($_POST['topic']??''));if(!$topic)wp_send_json_error('Vui lòng nhập chủ đề.');
        $result=XDN_AI_Gemini::research($topic,self::settings());if(is_wp_error($result))wp_send_json_error($result->get_error_message());
        update_option('xdn_ai_last_research',$result,false);update_option('xdn_ai_last_research_at',current_time('mysql'),false);wp_send_json_success($result);
    }

    public static function ajax_write() {
        if(!current_user_can('publish_posts') || !check_ajax_referer('xdn_ai_nonce','nonce',false)) wp_send_json_error('Không có quyền.');
        $research=get_option('xdn_ai_last_research',array());if(!is_array($research)||!$research)wp_send_json_error('Chưa có nghiên cứu.');
        $keyword=sanitize_text_field(wp_unslash($_POST['keyword']??''));$gap=sanitize_textarea_field(wp_unslash($_POST['content_gap']??''));
        if($keyword){$research['target_keyword']=$keyword;$research['target_content_gap']=$gap;}
        $result=XDN_AI_OpenAI::write_article($research,self::settings());if(is_wp_error($result))wp_send_json_error($result->get_error_message());
        $json=self::normalize_article($result);if(is_wp_error($json))wp_send_json_error($json->get_error_message());
        $status='draft';$date=current_time('mysql');$date_gmt=current_time('mysql',true);$schedule_at=sanitize_text_field(wp_unslash($_POST['schedule_at']??''));
        if(!empty($_POST['publish_now'])){$status='publish';}
        elseif($schedule_at){$ts=strtotime(str_replace('T',' ',$schedule_at));if(!$ts||$ts<=current_time('timestamp'))wp_send_json_error('Thời gian đặt lịch phải ở tương lai.');$date=wp_date('Y-m-d H:i:s',$ts);$date_gmt=get_gmt_from_date($date);$status='future';}
        $post_id=wp_insert_post(array('post_title'=>sanitize_text_field($json['title']??'Bài viết AI'),'post_name'=>sanitize_title($json['slug']??($json['title']??'')),'post_excerpt'=>wp_kses_post($json['excerpt']??''),'post_content'=>wp_kses_post($json['content_html']??''),'post_status'=>$status,'post_type'=>'post','post_date'=>$date,'post_date_gmt'=>$date_gmt),true);
        if(is_wp_error($post_id))wp_send_json_error($post_id->get_error_message());
        update_post_meta($post_id,'_xdn_ai_created','1');update_post_meta($post_id,'_xdn_ai_focus_keyword',sanitize_text_field($json['focus_keyword']??$keyword));update_post_meta($post_id,'_xdn_ai_meta_description',sanitize_text_field($json['meta_description']??''));update_post_meta($post_id,'_xdn_ai_created_at',current_time('mysql'));
        wp_send_json_success(array('post_id'=>$post_id,'edit_url'=>get_edit_post_link($post_id,''),'status'=>$status,'date'=>get_post_time('d/m/Y H:i',true,$post_id),'data'=>$json));
    }

    public static function ajax_publish() {
        if(!current_user_can('publish_posts') || !check_ajax_referer('xdn_ai_nonce','nonce',false))wp_send_json_error('Không có quyền.');
        $post_id=absint($_POST['post_id']??0);$post=get_post($post_id);if(!$post||'post'!==$post->post_type)wp_send_json_error('Không tìm thấy bài viết.');
        $updated=wp_update_post(array('ID'=>$post_id,'post_status'=>'publish'),true);if(is_wp_error($updated))wp_send_json_error($updated->get_error_message());wp_send_json_success(array('post_id'=>$post_id,'url'=>get_permalink($post_id)));
    }

    public static function ajax_schedule() {
        if(!current_user_can('publish_posts') || !check_ajax_referer('xdn_ai_nonce','nonce',false))wp_send_json_error('Không có quyền.');
        $post_id=absint($_POST['post_id']??0);$datetime=sanitize_text_field(wp_unslash($_POST['datetime']??''));$post=get_post($post_id);if(!$post||'post'!==$post->post_type)wp_send_json_error('Không tìm thấy bài viết.');
        $ts=strtotime(str_replace('T',' ',$datetime));if(!$ts||$ts<=current_time('timestamp'))wp_send_json_error('Thời gian đặt lịch phải ở tương lai.');$date=wp_date('Y-m-d H:i:s',$ts);
        $updated=wp_update_post(array('ID'=>$post_id,'post_status'=>'future','post_date'=>$date,'post_date_gmt'=>get_gmt_from_date($date)),true);if(is_wp_error($updated))wp_send_json_error($updated->get_error_message());
        wp_send_json_success(array('post_id'=>$post_id,'date'=>wp_date('d/m/Y H:i',$ts)));
    }

    public static function ajax_posts() {
        if(!current_user_can('manage_options') || !check_ajax_referer('xdn_ai_nonce','nonce',false))wp_send_json_error('Không có quyền.');ob_start();self::render_posts_table(30);$html=ob_get_clean();wp_send_json_success(array('html'=>$html));
    }

    private static function normalize_article($result) {
        if(is_array($result)){if(isset($result['title'])||isset($result['content_html']))return $result;return new WP_Error('invalid_article','OpenAI trả về mảng nhưng thiếu dữ liệu bài viết.');}
        if(!is_string($result))return new WP_Error('invalid_article','Kết quả AI không phải chuỗi hoặc mảng.');
        $text=trim($result);$text=preg_replace('/^```(?:json)?\s*/i','',$text);$text=preg_replace('/\s*```$/','',$text);$json=json_decode($text,true);if(is_array($json))return $json;
        $start=strpos($text,'{');$end=strrpos($text,'}');if($start!==false&&$end!==false&&$end>$start){$json=json_decode(substr($text,$start,$end-$start+1),true);if(is_array($json))return $json;}
        return new WP_Error('invalid_article','AI trả về JSON không hợp lệ.');
    }
}
