<?php

namespace org\projasource\httpclient;

/**
 * Describes response of http server, provides methods for
 * fast access to different response parts.
 * 
 * @author Oleg Kasian <o-kasian@yandex.ru>
 * @license http://opensource.org/licenses/MIT MIT
 */
class HttpResponse {

    private $statusCode = 0;
    private $reasonPhrase;
    private $httpVersion;
    private $headers = array();
    private $body = '';
    private $contentType;
    private $charset = "ISO-8859-1";
    private $entity;

    /**
     * Creates new HttpResponse, reading it from
     * input sream of the given resource.
     * 
     * In most cases Response is not constructed
     * manually, however it may be 'mocked' by opening
     * a file containing raw http response, and passing
     * file descriptor to a constructor f.ex.
     * <code><pre>
     * $fp = fopen('response.raw');
     * $resp = new HttpResponse($fp);
     * </pre></code>
     * 
     * @param resource $resource
     */
    public function __construct($resource) {
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
                //TODO: conversion
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

    /**
     * Returns http headers in a form of array.
     * 
     * NOTICE! All header names will be lowercased,
     * as far as they have to be case insensitive.
     * If you need to obtain an information about
     * a concrete header, use {@see getHeader}
     * 
     * @return array associative array $header_name => $header_value
     */
    public function getHeaders() {
        return $this->headers;
    }

    /**
     * Returns value for the header with a given name.
     * 
     * NOTICE! Header names are case insensitive.
     * 
     * @param string $name name of the header
     * @return string header value, or null if no header for the
     * given name exist.
     */
    public function getHeader($name) {
        $nm = trim(strtolower($name));
        return isset($this->headers[$nm]) ? $this->headers[$nm] : null;
    }

    /**
     * Returns a 'raw' http entity.
     * 
     * No conversion is made, and everything after
     * header section of the response (without leading CRLF)
     * is returned.
     * 
     * @return string response body
     */
    public function getRawBody() {
        return $this->body;
    }

    /**
     * Returns a http status code of the response,
     * is a first part of status line.<br>
     * <b>200</b> OK HTTP/1.1<br>
     * 
     * @return integer http status, or '0', if response
     * could not be parsed.
     */
    public function getStatusCode() {
        return $this->statusCode;
    }

    /**
     * Returns reason phrase, is a second part
     * of status line.<br>
     * 200 <b>OK</b> HTTP/1.1<br>
     * 
     * @return string a reason phrase
     */
    public function getReasonPhrase() {
        return $this->reasonPhrase;
    }

    /**
     * Return http version, in form of HTTP/{MAJOR}.{MINOR}<br>
     * 200 OK <b>HTTP/1.1</b><br>
     * @return string http version
     */
    public function getHttpVersion() {
        return $this->httpVersion;
    }

    /**
     * Returns content type in form type/subtype.
     * @return string content type
     */
    public function getContentType() {
        return $this->contentType;
    }

    /**
     * Returns charset.
     * @return string charset parsed from request or default "ISO-8859-1"
     */
    public function getCharset() {
        return $this->charset;
    }

    /**
     * Returns http entity parsed form response.
     * @return mixed an object representation of an entity, depending on a content-type
     */
    public function getEntity() {
        return $this->entity;
    }

    protected function parseStatusLine($line) {
        $args = explode(" ", trim($line));
        $this->statusCode = $args[0];
        $this->httpVersion = $args[count($args) - 1];
        $this->reasonPhrase = $args[implode(" ", array_slice($args, 1, -1))];
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
