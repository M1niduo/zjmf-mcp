<?php

if (!function_exists('array_is_list')) {
    function array_is_list(array $array)
    {
        $expectedKey = 0;
        foreach ($array as $key => $value) {
            if ($key !== $expectedKey) {
                return false;
            }
            ++$expectedKey;
        }

        return true;
    }
}
