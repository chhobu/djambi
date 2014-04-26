<?php
spl_autoload_register(function($class) {
  if (substr($class, 0, 7) == 'Djambi\\') {
    include __DIR__ . '/../../' . str_replace('\\', '/', $class) . '.php';
  }
});
