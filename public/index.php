<?php

use Avolle\UpcomingMatches\App;
use Avolle\UpcomingMatches\View\View;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'paths.php';
require(ROOT . 'vendor/autoload.php');
require(ROOT . 'functions.php');
$config = require(ROOT . 'config.php');

if (!setlocale(LC_ALL, 'nb_NO')) {
    setlocale(LC_ALL, 'nb');
};

$log = new Logger('error');
$log->pushHandler(new StreamHandler(LOGS . 'error.log', Logger::ERROR));

$whoops = new Run();
if ($config['debug']) {
    $handler = new PrettyPageHandler();
} else {
    $handler = function() use ($whoops) {
        $view = new View();
        $view->setVar('whoops', $whoops);
        $view->display('error');
    };
}
$whoops->pushHandler($handler);
$whoops->pushHandler(function ($exception) use ($log) {
    $log->error($exception->getMessage() . "\n" . $exception->getTraceAsString());
});
$whoops->register();

$app = new App($_GET);

$app->run();
