<?php

use div\DeployAgent\Application;
use div\DeployAgent\ClearOpCacheCommand;
use div\DeployAgent\InitCommand;
use div\DeployAgent\SelfUpdateCommand;
use div\DeployAgent\ShowLastOutputCommand;
use div\DeployAgent\StartCommand;
use div\DeployAgent\StartWebDeployCommand;

require_once __DIR__ . '/vendor/autoload.php';

$__app__ = new Application('Инструмент для развётрывания php приложений', '1.2.3');

$__app__->add(new InitCommand());
$__app__->add(new StartCommand());
$__app__->add(new StartWebDeployCommand());
$__app__->add(new ClearOpCacheCommand());
$__app__->add(new SelfUpdateCommand());
$__app__->add(new ShowLastOutputCommand());

return $__app__;
