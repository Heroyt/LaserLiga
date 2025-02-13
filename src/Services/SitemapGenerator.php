<?php

namespace App\Services;

use App\GameModels\Factory\GameFactory;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\DataObjects\Game\MinimalGameRow;
use App\Models\Events\Event;
use App\Models\GameGroup;
use App\Models\Tournament\League\League;
use App\Models\Tournament\League\LeagueTeam;
use App\Models\Tournament\Tournament;
use Dibi\Exception;
use Iterator;
use Lsr\Core\App;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Routing\Route;
use Lsr\Core\Routing\Router;
use Lsr\Enums\RequestMethod;
use Lsr\Interfaces\RouteInterface;
use RuntimeException;
use SimpleXMLElement;

class SitemapGenerator
{

	public const string SITEMAP_INDEX_FILE = ROOT . 'sitemap_index.xml';
	public const string SITEMAP_FILE       = ROOT . 'sitemap.xml';
	public const string SITEMAP_GAMES_FILE = ROOT . 'sitemap_games.xml';
	public const string SITEMAP_USERS_FILE = ROOT . 'sitemap_users.xml';

	private const int GAMES_LIMIT = 5000;

	public const array  IGNORE_PATHS      = [
		'logout',
		'admin',
		'kiosk',
		'api',
		'g',
		'player',
		'players',
		'questionnaire',
		'push',
		'mailtest',
		'dashboard',
	];
	public const array  USER_IGNORE_PATHS = ['stats', 'rank', 'img', 'img.png', 'compare', 'avatar', 'title'];

	/**
	 * @var Arena[]
	 */
	private static array $arenas;
	/**
	 * @var Tournament[]
	 */
	private static array $tournaments;
	/**
	 * @var League[]
	 */
	private static array $leagues;
	/**
	 * @var LigaPlayer[]
	 */
	private static array $users;
	/** @var GameGroup[] */
	private static array $groups;
	/**
	 * @var Event[]
	 */
	private static array $events;

	public static function generate(): string {
		self::generateGamesSitemap();
		self::generateUsersSitemap();
		self::generateSitemap();
		return self::generateIndex();
	}

	public static function generateGamesSitemap(): string {
		$xmlGames = new SimpleXMLElement(
			'<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"></urlset>'
		);

		$routesRaw = Router::$availableRoutes;
		$routes = [];
		self::getRoutes($routesRaw, $routes);

		foreach ($routes as $route) {
			if (!($route instanceof Route) || $route->getMethod() !== RequestMethod::GET) {
				continue;
			}
			$path = array_values($route->getPath());
			if (($path[0] ?? '') !== 'game') {
				continue;
			}

			if (($path[1] ?? '') === 'group' && ($path[2] ?? '') === '{groupid}' && count($path) === 3) {
				self::updateGameGroups($xmlGames, $path);
				continue;
			}

			if (count($path) > 2) {
				continue;
			}

			if (
				($path[1] ?? '') === '{code}'
				&& (
					!isset($path[2])
					|| !in_array($path[2], ['thumb', 'highlights', 'thumb.png'], true)
				)
			) {
				self::updateGames($xmlGames, $path);
			}
		}

		$content = $xmlGames->asXML();
		assert($content !== false);
		file_put_contents(self::SITEMAP_GAMES_FILE, $content);

		return $content;
	}

	/**
	 * @param array<RouteInterface|RouteInterface[]> $routes
	 * @param RouteInterface[]                       $out
	 *
	 * @return void
	 */
	private static function getRoutes(array $routes, array &$out): void {
		foreach ($routes as $value) {
			if (is_array($value)) {
				self::getRoutes($value, $out);
			}
			else {
				$out[] = $value;
			}
		}
	}

	/**
	 * @param SimpleXMLElement $parent
	 * @param string[]         $path
	 *
	 * @return void
	 * @throws ValidationException
	 */
	private static function updateGameGroups(SimpleXMLElement $parent, array $path): void {
		self::$groups ??= GameGroup::getAll();

		foreach (self::$groups as $group) {
			$path[2] = $group->getEncodedId();
			$url = App::getLink($path);
			$element = self::findOrCreateUrl($url, $parent);
			self::updateUrl($element, count($path) > 3 ? '0.6' : '0.8');
		}
	}

	private static function findOrCreateUrl(string $url, SimpleXMLElement $parent): SimpleXMLElement {
		/*foreach ($parent->children() as $child) {
			foreach ($child->children() as $name => $value) {
				if ($name === 'loc' && ((string)$value) === $url) {
					return $child;
				}
			}
		}*/

		$new = $parent->addChild('url');
		assert($new !== null);
		$new->addChild('loc', $url);
		$new->addChild('lastmod', date('c'));
		return $new;
	}

	private static function updateUrl(SimpleXMLElement $element, string $priority = '1.0', string $changeFreq = 'monthly', ?string $lastMod = null): void {
		$hasModifiedAt = $hasPriority = $hasChangeFrequency = false;
		$lastMod ??= date('c');
		foreach ($element->children() as $name => $child) {
			switch ($name) {
				case 'lastmod':
					$element->lastmod = $lastMod;
					$hasModifiedAt = true;
					break;
				case 'priority':
					$element->priority = $priority;
					$hasPriority = true;
					break;
				case 'changefreq':
					$element->changefreq = $changeFreq;
					$hasChangeFrequency = true;
					break;
			}
		}
		if (!$hasModifiedAt) {
			$element->addChild('modifiedAt', $lastMod);
		}
		if (!$hasPriority) {
			$element->addChild('priority', $priority);
		}
		if (!$hasChangeFrequency) {
			$element->addChild('changefreq', $changeFreq);
		}
	}

	/**
	 * @param SimpleXMLElement $parent
	 * @param string[]         $path
	 *
	 * @return void
	 * @throws Exception
	 */
	private static function updateGames(SimpleXMLElement $parent, array $path): void {
		$i = 0;
		foreach (self::getLastGames() as $row) {
			$path[1] = $row->code;
			$url = App::getLink($path);
			$element = self::findOrCreateUrl($url, $parent);
			self::updateUrl($element, '0.6', 'never', $row->end->format('c'));

			$imgPath = $path;
			$imgPath[2] = 'thumb.png';
			self::addImage($element, App::getLink($imgPath));

			++$i;
			if ($i > self::GAMES_LIMIT) {
				break;
			}
		}
	}

	/**
	 * @return Iterator<MinimalGameRow>
	 * @throws Exception
	 */
	private static function getLastGames(): Iterator {
		return GameFactory::queryGames()
		                  ->orderBy('start')
		                  ->desc()
		                  ->limit(self::GAMES_LIMIT)
		                  ->fetchIteratorDto(MinimalGameRow::class);
	}

	private static function addImage(SimpleXMLElement $parent, string $url): void {
		$img = $parent->addChild('image', namespace: 'http://www.google.com/schemas/sitemap-image/1.1');
		assert($img !== null);
		$img->addChild('loc', $url, 'http://www.google.com/schemas/sitemap-image/1.1');
	}

	public static function generateUsersSitemap(): string {
		$xmlUsers = new SimpleXMLElement(
			'<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"></urlset>'
		);

		$routesRaw = Router::$availableRoutes;
		$routes = [];
		self::getRoutes($routesRaw, $routes);

		foreach ($routes as $route) {
			if (!($route instanceof Route) || $route->getMethod() !== RequestMethod::GET) {
				continue;
			}
			$path = array_values($route->getPath());
			if (
				$path[0] !== 'user'
				|| $path[1] !== '{code}'
				|| in_array($path[2] ?? '', self::USER_IGNORE_PATHS, true)
			) {
				continue;
			}
			self::updateUsers($xmlUsers, $path);
		}

		$content = $xmlUsers->asXML();
		assert($content !== false);
		file_put_contents(self::SITEMAP_USERS_FILE, $content);

		return $content;
	}

	/**
	 * @param SimpleXMLElement $parent
	 * @param string[]         $path
	 *
	 * @return void
	 * @throws ValidationException
	 */
	private static function updateUsers(SimpleXMLElement $parent, array $path): void {
		self::$users ??= LigaPlayer::getAll();

		foreach (self::$users as $player) {
			$path[1] = $player->getCode();
			$url = App::getLink($path);
			$element = self::findOrCreateUrl($url, $parent);
			self::updateUrl($element, count($path) > 3 ? '0.6' : '0.8');
			if (count($path) === 2) {
				$imgPath = $path;
				$imgPath[2] = 'img.png';
				self::addImage($element, App::getLink($imgPath));
			}
		}
	}

	public static function generateSitemap(): string {
		$xml = new SimpleXMLElement(
			'<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>'
		);

		$routesRaw = Router::$availableRoutes;
		$routes = [];
		self::getRoutes($routesRaw, $routes);

		foreach ($routes as $route) {
			if (!($route instanceof Route) || $route->getMethod() !== RequestMethod::GET) {
				continue;
			}
			$path = array_values($route->getPath());
			//echo json_encode($path).PHP_EOL;
			if (in_array($path[0], self::IGNORE_PATHS, true)) {
				continue;
			}
			switch ($path[0]) {
				case 'game':
					break;
				case 'arena':
					if (!isset($path[1])) {
						self::updateUrl(self::findOrCreateUrl(App::getLink(['arena']), $xml), '0.9');
						break;
					}
					if ($path[1] === '{id}' && !isset($path[2])) {
						self::updateArenas($xml, $path);
					}
					break;
				case 'tournament':
					if (!isset($path[1])) {
						self::updateUrl(self::findOrCreateUrl(App::getLink(['tournament']), $xml), '0.9');
						break;
					}
					if ($path[1] === '{id}') {
						self::updateTournaments($xml, $path);
					}
					break;
				case 'events':
					if (!isset($path[1])) {
						self::updateUrl(self::findOrCreateUrl(App::getLink(['events']), $xml), '0.9');
						break;
					}
					if ($path[1] === '{id}') {
						self::updateEvents($xml, $path);
					}
					break;
				case 'league':
					if (isset($path[2]) && $path[2] === 'team') {
						break;
					}
					if (!isset($path[1])) {
						self::updateUrl(self::findOrCreateUrl(App::getLink(['league']), $xml), '0.9');
						break;
					}
					if ($path[1] === '{id}') {
						self::updateLeagues($xml, $path);
					}
					break;
				case 'liga':
					if (!isset($path[1])) {
						self::updateUrl(self::findOrCreateUrl(App::getLink(['liga']), $xml), '0.9');
						break;
					}
					if ($path[1] === '{slug}') {
						self::updateLeaguesSlugs($xml, $path);
					}
					break;
				case 'user':
					if ($path[1] === '{code}') {
						break;
					}

					if ($path[1] === 'leaderboard') {
						if (isset($path[2]) && $path[2] === '{arenaId}') {
							$arenas = Arena::getAll();
							foreach ($arenas as $arena) {
								assert($arena->id !== null);
								$path[2] = (string)$arena->id;
								$elem = self::findOrCreateUrl(App::getLink($path), $xml);
								self::updateUrl($elem);
							}
							break;
						}
						$elem = self::findOrCreateUrl(App::getLink($path), $xml);
						self::updateUrl($elem);
					}
					break;
				case 'login':
					if (count($path) > 2) {
						break;
					}
				default:
					try {
						$elem = self::findOrCreateUrl(App::getLink($path), $xml);
						self::updateUrl($elem);
					} catch (RuntimeException) {
					}
					break;
			}
		}

		$content = $xml->asXML();
		assert($content !== false);
		file_put_contents(self::SITEMAP_FILE, $content);

		return $content;
	}

	/**
	 * @param SimpleXMLElement $parent
	 * @param string[]         $path
	 *
	 * @return void
	 * @throws ValidationException
	 */
	private static function updateArenas(SimpleXMLElement $parent, array $path): void {
		self::$arenas ??= Arena::getAll();

		foreach (self::$arenas as $arena) {
			assert($arena->id !== null);
			$path[1] = (string)$arena->id;
			$url = App::getLink($path);
			$element = self::findOrCreateUrl($url, $parent);
			self::updateUrl($element, '0.8', 'daily');
		}
	}

	/**
	 * @param SimpleXMLElement $parent
	 * @param string[]         $path
	 *
	 * @return void
	 * @throws ValidationException
	 */
	private static function updateTournaments(SimpleXMLElement $parent, array $path): void {
		self::$tournaments ??= Tournament::getAll();

		foreach (self::$tournaments as $tournament) {
			assert($tournament->id !== null);
			$path[1] = (string)$tournament->id;
			$url = App::getLink($path);
			$element = self::findOrCreateUrl($url, $parent);
			self::updateUrl($element, '0.8', 'weekly');
		}
	}

	/**
	 * @param SimpleXMLElement $parent
	 * @param string[]         $path
	 *
	 * @return void
	 * @throws ValidationException
	 */
	private static function updateEvents(SimpleXMLElement $parent, array $path): void {
		self::$events ??= Event::getAll();

		foreach (self::$events as $event) {
			assert($event->id !== null);
			$path[1] = (string)$event->id;
			$url = App::getLink($path);
			$element = self::findOrCreateUrl($url, $parent);
			self::updateUrl($element, '0.8', 'weekly');
		}
	}

	/**
	 * @param SimpleXMLElement $parent
	 * @param string[]         $path
	 *
	 * @return void
	 * @throws ValidationException
	 */
	private static function updateLeagues(SimpleXMLElement $parent, array $path): void {
		self::$leagues ??= League::getAll();

		foreach (self::$leagues as $league) {
			assert($league->id !== null);
			$path[1] = (string)$league->id;
			$url = App::getLink($path);
			$element = self::findOrCreateUrl($url, $parent);
			self::updateUrl($element, '0.8');

			$teams = LeagueTeam::query()->where('id_league = %i', $league->id)->get();
			$path[2] = 'team';
			foreach ($teams as $team) {
				assert($team->id !== null);
				$path[3] = (string)$team->id;
				$url = App::getLink($path);
				$element = self::findOrCreateUrl($url, $parent);
				self::updateUrl($element, '0.8');
			}
		}
	}

	/**
	 * @param SimpleXMLElement $parent
	 * @param string[]         $path
	 *
	 * @return void
	 * @throws ValidationException
	 */
	private static function updateLeaguesSlugs(SimpleXMLElement $parent, array $path): void {
		self::$leagues ??= League::getAll();

		foreach (self::$leagues as $league) {
			if (!isset($league->slug)) {
				continue;
			}
			$path[1] = $league->slug;
			$url = App::getLink($path);
			$element = self::findOrCreateUrl($url, $parent);
			self::updateUrl($element, '0.8');
		}
	}

	public static function generateIndex(): string {
		$xmlIndex = new SimpleXMLElement(
			'<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>'
		);

		$baseUrl = App::getInstance()->getBaseUrl();

		$new = $xmlIndex->addChild('sitemap');
		assert($new !== null);
		$new->addChild('loc', str_replace(ROOT, $baseUrl, self::SITEMAP_FILE));
		$new = $xmlIndex->addChild('sitemap');
		assert($new !== null);
		$new->addChild('loc', str_replace(ROOT, $baseUrl, self::SITEMAP_GAMES_FILE));
		$new = $xmlIndex->addChild('sitemap');
		assert($new !== null);
		$new->addChild('loc', str_replace(ROOT, $baseUrl, self::SITEMAP_USERS_FILE));

		$content = $xmlIndex->asXML();
		assert($content !== false);
		file_put_contents(self::SITEMAP_INDEX_FILE, $content);
		return $content;
	}

}