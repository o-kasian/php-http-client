<?php

namespace org\projasource\httpclient;

use org\projasource\httpclient\HttpProxy;
use org\projasource\httpclient\HttpResponse;

/**
 * 
 * @author Oleg Kasian <o-kasian@yandex.ru>
 * @license http://opensource.org/licenses/MIT MIT
 */
class HttpRequest {

    private $useSSL = true;
    private $port;
    private $host;
    private $path = '/';
    private $headers = array();
    private $method = 'GET';
    private $queryParams = array();
    private $entity;
    private $contentType = 'application/octet-stream';
    private $charset = "ISO-8859-1";
    private $accept = array();
    private $context;
    private $proxy;
    private $connectionTimeOut = 10;
    private $readTimeOut = 10;
    private $async = false;

    private static $ALLOWED_METHODS = array(
        'OPTIONS', 'GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'TRACE', 'PATCH',
    );
    const CRLF = "\r\n";

    public static function build($url) {
        return new HttpRequest($url);
    }

    public function __construct($url) {
        $pu = parse_url($url);
        $this->host = $pu['host'];
        isset($pu['path']) && $this->path = $pu['path'];
        if (strpos($url, '?')) {
            $this->path .= substr($url, strpos($url, '?'));
        }
        $this->scheme = $pu['scheme'] == 'https' ? 'ssl://' : 'tcp://';
        $this->port = isset($pu["port"]) ? $pu["port"] : $this->scheme === 'ssl://' ? 443 : 80;
        isset($pu['user'], $pu['pass']) && $this->headers["Authorization"] = "Basic " . base64_encode($pu['user']. ":" . $pu['pass']);
        $this->headers['User-Agent'] = 'php-HttpRequest/1.0 (' . php_uname() . ')';
    }

    public function header($headerName, $value) {
        if (!preg_match('/^[\x21-\x39\x3B-\x7E]*$/', $headerName)) {
            trigger_error("Header name " . $headerName . " is not compliant with RFC822 section 3.1", E_WARNING);
        }
        if (strpos($headerName, '_')) {
            trigger_error("Header name " . $headerName . " contains undersocre, which is not properly recognized by some HTTP servers", E_USER_NOTICE);
        }
        $this->headers[$headerName] = $value;
        return $this;
    }

    public function method($method) {
        if (in_array($method, self::$ALLOWED_METHODS)) {
            $this->method = $method;
        }
        return $this;
    }

    public function param($name, $value) {
        $this->queryParams[$name] = urlencode($value);
        return $this;
    }

    public function entity($entity) {
        $this->entity = $entity;
        return $this;
    }

    public function contentType($type) {
        //TODO: validate
        $this->contentType = $type;
        return $this;
    }

    public function charset($charset) {
        //TODO: validate
        $this->charset = $charset;
        return $this;
    }

    public function accept($type) {
        //TODO: validate
        $this->accept[] = $type;
        return $this;
    }
    
    public function proxy($proxy) {
        switch (gettype($proxy)) {
            case 'string':
                $this->proxy = new HttpProxy($proxy);
                break;
            case 'object':
                if ($proxy instanceof HttpProxy) {
                    $this->proxy = $proxy;
                } else {
                    trigger_error("proxy must be of type 'string' or 'org\projasource\httpclient\HttpProxy'", E_WARNING);
                }
                break;
        }
        return $this;
    }

    public function context($context) {
        $this->context = $context;
        return $this;
    }

    public function connectTimeout($timeout) {
        $this->connectionTimeOut = $timeout;
        return $this;
    }

    public function readTimeout($timeout) {
        $this->readTimeOut = $timeout;
        return $this;
    }

    public function async($async) {
        $this->async = $async;
        return $this;
    }

    public function execute() {
        $socket = $this->proxy == null ? $this->connect() : $this->proxy->connect($this);
        if ($this->async) {
            stream_set_blocking($sc, false);
        }
        $this->write($socket);
        $response = new HttpResponse($socket);
        fclose($socket);
        return $response;
    }

    public function write($resource) {
        $this->writeStatusLine($resource);
        $this->writeHeaders($resource);
        $this->writeBody($resource);
    }

    public function getUseSSL() {
        return $this->useSSL;
    }

    public function getPort() {
        return $this->port;
    }

    public function getHost() {
        return $this->host;
    }

    public function getContext() {
        return $this->context ? $this->context : $this->getDefaultContext();
    }

    public function getConnectionTimeOut() {
        return $this->connectionTimeOut;
    }

    public function getReadTimeOut() {
        return $this->readTimeOut;
    }
    
    public function getDefaultContext() {
        $context = stream_context_create();
        $path = getenv(HTTPCLIENT_CACERT_PATH);
        if ($path && file_exists($path)) {
            stream_context_set_option($context, 'ssl', 'cafile', $path);
            if (getenv(HTTPCLIENT_CACERT_PASSPHRASE)) {
                stream_context_set_option($context, 'ssl', 'passphrase', getenv(HTTPCLIENT_CACERT_PASSPHRASE));
            }
            stream_context_set_option($context, 'ssl', 'verify_host', true);
            stream_context_set_option($context, 'ssl', 'verify_peer', true);
            stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
        }
        return $context;
    }

    protected function connect() {
        $context = $this->context;
        $sc = stream_socket_client(
                $this->scheme . $this->host . ":" . $this->port,
                $errno, $errstr, $this->getConnectionTimeOut(),
                STREAM_CLIENT_CONNECT, $context);
        if (!$sc) {
            throw new Exception($errstr, $errno);
        }
        stream_set_timeout($sc, $this->getReadTimeOut());
        return $sc;
    }

    protected function writeStatusLine($res) {
        fwrite($res, $this->method . ' ' . $this->path . ' HTTP/1.1' . self::CRLF);
    }

    protected function writeHeaders($res) {
        fwrite($res, "Host: " . $this->host . "\r\n");
        if ($this->entity != null && !in_array($this->method, array('GET', 'HEAD'))) {
            $this->headers['Content-Type'] = $this->contentType . ';charset=' . $this->charset;
            $this->headers['Content-Length'] = strlen($this->entity);
        }
        if (count($this->accept) !== 0) {
            $this->headers['Accept'] = implode(', ', $this->accept);
        }
        $this->headers['Accept-Encoding'] = 'gzip, deflate';
        $this->headers['Connection'] = 'close';
        foreach ($this->headers as $fName => $fValue) {
            fwrite($res, $fName . ': ' . $fValue . self::CRLF);
        }
    }

    protected function writeBody($res) {
        fwrite($res, self::CRLF);
        if ($this->entity) {
            fwrite($res, $this->entity);
        }
    }
}