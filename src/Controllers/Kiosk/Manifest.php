<?php
declare(strict_types=1);

namespace App\Controllers\Kiosk;

use Lsr\Core\Controllers\Controller;
use Lsr\Interfaces\SessionInterface;
use Psr\Http\Message\ResponseInterface;

class Manifest extends Controller
{

	public function __construct(
		private readonly SessionInterface $session,
	) {
		parent::__construct();
	}

	function getManifest(): ResponseInterface {
		$contents = file_get_contents(ROOT . 'assets/manifest.json');
		assert(is_string($contents));
		$manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
		$kioskArena = $this->session->get('kioskArena');

		$manifest['start_url'] = '/kiosk/' . $kioskArena;
		$manifest['orientation'] = 'landscape';
		$manifest['shortcuts'] = [
			[
				'name'        => 'Kiosk',
				'shortName'   => 'Kiosk',
				'description' => 'Kiosk dashboard',
				'url'         => '/kiosk/' . $kioskArena,
				'icons'       => [
					[
						'src'   => "favicon/maskable_icon_x192.png",
						'sizes' => '192x192',
						'type'  => 'image/png',
					],
				],
			],
			[
				'name'        => 'Statistiky',
				'shortName'   => 'Statistiky',
				'description' => 'Statistiky arény',
				'url'         => '/kiosk/' . $kioskArena . '/games',
				'icons'       => [
					[
						'src'   => "favicon/maskable_icon_x192.png",
						'sizes' => '192x192',
						'type'  => 'image/png',
					],
				],
			],
			[
				'name'        => 'Hudební módy',
				'shortName'   => 'Hudba',
				'description' => 'Hudební módy arény',
				'url'         => '/kiosk/' . $kioskArena . '/music',
				'icons'       => [
					[
						'src'   => "favicon/maskable_icon_x192.png",
						'sizes' => '192x192',
						'type'  => 'image/png',
					],
				],
			],
			[
				'name'        => 'Výsledky',
				'shortName'   => 'Výsledky',
				'description' => 'Výsledky z posledních her',
				'url'         => '/kiosk/' . $kioskArena . '/games',
				'icons'       => [
					[
						'src'   => "favicon/maskable_icon_x192.png",
						'sizes' => '192x192',
						'type'  => 'image/png',
					],
				],
			],
			[
				'name'        => 'Žebříček',
				'shortName'   => 'Žebříček',
				'description' => 'Žebříček registrovaných hráčů',
				'url'         => '/kiosk/' . $kioskArena . '/leaderboard',
				'icons'       => [
					[
						'src'   => "favicon/maskable_icon_x192.png",
						'sizes' => '192x192',
						'type'  => 'image/png',
					],
				],
			],
		];

		return $this->respond($manifest);
	}

}