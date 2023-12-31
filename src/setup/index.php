<?php

use EcclesiaCRM\dto\SystemURLs;
use EcclesiaCRM\dto\SystemConfig;

use Slim\Factory\AppFactory;
use DI\Container;

error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/external.log');

if (file_exists('../Include/Config.php')) {
    header('Location: ../index.php');
} else {
    require_once dirname(__FILE__) . '/../vendor/autoload.php';

    $rootPath = str_replace('/setup/index.php', '', $_SERVER['SCRIPT_NAME']);
    SystemURLs::init($rootPath, '', dirname(__FILE__)."/../");
    SystemConfig::init(null, 1);

    // Instantiate the app
    $container = new Container();

    $settings = require __DIR__ . '/../Include/slim/settings.php';
    $settings($container);

    AppFactory::setContainer($container);

    $app = AppFactory::create();

    $app->setBasePath($rootPath . "/setup");

    require __DIR__.'/../Include/slim/error-handler.php';
    require __DIR__ . '/routes/setup.php';

    $app->run();
}
