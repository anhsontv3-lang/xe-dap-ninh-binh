<?php
/**
 * Plugin Name: XE DAP NINH BINH - V2.3 Variation Guard
 * Description: Bộ bảo vệ biến thể cho XE DAP NINH BINH. Ngăn thuộc tính rỗng và variation "Bất kỳ" đối với sản phẩm được importer tạo.
 * Version: 2.3.0
 * Author: Xe Đạp Ninh Bình
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) exit;

final class XDN_Variation_Guard_230 {
    const VERSION = '2.3.0';
    const MENU = 'xdn-variation-guard';
    const NONCE = 'xdn_vg_230';

    public function __construct() {
        add_action('plugins_loaded', [$this, 'boot'], 20);
    }

    public function boot() {
        if (!class_exists('WooCommerce')) return;
        // Catch variation saves, including variations created by the importer.
        add_action('woocommerce_save_product_variation', [$this, 'guard_variation'], 99, 2);
        add_action('admin_menu', [$this, 'menu']);
    }

    public function menu() {
        add_submenu_page(
            'xdn-ai-importer',
            'Kiểm soát biến thể',
            'Kiểm soát biến thể',
            'manage_woocommerce',
            self::MENU,
            [$this, 'page']
        );
    }

    private function is_xdn_product($product_id) {
        return (bool) get_post_meta($product_id, '_xdn_source_url', true);
    }

    private function normalize_options($attribute) {
        $options = (array) $attribute->get_options();
        $out = [];
        foreach ($options as $option) {
            $option = trim((string) $option);
            if ($option === '') continue;
            if (in_array(mb_strtolower($option), ['any', 'bất kỳ'], true)) continue;
            $out[] = $option;
        }
        return array_values(array_unique($out));
    }

    public function guard_variation($variation_id, $loop = 0) {
        $variation = wc_get_product($variation_id);
        if (!$variation || !$variation->is_type('variation')) return;
        $parent_id = $variation->get_parent_id();
        if (!$parent_id || !$this->is_xdn_product($parent_id)) return;

        $parent = wc_get_product($parent_id);
        if (!$parent || !$parent->is_type('variable')) return;

        $attributes = $parent->get_attributes();
        $valid = [];
        $single_values = [];

        foreach ($attributes as $attribute) {
            $options = $this->normalize_options($attribute);
            if (!$options) continue;
            $name = $attribute->get_name();
            $key = $attribute->is_taxonomy() ? sanitize_title($attribute->get_taxonomy()) : sanitize_title($name);
            $valid[$key] = true;
            if (count($options) === 1) $single_values[$key] = [$attribute, $options[0]];
        }

        $values = $variation->get_attributes();
        $changed = false;

        // Remove orphan/empty attributes from the variation.
        foreach ($values as $key => $value) {
            $normalized_key = sanitize_title($key);
            if (!isset($valid[$normalized_key])) {
                unset($values[$key]);
                $changed = true;
                continue;
            }
            if (trim((string) $value) === '' || in_array(mb_strtolower(trim((string) $value)), ['any', 'bất kỳ'], true)) {
                $values[$key] = '';
                $changed = true;
            }
        }

        // If the parent has exactly one real option, never leave the variation as "Any".
        foreach ($single_values as $key => $pair) {
            [$attribute, $only] = $pair;
            $current_key = $key;
            $current = isset($values[$current_key]) ? trim((string) $values[$current_key]) : '';
            if ($current === '' || in_array(mb_strtolower($current), ['any', 'bất kỳ'], true)) {
                if ($attribute->is_taxonomy()) {
                    $terms = get_terms([
                        'taxonomy' => $attribute->get_taxonomy(),
                        'hide_empty' => false,
                        'slug' => sanitize_title($only),
                    ]);
                    if (!is_wp_error($terms) && !empty($terms)) $only = $terms[0]->slug;
                }
                $values[$current_key] = $only;
                $changed = true;
            }
        }

        if ($changed) {
            $variation->set_attributes($values);
            // Guard against a save loop. WooCommerce will persist the changed variation here.
            remove_action('woocommerce_save_product_variation', [$this, 'guard_variation'], 99);
            $variation->save();
            add_action('woocommerce_save_product_variation', [$this, 'guard_variation'], 99, 2);
        }
    }

    private function fix_product($product_id) {
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable') || !$this->is_xdn_product($product_id)) return false;

        $changed = false;
        $attributes = $product->get_attributes();
        foreach ($attributes as $key => $attribute) {
            $options = $this->normalize_options($attribute);
            if (!$options) {
                unset($attributes[$key]);
                $changed = true;
                continue;
            }
            $old = (array) $attribute->get_options();
            if ($old !== $options) {
                $attribute->set_options($options);
                $attributes[$key] = $attribute;
                $changed = true;
            }
        }

        if ($changed) {
            $product->set_attributes($attributes);
            $product->save();
        }

        foreach ($product->get_children() as $variation_id) {
            $before = wc_get_product($variation_id);
            if (!$before) continue;
            $snapshot = $before->get_attributes();
            $this->guard_variation($variation_id);
            $after = wc_get_product($variation_id);
            if ($after && $after->get_attributes() !== $snapshot) $changed = true;
        }

        if ($changed) {
            WC_Product_Variable::sync($product_id);
            wc_delete_product_transients($product_id);
        }
        return $changed;
    }

    public function page() {
        if (!current_user_can('manage_woocommerce')) return;
        $products = wc_get_products([
            'status' => ['draft', 'publish', 'pending', 'private'],
            'type' => 'variable',
            'limit' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        $fixed = isset($_GET['fixed']) ? absint($_GET['fixed']) : null;
        ?>
        <div class="wrap">
            <h1>XE DAP NINH BINH – Kiểm soát biến thể <span style="font-size:12px;color:#777">V2.3.0</span></h1>
            <div class="notice notice-info"><p><b>V2.3:</b> chỉ áp dụng cho sản phẩm có nguồn nhập bởi XE DAP NINH BINH. Thuộc tính rỗng bị loại bỏ; nếu một thuộc tính chỉ có 1 giá trị thật, variation sẽ không còn ở trạng thái “Bất kỳ”.</p></div>
            <?php if ($fixed !== null): ?><div class="notice notice-success"><p>Đã kiểm tra <?php echo $fixed; ?> sản phẩm.</p></div><?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="xdn_vg_fix_products">
                <?php wp_nonce_field(self::NONCE); ?>
                <p><button type="submit" class="button button-primary">Sửa các sản phẩm đã chọn</button></p>
                <table class="widefat striped">
                    <thead><tr><th style="width:40px"><input type="checkbox" id="xdn-vg-all"></th><th>ID</th><th>Sản phẩm</th><th>Thuộc tính</th><th>Biến thể</th></tr></thead>
                    <tbody>
                    <?php foreach ($products as $product):
                        if (!$this->is_xdn_product($product->get_id())) continue;
                        $labels = [];
                        foreach ($product->get_attributes() as $attribute) {
                            $options = $this->normalize_options($attribute);
                            $labels[] = $attribute->get_name() . ': ' . ($options ? implode(', ', $options) : 'RỖNG');
                        }
                    ?>
                    <tr>
                        <td><input class="xdn-vg-item" type="checkbox" name="product_ids[]" value="<?php echo esc_attr($product->get_id()); ?>"></td>
                        <td><?php echo esc_html($product->get_id()); ?></td>
                        <td><a href="<?php echo esc_url(get_edit_post_link($product->get_id())); ?>"><?php echo esc_html($product->get_name()); ?></a></td>
                        <td><?php echo esc_html(implode(' | ', $labels)); ?></td>
                        <td><?php echo esc_html(count($product->get_children())); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <script>document.getElementById('xdn-vg-all')?.addEventListener('change',function(){document.querySelectorAll('.xdn-vg-item').forEach(function(x){x.checked=this.checked},this)});</script>
        <?php
    }

    public function fix_request() {
        if (!current_user_can('manage_woocommerce')) wp_die('Không có quyền.');
        check_admin_referer(self::NONCE);
        $ids = array_map('absint', (array) ($_POST['product_ids'] ?? []));
        $fixed = 0;
        foreach ($ids as $id) if ($this->fix_product($id)) $fixed++;
        wp_safe_redirect(add_query_arg(['page' => self::MENU, 'fixed' => $fixed], admin_url('admin.php')));
        exit;
    }
}

add_action('admin_post_xdn_vg_fix_products', function(){
    static $booted = false;
    if ($booted) return;
    $booted = true;
    $guard = new XDN_Variation_Guard_230();
    $guard->fix_request();
});

new XDN_Variation_Guard_230();
