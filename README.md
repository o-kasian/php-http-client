#PHP HttpClient

A simple and flexible tool to make HTTP requests from php applications.

###Key features:
* no curl required, plain sockets only
* synchronous and **asynchronous** requests
* adjustable stream context via [stream_context_create](http://www.php.net/manual/en/function.stream-context-create.php) makes it possible to use custom certificates per request, by default ssl verification is off
* easy to use with **HTTP proxy**. Supports SSL connections through proxy.
* easy and powerfull api, that helps you to to concentrate on things that are really important.

###Installation:
You can either use composer or include **util.php** in your app, adding
```php
httpClientAutoLoad($className);
```
to your `__autoload` chain
###Examples:
Basically, any request starts with `HttpRequest::build($url)`.
####Basic Example:
```php
$response = HttpRequest::build("https://example.com")->execute();
var_dump($response->getEntity());
```
####Proxy Example:
```php
$response = HttpRequest::build("https://example.com")
    ->method("POST")
    ->contentType("application/json")
    ->entity(json_encode($entity))
    ->proxy("http://user:password@192.168.0.1:8080")
    ->accept("application/json")
    ->execute();
echo $response->getEntity()->field;
```
####Extended SSL example:
```php
$context = stream_context_create();
stream_context_set_option($context, 'ssl', 'cafile', $path);
stream_context_set_option($context, 'ssl', 'verify_host', true);
stream_context_set_option($context, 'ssl', 'verify_peer', true);
stream_context_set_option($context, 'ssl', 'allow_self_signed', true);

$response = HttpRequest::build("https://example.com")
    ->context($context)
    ->execute();
```

####API Documentation
To get api documentation one may use **generate-doc** make target, or [view it online](http://o-kasian.github.io/php-http-client/doc/namespaces/org.projasource.httpclient.html)
