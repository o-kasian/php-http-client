<?php

function httpClientAutoLoad($className) {
    $ns = "org\projasource\httpclient";
    if (strpos($className, $ns) === 0) {
        $cls = substr($className, strlen($ns) + 1);
        $path = dirname(__FILE__) . '/' . $cls . '.php';
        if (file_exists($path)) {
            include_once $path;
        }
        return true;
    }
    return false;
}