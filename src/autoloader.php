<?php
/**
 * PSR4 compliant autoloader
 * Copyright (C) 2023  FusionDirectory
 *
 * @param string $class The fully-qualified class name.
 */

spl_autoload_register(function ($class) {
  // Simple array to keep track of which classes have already been loaded.
  static $classes = [];

  // Avoids re-loading classes that have already been loaded.
  if (array_key_exists($class, $classes)) {
    return;
  }

  $base_dir = __DIR__;
  $file = $base_dir . '/' . str_replace('\\', '/', $class) . '.php';

  if (file_exists($file)) {
    require $file;

  } else {
    // More efficient than glob() - it avoids scanning subdirectories that have already been processed.
    $iterator = new RecursiveDirectoryIterator($base_dir);
    $flattened = new RecursiveIteratorIterator($iterator);

    foreach ($flattened as $f) {
      if (!$f->isFile()) {
        continue;
      }

      if (strpos($f->getPathname(), $class . '.php') !== FALSE) {
        require $f->getPathname();
        break;
      }
    }
  }

  $classes[$class] = TRUE;

});
