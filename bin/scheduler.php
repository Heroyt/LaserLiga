<?php

use Lsr\Core\App;
use Orisai\Scheduler\Scheduler;

const ROOT = __DIR__ . '/../';
const INDEX = false;

require_once ROOT . "include/load.php";

$scheduler = App::getServiceByType(Scheduler::class);
$scheduler->run();