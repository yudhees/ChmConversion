<?php

namespace Yudhees\ChmConversion;

if (!function_exists('def_pipeline_option')) {
    function def_pipeline_option(): array
    {
        return ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']];
    };
}

if (!function_exists('JsontoString')) {
    function JsontoString($data)
    {
        return is_object($data) || is_array($data) ? json_encode($data) : $data;
    }
}

if (!function_exists('ssl_decrypt')) {
    function ssl_decrypt(string $str)
    {
        $encryption_ciphering = "AES-128-CTR";
        $encryption_saltkey = "100testbakuun001";
        $encryption_options = "0";
        $encryption_IV = "1234567891011121";
        $string = str_replace("~", "+", $str);
        $string = str_replace(",", "=", $string);
        $string = openssl_decrypt($string, $encryption_ciphering, $encryption_saltkey, $encryption_options, $encryption_IV);
        return $string;

    }
}