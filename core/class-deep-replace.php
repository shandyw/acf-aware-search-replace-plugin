<?php

if (!defined('ABSPATH')) exit;

class ACFSR_Core_Deep_Replace
{
    public static function replace($data, $needle, $replace, $caseSensitive = false, $wholeWord = false, $useRegex = false): array
    {
        $matches = 0;
        $pattern = ACFSR_Core_Helpers::build_pattern((string)$needle, (bool)$caseSensitive, (bool)$wholeWord, (bool)$useRegex);

        $replacer = function ($str) use ($pattern, $replace, &$matches, $useRegex) {
            if (!is_string($str) || $pattern === '//') return $str;
            $new = preg_replace($pattern, (string)$replace, $str, -1, $count);
            // preg_replace returns null on bad regex; fail safe by keeping original
            if ($new === null) return $str;
            $matches += (int)$count;
            return $new;
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