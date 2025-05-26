<?php
declare(strict_types=1);

namespace App\Core\Middleware;

use Lsr\Core\App;
use Lsr\Core\Exceptions\InvalidLanguageException;
use Lsr\Core\Requests\Response;
use Lsr\Core\Routing\Middleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RedirectFromDefaultToDesiredLang implements Middleware
{

	/**
	 * @inheritDoc
	 */
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
		// Do not redirect bots or crawlers
		if ($request->hasHeader('User-Agent')) {
			$userAgent = $request->getHeaderLine('User-Agent');
			if (preg_match('/(bot|crawl|spider|slurp|archive)/i', $userAgent)) {
				return $handler->handle($request);
			}
		}

		// If the user just got to the page and has the Accept-Language header set, we want to redirect them to the desired language instead of using the default one.
		$app = App::getInstance();
		$translations = $app->translations;
		$sessionLang = $app->session->get('lang');

		if ($sessionLang !== null) {
			// The session is already set, no need to redirect
			return $handler->handle($request);
		}

		$lang = $request->getAttribute('lang');
		try {
			$defaultLang = $translations->getDefaultLangId();
		} catch (InvalidLanguageException) {
			$defaultLang = DEFAULT_LANGUAGE;
		}

		// Find the desired language based on the request header
		$desiredLang = $lang;
		if ($lang === $defaultLang && $request->hasHeader('Accept-Language')) {
			$header = $request->getHeader('Accept-Language');
			foreach ($header as $value) {
				$info = explode(';', $value);
				$languages = explode(',', $info[0]);
				foreach ($languages as $language) {
					if ($translations->supportsLanguage($language)) {
						$desiredLang = explode('_', $translations->findLanguage($language)->id)[0];
						break 2; // Break out of both loops
					}
				}
			}
		}

		// If the current language is the default language and the user's desired language is different, redirect to the desired language
		if ($desiredLang !== $lang) {
			// Get the current path
			$path = array_filter(explode('/', $request->getUri()->getPath()));
			parse_str($request->getUri()->getQuery(), $query);
			$path = array_merge($path, $query);
			$path['lang'] = $desiredLang;

			// Remember the desired language in the session
			$app->session->set('lang', $desiredLang);

			// Create a new response with a 301 redirect
			return Response::create(
				307,
				[
					'Location' => App::getLink($path),
				]
			);
		}

		return $handler->handle($request);
	}
}