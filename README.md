# mwsX - write in one language, run in all
[![githalytics.com alpha](https://cruel-carlota.pagodabox.com/00c44fb2387137370abf057a0b4906cd "githalytics.com")](http://githalytics.com/loureirorg/mwsx)
mwsX it's a non-intrusive library to remotelly call a function, independent of which language it was written (aka "webservice"). You can include your PHP file in Javascript and access the PHP functions, or call Ruby methods in PHP, even in different server.

## Some uses
* you can use PHP functions directly in JavaScript;
* you can publish your PHP functions as web-services;

## Use example
```php
<?php
// this file: my_php.php
require "mwsx.php";

/* _EXPORT_ */
function my_function($a) {
    return my_function("Hello $a");
}
```
```html
<!-- this file: my_html.html -->
<script type="text/javascript" src="mwsx.js"></script>
<script type="text/javascript">
    require_ws("my_php.php?mwsd");

    alert(my_function("World"));
</script>
```
Calling "my_html.html" an alert will popup with "Hello World" message

## Features
* http/json based communication
* supported languages: PHP, Javascript
* support to session on server side
* support to APC and memcached for speed-up
* syncronous or assyncronous response
* support to binary file transfer
* support to transfer progress monitor
* support to error/warnings/signals
* remote functions in objects (methods) or inline
* control of what functions will be published
* namespaced
* config file driven

## Requirements at PHP side
* PHP 5.3 or higher
* APC (* optional)

## Goals
* not intrusive
* easy to install and use
* "write once, run anywhere" concept
* minimalist code
* high responsiveness
