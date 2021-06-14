<?php
spl_autoload_register('autoload_doofinder_classes');

function autoload_doofinder_classes($className) {
  $libraryPrefix = 'Doofinder\\';
  $libraryDirectory = __DIR__ . '/src/';

  if (strpos($className, 'Doofinder\GuzzleHttp\Promise') !== false) {
    $libraryPrefix = 'Doofinder\\GuzzleHttp\\Promise';
    $libraryDirectory = __DIR__ . '/vendor/guzzlehttp/promises/src';
  }

  if (strpos($className, 'Doofinder\GuzzleHttp\Psr7') !== false) {
    $libraryPrefix = 'Doofinder\\GuzzleHttp\\Psr7';
    $libraryDirectory = __DIR__ . '/vendor/guzzlehttp/psr7/src';
  }

  if (strpos($className, 'Doofinder\GuzzleHttp') !== false) {
    $libraryPrefix = 'Doofinder\\GuzzleHttp';
    $libraryDirectory = __DIR__ . '/vendor/guzzlehttp/guzzle/src';
  }

  if (strpos($className, 'Doofinder\Psr\Http\Message') !== false) {
    $libraryPrefix = 'Doofinder\\Psr\\Http\\Message';
    $libraryDirectory = __DIR__ . '/vendor/psr/http-message/src';
  }

  $len = strlen($libraryPrefix);

  // Binary safe comparison of $len first characters
  if (strncmp($libraryPrefix, $className, $len) !== 0) {
    return;
  }

  $classPath = str_replace('\\', '/', substr($className, $len)) . '.php';
  $file = $libraryDirectory . $classPath;

  if (file_exists($file)) {
    require $file;
  }
}
