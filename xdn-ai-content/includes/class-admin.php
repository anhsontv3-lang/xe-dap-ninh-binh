<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class XDN_AI_Admin {
    public static function init() {
        add_action('admin_menu',array(__CLASS__,'menu'));
        add_action('admin_init',array(__CLASS__,'register_settings'));
        add_action('admin_enqueue_scripts',array(__CLASS__,'assets'));
        add_action('wp_ajax_xdn_ai_research',array(__CLASS__,'ajax_research'));
        add_action('wp_ajax_xdn_ai_write',array(__CLASS__,'ajax_write'));
        add_action('wp_ajax_xdn_ai_watermark_all',array(__CLASS__,'ajax_watermark_all'));
    }
    public static function menu(){
        add_menu_page('XDN AI Content','XDN AI Content','manage_options','xdn-ai-content',array(__CLASS__,'dashboard'),'dashicons-edit-page',58);
        add_submenu_page('xdn-ai-content','Content Planner','Content Planner','manage_options','xdn-ai-planner',array(__CLASS__,'planner_page'));
        add_submenu_page('xdn-ai-content','AI SEO Research','AI SEO Research','manage_options','xdn-ai-research',array(__CLASS__,'research_page'));
        add_submenu_page('xdn-ai-content','Cài đặt','Cài đặt','manage_options','xdn-ai-settings',array(__CLASS__,'settings_page'));
    }
    public static function assets($hook){ if(strpos($hook,'xdn-ai')===false)return; wp_enqueue_media(); }
    public static function register_settings(){ register_setting('xdn_ai_settings_group','xdn_ai_settings',array(__CLASS__,'sanitize_settings')); }
    public static function sanitize_settings($input){
        $old=get_option('xdn_ai_settings',array()); $out=$old;
        $out['openai_key']=isset($input['openai_key'])&&$input['openai_key']!==''?sanitize_text_field($input['openai_key']):($old['openai_key']??'');
        $out['gemini_key']=isset($input['gemini_key'])&&$input['gemini_key']!==''?sanitize_text_field($input['gemini_key']):($old['gemini_key']??'');
        $out['openai_model']=sanitize_text_field($input['openai_model']??'gpt-5.6-luna'); $out['gemini_model']=sanitize_text_field($input['gemini_model']??'gemini-3.6-flash');
        $out['seed_topic']=sanitize_text_field($input['seed_topic']??'xe đạp Ninh Bình'); $out['auto_research']=!empty($input['auto_research'])?1:0; $out['auto_publish']=!empty($input['auto_publish'])?1:0;
        $out['publish_mode']=in_array(($input['publish_mode']??'draft'),array('draft','publish'),true)?$input['publish_mode']:'draft'; $out['posts_per_week']=max(1,min(7,(int)($input['posts_per_week']??7)));
        $out['watermark_enabled']=!empty($input['watermark_enabled'])?1:0; $out['logo_id']=absint($input['logo_id']??0); $out['logo_position']=sanitize_key($input['logo_position']??'bottom-right');
        $out['logo_size']=max(5,min(30,(float)($input['logo_size']??12))); $out['logo_opacity']=max(0,min(100,(int)($input['logo_opacity']??85))); $out['logo_margin']=max(1,min(10,(float)($input['logo_margin']??2)));
        $out['source_urls']=array_values(array_filter(array_map('esc_url_raw',preg_split('/\r\n|\r|\n/',(string)($input['source_urls']??'')))));
        $out['topic_pool']=array_values(array_filter(array_map('sanitize_text_field',preg_split('/\r\n|\r|\n/',(string)($input['topic_pool']??'')))));
        return $out;
    }
    private static function settings(){return get_option('xdn_ai_settings',array());}
    public static function dashboard(){
        $s=self::settings(); $week=(int)($s['posts_per_week']??7); $last=get_option('xdn_ai_last_post_id',0);
        echo '<div class="wrap"><h1>🚲 XDN AI Content Engine 0.2.0</h1><p>Hệ thống xây dựng nội dung, SEO, ảnh và lịch đăng tự động.</p><div style="display:flex;gap:12px;flex-wrap:wrap">';
        foreach(array('Mục tiêu tuần'=>$week,'Logo ID'=>($s['logo_id']??0),'Bài gần nhất'=>$last) as $k=>$v) echo '<div style="background:#fff;border:1px solid #ddd;padding:18px;min-width:150px"><b>'.esc_html($k).'</b><div style="font-size:24px;margin-top:8px">'.esc_html($v).'</div></div>';
        echo '</div><p style="margin-top:20px"><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=xdn-ai-planner')).'">📝 Content Planner</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=xdn-ai-settings')).'">⚙ Cài đặt</a></p>';
        echo !empty($s['watermark_enabled'])?'<p>✅ Tự động đóng logo đang <b>bật</b> cho ảnh mới và ảnh sản phẩm.</p>':'<p>⚠️ Tự động đóng logo đang tắt.</p>'; echo '</div>';
    }
    public static function planner_page(){
        $s=self::settings(); $topics=!empty($s['topic_pool'])?$s['topic_pool']:XDN_AI_Content_Engine::default_topics();
        echo '<div class="wrap"><h1>📝 Content Planner</h1><p>Mặc định 7 bài/tuần, ưu tiên sửa chữa, bảo dưỡng, tư vấn, sản phẩm và Ninh Bình.</p><table class="widefat striped"><thead><tr><th>#</th><th>Chủ đề</th><th>Nhóm</th><th>Trạng thái</th></tr></thead><tbody>';
        foreach($topics as $i=>$topic){$group='Kiến thức';if(stripos($topic,'sửa')!==false||stripos($topic,'bảo dưỡng')!==false)$group='Sửa chữa/Bảo dưỡng';elseif(stripos($topic,'mua')!==false||stripos($topic,'chọn')!==false||stripos($topic,'xe đạp cho')!==false)$group='Tư vấn';elseif(stripos($topic,'Ninh Bình')!==false)$group='Local SEO';echo '<tr><td>'.($i+1).'</td><td>'.esc_html($topic).'</td><td>'.esc_html($group).'</td><td>Chờ AI xử lý</td></tr>';}
        echo '</tbody></table></div>';
    }
    public static function research_page(){
        $s=self::settings();$nonce=wp_create_nonce('xdn_ai_nonce');
        echo '<div class="wrap"><h1>AI SEO Research</h1><p>Gemini nghiên cứu Google Search, sau đó XDN dùng dữ liệu để viết bài mới.</p><table class="form-table"><tr><th>Chủ đề gốc</th><td><input id="xdn-topic" type="text" class="regular-text" value="'.esc_attr($s['seed_topic']??'xe đạp Ninh Bình').'"></td></tr></table><p><button id="xdn-research" class="button button-primary">🔍 Tìm cơ hội SEO</button> <span id="xdn-status"></span></p><div id="xdn-result"></div>';
        echo '<script>window.XDN_AI={ajax:"'.esc_js(admin_url('admin-ajax.php')).'",nonce:"'.esc_js($nonce).'"};</script><script>(function(){const b=document.getElementById("xdn-research"),s=document.getElementById("xdn-status"),r=document.getElementById("xdn-result");b.addEventListener("click",function(){b.disabled=true;s.textContent=" Đang nghiên cứu...";const f=new FormData();f.append("action","xdn_ai_research");f.append("nonce",XDN_AI.nonce);f.append("topic",document.getElementById("xdn-topic").value);fetch(XDN_AI.ajax,{method:"POST",body:f}).then(x=>x.json()).then(j=>{b.disabled=false;if(!j.success){s.textContent=" Lỗi: "+j.data;return;}s.textContent=" Hoàn tất";let h="<h2>Keyword opportunities</h2><table class=\"widefat striped\"><thead><tr><th>Keyword</th><th>Intent</th><th>Priority</th><th>Reason</th></tr></thead><tbody>";(j.data.keyword_opportunities||[]).forEach(x=>h+="<tr><td>"+(x.keyword||"")+"</td><td>"+(x.intent||"")+"</td><td>"+(x.priority||"")+"</td><td>"+(x.reason||"")+"</td></tr>");h+="</tbody></table><h2>Content gaps</h2><ul>";(j.data.content_gaps||[]).forEach(x=>h+="<li>"+x+"</li>");h+="</ul><h2>Tiêu đề đề xuất</h2><ol>";(j.data.recommended_titles||[]).forEach(x=>h+="<li>"+x+"</li>");h+="</ol>";r.innerHTML=h;}).catch(()=>{b.disabled=false;s.textContent=" Lỗi kết nối";});});})();</script></div>';
    }
    public static function settings_page(){
        $s=self::settings();$logo=(int)($s['logo_id']??0);$logo_url=$logo?wp_get_attachment_image_url($logo,'thumbnail'):'';
        echo '<div class="wrap"><h1>⚙ Cài đặt XDN AI</h1><form method="post" action="options.php">';settings_fields('xdn_ai_settings_group');echo '<table class="form-table">';
        echo '<tr><th>OpenAI API Key</th><td><input type="password" name="xdn_ai_settings[openai_key]" class="regular-text" placeholder="Giữ trống để giữ key hiện tại"> <span>Đã lưu: '.(!empty($s['openai_key'])?'Có':'Chưa có').'</span></td></tr>';
        echo '<tr><th>OpenAI Model</th><td><input name="xdn_ai_settings[openai_model]" class="regular-text" value="'.esc_attr($s['openai_model']??'gpt-5.6-luna').'"></td></tr>';
        echo '<tr><th>Gemini API Key</th><td><input type="password" name="xdn_ai_settings[gemini_key]" class="regular-text" placeholder="Giữ trống để giữ key hiện tại"> <span>Đã lưu: '.(!empty($s['gemini_key'])?'Có':'Chưa có').'</span></td></tr>';
        echo '<tr><th>Gemini Model</th><td><input name="xdn_ai_settings[gemini_model]" class="regular-text" value="'.esc_attr($s['gemini_model']??'gemini-3.6-flash').'"></td></tr>';
        echo '<tr><th>Chủ đề gốc</th><td><input name="xdn_ai_settings[seed_topic]" class="regular-text" value="'.esc_attr($s['seed_topic']??'xe đạp Ninh Bình').'"></td></tr>';
        echo '<tr><th>Lịch nội dung</th><td><input type="number" min="1" max="7" name="xdn_ai_settings[posts_per_week]" value="'.esc_attr($s['posts_per_week']??7).'"> bài/tuần<br><label><input type="checkbox" name="xdn_ai_settings[auto_publish]" value="1" '.checked(!empty($s['auto_publish']),true,false).'> Bật tự động tạo bài hàng ngày</label><br><select name="xdn_ai_settings[publish_mode]"><option value="draft" '.selected($s['publish_mode']??'draft','draft',false).'>Tạo bản nháp</option><option value="publish" '.selected($s['publish_mode']??'draft','publish',false).'>Tự động xuất bản</option></select></td></tr>';
        echo '<tr><th>Tự nghiên cứu</th><td><label><input type="checkbox" name="xdn_ai_settings[auto_research]" value="1" '.checked(!empty($s['auto_research']),true,false).'> Tự nghiên cứu hàng ngày</label></td></tr>';
        echo '<tr><th>Nguồn tham khảo</th><td><textarea name="xdn_ai_settings[source_urls]" rows="6" class="large-text code" placeholder="Mỗi dòng một URL website/RSS">'.esc_textarea(implode("\n",$s['source_urls']??array())).'</textarea><p class="description">AI đọc tối đa 5 nguồn và viết lại/tổng hợp, không copy nguyên bài.</p></td></tr>';
        echo '<tr><th>Kho chủ đề</th><td><textarea name="xdn_ai_settings[topic_pool]" rows="10" class="large-text">'.esc_textarea(implode("\n",$s['topic_pool']??XDN_AI_Content_Engine::default_topics())).'</textarea></td></tr>';
        echo '<tr><th>Logo thương hiệu</th><td><input type="hidden" id="xdn-logo-id" name="xdn_ai_settings[logo_id]" value="'.esc_attr($logo).'"> <button type="button" class="button" id="xdn-select-logo">Chọn logo từ Media Library</button> <span id="xdn-logo-preview">'.($logo_url?'<img src="'.esc_url($logo_url).'" style="max-width:100px;max-height:60px;vertical-align:middle;margin-left:10px">':'Chưa chọn').'</span></td></tr>';
        echo '<tr><th>Đóng logo tự động</th><td><label><input type="checkbox" name="xdn_ai_settings[watermark_enabled]" value="1" '.checked(!empty($s['watermark_enabled']),true,false).'> Đóng logo lên tất cả ảnh được WordPress xử lý, gồm ảnh sản phẩm</label></td></tr>';
        echo '<tr><th>Vị trí</th><td><select name="xdn_ai_settings[logo_position]">';foreach(array('top-left'=>'Trên trái','top-right'=>'Trên phải','bottom-left'=>'Dưới trái','bottom-right'=>'Dưới phải','center'=>'Chính giữa') as $k=>$v)echo '<option value="'.esc_attr($k).'" '.selected($s['logo_position']??'bottom-right',$k,false).'>'.esc_html($v).'</option>';echo '</select></td></tr>';
        echo '<tr><th>Kích thước logo</th><td><input type="number" min="5" max="30" step="0.5" name="xdn_ai_settings[logo_size]" value="'.esc_attr($s['logo_size']??12).'"> % chiều rộng ảnh</td></tr><tr><th>Độ trong suốt</th><td><input type="number" min="0" max="100" name="xdn_ai_settings[logo_opacity]" value="'.esc_attr($s['logo_opacity']??85).'"> %</td></tr><tr><th>Khoảng cách mép</th><td><input type="number" min="1" max="10" step="0.5" name="xdn_ai_settings[logo_margin]" value="'.esc_attr($s['logo_margin']??2).'"> %</td></tr>';
        echo '</table>';submit_button('Lưu cài đặt');echo '</form><hr><h2>Ảnh hiện có</h2><p>Xử lý các ảnh Media Library chưa có dấu logo, bao gồm ảnh sản phẩm đã có.</p><button type="button" class="button button-primary" id="xdn-watermark-all">🖼️ Đóng logo cho tất cả ảnh chưa xử lý</button> <span id="xdn-watermark-status"></span>';
        $nonce=wp_create_nonce('xdn_ai_nonce');echo '<script>(function(){const b=document.getElementById("xdn-select-logo");if(b)b.addEventListener("click",function(){const f=wp.media({title:"Chọn logo",button:{text:"Dùng logo này"},multiple:false,library:{type:"image"}});f.on("select",function(){const a=f.state().get("selection").first().toJSON();document.getElementById("xdn-logo-id").value=a.id;document.getElementById("xdn-logo-preview").innerHTML="<img src=\""+(a.sizes&&a.sizes.thumbnail?a.sizes.thumbnail.url:a.url)+"\" style=\"max-width:100px;max-height:60px;vertical-align:middle;margin-left:10px\">";});f.open();});const w=document.getElementById("xdn-watermark-all");if(w)w.addEventListener("click",function(){w.disabled=true;document.getElementById("xdn-watermark-status").textContent=" Đang xử lý...";const f=new FormData();f.append("action","xdn_ai_watermark_all");f.append("nonce","'.$nonce.'");fetch("'.esc_url(admin_url('admin-ajax.php')).'",{method:"POST",body:f}).then(x=>x.json()).then(j=>{w.disabled=false;document.getElementById("xdn-watermark-status").textContent=j.success?" Đã xử lý "+j.data+" ảnh.":" Lỗi: "+j.data;}).catch(()=>{w.disabled=false;document.getElementById("xdn-watermark-status").textContent=" Lỗi kết nối";});});})();</script></div>';
    }
    public static function ajax_research(){
        if(!current_user_can('manage_options')||!check_ajax_referer('xdn_ai_nonce','nonce',false))wp_send_json_error('Không có quyền.');$topic=sanitize_text_field(wp_unslash($_POST['topic']??''));if(!$topic)wp_send_json_error('Vui lòng nhập chủ đề.');$result=XDN_AI_Gemini::research($topic,self::settings());if(is_wp_error($result))wp_send_json_error($result->get_error_message());update_option('xdn_ai_last_research',$result,false);update_option('xdn_ai_last_research_at',current_time('mysql'),false);wp_send_json_success($result);
    }
    public static function ajax_write(){
        if(!current_user_can('publish_posts')||!check_ajax_referer('xdn_ai_nonce','nonce',false))wp_send_json_error('Không có quyền.');$research=get_option('xdn_ai_last_research',array());if(!$research)wp_send_json_error('Chưa có nghiên cứu.');$result=XDN_AI_OpenAI::write_article($research,self::settings());if(is_wp_error($result))wp_send_json_error($result->get_error_message());$json=json_decode(trim(preg_replace('/^```(?:json)?\s*|\s*```$/i','',trim($result))),true);if(!is_array($json))wp_send_json_error('GPT trả về dữ liệu không hợp lệ.');$post_id=wp_insert_post(array('post_title'=>sanitize_text_field($json['title']??'Bài viết AI'),'post_name'=>sanitize_title($json['slug']??($json['title']??'')),'post_excerpt'=>wp_kses_post($json['excerpt']??''),'post_content'=>wp_kses_post($json['content_html']??''),'post_status'=>'draft','post_type'=>'post'),true);if(is_wp_error($post_id))wp_send_json_error($post_id->get_error_message());update_post_meta($post_id,'_xdn_ai_focus_keyword',sanitize_text_field($json['focus_keyword']??''));update_post_meta($post_id,'_xdn_ai_meta_description',sanitize_text_field($json['meta_description']??''));wp_send_json_success(array('post_id'=>$post_id,'edit_url'=>get_edit_post_link($post_id,''),'data'=>$json));
    }
    public static function ajax_watermark_all(){
        if(!current_user_can('manage_options')||!check_ajax_referer('xdn_ai_nonce','nonce',false))wp_send_json_error('Không có quyền.');$count=XDN_AI_Image::process_existing_images();if(!$count&&empty(self::settings()['logo_id']))wp_send_json_error('Chưa chọn logo hoặc tính năng đóng logo đang tắt.');wp_send_json_success($count);
    }
}
