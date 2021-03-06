<?php

/**
 * Utility functions for HttpClient.
 * This file is required to be included, if you are using HttpClient.
 * 
 * @author Oleg Kasian <o-kasian@yandex.ru>
 * @license http://opensource.org/licenses/MIT MIT
 */

define("HTTPCLIENT_CACERT_PATH", "httpclient.cacert.path");
define("HTTPCLIENT_CACERT_PASSPHRASE", "httpclient.cacert.passphrase");
define("HTTPCLIENT_PROXY_URL", "httpclient.proxy.url");
define("HTTPCLIENT_NOPROXY", "httpclient.noproxy");

/**
 * HttpClient autoloader.
 * 
 * If you do not use Composer, you can
 * add this in the begining of your <code>__autoload</code> chain f.ex.
 * <br>
 * <pre><code>
 * function __autoload($className) {
 * &nbsp;&nbsp;if (httpClientAutoLoad($className)) {
 * &nbsp;&nbsp;&nbsp;&nbsp;return;
 * &nbsp;&nbsp;}
 * &nbsp;&nbsp;//your code here
 * }
 * </code></pre>
 * 
 * @param type $className name of the class to be looked for
 * @return boolean if the following class was loaded
 */
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

/**
 * Checks if a given ip is in a network range.
 * 
 * @author https://gist.github.com/tott/7684443
 * @param string $ip IP to check in IPV4 format eg. 127.0.0.1
 * @param string $range IP/CIDR netmask eg. 127.0.0.0/24, also 127.0.0.1 is accepted and /32 assumed
 * @return boolean true if the ip is in this range / false if not.
 */
function isIpInRange($ip, $range) {
    if (strpos($range, '/') == false) {
        $range .= '/32';
    }
    list( $range, $netmask ) = explode('/', $range, 2);
    $range_decimal = ip2long($range);
    $ip_decimal = ip2long($ip);
    $wildcard_decimal = pow(2, ( 32 - $netmask)) - 1;
    $netmask_decimal = ~ $wildcard_decimal;
    return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
}

/**
 * Builds a new {@see \org\projasource\httpclient\HttpRequest}.
 * 
 * An alias for {@see \org\projasource\httpclient\HttpRequest::build($url)}.
 * 
 * @param string $url an url for constructing a request
 * @return \org\projasource\httpclient\HttpRequest
 */
function httpRequest($url) {
    return \org\projasource\httpclient\HttpRequest::build($url);
}