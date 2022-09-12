<?php

/*******************************************************************************
 *
 *  filename    : NewsDashboard.php
 *  last change : 2022-09-10
 *  description : manage the Pastoral Care
 *
 *  http://www.ecclesiacrm.com/
 *  This code is under copyright not under MIT Licence
 *  copyright   : 2020 Philippe Logel all right reserved not MIT licence
 *                This code can't be include in another software
 *  Updated : 2018-07-13
 *
 ******************************************************************************/

// Routes
use Slim\Routing\RouteCollectorProxy;

// in practice you would require the composer loader if it was not already part of your framework or project
spl_autoload_register(function ($className) {
    include_once str_replace(array('Plugins\\APIControllers', '\\'), array(__DIR__.'/../core/APIControllers', '/'), $className) . '.php';
});

use Plugins\APIControllers\NewsDashboardController;

$app->group('/newsdashboardplugin', function (RouteCollectorProxy $group) {

    $group->post('/create', NewsDashboardController::class . ':create' );
    $group->post('/update', NewsDashboardController::class . ':update' );
    $group->post('/info', NewsDashboardController::class . ':info' );
    $group->delete('/remove', NewsDashboardController::class . ':remove' );

});
