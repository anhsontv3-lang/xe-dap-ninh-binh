<?php
/**
 * Plugin Name: XDN AI Content Engine
 * Description: AI SEO research, source-driven content, weekly publishing, smart images and logo watermarking.
 * Version: 0.2.2
 * Author: Xe Đạp Ninh Bình
 */
if(!defined('ABSPATH'))exit;
define('XDN_AI_VERSION','0.2.2');define('XDN_AI_FILE',__FILE__);define('XDN_AI_DIR',plugin_dir_path(__FILE__));
require_once XDN_AI_DIR.'includes/class-openai.php';require_once XDN_AI_DIR.'includes/class-gemini.php';require_once XDN_AI_DIR.'includes/class-image.php';require_once XDN_AI_DIR.'includes/class-admin.php';
final class XDN_AI_Content_Engine{
 public static function init(){XDN_AI_Admin::init();XDN_AI_Image::init();add_action('xdn_ai_hourly',array(__CLASS__,'scheduler'));if(!wp_next_scheduled('xdn_ai_hourly'))wp_schedule_event(time()+300,'hourly','xdn_ai_hourly');}
 public static function activate(){wp_clear_scheduled_hook('xdn_ai_daily_research');wp_clear_scheduled_hook('xdn_ai_daily_content');if(!wp_next_scheduled('xdn_ai_hourly'))wp_schedule_event(time()+300,'hourly','xdn_ai_hourly');}
 public static function deactivate(){wp_clear_scheduled_hook('xdn_ai_daily_research');wp_clear_scheduled_hook('xdn_ai_daily_content');wp_clear_scheduled_hook('xdn_ai_hourly');}
 public static function scheduler(){self::scheduled_research();self::scheduled_content();}
 public static function scheduled_research(){
  $s=get_option('xdn_ai_settings',array());if(empty($s['auto_research'])||empty($s['gemini_key']))return;
  $last=get_option('xdn_ai_last_research_at','');if($last&&strtotime($last)>current_time('timestamp')-20*HOUR_IN_SECONDS)return;
  $topic=!empty($s['seed_topic'])?$s['seed_topic']:'xe đạp Ninh Bình';$r=XDN_AI_Gemini::research($topic,$s);if(!is_wp_error($r)){update_option('xdn_ai_last_research',$r,false);update_option('xdn_ai_last_research_at',current_time('mysql'),false);}
 }
 public static function scheduled_content(){
  $s=get_option('xdn_ai_settings',array());if(empty($s['auto_publish'])||empty($s['openai_key']))return;
  $schedule=!empty($s['schedule'])&&is_array($s['schedule'])?$s['schedule']:self::default_schedule();$dow=(int)current_time('N');$now=current_time('timestamp');$slot=$schedule[$dow]??array();
  if(empty($slot['enabled'])||empty($slot['time']))return;
  $target=strtotime(current_time('Y-m-d').' '.$slot['time']);if(abs($now-$target)>3599)return;
  $done_key='xdn_ai_slot_done_'.current_time('Y-m-d').'_'.$dow;if(get_option($done_key,false))return;
  $target_week=max(1,min(7,(int)($s['posts_per_week']??7)));$week_key='xdn_ai_week_count_'.date('o-W',$now);$count=(int)get_option($week_key,0);if($count>=$target_week)return;
  $topics=!empty($s['topic_pool'])&&is_array($s['topic_pool'])?$s['topic_pool']:self::default_topics();$idx=(int)get_option('xdn_ai_topic_index',0);$topic=$slot['topic']??$topics[$idx%count($topics)];update_option('xdn_ai_topic_index',$idx+1,false);
  $research=array('topic'=>$topic,'search_intent'=>'informational/local/commercial','keyword_opportunities'=>array(array('keyword'=>$topic,'intent'=>'informational','priority'=>'high','reason'=>'Weekly content plan')),'content_gaps'=>array(),'recommended_titles'=>array($topic),'questions'=>array(),'sources'=>array());
  if(!empty($s['gemini_key'])){$fresh=XDN_AI_Gemini::research($topic,$s);if(!is_wp_error($fresh))$research=$fresh;}
  $r=XDN_AI_OpenAI::write_article($research,$s);if(is_wp_error($r))return;$j=json_decode(trim(preg_replace('/^```(?:json)?\s*|\s*```$/i','',trim($r))),true);if(!is_array($j))return;
  $status=($s['publish_mode']??'draft')==='publish'?'publish':'draft';$post=wp_insert_post(array('post_title'=>sanitize_text_field($j['title']??$topic),'post_name'=>sanitize_title($j['slug']??$topic),'post_excerpt'=>wp_kses_post($j['excerpt']??''),'post_content'=>wp_kses_post($j['content_html']??''),'post_status'=>$status,'post_type'=>'post'),true);if(is_wp_error($post))return;
  update_post_meta($post,'_xdn_ai_focus_keyword',sanitize_text_field($j['focus_keyword']??$topic));update_post_meta($post,'_xdn_ai_meta_description',sanitize_text_field($j['meta_description']??''));update_post_meta($post,'_xdn_ai_source_data',wp_json_encode($research,JSON_UNESCAPED_UNICODE));if(!empty($j['image_specs'])&&is_array($j['image_specs']))XDN_AI_Image::resolve_post_images($post,$j['image_specs'],$s);
  update_option($week_key,$count+1,false);update_option($done_key,1,false);update_option('xdn_ai_last_post_id',$post,false);update_option('xdn_ai_last_post_at',current_time('mysql'),false);
 }
 public static function default_schedule(){return array(1=>array('enabled'=>1,'time'=>'09:00'),2=>array('enabled'=>1,'time'=>'09:00'),3=>array('enabled'=>1,'time'=>'09:00'),4=>array('enabled'=>1,'time'=>'09:00'),5=>array('enabled'=>1,'time'=>'09:00'),6=>array('enabled'=>1,'time'=>'09:00'),7=>array('enabled'=>1,'time'=>'09:00'));}
 public static function default_topics(){return array('Sửa chữa phanh xe đạp','Sửa chữa đề xe đạp','Xích xe đạp bị tuột và cách xử lý','Cách bảo dưỡng xe đạp đúng cách','Cách chọn xe đạp theo chiều cao','Xe đạp cho học sinh','Các lỗi xe đạp thường gặp','Cách vệ sinh xe đạp','Khi nào cần thay má phanh xe đạp','Cách căn chỉnh đề xe đạp','Bánh xe đạp bị đảo phải làm sao','Kinh nghiệm mua xe đạp cũ','Phụ kiện xe đạp cần thiết','Xe đạp thể thao cho người mới','Xe đạp địa hình và cách chọn','Bảo quản xe đạp trong mùa mưa','Sửa xích và líp xe đạp','Bảo dưỡng xe đạp điện','Mua xe đạp tại Ninh Bình','Đạp xe và các cung đường đẹp ở Ninh Bình');}
}
register_activation_hook(__FILE__,array('XDN_AI_Content_Engine','activate'));register_deactivation_hook(__FILE__,array('XDN_AI_Content_Engine','deactivate'));add_action('plugins_loaded',array('XDN_AI_Content_Engine','init'));