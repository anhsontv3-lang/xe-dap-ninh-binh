<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class XDN_AI_Rank_Math {
    public static function is_active() {
        return defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath\\Post\\Meta' );
    }

    public static function save_meta( $post_id, $seo = array() ) {
        if ( ! $post_id || ! is_array( $seo ) ) return false;

        $map = array(
            'focus_keyword'     => 'rank_math_focus_keyword',
            'seo_title'         => 'rank_math_title',
            'meta_description'  => 'rank_math_description',
            'canonical_url'     => 'rank_math_canonical_url',
            'robots'            => 'rank_math_robots',
        );

        foreach ( $map as $key => $meta_key ) {
            if ( isset( $seo[ $key ] ) && $seo[ $key ] !== '' ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $seo[ $key ] ) );
            }
        }

        if ( ! empty( $seo['schema_type'] ) ) {
            update_post_meta( $post_id, 'rank_math_schema_Category', sanitize_text_field( $seo['schema_type'] ) );
        }

        return true;
    }
}
