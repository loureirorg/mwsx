# mwsX - write in a language, run in all


## What is?
mwsX its a library to remotelly call a function, independent of which language it was written. You can include your PHP file in Javascript and access the PHP functions, or call Ruby methods in PHP, even in different server.

## How?
```ruby
# this file: my_ruby.rb
require "mwsx"

# _EXPORT_
def my_ruby_function(a)
	return "Hello #{a}!"
end
```
```php
<?php
// this file: my_php.php
include_once("mwsx.php");
include_ws("my_ruby.rb");

/* _EXPORT_ */
function my_php_function($a)
{
	return "PHP say: ".my_ruby_function(a);
}

```
```html
<script type="text/javascript" src="mwsx.js"></script>
<script type="text/javascript">
	ws_include("my_php.php?mwsd");
	alert(my_php_function("World"));
</script>

```


## Features
* http/json based communication
* supported languages: PHP, Javascript, Ruby* (in development)
* support to session on server side
* support to memcached
* syncronous or assyncronous response
* support to binary file transfer
* support to transfer progress monitor
* support to error/warnings/signals
* remote functions in objects (methods) or inline
* control of what functions will be published


## Goals
* not intrusive
* easy to install and use
* "write once, run anywhere" concept
* minimalist code
* high responsiveness