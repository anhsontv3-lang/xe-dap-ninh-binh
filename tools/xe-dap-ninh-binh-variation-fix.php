<?php
/**
 * Plugin Name: XE DAP NINH BINH - Variation Fix
 * Description: Công cụ làm sạch biến thể WooCommerce được tạo bởi XE DAP NINH BINH. Loại thuộc tính rỗng và variation toàn bộ "any".
 * Version: 2.2.2
 * Author: Xe Đạp Ninh Bình
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

class XDN_Variation_Fix_222 {
    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_xdn_fix_product_variations', [$this, 'fix_request']);
    }

    public function menu() {
        add_submenu_page(
            'xdn-ai-importer',
            'Sửa biến thể',
            'Sửa biến thể',
            'manage_woocommerce',
            'xdn-variation-fix',
            [$this, 'page']
        );
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
            <h1>XE DAP NINH BINH – Sửa biến thể <span style="font-size:12px;color:#777">V2.2.2</span></h1>
            <div class="notice notice-info"><p><b>Mục đích:</b> loại thuộc tính rỗng, sửa variation "Bất kỳ" khi chỉ có một giá trị thực tế và xóa variation không có thuộc tính.</p></div>
            <?php if (!empty($_GET['fixed'])): ?>
                <div class="notice notice-success"><p>Đã xử lý <?php echo absint($_GET['fixed']); ?> sản phẩm.</p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="xdn_fix_product_variations">
                <?php wp_nonce_field('xdn_fix_variations'); ?>
                <table class="widefat striped">
                    <thead><tr><th style="width:40px"></th><th>ID</th><th>Sản phẩm</th><th>Thuộc tính</th><th>Biến thể</th></tr></thead>
                    <tbody>
                    <?php foreach ($products as $product):
                        $attrs = $product->get_attributes();
                        $variation_ids = $product->get_children();
                        $labels = [];
                        foreach ($attrs as $attr) {
                            $vals = $attr->get_options();
                            $labels[] = $attr->get_name() . ': ' . (empty($vals) ? 'RỖNG' : implode(', ', array_map('strval', $vals)));
                        }
                    ?>
                        <tr>
                            <td><input type="checkbox" name="product_ids[]" value="<?php echo esc_attr($product->get_id()); ?>"></td>
                            <td><?php echo esc_html($product->get_id()); ?></td>
                            <td><a href="<?php echo esc_url(get_edit_post_link($product->get_id())); ?>"><?php echo esc_html($product->get_name()); ?></a></td>
                            <td><?php echo esc_html(implode(' | ', $labels)); ?></td>
                            <td><?php echo esc_html(count($variation_ids)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button class="button button-primary">Sửa sản phẩm đã chọn</button></p>
            </form>
        </div>
        <?php
    }

    public function fix_request() {
        if (!current_user_can('manage_woocommerce')) wp_die('Không có quyền.');
        check_admin_referer('xdn_fix_variations');
        $ids = array_map('absint', (array) ($_POST['product_ids'] ?? []));
        $fixed = 0;
        foreach ($ids as $id) {
            if ($this->fix_product($id)) $fixed++;
        }
        wp_safe_redirect(add_query_arg([
            'page' => 'xdn-variation-fix',
            'fixed' => $fixed,
        ], admin_url('admin.php')));
        exit;
    }

    private function fix_product($product_id) {
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) return false;

        $changed = false;
        $attributes = $product->get_attributes();

        // Remove attributes that contain no real options. This prevents empty
        // attributes such as "Frame Size" from creating "Bất kỳ" variations.
        foreach ($attributes as $key => $attribute) {
            $options = array_values(array_filter(array_map('trim', array_map('strval', (array) $attribute->get_options())), function($v){
                return $v !== '' && strtolower($v) !== 'any' && strtolower($v) !== 'bất kỳ';
            }));
            if (!$options) {
                unset($attributes[$key]);
                $changed = true;
                continue;
            }
            $attribute->set_options(array_values(array_unique($options)));
            $attributes[$key] = $attribute;
        }
        $product->set_attributes($attributes);
        $product->save();

        $valid_attribute_keys = [];
        foreach ($attributes as $key => $attribute) {
            $name = $attribute->get_name();
            $valid_attribute_keys[] = sanitize_title($name);
            $valid_attribute_keys[] = sanitize_title(wc_attribute_taxonomy_slug($name));
        }

        foreach ($product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) continue;
            $va = $variation->get_attributes();
            $variation_changed = false;

            // Remove attributes no longer present on the parent.
            foreach ($va as $k => $v) {
                if (!in_array(sanitize_title($k), $valid_attribute_keys, true)) {
                    unset($va[$k]);
                    $variation_changed = true;
                }
            }

            // If a parent attribute has exactly one real option, replace
            // empty/"any" variation values with that option.
            foreach ($attributes as $key => $attribute) {
                $name = $attribute->get_name();
                $lookup = sanitize_title($name);
                $tax = $attribute->is_taxonomy() ? $attribute->get_taxonomy() : '';
                $vkey = $tax ? sanitize_title($tax) : $lookup;
                $options = array_values(array_filter(array_map('trim', array_map('strval', (array) $attribute->get_options()))));
                if (count($options) === 1) {
                    $current = isset($va[$vkey]) ? trim((string) $va[$vkey]) : '';
                    if ($current === '' || strtolower($current) === 'any' || strtolower($current) === 'bất kỳ') {
                        $value = $options[0];
                        if ($attribute->is_taxonomy()) {
                            $term = get_term((int) $value, $tax);
                            if ($term && !is_wp_error($term)) $value = $term->slug;
                        }
                        $va[$vkey] = $value;
                        $variation_changed = true;
                    }
                }
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
}

add_action('plugins_loaded', function(){
    if (class_exists('WooCommerce')) new XDN_Variation_Fix_222();
});
