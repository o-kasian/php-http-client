<?php

namespace org\projasource\httpclient;

/**
 * 
 * @author Oleg Kasian <o-kasian@yandex.ru>
 * @license http://opensource.org/licenses/MIT MIT
 */
class HttpResponse {

    private $statusCode;
    private $reasonPhrase;
    private $httpVersion;
    private $headers = array();
    private $body = '';
    private $contentType;
    private $charset;
    private $entity;

    function __construct($resource) {
        $inBody = false;
        $lastHeader = null;
        while ($line = fgets($resource)) {
            if (!$this->statusCode && strlen(trim($line)) == 0) {
                continue;
            }
            if (!$this->statusCode) {
                $this->parseStatusLine($line);
                continue;
            }
            if (!$inBody && $line === "\r\n") {
                $inBody = true;
                continue;
            }
            if ($inBody) {
                $this->body .= $line;
            } else {
                if ($lastHeader == null || substr($line, 0, 1) !== " ") {
                    $lastHeader = $this->parseHeader($line);
                } else {
                    $this->headers[$lastHeader] .= trim($line);
                }
            }
        }
        $this->process();
    }

    public function getHeaders() {
        return $this->headers;
    }

    public function getHeader($name) {
        return $this->headers[trim(strtolower($name))];
    }

    public function getRawBody() {
        return $this->body;
    }

    public function getStatusCode() {
        return $this->statusCode;
    }

    public function getReasonPhrase() {
        return $this->reasonPhrase;
    }

    public function getHttpVersion() {
        return $this->httpVersion;
    }

    public function getContentType() {
        return $this->contentType;
    }

    public function getCharset() {
        return $this->charset;
    }

    public function getEntity() {
        return $this->entity;
    }

    protected function parseStatusLine($line) {
        $args = explode(" ", trim($line), 3);
        $this->statusCode = $args[0];
        $this->reasonPhrase = $args[1];
        $this->httpVersion = $args[2];
    }

    protected function parseHeader($line) {
        $args = explode(":", $line, 2);
        $this->headers[strtolower($args[0])] = trim($args[1]);
        return strtolower($args[0]);
    }

    protected function process() {
        switch ($this->getHeader('content-encoding')) {
            case 'gzip':
                $this->body = gzdecode($this->body);
                break;
            case 'deflate':
                $this->body = zlib_decode($this->body);
                break;
            default:
        }
        $this->processContentType();
        $this->processEntity();
    }

    protected function processContentType() {
        if ($this->getHeader('content-type')) {
            $ct = explode(";", $this->getHeader('content-type'));
            if (count($ct) > 1) {
                for ($i = 1; $i < count($ct); $i++) {
                    $meta = explode("=", trim($ct[$i]));
                    if (count($meta) > 1) {
                        switch ($meta[0]) {
                            case 'charset':
                                $this->charset = $meta[1];
                                break;
                        }
                    }
                }
            }
            $this->contentType = trim($ct[0]);
        }
    }

    protected function processEntity() {
        switch ($this->contentType) {
            case 'text/xml':
            case 'application/xml':
                $resp = explode('&', trim($this->body));
                $this->entity = array();
                foreach ($resp as $part) {
                    $kv = explode('=', $part);
                    $this->entity[$kv[0]] = urldecode($kv[1]);
                }
                break;
            case 'application/json':
                $this->entity = json_decode($this->body);
                break;
            case 'application/x-www-form-urlencoded':
                $this->entity = simplexml_load_string(trim($this->body));
                break;
            default:
                $this->entity = $this->body;
        }
    }

}
