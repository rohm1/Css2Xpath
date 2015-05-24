<?php

spl_autoload_register(function($name) {
    if (strpos($name, 'RPBase\\Css2Xpath\\') === 0) {
        require_once __DIR__ . '/../src/' . str_replace('\\', '/', $name) . '.php';
    }
});
