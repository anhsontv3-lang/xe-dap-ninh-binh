<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class XDN_AI_RankMath {
    public static function available() { return defined('RANK_MATH_VERSION') || class_exists('RankMath'); }
    public static function save( $post_id, $data ) {
        if ( ! $post_id || ! is_array($data) ) return;
        $map = array(
            '_rank_math_focus_keyword' => 'focus_keyword',
            '_rank_math_title' => 'seo_title',
            '_rank_math_description' => 'meta_description',
            '_rank_math_canonical_url' => 'canonical',
        );
        foreach ( $map as $meta => $key ) if ( isset($data[$key]) && $data[$key] !== '' ) update_post_meta($post_id, $meta, sanitize_text_field($data[$key]));
        if ( isset($data['robots']) && is_array($data['robots']) ) update_post_meta($post_id, '_rank_math_robots', array_map('sanitize_text_field',$data['robots']));
        update_post_meta($post_id, '_xdn_ai_rankmath_managed', 1);
    }
    public static function score_hint( $post_id ) {
        if ( ! self::available() ) return null;
        $kw = get_post_meta($post_id,'_rank_math_focus_keyword',true);
        $title = get_post_meta($post_id,'_rank_math_title',true);
        $desc = get_post_meta($post_id,'_rank_math_description',true);
        $score = 0;
        if ($kw) $score += 30;
        if ($title && mb_strlen($title) >= 30 && mb_strlen($title) <= 65) $score += 25;
        if ($desc && mb_strlen($desc) >= 100 && mb_strlen($desc) <= 170) $score += 25;
        if (get_post_thumbnail_id($post_id)) $score += 10;
        if (has_excerpt($post_id)) $score += 10;
        return $score;
    }
}
