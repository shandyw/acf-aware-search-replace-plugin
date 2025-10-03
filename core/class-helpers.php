<?php

namespace ACFSR;

if (!defined('ABSPATH')) exit;

class ACFSR_Core_Helpers
{
    public static function esc_text($s)
    {
        return esc_html($s ?? '');
    }

    public static function make_like($needle)
    {
        global $wpdb;
        return '%' . $wpdb->esc_like($needle) . '%';
    }

    // Provide a human snippet around first match
    public static function snippet($haystack, $needle, $caseSensitive = false, $radius = 45)
    {
        if (!is_string($haystack)) return '';
        $pos = $caseSensitive ? strpos($haystack, $needle) : stripos($haystack, $needle);
        if ($pos === false) return '';
        $start = max(0, $pos - $radius);
        $len = mb_strlen($needle);
        $end = min(mb_strlen($haystack), $pos + $len + $radius);
        $pre = $start > 0 ? '…' : '';
        $post = $end < mb_strlen($haystack) ? '…' : '';
        $chunk = mb_substr($haystack, $start, $end - $start);
        return $pre . $chunk . $post;
    }

    public static function is_serialized_value($value)
    {
        if (!is_string($value)) return false;
        $value = trim($value);
        if ($value === 'N;') return true;
        if (!preg_match('/^[aOsibd]:/', $value)) return false;
        return @unserialize($value) !== false || $value === 'b:0;';
    }
}