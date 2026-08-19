<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Scores SEO opportunities without pretending to predict Google rankings. */
class XDN_AI_Opportunity {
    public static function score( $row ) {
        $intent = strtolower( (string) ( $row['intent'] ?? '' ) );
        $priority = strtolower( (string) ( $row['priority'] ?? '' ) );
        $score = 40;
        if ( in_array( $priority, array( 'high', 'cao' ), true ) ) $score += 25;
        elseif ( in_array( $priority, array( 'medium', 'trung bình' ), true ) ) $score += 12;
        if ( strpos( $intent, 'local' ) !== false || strpos( $intent, 'commercial' ) !== false || strpos( $intent, 'mua' ) !== false ) $score += 20;
        if ( strpos( $intent, 'informational' ) !== false || strpos( $intent, 'thông tin' ) !== false ) $score += 8;
        return min( 100, max( 0, $score ) );
    }

    public static function enrich( $research ) {
        if ( empty( $research['keyword_opportunities'] ) || ! is_array( $research['keyword_opportunities'] ) ) return $research;
        foreach ( $research['keyword_opportunities'] as &$row ) {
            $row['opportunity_score'] = self::score( $row );
        }
        unset( $row );
        usort( $research['keyword_opportunities'], function( $a, $b ) {
            return (int) ( $b['opportunity_score'] ?? 0 ) <=> (int) ( $a['opportunity_score'] ?? 0 );
        } );
        return $research;
    }
}
