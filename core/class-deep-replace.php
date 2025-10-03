<?php

namespace ACFSR;

if (!defined('ABSPATH')) exit;

class ACFSR_Core_Deep_Replace
{
    /**
     * Deeply replace in strings/arrays/objects. Returns array:
     * ['value' => mixed, 'matches' => int]
     */
    public static function replace($data, $needle, $replace, $caseSensitive = false)
    {
        $matches = 0;

        $replacer = function ($str) use ($needle, $replace, $caseSensitive, &$matches) {
            if (!is_string($str)) return $str;
            if ($needle === '') return $str;
            if ($caseSensitive) {
                $count = 0;
                $new = str_replace($needle, $replace, $str, $count);
                $matches += $count;
                return $new;
            } else {
                // case-insensitive replace with count
                $pattern = '/' . preg_quote($needle, '/') . '/i';
                $new = preg_replace($pattern, $replace, $str, -1, $count);
                $matches += (int)$count;
                return $new;
            }
        };

        $walker = function ($v) use (&$walker, $replacer) {
            if (is_string($v)) return $replacer($v);
            if (is_array($v)) {
                foreach ($v as $k => $vv) $v[$k] = $walker($vv);
                return $v;
            }
            if (is_object($v)) {
                foreach (get_object_vars($v) as $k => $vv) $v->$k = $walker($vv);
                return $v;
            }
            return $v;
        };

        $newVal = $walker($data);
        return ['value' => $newVal, 'matches' => $matches];
    }
}