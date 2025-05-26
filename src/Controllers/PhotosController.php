<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Photos\Photo;
use App\Models\Photos\PhotoVariation;
use GrahamCampbell\GuzzleFactory\GuzzleFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Response;
use Lsr\Logging\Logger;
use Psr\Http\Message\ResponseInterface;

class PhotosController extends Controller
{

	public function photo(string $arena, string $file, Request $request): ResponseInterface {
		// Find photo
		$photo = Photo::findByIdentifier('photos/' . $arena . '/' . $file);
		if ($photo === null || $photo->url === null) {
			return $this->respond(new ErrorResponse('Photo not found', ErrorType::NOT_FOUND), 404);
		}

		return $this->proxy($photo->url);
	}

	private function proxy(string $url): ResponseInterface {
		$logger = new Logger(LOG_DIR, 'photos-proxy');
		$client = new Client(['handler' => GuzzleFactory::handler()]);

		$maxAge = 7776000; // 90 days

		$headers = [
			'Cache-Control' => 'public, max-age=' . $maxAge,
			'Pragma'        => 'public',
			'Expires'       => gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT',
		];

		try {
			$response = $client->get($url, ['stream' => true]);
		} catch (GuzzleException $e) {
			$logger->exception($e);
			return $this->respond(new ErrorResponse('Error fetching photo', ErrorType::INTERNAL), 500);
		}

		if ($response->hasHeader('Content-Type')) {
			$headers['Content-Type'] = $response->getHeaderLine('Content-Type');
		}
		else {
			$headers['Content-Type'] = 'image/jpeg';
		}

		if ($response->hasHeader('Content-Size')) {
			$headers['Content-Size'] = $response->getHeaderLine('Content-Size');
		}
		else {
			$size = $response->getBody()->getSize();
			if ($size !== null) {
				$headers['Content-Size'] = $size;
			}
		}

		if ($response->hasHeader('ETag')) {
			$headers['ETag'] = $response->getHeaderLine('ETag');
		}

		return Response::create(
			200,
			$headers,
			$response->getBody(),
		);
	}

	public function variation(string $arena, string $file, Request $request): ResponseInterface {
		// Find photo
		$photo = PhotoVariation::findByIdentifier('photos/' . $arena . '/' . $file);
		if ($photo === null || $photo->url === null) {
			return $this->respond(new ErrorResponse('Photo not found', ErrorType::NOT_FOUND), 404);
		}

		return $this->proxy($photo->url);
	}

}