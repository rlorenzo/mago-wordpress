<?php

declare(strict_types=1);

use Rlorenzo\MagoWordPress\WordPressExtension;
use Mago\Sdk\Worker;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

(new Worker(WordPressExtension::create()))->run();
