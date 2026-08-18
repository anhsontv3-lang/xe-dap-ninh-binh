<?php
/**
 * Plugin Name: XE DAP NINH BINH – Variation Guard
 * Description: Tự động làm sạch thuộc tính và biến thể WooCommerce cho bộ nhập sản phẩm XE DAP NINH BINH; loại giá trị rỗng/Bất kỳ và sửa biến thể khi thuộc tính chỉ có một giá trị thực.
 * Version: 2.2.3
 * Author: Xe Đạp Ninh Bình
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

class XDN_Variation_Guard_223 {
    const VERSION = '2.2.3';

    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_xdn_guard_repair', [$this, 'repair_request']);

        // WooCommerce fires these when variations are created/updated. The
        // guard cleans the variation immediately, so future imports are fixed
        // without requiring a manual repair pass.
        add_action('woocommerce_new_product_variation', [$this, 'guard_variation'], 99, 1);
        add_action('woocommerce_update_product_variation', [$this, 'guard_variation'], 99, 1);
    }

    public function menu() {
        if (!class_exists('WooCommerce')) return;
        add_submenu_page(
            'xdn-ai-importer',
            'Kiểm soát biến thể',
            'Kiểm soát biến thể',
            'manage_woocommerce',
            'xdn-variation-guard',
            [$this, 'page']
        );
    }

    private function is_any($value) {
        $v = strtolower(trim((string) $value));
        return $v === '' || $v === 'any' || $v === 'bất kỳ' || $v === 'bat ky';
    }

    private function normalize_options($attribute) {
        $options = [];
        foreach ((array) $attribute->get_options() as $option) {
            $option = trim((string) $option);
            if ($this->is_any($option)) continue;
            $options[] = $option;
        }
        return array_values(array_unique($options));
    }

    private function clean_parent($product) {
        $attributes = $product->get_attributes();
        $changed = false;

        foreach ($attributes as $key => $attribute) {
            $options = $this->normalize_options($attribute);
            if (!$options) {
                unset($attributes[$key]);
                $changed = true;
                continue;
            }
            if ($options !== array_values(array_map('strval', (array) $attribute->get_options()))) {
                $attribute->set_options($options);
                $attributes[$key] = $attribute;
                $changed = true;
            }
        }

        if ($changed) {
            $product->set_attributes($attributes);
            $product->save();
        }

        return [$attributes, $changed];
    }

    private function valid_attribute_keys($attributes) {
        $keys = [];
        foreach ($attributes as $attribute) {
            $name = $attribute->get_name();
            $keys[] = sanitize_title($name);
            if ($attribute->is_taxonomy()) {
                $keys[] = sanitize_title($attribute->get_taxonomy());
            }
        }
        return array_values(array_unique($keys));
    }

    private function repair_product($product_id) {
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) return false;

        [$attributes, $changed] = $this->clean_parent($product);
        $valid_keys = $this->valid_attribute_keys($attributes);

        foreach ($product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) continue;

            $va = $variation->get_attributes();
            $variation_changed = false;

            // Remove attributes which no longer exist on the parent.
            foreach ($va as $vkey => $vvalue) {
                $normalized_key = sanitize_title($vkey);
                if (!in_array($normalized_key, $valid_keys, true)) {
                    unset($va[$vkey]);
                    $variation_changed = true;
                }
            }

            // A single real option is deterministic. Never leave it as
            // "any"/empty in a variation.
            foreach ($attributes as $attribute) {
                $name = $attribute->get_name();
                $lookup = sanitize_title($name);
                $taxonomy = $attribute->is_taxonomy() ? $attribute->get_taxonomy() : '';
                $vkey = $taxonomy ? sanitize_title($taxonomy) : $lookup;
                $options = $this->normalize_options($attribute);
                if (count($options) !== 1) continue;

                $current = isset($va[$vkey]) ? trim((string) $va[$vkey]) : '';
                if (!$this->is_any($current)) continue;

                $value = $options[0];
                if ($attribute->is_taxonomy() && is_numeric($value)) {
                    $term = get_term((int) $value, $taxonomy);
                    if ($term && !is_wp_error($term)) $value = $term->slug;
                }
                $va[$vkey] = $value;
                $variation_changed = true;
            }

            if ($variation_changed) {
                $variation->set_attributes($va);
                $variation->save();
                $changed = true;
            }
        }

        if ($changed) {
            WC_Product_Variable::sync($product_id);
            wc_delete_product_transients($product_id);
        }

        return $changed;
    }

    public function guard_variation($variation_id) {
        if (!class_exists('WooCommerce')) return;
        $variation = wc_get_product($variation_id);
        if (!$variation || !$variation->is_type('variation')) return;
        $parent_id = $variation->get_parent_id();
        if (!$parent_id) return;

        // Avoid doing the same work recursively when save() below triggers an
        // update action again.
        static $busy = false;
        if ($busy) return;
        $busy = true;
        $this->repair_product($parent_id);
        $busy = false;
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
        ?>
        <div class="wrap">
            <h1>XE DAP NINH BINH – Kiểm soát biến thể <span style="font-size:12px;color:#777">V2.2.3</span></h1>
            <div class="notice notice-info"><p><b>Đã bật bảo vệ tự động:</b> khi bộ nhập tạo/cập nhật variation, plugin sẽ loại thuộc tính rỗng, loại “Bất kỳ” và điền giá trị duy nhất nếu thuộc tính chỉ có một lựa chọn thực tế.</p></div>
            <?php if (isset($_GET['fixed'])): ?>
                <div class="notice notice-success"><p>Đã xử lý <?php echo absint($_GET['fixed']); ?> sản phẩm.</p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="xdn_guard_repair">
                <?php wp_nonce_field('xdn_guard_repair'); ?>
                <table class="widefat striped">
                    <thead><tr><th style="width:40px"><input type="checkbox" onclick="document.querySelectorAll('.xdn-g').forEach(e=>e.checked=this.checked)"></th><th>ID</th><th>Sản phẩm</th><th>Thuộc tính</th><th>Variation</th></tr></thead>
                    <tbody>
                    <?php foreach ($products as $product):
                        $labels = [];
                        foreach ($product->get_attributes() as $attribute) {
                            $options = $this->normalize_options($attribute);
                            $labels[] = $attribute->get_name() . ': ' . ($options ? implode(', ', $options) : 'RỖNG');
                        }
                    ?>
                        <tr>
                            <td><input class="xdn-g" type="checkbox" name="product_ids[]" value="<?php echo esc_attr($product->get_id()); ?>"></td>
                            <td><?php echo esc_html($product->get_id()); ?></td>
                            <td><a href="<?php echo esc_url(get_edit_post_link($product->get_id())); ?>"><?php echo esc_html($product->get_name()); ?></a></td>
                            <td><?php echo esc_html(implode(' | ', $labels)); ?></td>
                            <td><?php echo esc_html(count($product->get_children())); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button class="button button-primary">Sửa sản phẩm đã chọn</button></p>
            </form>
        </div>
        <?php
    }

    public function repair_request() {
        if (!current_user_can('manage_woocommerce')) wp_die('Không có quyền.');
        check_admin_referer('xdn_guard_repair');
        $ids = array_map('absint', (array) ($_POST['product_ids'] ?? []));
        $fixed = 0;
        foreach ($ids as $id) {
            if ($this->repair_product($id)) $fixed++;
        }
        wp_safe_redirect(add_query_arg([
            'page' => 'xdn-variation-guard',
            'fixed' => $fixed,
        ], admin_url('admin.php')));
        exit;
    }
}

add_action('plugins_loaded', function () {
    if (class_exists('WooCommerce')) new XDN_Variation_Guard_223();
}, 20);
