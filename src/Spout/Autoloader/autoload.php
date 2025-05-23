<?php

namespace Box\Spout\Autoloader;

require_once 'Psr4Autoloader.php';

/**
 * @var string
 * Full path to "src/Spout" which is what we want "Box\Spout" to map to.
 */
$srcBaseDirectory = \dirname(__FILE__, 2);

$loader = new Psr4Autoloader();
$loader->register();
$loader->addNamespace('Box\Spout', $srcBaseDirectory);
