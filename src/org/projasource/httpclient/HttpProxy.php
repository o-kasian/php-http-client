<?php

namespace org\projasource\httpclient;

class HttpProxy {

    private $host;
    private $port;
    private $user;
    private $password;
    private $socketConnection;
    private $scheme;

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

    public function user($userName) {
        $this->user = $userName;
    }

    public function password($password) {
        $this->password = $password;
    }

    public function host($host) {
        $this->host = $host;
    }

    public function port($port) {
        $this->port = $port;
    }

    public function connect($request) {
        if (!$this->isValid()) {
            throw new Exception("Proxy parameters are not set correctly " . $this);
        }
        return $request->getUseSSL() ? $this->connectViaSSL($request) : $this->connectViaTCP($request);
    }

    public function disconnect() {
        if ($this->socketConnection) {
            fclose($this->socketConnection);
        }
    }

    public function __toString() {
        return isset($this->user, $this->password) ? "{$this->user}:{$this->password}@{$this->host}:{$this->port}" : "{$this->host}:{$this->port}";
    }

    protected function isValid() {
        return isset($this->host, $this->port);
    }

    protected function connectViaSSL($request) {
        $sl = $this->doConnect($request);
        if ($sl[1] !== '200') {
            throw new \Exception($sl[2], $sl[1]);
        }
        stream_socket_enable_crypto($this->socketConnection, true, STREAM_CRYPTO_METHOD_SSLv23_CLIENT);
        return $this->socketConnection;
    }

    protected function connectViaTCP($request) {
        $sl = $this->doConnect($request);
        if ($sl[1] !== '200') {
            throw new \Exception($sl[2], $sl[1]);
        }
        return $this->socketConnection;
    }

    protected function doConnect($request) {
        $this->socketConnection = stream_socket_client(
                $this->scheme . $this->host . ":" . $this->port,
                $errno, $errstr, $request->getConnectionTimeOut(),
                STREAM_CLIENT_CONNECT, $request->getContext());
        if (!$this->socketConnection) {
            throw new Exception($errstr, $errno);
        }
        stream_set_timeout($this->socketConnection, $request->getReadTimeOut());
        fwrite($this->socketConnection, "CONNECT " . $request->getHost() . ":" . $request->getPort() . " HTTP/1.1\r\n");
        fwrite($this->socketConnection, "Host: " . $request->getHost() . ":" . $request->getPort() . "\r\n");
        if ($this->user != null && $this->password != null) {
            fwrite($this->socketConnection, "Proxy-Authorization: basic " . base64_encode($this->user . ":" . $this->password) . "\r\n");
        }
        fwrite($this->socketConnection, "Connection: close\r\n\r\n");
        while (true) {
            $connectLine = fgets($this->socketConnection);
            if (trim($connectLine) != '') {
                $parts = explode(" ", $connectLine, 3);
                return $parts;
            }
        }
        throw new Exception("Could nor recieve a valid header from proxy");
    }

//    protected function setupCacerts($context) {
//        $path = getenv("httpclient.cacert.path");
//        if ($path && file_exists($path)) {
//            stream_context_set_option($context, 'ssl', 'cafile', $path);
//            if (getenv("httpclient.cacert.passprase")) {
//                stream_context_set_option($context, 'ssl', 'passphrase', getenv("httpclient.cacert.passprase"));
//            }
//            stream_context_set_option($context, 'ssl', 'verify_host', true);
//            stream_context_set_option($context, 'ssl', 'verify_peer', true);
//            stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
//            return true;
//        }
//        return false;
//    }
}
