<?php

namespace App\Api;

use OpenApi\Attributes as OA;

#[OA\Info(
	version    : '1.0',
	description: 'All API methods on the Laser liga portal.',
	title      : 'LaserLiga API',
)]
#[OA\OpenApi(security: [['bearerAuth' => []]])]
#[OA\Server(url: 'https://laserliga.cz')]
#[OA\SecurityScheme(securityScheme: "bearerAuth", type: "http", scheme: "bearer")]
class OpenApi
{

}