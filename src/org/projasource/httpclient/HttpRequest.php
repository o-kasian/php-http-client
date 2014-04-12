<?php

namespace org\projasource\httpclient;

use org\projasource\httpclient\HttpProxy;
use org\projasource\httpclient\HttpResponse;

/**
 * Starting point for making a http requests.
 * 
 * Uses builder pattern making your code easy
 * to write, and easy to understand.
 * 
 * A basic example of usage would be:<br>
 * 
 * <pre><code>
 * $response = HttpRequest::build("https://example.com")
 * &nbsp;&nbsp;&nbsp;&nbsp;->method("POST")
 * &nbsp;&nbsp;&nbsp;&nbsp;->contentType("application/json")
 * &nbsp;&nbsp;&nbsp;&nbsp;->entity(json_encode($entity))
 * &nbsp;&nbsp;&nbsp;&nbsp;->proxy("http://user:password@192.168.0.1:8080")
 * &nbsp;&nbsp;&nbsp;&nbsp;->accept("application/json")
 * &nbsp;&nbsp;&nbsp;&nbsp;->execute();
 * 
 * echo $response->getEntity()->field;
 * </pre></code>
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
        'OPTIONS', 'GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'TRACE', 'PATCH'
    );
    const CRLF = "\r\n";

    /**
     * Returns request constructed or the $url.
     * 
     * Basicly, it does nothing but <code>new HttpRequest($url);</code>,
     * however making it possible to write a request without assigning link to it,
     * f.ex.<br>
     * <code>HttpRequest::build("https://example.com")->async(true)->execute();</code>
     * <br>
     * In future some other logic may be added to this method, so it is
     * recomended to be used instead of constructor.
     * 
     * NOTICE! an url containing user:pass section.
     * @param string $url an url for request
     * @return \org\projasource\httpclient\HttpRequest
     */
    public static function build($url) {
        return new HttpRequest($url);
    }

    /**
     * Constructs a new HttpRequest with a given url.
     * 
     * It is possible but not recomended to use constructor for
     * obtaining an instance of HttpRequest, use {@see HttpRequest::build($url)}
     * instead.
     * 
     * @param string $url an url for request
     */
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

    /**
     * Set's a http request header.
     * 
     * NOTICE! A validation is performed, according to RFC822 section 3.1,
     * triggering a warning if not valid.
     * 
     * If a headers containing an underscore, would be concerned partialy valid,
     * as they are valid by RFC822, but not recognized well by most http servers
     * (becouse of CGI env variables naming).
     *  
     * @param type $headerName
     * @param type $value
     * @return \org\projasource\httpclient\HttpRequest
     */
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

    /**
     * Set's a request method.
     * 
     * @param string $method could be any of
     * 'OPTIONS', 'GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'TRACE', 'PATCH'
     * 
     * @return \org\projasource\httpclient\HttpRequest
     */
    public function method($method) {
        if (in_array($method, self::$ALLOWED_METHODS)) {
            $this->method = $method;
        }
        return $this;
    }

    /**
     * Set's a request query parameter, to be appended into request query.
     * 
     * Parameter would be urlencoded automatically, so you
     * don't have to make any encoding by yourself.
     * 
     * @param string $name parameter name
     * @param string $value parameter value
     * @return \org\projasource\httpclient\HttpRequest
     */
    public function param($name, $value) {
        $this->queryParams[$name] = urlencode($value);
        return $this;
    }

    /**
     * Set's an entity to be used in HttpRequest.
     * 
     * Could be of any type, conversion will be made
     * using when sending a request with a {@link contentType($type)}
     * set.
     * 
     * If a content type, or type of entity are not recognized,
     * conversion to string will be obtained.
     * 
     * @param mixed $entity entity of any type
     * @return \org\projasource\httpclient\HttpRequest
     */
    public function entity($entity) {
        //TODO: convert if required
        //TODO: consider multipart entities
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
        //TODO: look into HTTPCLIENT_PROXY_URL, HTTPCLIENT_NOPROXY
        //TODO: add validation for no-proxy hosts
        $socket = $this->proxy == null ? $this->connect() : $this->proxy->connect($this);
        if ($this->async) {
            stream_set_blocking($socket, false);
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