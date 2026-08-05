<?php

spl_autoload_register(function ($class) {

    $prefix = 'LosInmortales\\EmailProvisioner\\';

    // Compatible con PHP 7+
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));

    $path = __DIR__
        . '/modules/EmailProvisioner/'
        . str_replace('\\', '/', $relative)
        . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});