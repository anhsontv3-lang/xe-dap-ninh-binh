<?php
/**
 * Plugin Name: XE DAP NINH BINH – AI Bridge V2.5.1
 * Description: Lớp cầu AI SEO cho importer chính. Chỉ viết lại nội dung; không thay đổi giá, thuộc tính, variation hoặc ảnh lấy từ nguồn.
 * Version: 2.5.1
 * Author: Xe Đạp Ninh Bình
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) exit;

class XDN_AI_Bridge_251 {
    const OPT = 'xdn_ai_importer_options';
    public function __construct() {
        add_action('admin_menu', [$this, 'menu'], 20);
        add_action('wp_ajax_xdn_ai_bridge_preview', [$this, 'preview']);
    }
    private function opts() {
        return wp_parse_args(get_option(self::OPT, []), ['api_key'=>'','model'=>'gpt-5-mini']);
    }
    public function menu() {
        add_submenu_page('xdn-ai-importer', 'AI SEO V2.5.1', 'AI SEO V2.5.1', 'manage_woocommerce', 'xdn-ai-251', [$this, 'page']);
    }
    public function page() {
        if (!current_user_can('manage_woocommerce')) return;
        $o = $this->opts();
        $nonce = wp_create_nonce('xdn_ai_bridge_251');
        ?>
        <div class="wrap">
            <h1>XE DAP NINH BINH – AI SEO V2.5.1</h1>
            <div style="max-width:1100px;background:#fff;border:1px solid #ddd;padding:18px">
                <p><b>Nguyên tắc:</b> AI chỉ viết lại nội dung. Giá, SKU, màu, size, variation và ảnh phải lấy từ dữ liệu nguồn.</p>
                <textarea id="xdn251_json" style="width:100%;min-height:260px" placeholder='Dán JSON dữ liệu sản phẩm đã quét vào đây'></textarea>
                <p><button class="button button-primary" id="xdn251_run">Viết lại bằng GPT</button></p>
                <div id="xdn251_out"></div>
            </div>
        </div>
        <script>
        (()=>{const a='<?php echo esc_js(admin_url('admin-ajax.php'));?>',n='<?php echo esc_js($nonce);?>',o=document.getElementById('xdn251_out');
        document.getElementById('xdn251_run').onclick=async()=>{let raw=document.getElementById('xdn251_json').value.trim();if(!raw)return alert('Dán JSON dữ liệu sản phẩm trước.');let f=new FormData();f.append('action','xdn_ai_bridge_preview');f.append('nonce',n);f.append('payload',raw);o.innerHTML='Đang xử lý...';try{let r=await fetch(a,{method:'POST',body:f});let j=await r.json();if(!j.success)throw Error(j.data?.message||j.data||'Lỗi');o.innerHTML='<h2>Kết quả AI</h2><pre style="white-space:pre-wrap;background:#f6f7f7;padding:15px">'+JSON.stringify(j.data,null,2).replace(/[&<>]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[m]))+'</pre>'}catch(e){o.innerHTML='<p style="color:#b32d2e">'+e.message+'</p>'}}})();
        </script>
        <?php
    }
    public function preview() {
        if (!current_user_can('manage_woocommerce')) wp_send_json_error(['message'=>'Không có quyền.'],403);
        check_ajax_referer('xdn_ai_bridge_251','nonce');
        $payload = json_decode(wp_unslash($_POST['payload'] ?? ''), true);
        if (!is_array($payload)) wp_send_json_error(['message'=>'JSON sản phẩm không hợp lệ.']);
        $o = $this->opts();
        if (empty($o['api_key'])) wp_send_json_error(['message'=>'Chưa có OpenAI API Key.']);
        $source = [
            'name' => $payload['title'] ?? $payload['name'] ?? '',
            'short_description' => $payload['short_description'] ?? '',
            'description' => $payload['content'] ?? $payload['description'] ?? '',
            'specs' => $payload['specs'] ?? '',
            'attributes' => $payload['attributes'] ?? [],
            'variations' => $payload['variations'] ?? [],
            'regular_price' => $payload['regular_price'] ?? 0,
            'sale_price' => $payload['sale_price'] ?? 0,
        ];
        $prompt = 'Bạn là chuyên gia SEO thương mại điện tử Việt Nam cho Xe Đạp Ninh Bình. Viết lại nội dung sản phẩm thành nội dung riêng, tự nhiên và hữu ích. TUYỆT ĐỐI không thay đổi hoặc bịa tên model, giá, màu, size, SKU, thông số, tính năng. Không tự tạo variation. Các trường kỹ thuật trong dữ liệu nguồn chỉ dùng để tham khảo chính xác. Trả JSON hợp lệ gồm name, short_description, description, seo_title, meta_description, tags. description dùng HTML đơn giản.\nDỮ LIỆU NGUỒN:\n'.wp_json_encode($source, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $body = ['model'=>$o['model'] ?: 'gpt-5-mini', 'input'=>$prompt, 'text'=>['format'=>['type'=>'json_object']]];
        $r = wp_remote_post('https://api.openai.com/v1/responses', ['timeout'=>90,'headers'=>['Authorization'=>'Bearer '.$o['api_key'],'Content-Type'=>'application/json'],'body'=>wp_json_encode($body)]);
        if (is_wp_error($r)) wp_send_json_error(['message'=>$r->get_error_message()]);
        $code = wp_remote_retrieve_response_code($r); $raw = wp_remote_retrieve_body($r); $j = json_decode($raw,true);
        if ($code < 200 || $code >= 300) wp_send_json_error(['message'=>$j['error']['message'] ?? ('OpenAI HTTP '.$code)]);
        $text = $j['output_text'] ?? '';
        if (!$text && !empty($j['output'])) foreach ($j['output'] as $out) foreach (($out['content'] ?? []) as $c) if (isset($c['text'])) $text .= $c['text'];
        $ai = json_decode(trim($text),true);
        if (!is_array($ai)) wp_send_json_error(['message'=>'AI không trả JSON hợp lệ.']);
        wp_send_json_success(['source'=>$source,'ai'=>$ai,'note'=>'Giá, thuộc tính, variation và ảnh vẫn phải lấy từ nguồn.']);
    }
}
new XDN_AI_Bridge_251();
