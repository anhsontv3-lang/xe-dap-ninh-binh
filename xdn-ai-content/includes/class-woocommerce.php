<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class XDN_AI_WooCommerce {
    public static function available() { return class_exists('WooCommerce'); }
    public static function products( $keyword = '', $limit = 6 ) {
        if ( ! self::available() ) return array();
        $args = array('status'=>'publish','limit'=>max(1,(int)$limit),'return'=>'objects');
        if ($keyword) $args['search'] = $keyword;
        $items = wc_get_products($args); $out=array();
        foreach($items as $p) $out[] = array('id'=>$p->get_id(),'name'=>$p->get_name(),'url'=>$p->get_permalink(),'price'=>$p->get_price(),'image'=>$p->get_image_id(),'sku'=>$p->get_sku());
        return $out;
    }
    public static function append_product_box( $html, $products ) {
        if ( empty($products) ) return $html;
        $html .= '<div class="xdn-ai-products"><h2>Sản phẩm liên quan</h2><ul>';
        foreach($products as $p) $html .= '<li><a href="'.esc_url($p['url']).'">'.esc_html($p['name']).'</a>'.($p['price']!==''?' – '.esc_html(wc_price($p['price'])):'').'</li>';
        return $html.'</ul></div>';
    }
}
