<?php

namespace org\projasource\httpclient;

/**
 * A class for obtaining a connection via HTTP(s) proxy.
 * 
 * An instance of this class does not change it's state between connections,
 * thereofore may be safely used several times during one process.
 * 
 * @author Oleg Kasian <o-kasian@yandex.ru>
 * @license http://opensource.org/licenses/MIT MIT
 */
class HttpProxy {

    private $host;
    private $port = 8080;
    private $user;
    private $password;
    private $scheme;
    private $noproxy = array();

    /**
     * Constructor for the proxy,
     * may be early initialized by passing url string,
     * or lately, using builder methods.
     * 
     * @param string $url string containig valid proxy url
     */
    public function __construct($url = null) {
        if ($url !== null && gettype($url) === 'string') {
            $pu = parse_url($url);
            $this->host = $pu['host'];
            $this->port = $pu['port'];
            $this->scheme = $pu['scheme'] == 'https' ? 'ssl://' : 'tcp://';
            $this->user = isset($pu['user']) ? $pu['user'] : null;
            $this->password = isset($pu['pass']) ? $pu['pass'] : null;
        }
    }

    /**
     * Sets username for authorization.
     * 
     * @param string $userName
     * @return HttpProxy
     */
    public function user($userName) {
        $this->user = $userName;
        return $this;
    }

    /**
     * Sets password for authorization.
     * 
     * @param string $password
     * @return HttpProxy itself
     */
    public function password($password) {
        $this->password = $password;
        return $this;
    }

    /**
     * Sets proxy hostname.
     * 
     * @param string $host
     * @return HttpProxy itself
     */
    public function host($host) {
        $this->host = $host;
        return $this;
    }

    /**
     * Sets proxy port (default is 8080).
     * 
     * @param string $port
     * @return HttpProxy itself
     */
    public function port($port) {
        $this->port = $port;
        return $this;
    }

    /**
     * Sets a noproxy range(s) for skipping
     * while making a socket connection.
     * 
     * @param mixed $hosts an array (or a single string)
     * of hostnames, ip addresses,
     * network ranges to be skipped by proxy.
     * @return HttpProxy itself
     */
    public function noproxy($hosts) {
        switch (gettype($hosts)) {
            case "array":
                $this->noproxy = array_merge($hosts, $this->noproxy);
                break;
            case "string":
                $this->noproxy[] = $hosts;
                break;
        }
        return $this;
    }

    /**
     * Connects to a proxy, making CONNECT request using instance of {@see HttpRequest}
     * for obtaining host and port.
     * 
     * @param HttpRequest $request a request to be made
     * @return resource socket connection to a proxy, ready for writing a request
     * @throws Exception in case a connection could not be made,
     * or proxy returned any status except for 200
     */
    public function connect($request) {
        if (!$this->isValid()) {
            throw new Exception("Proxy parameters are not set correctly " . $this);
        }
        //TODO: add validation for no-proxy hosts
        return $request->getUseSSL() ? $this->connectViaSSL($request) : $this->connectViaTCP($request);
    }

    public function __toString() {
        return isset($this->user, $this->password)
                ? "{$this->user}:{$this->password}@{$this->host}:{$this->port}"
                : "{$this->host}:{$this->port}";
    }

    protected function isValid() {
        return isset($this->host, $this->port);
    }

    protected function connectViaSSL($request) {
        $socket = $this->doConnect($request);
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_SSLv23_CLIENT);
        return $socket;
    }

    protected function connectViaTCP($request) {
        return $this->doConnect($request);
    }

    protected function doConnect($request) {
        $socket = stream_socket_client(
                $this->scheme . $this->host . ":" . $this->port,
                $errno, $errstr, $request->getConnectionTimeOut(),
                STREAM_CLIENT_CONNECT, $request->getContext());
        if (!$socket) {
            throw new Exception($errstr, $errno);
        }
        stream_set_timeout($socket, $request->getReadTimeOut());
        fwrite($socket, "CONNECT " . $request->getHost() . ":" . $request->getPort() . " HTTP/1.1\r\n");
        fwrite($socket, "Host: " . $request->getHost() . ":" . $request->getPort() . "\r\n");
        if ($this->user != null && $this->password != null) {
            fwrite($socket, "Proxy-Authorization: basic " . base64_encode($this->user . ":" . $this->password) . "\r\n");
        }
        fwrite($socket, "Connection: close\r\n\r\n");
        while (true) {
            $connectLine = fgets($socket);
            if (trim($connectLine) != '') {
                $parts = explode(" ", $connectLine, 3);
                if ($parts[1] !== '200') {
                    throw new \Exception($parts[2], $parts[1]);
                }
                return $socket;
            }
        }
        throw new Exception("Could nor recieve a valid header from proxy");
    }

}
