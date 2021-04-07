<?php

use Slim\Routing\RouteCollectorProxy;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


use EcclesiaCRM\Utils\GeoUtils;

$app->group('/geocoder', function (RouteCollectorProxy $group) {
    $group->post('/address', GeocoderController::class . ':getGeoLocals' );
    $group->post('/address/', GeocoderController::class . ':getGeoLocals' );
});

class GeocoderController
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * A method that return GeoLocation based on an address.
     *
     * @param \Slim\Http\Request $p_request   The request.
     * @param \Slim\Http\Response $p_response The response.
     * @param array $p_args Arguments
     * @return \Slim\Http\Response The augmented response.
     */
    function getGeoLocals (ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface {
        $input = json_decode($request->getBody());
        if (!empty($input)) {
            return $response->withJson(GeoUtils::getLatLong($input->address));
        }
        return $response->withStatus(404);
    }
}


