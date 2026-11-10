<?php
spl_autoload_register( 'app_root_autoloader' );
function app_root_autoloader($class) {
    $class_path = str_replace('\\', '/', $class);
    $file =  __DIR__ . '/../' . $class_path . '.php';
    //var_dump($file);
    if (file_exists($file)) {
        require $file;
    }
}