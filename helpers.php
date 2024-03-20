<?php

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        if (!array_key_exists($key, $_ENV)) {
            return $default;
        }
        $value = $_ENV[$key];
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }
        $len = strlen($value);
        if ($len > 1 && ($value[0] == '"' && $value[$len - 1] == '"')) {
            return substr($value, 1, -1);
        }
        return $value;
    }
}

if (!function_exists('console_log')) {
    function console_log(string $text)
    {
        printf("$text\n");
    }
}

if (!function_exists('get_data')) {
    function get_data()
    {
        $path = ROOT_PATH . '/data.json';
        $data = [];
        if (is_readable($path)) {
            $body = @json_decode(@file_get_contents($path), true);
            if (is_array($body)) {
                $data = $body;
            }
        }
        return $data;
    }
}
