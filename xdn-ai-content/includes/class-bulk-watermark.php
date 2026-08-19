<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Bulk watermark manager.
 * Processes existing Media Library images in small AJAX batches so shared hosting
 * is less likely to hit PHP execution limits. Featured thumbnails are skipped by
 * XDN_AI_Images::watermark_attachment().
 */
class XDN_AI_Bulk_Watermark {
    const BATCH = 10;

    public static function init() {
        add_submenu_page('xdn-ai-content', 'Watermark ảnh cũ', 'Watermark ảnh cũ', 'manage_options', 'xdn-ai-bulk-watermark', array(__CLASS__, 'page'));
        add_action('wp_ajax_xdn_ai_bulk_watermark', array(__CLASS__, 'ajax'));
    }

    private static function eligible_ids() {
        global $wpdb;
        $types = array('post', 'product');
        $placeholders = implode(',', array_fill(0, count($types), '%s'));
        $sql = "SELECT DISTINCT a.ID
                FROM {$wpdb->posts} a
                INNER JOIN {$wpdb->posts} p ON p.ID = a.post_parent
                WHERE a.post_type = 'attachment'
                  AND a.post_mime_type LIKE 'image/%'
                  AND p.post_type IN ($placeholders)
                  AND a.post_status <> 'trash'
                ORDER BY a.ID ASC";
        return array_map('absint', $wpdb->get_col($wpdb->prepare($sql, $types)));
    }

    public static function page() {
        if ( ! current_user_can('manage_options') ) wp_die('Không có quyền.');
        $settings = get_option('xdn_ai_image_settings', array());
        $enabled = ! empty($settings['watermark_enabled']);
        $ids = self::eligible_ids();
        $total = count($ids);
        $done = 0;
        foreach ($ids as $id) if ( get_post_meta($id, '_xdn_ai_watermarked', true) ) $done++;
        $pending = max(0, $total - $done);
        ?>
        <div class="wrap" id="xdn-bulk-watermark">
            <h1>🏷️ Watermark ảnh cũ <small style="font-size:13px;font-weight:400">v<?php echo esc_html(XDN_AI_VERSION); ?></small></h1>
            <p>Đóng logo cho ảnh đã có trong Media Library và đang được gắn với bài viết/sản phẩm. <strong>Ảnh đại diện/Thumbnail được tự động bỏ qua.</strong></p>
            <div style="max-width:760px;background:#fff;border:1px solid #ccd0d4;padding:20px;border-radius:6px">
                <p><strong>Tổng ảnh có thể xử lý:</strong> <span id="xdn-total"><?php echo esc_html($total); ?></span></p>
                <p><strong>Đã watermark:</strong> <span id="xdn-done"><?php echo esc_html($done); ?></span></p>
                <p><strong>Còn lại:</strong> <span id="xdn-pending"><?php echo esc_html($pending); ?></span></p>
                <?php if ( ! $enabled ) : ?>
                    <div class="notice notice-warning inline"><p>Watermark đang tắt. Hãy bật và chọn logo trong <a href="<?php echo esc_url(admin_url('admin.php?page=xdn-ai-images')); ?>">Ảnh & Logo</a> trước.</p></div>
                <?php endif; ?>
                <div style="height:18px;background:#f0f0f1;border-radius:9px;overflow:hidden;margin:18px 0">
                    <div id="xdn-progress" style="width:<?php echo $total ? esc_attr(round(($done/$total)*100,2)) : 100; ?>%;height:100%;background:#2271b1;transition:width .25s"></div>
                </div>
                <button type="button" class="button button-primary" id="xdn-start" <?php disabled(!$enabled || !$pending); ?>>🏷️ Chạy watermark ảnh cũ</button>
                <span id="xdn-status" style="margin-left:10px"></span>
                <p class="description">Mỗi lượt xử lý <?php echo self::BATCH; ?> ảnh để hạn chế timeout trên hosting.</p>
            </div>
            <script>
            jQuery(function($){
                var running=false;
                function runBatch(){
                    if(running===false)return;
                    $.post(ajaxurl, {action:'xdn_ai_bulk_watermark', nonce:'<?php echo esc_js(wp_create_nonce('xdn_ai_bulk_watermark')); ?>'}, function(r){
                        if(!r || !r.success){ $('#xdn-status').text('❌ '+((r&&r.data&&r.data.message)?r.data.message:'Không xử lý được.')); running=false; $('#xdn-start').prop('disabled',false); return; }
                        $('#xdn-done').text(r.data.done); $('#xdn-pending').text(r.data.pending);
                        var total=parseInt(r.data.total||0,10), done=parseInt(r.data.done||0,10);
                        $('#xdn-progress').css('width',(total?Math.min(100,(done/total)*100):100)+'%');
                        $('#xdn-status').text(r.data.pending>0 ? '⏳ Đang xử lý...' : '✅ Đã hoàn tất.');
                        if(r.data.pending>0) setTimeout(runBatch,250); else {running=false;$('#xdn-start').prop('disabled',true);}
                    }).fail(function(){ $('#xdn-status').text('❌ AJAX/hosting không phản hồi. Bấm chạy lại để tiếp tục.'); running=false; $('#xdn-start').prop('disabled',false); });
                }
                $('#xdn-start').on('click',function(){if(running)return;running=true;$(this).prop('disabled',true);$('#xdn-status').text('⏳ Bắt đầu...');runBatch();});
            });
            </script>
        </div>
        <?php
    }

    public static function ajax() {
        if ( ! current_user_can('manage_options') ) wp_send_json_error(array('message'=>'Không có quyền.'), 403);
        check_ajax_referer('xdn_ai_bulk_watermark', 'nonce');
        $settings = get_option('xdn_ai_image_settings', array());
        if ( empty($settings['watermark_enabled']) ) wp_send_json_error(array('message'=>'Watermark đang tắt.'));
        $ids = self::eligible_ids();
        $processed = 0;
        $errors = 0;
        foreach ($ids as $id) {
            if ( $processed >= self::BATCH ) break;
            if ( get_post_meta($id, '_xdn_ai_watermarked', true) ) continue;
            $parent = get_post_field('post_parent', $id);
            $parent_type = $parent ? get_post_type($parent) : 'post';
            $ok = XDN_AI_Images::watermark_attachment($id, 'product' === $parent_type);
            $processed++;
            if ( ! $ok && ! get_post_meta($id, '_xdn_ai_watermarked', true) ) $errors++;
        }
        $done = 0;
        foreach ($ids as $id) if ( get_post_meta($id, '_xdn_ai_watermarked', true) ) $done++;
        $pending = max(0, count($ids) - $done);
        wp_send_json_success(array('total'=>count($ids),'done'=>$done,'pending'=>$pending,'processed'=>$processed,'errors'=>$errors));
    }
}
