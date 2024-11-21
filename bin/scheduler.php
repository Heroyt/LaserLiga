<?php

use Lsr\Core\App;
use Lsr\Core\Requests\Request;
use Nyholm\Psr7\ServerRequest;
use Orisai\Scheduler\Scheduler;

const ROOT = __DIR__ . '/../';
const INDEX = false;

require_once ROOT . "include/load.php";

// For link generation and base URL to work
App::getInstance()->setRequest(new Request(new ServerRequest('GET', 'https://laserliga.cz/')));
$scheduler = App::getServiceByType(Scheduler::class);
$scheduler->run();