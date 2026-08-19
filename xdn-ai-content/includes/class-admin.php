<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class XDN_AI_Admin {
    public static function init() {
        add_action('admin_menu',array(__CLASS__,'menu'));
        add_action('admin_init',array(__CLASS__,'register_settings'));
        add_action('wp_ajax_xdn_ai_research',array(__CLASS__,'ajax_research'));
        add_action('wp_ajax_xdn_ai_write',array(__CLASS__,'ajax_write'));
        add_action('wp_ajax_xdn_ai_image',array(__CLASS__,'ajax_image'));
    }
    private static function settings(){return get_option('xdn_ai_settings',array());}
    public static function menu(){
        add_menu_page('XDN AI Content','XDN AI Content','manage_options','xdn-ai-content',array(__CLASS__,'dashboard'),'dashicons-edit-page',58);
        add_submenu_page('xdn-ai-content','AI SEO Research','AI SEO Research','manage_options','xdn-ai-research',array(__CLASS__,'research_page'));
        add_submenu_page('xdn-ai-content','Content Calendar','Lịch nội dung','manage_options','xdn-ai-calendar',array(__CLASS__,'calendar_page'));
        add_submenu_page('xdn-ai-content','Cài đặt','Cài đặt','manage_options','xdn-ai-settings',array(__CLASS__,'settings_page'));
    }
    public static function register_settings(){register_setting('xdn_ai_settings_group','xdn_ai_settings',array(__CLASS__,'sanitize_settings'));}
    public static function sanitize_settings($in){
        $old=self::settings(); $out=$old;
        foreach(array('openai_key','gemini_key') as $k) if(isset($in[$k])&&$in[$k]!=='') $out[$k]=sanitize_text_field($in[$k]);
        $out['openai_model']=sanitize_text_field($in['openai_model']??($old['openai_model']??'gpt-5.6-luna'));
        $out['gemini_model']=sanitize_text_field($in['gemini_model']??($old['gemini_model']??'gemini-3.6-flash'));
        $out['image_model']=sanitize_text_field($in['image_model']??($old['image_model']??'gpt-image-2'));
        $out['image_size']=in_array(($in['image_size']??''),array('1024x1024','1536x1024','1024x1536'),true)?$in['image_size']:'1536x1024';
        $out['seed_topic']=sanitize_text_field($in['seed_topic']??'xe đạp Ninh Bình');
        $out['posts_per_week']=max(1,min(14,(int)($in['posts_per_week']??3)));
        $out['auto_research']=empty($in['auto_research'])?0:1;
        $out['auto_publish']=empty($in['auto_publish'])?0:1;
        $out['auto_images']=empty($in['auto_images'])?0:1;
        return $out;
    }
    private static function nonce(){return wp_create_nonce('xdn_ai_nonce');}
    public static function dashboard(){
        $last=get_option('xdn_ai_last_research',array()); $s=self::settings();
        echo '<div class="wrap"><h1>🚲 XDN AI Content Engine</h1><p>Gemini nghiên cứu Google, GPT viết và tối ưu nội dung; Rank Math, WooCommerce, ảnh và lịch đăng được tích hợp.</p>';
        echo '<p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=xdn-ai-research')).'">🔍 Tìm cơ hội SEO</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=xdn-ai-calendar')).'">📅 Lịch nội dung</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=xdn-ai-settings')).'">⚙ Cài đặt</a></p>';
        echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:20px 0">';
        echo '<div style="background:#fff;border:1px solid #ddd;padding:16px;min-width:180px"><b>Rank Math</b><br>'.(XDN_AI_RankMath::available()?'🟢 Đã phát hiện':'🟡 Chưa cài').'</div>';
        echo '<div style="background:#fff;border:1px solid #ddd;padding:16px;min-width:180px"><b>WooCommerce</b><br>'.(XDN_AI_WooCommerce::available()?'🟢 Đã phát hiện':'🟡 Chưa cài').'</div>';
        echo '<div style="background:#fff;border:1px solid #ddd;padding:16px;min-width:180px"><b>Auto Publish</b><br>'.(!empty($s['auto_publish'])?'🟢 Bật':'🔴 Tắt').'</div></div>';
        if($last){echo '<h2>Cơ hội SEO gần nhất</h2>';self::render_research($last);} else echo '<p>Chưa có nghiên cứu. Hãy chạy nghiên cứu đầu tiên.</p>';
        echo '</div>';
    }
    private static function render_research($d){
        echo '<table class="widefat striped"><thead><tr><th>Keyword</th><th>Intent</th><th>Opportunity</th><th>Priority</th><th></th></tr></thead><tbody>';
        foreach(($d['keyword_opportunities']??array()) as $i=>$r){$score=(int)($r['opportunity_score']??0);echo '<tr><td><strong>'.esc_html($r['keyword']??'').'</strong></td><td>'.esc_html($r['intent']??'').'</td><td><b>'.esc_html($score).'/100</b></td><td>'.esc_html($r['priority']??'').'</td><td><button class="button xdn-write" data-index="'.esc_attr($i).'">✍️ Tạo bài</button></td></tr>';}
        echo '</tbody></table><div id="xdn-write-status" style="margin-top:12px"></div>';
        echo '<script>window.XDN_AI=window.XDN_AI||{};XDN_AI.ajax="'.esc_js(admin_url('admin-ajax.php')).'";XDN_AI.nonce="'.esc_js(self::nonce()).'";XDN_AI.research='.wp_json_encode($d).';</script>';
        echo '<script>(function(){document.querySelectorAll(".xdn-write").forEach(function(b){b.onclick=function(){b.disabled=true;document.getElementById("xdn-write-status").textContent="Đang viết và tạo Draft...";var f=new FormData();f.append("action","xdn_ai_write");f.append("nonce",XDN_AI.nonce);f.append("index",b.dataset.index);fetch(XDN_AI.ajax,{method:"POST",body:f}).then(r=>r.json()).then(j=>{b.disabled=false;if(!j.success){document.getElementById("xdn-write-status").textContent="Lỗi: "+j.data;return;}document.getElementById("xdn-write-status").innerHTML="✅ Đã tạo Draft: <a href=\""+j.data.edit_url+"\">Mở bài</a> — SEO hint: "+j.data.rank_score+"/100";}).catch(function(){b.disabled=false;document.getElementById("xdn-write-status").textContent="Lỗi kết nối";});};});})();</script>';
        if(!empty($d['content_gaps'])){echo '<h3>Content gaps</h3><ul>';foreach($d['content_gaps'] as $x)echo '<li>'.esc_html($x).'</li>';echo '</ul>';}
        if(!empty($d['sources'])){echo '<h3>Nguồn nghiên cứu</h3><ul>';foreach($d['sources'] as $x)echo '<li><a target="_blank" rel="noopener" href="'.esc_url($x['url']??'').'">'.esc_html($x['title']??$x['url']??'Nguồn').'</a></li>';echo '</ul>';}
    }
    public static function research_page(){
        $s=self::settings();$last=get_option('xdn_ai_last_research',array());
        echo '<div class="wrap"><h1>🔍 AI SEO Research</h1><p>Gemini sử dụng Google Search Grounding để nghiên cứu web hiện tại và trả về cơ hội nội dung cùng nguồn tham khảo.</p>';
        echo '<table class="form-table"><tr><th>Chủ đề</th><td><input id="xdn-topic" class="regular-text" value="'.esc_attr($s['seed_topic']??'xe đạp Ninh Bình').'"></td></tr></table><p><button id="xdn-research" class="button button-primary">🔍 Tìm cơ hội SEO</button> <span id="xdn-status"></span></p><div id="xdn-result">';if($last)self::render_research($last);echo '</div>';
        echo '<script>window.XDN_AI={ajax:"'.esc_js(admin_url('admin-ajax.php')).'",nonce:"'.esc_js(self::nonce()).'"};</script><script>(function(){var b=document.getElementById("xdn-research");if(!b)return;b.onclick=function(){b.disabled=true;document.getElementById("xdn-status").textContent="Đang nghiên cứu Google...";var f=new FormData();f.append("action","xdn_ai_research");f.append("nonce",XDN_AI.nonce);f.append("topic",document.getElementById("xdn-topic").value);fetch(XDN_AI.ajax,{method:"POST",body:f}).then(r=>r.json()).then(j=>{b.disabled=false;if(!j.success){document.getElementById("xdn-status").textContent="Lỗi: "+j.data;return;}document.getElementById("xdn-status").textContent="Hoàn tất";location.reload();}).catch(function(){b.disabled=false;document.getElementById("xdn-status").textContent="Lỗi kết nối";});};})();</script></div>';
    }
    public static function calendar_page(){
        $posts=get_posts(array('post_type'=>'post','post_status'=>array('draft','future','publish'),'posts_per_page'=>30,'meta_key'=>'_xdn_ai_generated','meta_value'=>1,'orderby'=>'date','order'=>'ASC'));
        echo '<div class="wrap"><h1>📅 Lịch nội dung</h1><table class="widefat striped"><thead><tr><th>Bài</th><th>Trạng thái</th><th>Ngày</th><th>SEO</th></tr></thead><tbody>';
        foreach($posts as $p)echo '<tr><td><a href="'.esc_url(get_edit_post_link($p->ID)).'">'.esc_html($p->post_title).'</a></td><td>'.esc_html($p->post_status).'</td><td>'.esc_html(get_the_date('Y-m-d H:i',$p)).'</td><td>'.esc_html(XDN_AI_RankMath::score_hint($p->ID)??'—').'</td></tr>';
        echo '</tbody></table></div>';
    }
    public static function settings_page(){
        $s=self::settings();echo '<div class="wrap"><h1>⚙ XDN AI Settings</h1><form method="post" action="options.php">';settings_fields('xdn_ai_settings_group');echo '<table class="form-table">';
        echo '<tr><th>OpenAI API Key</th><td><input type="password" name="xdn_ai_settings[openai_key]" class="regular-text" placeholder="Giữ trống để giữ key hiện tại"></td></tr>';
        echo '<tr><th>OpenAI text model</th><td><input name="xdn_ai_settings[openai_model]" class="regular-text" value="'.esc_attr($s['openai_model']??'gpt-5.6-luna').'"></td></tr>';
        echo '<tr><th>OpenAI image model</th><td><input name="xdn_ai_settings[image_model]" class="regular-text" value="'.esc_attr($s['image_model']??'gpt-image-2').'"></td></tr>';
        echo '<tr><th>Kích thước ảnh AI</th><td><select name="xdn_ai_settings[image_size]">';foreach(array('1024x1024','1536x1024','1024x1536') as $v)echo '<option value="'.$v.'" '.selected($s['image_size']??'1536x1024',$v,false).'>'.$v.'</option>';echo '</select></td></tr>';
        echo '<tr><th>Gemini API Key</th><td><input type="password" name="xdn_ai_settings[gemini_key]" class="regular-text" placeholder="Giữ trống để giữ key hiện tại"></td></tr>';
        echo '<tr><th>Gemini model</th><td><input name="xdn_ai_settings[gemini_model]" class="regular-text" value="'.esc_attr($s['gemini_model']??'gemini-3.6-flash').'"></td></tr>';
        echo '<tr><th>Chủ đề gốc</th><td><input name="xdn_ai_settings[seed_topic]" class="regular-text" value="'.esc_attr($s['seed_topic']??'xe đạp Ninh Bình').'"></td></tr>';
        echo '<tr><th>Số bài/tuần</th><td><input type="number" min="1" max="14" name="xdn_ai_settings[posts_per_week]" value="'.esc_attr($s['posts_per_week']??3).'"> <span class="description">Dùng cho lịch nội dung về sau.</span></td></tr>';
        echo '<tr><th>Tự nghiên cứu hàng ngày</th><td><label><input type="checkbox" name="xdn_ai_settings[auto_research]" value="1" '.checked(!empty($s['auto_research']),true,false).'> Bật</label></td></tr>';
        echo '<tr><th>Tự tạo ảnh AI</th><td><label><input type="checkbox" name="xdn_ai_settings[auto_images]" value="1" '.checked(!empty($s['auto_images']),true,false).'> Khi không có ảnh phù hợp</label></td></tr>';
        echo '<tr><th>Auto Publish</th><td><label><input type="checkbox" name="xdn_ai_settings[auto_publish]" value="1" '.checked(!empty($s['auto_publish']),true,false).'> <strong>Chỉ bật sau khi đã kiểm thử</strong></label></td></tr>';
        echo '</table>';submit_button('Lưu cài đặt');echo '</form><hr><p><strong>Rank Math:</strong> '.(XDN_AI_RankMath::available()?'Đã phát hiện. Plugin sẽ ghi focus keyword, SEO title, meta description và canonical vào post meta của Rank Math.':'Chưa phát hiện.').'</p></div>';
    }
    public static function ajax_research(){
        if(!current_user_can('manage_options')||!check_ajax_referer('xdn_ai_nonce','nonce',false))wp_send_json_error('Không có quyền.');
        $topic=sanitize_text_field(wp_unslash($_POST['topic']??''));if(!$topic)wp_send_json_error('Vui lòng nhập chủ đề.');
        $r=XDN_AI_Gemini::research($topic,self::settings());if(is_wp_error($r))wp_send_json_error($r->get_error_message());
        update_option('xdn_ai_last_research',$r,false);update_option('xdn_ai_last_research_at',current_time('mysql'),false);wp_send_json_success($r);
    }
    public static function ajax_write(){
        if(!current_user_can('publish_posts')||!check_ajax_referer('xdn_ai_nonce','nonce',false))wp_send_json_error('Không có quyền.');
        $research=get_option('xdn_ai_last_research',array());if(!$research)wp_send_json_error('Chưa có nghiên cứu.');
        $index=(int)($_POST['index']??0);$opp=$research['keyword_opportunities'][$index]??reset($research['keyword_opportunities']);
        $result=XDN_AI_OpenAI::write_article($research,self::settings(),$opp);if(is_wp_error($result))wp_send_json_error($result->get_error_message());
        $data=json_decode(trim($result),true);if(!is_array($data)){$result=preg_replace('/^```(?:json)?\s*|\s*```$/i','',trim($result));$data=json_decode($result,true);}if(!is_array($data))wp_send_json_error('GPT trả JSON không hợp lệ.');
        $products=XDN_AI_WooCommerce::products($opp['keyword']??'',5);
        $html=wp_kses_post($data['content_html']??'');$html=XDN_AI_WooCommerce::append_product_box($html,$products);
        $image_id=0;$s=self::settings();
        if(!empty($s['auto_images'])){$prompt='Create a clean editorial hero image for a Vietnamese bicycle article about '.($data['title']??$opp['keyword']??'bicycle').'. Realistic commercial photography, Vietnam/Ninh Binh context, no text, no logos, no watermark, natural light.';$image_id=XDN_AI_Images::generate($prompt,$s);if(is_wp_error($image_id))$image_id=0;}
        $status=!empty($s['auto_publish'])?'future':'draft';$date=current_time('timestamp')+DAY_IN_SECONDS;
        $post=array('post_title'=>sanitize_text_field($data['title']??'Bài viết Xe Đạp Ninh Bình'),'post_name'=>sanitize_title($data['slug']??$data['title']??''),'post_excerpt'=>wp_kses_post($data['excerpt']??''),'post_content'=>$html,'post_status'=>$status,'post_type'=>'post');if($status==='future')$post['post_date']=date('Y-m-d H:i:s',$date);
        $post_id=wp_insert_post($post,true);if(is_wp_error($post_id))wp_send_json_error($post_id->get_error_message());
        update_post_meta($post_id,'_xdn_ai_generated',1);update_post_meta($post_id,'_xdn_ai_source_research',wp_json_encode($research,JSON_UNESCAPED_UNICODE));update_post_meta($post_id,'_xdn_ai_products',wp_json_encode($products,JSON_UNESCAPED_UNICODE));
        XDN_AI_RankMath::save($post_id,$data);if($image_id&&!is_wp_error($image_id)){XDN_AI_Images::set_alt($image_id,$data['image_alt']??($data['focus_keyword']??$data['title']??''));set_post_thumbnail($post_id,$image_id);}
        wp_send_json_success(array('post_id'=>$post_id,'edit_url'=>get_edit_post_link($post_id,''),'rank_score'=>XDN_AI_RankMath::score_hint($post_id),'image_id'=>$image_id,'status'=>$status));
    }
    public static function ajax_image(){
        if(!current_user_can('upload_files')||!check_ajax_referer('xdn_ai_nonce','nonce',false))wp_send_json_error('Không có quyền.');$prompt=sanitize_textarea_field(wp_unslash($_POST['prompt']??''));if(!$prompt)wp_send_json_error('Thiếu prompt.');$id=XDN_AI_Images::generate($prompt,self::settings());if(is_wp_error($id))wp_send_json_error($id->get_error_message());wp_send_json_success(array('id'=>$id,'url'=>wp_get_attachment_image_url($id,'large')));
    }
}
