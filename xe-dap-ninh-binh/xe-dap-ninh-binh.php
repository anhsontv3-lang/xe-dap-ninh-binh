<?php
/**
 * Plugin Name: XE DAP NINH BINH
 * Plugin URI: https://xedapninhbinh.com
 * Description: Quét sản phẩm từ website nguồn, xem trước danh sách, lấy ảnh/thông tin và tạo sản phẩm WooCommerce; hỗ trợ viết lại nội dung bằng OpenAI.
 * Version: 2.1.0
 * Update URI: https://github.com/anhsontv3-lang/xe-dap-ninh-binh
 * Author: Xe Đạp Ninh Bình
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

class XDN_AI_Product_Importer_V2 {
    const VERSION = '2.1.0';
    const GITHUB_REPO = 'anhsontv3-lang/xe-dap-ninh-binh';
    const OPT_KEY = 'xdn_ai_importer_options';
    const NONCE = 'xdn_ai_importer_nonce';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_ajax_xdn_scan_source', [$this, 'ajax_scan_source']);
        add_action('wp_ajax_xdn_import_products', [$this, 'ajax_import_products']);

        // GitHub updater: WordPress will show the normal "Update now" notice.
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_plugin_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
        add_action('upgrader_process_complete', [$this, 'clear_update_cache'], 10, 2);
        add_action('admin_post_xdn_check_update', [$this, 'manual_update_check']);
    }

    private function plugin_basename() {
        return plugin_basename(__FILE__);
    }

    private function github_latest_release($force = false) {
        $cache_key = 'xdn_github_latest_release';
        if (!$force) {
            $cached = get_site_transient($cache_key);
            if (is_array($cached)) return $cached;
        }

        $url = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';
        $r = wp_remote_get($url, [
            'timeout' => 12,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'XE-DAP-NINH-BINH-WordPress/' . self::VERSION,
            ],
        ]);
        if (is_wp_error($r)) return [];
        $code = wp_remote_retrieve_response_code($r);
        if ($code !== 200) return [];
        $data = json_decode(wp_remote_retrieve_body($r), true);
        if (!is_array($data) || empty($data['tag_name'])) return [];

        $version = ltrim((string)$data['tag_name'], "vV ");
        $package = '';
        if (!empty($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                $name = isset($asset['name']) ? (string)$asset['name'] : '';
                $download = isset($asset['browser_download_url']) ? (string)$asset['browser_download_url'] : '';
                if ($download && preg_match('/\.zip$/i', $name)) {
                    $package = $download;
                    break;
                }
            }
        }
        if (!$package && !empty($data['zipball_url'])) {
            $package = $data['zipball_url'];
        }

        $result = [
            'version' => sanitize_text_field($version),
            'package' => esc_url_raw($package),
            'url' => !empty($data['html_url']) ? esc_url_raw($data['html_url']) : 'https://github.com/' . self::GITHUB_REPO,
            'name' => 'XE DAP NINH BINH',
            'body' => !empty($data['body']) ? wp_kses_post($data['body']) : '',
            'published_at' => !empty($data['published_at']) ? sanitize_text_field($data['published_at']) : '',
        ];
        set_site_transient($cache_key, $result, 6 * HOUR_IN_SECONDS);
        return $result;
    }

    public function check_for_plugin_update($transient) {
        if (!is_object($transient)) return $transient;
        if (empty($transient->checked)) return $transient;

        $release = $this->github_latest_release(false);
        if (empty($release['version']) || empty($release['package'])) return $transient;
        if (version_compare(self::VERSION, $release['version'], '>=')) return $transient;

        $item = (object) [
            'id' => 'https://github.com/' . self::GITHUB_REPO,
            'slug' => dirname($this->plugin_basename()),
            'plugin' => $this->plugin_basename(),
            'new_version' => $release['version'],
            'url' => $release['url'],
            'package' => $release['package'],
            'icons' => [],
            'tested' => get_bloginfo('version'),
            'requires_php' => '7.4',
        ];
        $transient->response[$this->plugin_basename()] = $item;
        return $transient;
    }

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug)) return $result;
        if ($args->slug !== dirname($this->plugin_basename())) return $result;

        $release = $this->github_latest_release(false);
        if (empty($release['version'])) return $result;

        return (object) [
            'name' => 'XE DAP NINH BINH',
            'slug' => dirname($this->plugin_basename()),
            'version' => $release['version'],
            'author' => '<a href="https://xedapninhbinh.com">Xe Đạp Ninh Bình</a>',
            'homepage' => 'https://github.com/' . self::GITHUB_REPO,
            'download_link' => $release['package'],
            'sections' => [
                'description' => 'WooCommerce AI Product Importer cho Xe Đạp Ninh Bình.',
                'changelog' => nl2br($release['body']),
            ],
        ];
    }

    public function clear_update_cache($upgrader, $options) {
        if (!empty($options['action']) && $options['action'] === 'update'
            && !empty($options['type']) && $options['type'] === 'plugin') {
            delete_site_transient('xdn_github_latest_release');
            delete_site_transient('update_plugins');
        }
    }

    public function manual_update_check() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Không có quyền.');
        }
        check_admin_referer('xdn_check_update');
        delete_site_transient('xdn_github_latest_release');
        delete_site_transient('update_plugins');
        wp_update_plugins();
        wp_safe_redirect(admin_url('admin.php?page=xdn-ai-importer&xdn_update_checked=1'));
        exit;
    }

    public function admin_menu() {
        add_menu_page(
            'XE DAP NINH BINH',
            'XE DAP NINH BINH',
            'manage_woocommerce',
            'xdn-ai-importer',
            [$this, 'admin_page'],
            'dashicons-cart',
            56
        );
    }

    public function admin_assets($hook) {
        if ($hook !== 'toplevel_page_xdn-ai-importer') return;
        wp_enqueue_style('dashicons');
    }

    private function opts() {
        $defaults = [
            'api_key' => '',
            'model' => 'gpt-5-mini',
            'default_category' => 0,
        ];
        return wp_parse_args(get_option(self::OPT_KEY, []), $defaults);
    }

    private function save_opts() {
        if (!current_user_can('manage_woocommerce')) return;
        check_admin_referer('xdn_save_settings');
        $opts = [
            'api_key' => isset($_POST['api_key']) ? trim(sanitize_text_field(wp_unslash($_POST['api_key']))) : '',
            'model' => isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : 'gpt-5-mini',
            'default_category' => isset($_POST['default_category']) ? absint($_POST['default_category']) : 0,
        ];
        update_option(self::OPT_KEY, $opts);
        echo '<div class="notice notice-success"><p>Đã lưu cấu hình.</p></div>';
    }

    public function admin_page() {
        if (!current_user_can('manage_woocommerce')) return;
        if (isset($_POST['xdn_save'])) $this->save_opts();
        $o = $this->opts();
        $cats = get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]);
        ?>
        <div class="wrap">
            <h1>XE DAP NINH BINH – AI Product Importer <span style="font-size:12px;color:#777">V2.1.0</span></h1>
            <?php
            $latest = $this->github_latest_release(false);
            $update_url = wp_nonce_url(admin_url('admin-post.php?action=xdn_check_update'), 'xdn_check_update');
            ?>
            <div style="background:#fff;border:1px solid #dcdcde;padding:12px 16px;margin:12px 0;max-width:1100px">
                <strong>Phiên bản:</strong> <?php echo esc_html(self::VERSION); ?>
                <?php if (!empty($latest['version']) && version_compare(self::VERSION, $latest['version'], '<')): ?>
                    <span style="color:#b32d2e;margin-left:10px">Có bản mới <?php echo esc_html($latest['version']); ?>.</span>
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('update-core.php')); ?>">Mở cập nhật WordPress</a>
                <?php elseif (!empty($_GET['xdn_update_checked'])): ?>
                    <span style="color:#087f23;margin-left:10px">Đã kiểm tra cập nhật.</span>
                <?php else: ?>
                    <span style="color:#087f23;margin-left:10px">Đang dùng bản mới nhất.</span>
                <?php endif; ?>
                <a class="button" style="margin-left:6px" href="<?php echo esc_url($update_url); ?>">Kiểm tra cập nhật</a>
                <a class="button" style="margin-left:6px" target="_blank" href="https://github.com/<?php echo esc_attr(self::GITHUB_REPO); ?>">GitHub</a>
            </div>

            <div style="background:#fff;border:1px solid #dcdcde;padding:18px;margin:16px 0;max-width:1100px">
                <h2 style="margin-top:0">1. Cấu hình</h2>
                <form method="post">
                    <?php wp_nonce_field('xdn_save_settings'); ?>
                    <input type="hidden" name="xdn_save" value="1">
                    <table class="form-table">
                        <tr>
                            <th><label for="xdn_api_key">OpenAI API Key</label></th>
                            <td>
                                <input id="xdn_api_key" type="password" name="api_key" value="<?php echo esc_attr($o['api_key']); ?>" class="regular-text" autocomplete="off">
                                <p class="description">Key chỉ được dùng ở phía máy chủ WordPress. Không gửi key cho người khác.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="xdn_model">OpenAI Model</label></th>
                            <td>
                                <input id="xdn_model" type="text" name="model" value="<?php echo esc_attr($o['model']); ?>" class="regular-text">
                                <p class="description">Mặc định: gpt-5-mini. Có thể đổi sang model bạn có quyền sử dụng.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Danh mục mặc định</label></th>
                            <td>
                                <select name="default_category">
                                    <option value="0">-- Chọn sau khi quét --</option>
                                    <?php foreach ($cats as $cat): ?>
                                        <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($o['default_category'], $cat->term_id); ?>>
                                            <?php echo esc_html($cat->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <p><button class="button button-primary" type="submit">Lưu cấu hình</button></p>
                </form>
            </div>

            <div style="background:#fff;border:1px solid #dcdcde;padding:18px;margin:16px 0;max-width:1100px">
                <h2 style="margin-top:0">2. Quét sản phẩm nguồn</h2>
                <p>Nhập trang danh mục, plugin sẽ tìm các sản phẩm và hiển thị danh sách trước khi bạn chọn nhập.</p>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <input id="xdn_source_url" type="url" class="regular-text" style="width:480px" placeholder="https://example.com/category" value="<?php echo isset($_POST['source_url']) ? esc_attr(wp_unslash($_POST['source_url'])) : ''; ?>">
                    <label><input type="checkbox" id="xdn_all_pages" checked> Quét tất cả trang phân trang</label>
                    <button type="button" class="button button-primary" id="xdn_scan_btn">Quét sản phẩm</button>
                </div>
                <div id="xdn_scan_message" style="margin-top:10px"></div>
                <div id="xdn_scan_results"></div>
            </div>
        </div>
        <?php
        $this->inline_admin_js($o);
    }

    private function inline_admin_js($o) {
        $ajax = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce(self::NONCE);
        ?>
        <script>
        window.XDN_IMPORTER = <?php echo wp_json_encode([
            'ajax' => $ajax,
            'nonce' => $nonce,
            'defaultCategory' => (int)$o['default_category'],
        ]); ?>;
        </script>
        <?php
    }

    public function ajax_scan_source() {
        if (!current_user_can('manage_woocommerce')) wp_send_json_error(['message'=>'Không có quyền.'],403);
        check_ajax_referer(self::NONCE,'nonce');
        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        if (!$url) wp_send_json_error(['message'=>'URL không hợp lệ.']);
        $products = $this->scan_products_from_url($url);
        wp_send_json_success(['count'=>count($products),'products'=>$products]);
    }

    private function scan_products_from_url($url) {
        $r = wp_remote_get($url, ['timeout'=>20,'redirection'=>5,'headers'=>['User-Agent'=>'Mozilla/5.0 (compatible; XDN Product Importer)']]);
        if (is_wp_error($r)) return [];
        if (wp_remote_retrieve_response_code($r) >= 400) return [];
        $html = wp_remote_retrieve_body($r);
        if (!$html) return [];

        $products=[];
        libxml_use_internal_errors(true);
        $dom=new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xp=new DOMXPath($dom);
        $nodes=$xp->query('//a[contains(@href,"/san-pham/") or contains(@href,"/product/")]');
        $seen=[];
        foreach($nodes as $a){
            $href=$a->getAttribute('href');
            if(!$href) continue;
            $href=esc_url_raw($this->absolute_url($href,$url));
            if(!$href || isset($seen[$href])) continue;
            $seen[$href]=1;
            $text=trim(preg_replace('/\s+/u',' ', $a->textContent));
            $img=$xp->query('.//img',$a);
            $image='';
            if($img && $img->length){$image=$img->item(0)->getAttribute('src') ?: $img->item(0)->getAttribute('data-src');}
            if($image) $image=esc_url_raw($this->absolute_url($image,$url));
            if(!$text && !$image) continue;
            $products[]=['url'=>$href,'name'=>$text ?: 'Sản phẩm','image'=>$image];
            if(count($products)>=100) break;
        }
        return $products;
    }

    private function absolute_url($link,$base){
        if(!$link) return '';
        if(strpos($link,'//')===0){$p=wp_parse_url($base);return ($p['scheme']??'https').':'.$link;}
        if(preg_match('#^https?://#i',$link)) return $link;
        $p=wp_parse_url($base);
        if(!$p || empty($p['scheme']) || empty($p['host'])) return $link;
        if(strpos($link,'/')===0) return $p['scheme'].'://'.$p['host'].$link;
        $path=isset($p['path']) ? dirname($p['path']).'/' : '/';
        return $p['scheme'].'://'.$p['host'].$path.$link;
    }

    public function ajax_import_products() {
        if (!current_user_can('manage_woocommerce')) wp_send_json_error(['message'=>'Không có quyền.'],403);
        check_ajax_referer(self::NONCE,'nonce');
        wp_send_json_error(['message'=>'Phiên bản GitHub V2.1 đã lưu phần khung nhập. Tính năng import hoàn chỉnh sẽ được phát triển tiếp trong V2.2.']);
    }
}

new XDN_AI_Product_Importer_V2();
