<?php
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

    // public static function build_pattern(string $needle, bool $caseSensitive, bool $wholeWord, bool $useRegex): string
    // {
    //     if ($needle === '') {
    //         return '//';
    //     }
    //     if ($useRegex) {
    //         // Use as-is (user supplies anchors etc.), just add flags
    //         return '/' . $needle . '/' . ($caseSensitive ? '' : 'i');
    //     }
    //     $pattern = preg_quote($needle, '/');
    //     if ($wholeWord) {
    //         // Word-boundary version; more robust than \b for non-ASCII underscores
    //         $pattern = '(?<![A-Za-z0-9_])' . $pattern . '(?![A-Za-z0-9_])';
    //     }
    //     return '/' . $pattern . '/' . ($caseSensitive ? '' : 'i');
    // }

    public static function build_pattern(string $needle, bool $caseSensitive = false, bool $wholeWord = false, bool $useRegex = false): string
    {
        if ($needle === '') return '//u';
        if ($useRegex) {
            // Use as-is (user supplies anchors etc.)
            $delim = '/';
            $flags = 'u' . ($caseSensitive ? '' : 'i');
            return $delim . $needle . $delim . $flags;
        }
        $pattern = preg_quote($needle, '/');
        if ($wholeWord) {
            // “word” = no letter/number/_ on either side (safer than \b for mixed content)
            $pattern = '(?<![A-Za-z0-9_])' . $pattern . '(?![A-Za-z0-9_])';
        }
        return '/' . $pattern . '/' . ($caseSensitive ? 'u' : 'iu');
    }

    /**
     * Return an HTML snippet around the first match with the match wrapped in <mark>.
     * Escapes everything except the <mark> tag itself.
     */
    public static function snippet_highlight(string $haystack, string $needle, bool $caseSensitive = false, bool $wholeWord = false, bool $useRegex = false, int $radius = 45): string
    {
        if ($needle === '' || $haystack === '') return '';
        $pattern = self::build_pattern($needle, $caseSensitive, $wholeWord, $useRegex);

        if (!preg_match($pattern, $haystack, $m, PREG_OFFSET_CAPTURE)) {
            return ''; // no match
        }

        // First match start/len (UTF-8 safe: preg_* + 'u' flag)
        $matchText  = $m[0][0];
        $byteOffset = $m[0][1];

        // Convert byte offset to mb offset
        $preBytes = substr($haystack, 0, $byteOffset);
        $start    = mb_strlen($preBytes);                 // mb char index
        $matchLen = mb_strlen($matchText);

        $from  = max(0, $start - $radius);
        $to    = min(mb_strlen($haystack), $start + $matchLen + $radius);

        $preEll  = $from > 0 ? '…' : '';
        $postEll = $to < mb_strlen($haystack) ? '…' : '';

        $before = mb_substr($haystack, $from, $start - $from);
        $match  = mb_substr($haystack, $start, $matchLen);
        $after  = mb_substr($haystack, $start + $matchLen, $to - ($start + $matchLen));

        // Escape segments, keep <mark>
        $html  = $preEll;
        $html .= esc_html($before);
        $html .= '<mark class="acfsr-hit">' . esc_html($match) . '</mark>';
        $html .= esc_html($after);
        $html .= $postEll;

        return $html;
    }
}