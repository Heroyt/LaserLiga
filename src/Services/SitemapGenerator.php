<?php

namespace App\Services;

use App\GameModels\Factory\GameFactory;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\Events\Event;
use App\Models\GameGroup;
use App\Models\Tournament\League\League;
use App\Models\Tournament\League\LeagueTeam;
use App\Models\Tournament\Tournament;
use Lsr\Core\App;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Routing\Route;
use Lsr\Core\Routing\Router;
use Lsr\Enums\RequestMethod;
use Lsr\Interfaces\RouteInterface;
use SimpleXMLElement;

class SitemapGenerator
{

	public const SITEMAP_FILE = ROOT . 'sitemap.xml';
	public const IGNORE_PATHS = [
		'logout',
		'admin',
		'api',
		'g',
		'player',
		'players',
		'questionnaire',
		'push',
		'mailtest',
		'dashboard',
	];
	public const USER_IGNORE_PATHS = ['stats', 'rank', 'img', 'compare', 'avatar', 'title'];

	/** @var string[] */
	private static array $lastGames = [];
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
					if (($path[1] ?? '') === 'group' && ($path[2] ?? '') === '{groupid}' && count($path) === 3) {
						self::updateGameGroups($xml, $path);
						break;
					}
					if (count($path) > 2) {
						break;
					}
					if (($path[1] ?? '') === '{code}' && (!isset($path[2]) || !in_array(
								$path[2],
								['thumb', 'highlights'],
								true
							))) {
						self::updateGames($xml, $path);
					}
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
						if (in_array($path[2] ?? '', self::USER_IGNORE_PATHS, true)) {
							break;
						}
						self::updateUsers($xml, $path);
					}
					else if ($path[1] === 'leaderboard') {
						if (isset($path[2]) && $path[2] === '{arenaId}') {
							$arenas = Arena::getAll();
							foreach ($arenas as $arena) {
								$path[2] = $arena->id;
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
					} catch (\RuntimeException) {
					}
					break;
			}
		}

		$content = $xml->asXML();
		file_put_contents(self::SITEMAP_FILE, $content);
		return $content;
	}

	private static function getRoutes(array $routes, array &$out): void {
		foreach ($routes as $value) {
			if (is_array($value)) {
				self::getRoutes($value, $out);
			}
			else if ($value instanceof RouteInterface) {
				$out[] = $value;
			}
		}
	}

	/**
	 * @param SimpleXMLElement $parent
	 * @param array $path
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
		$new->addChild('loc', $url);
		$new->addChild('lastmod', date('c'));
		return $new;
	}

	private static function updateUrl(SimpleXMLElement $element, string $priority = '1.0', string $changeFreq = 'monthly'): void {
		$hasModifiedAt = $hasPriority = $hasChangeFrequency = false;
		foreach ($element->children() as $name => $child) {
			switch ($name) {
				case 'lastmod':
					$element->lastmod = date('c');
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
			$element->addChild('modifiedAt', date('c'));
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
	 */
	private static function updateGames(SimpleXMLElement $parent, array $path): void {
		$lastGames = self::getLastGames();

		foreach ($lastGames as $code) {
			$path[1] = $code;
			$url = App::getLink($path);
			$element = self::findOrCreateUrl($url, $parent);
			self::updateUrl($element, '0.6', 'never');
		}
	}

	/**
	 * @return string[]
	 */
	private static function getLastGames(): array {
		if (empty(self::$lastGames)) {
			$rows = GameFactory::queryGames()->orderBy('start')->desc()->limit(200)->fetchAll();
			self::$lastGames = [];
			foreach ($rows as $row) {
				self::$lastGames[] = $row->code;
			}
		}
		return self::$lastGames;
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
			$path[1] = $arena->id;
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
			$path[1] = $tournament->id;
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
			$path[1] = $event->id;
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
			$path[1] = $league->id;
			$url = App::getLink($path);
			$element = self::findOrCreateUrl($url, $parent);
			self::updateUrl($element, '0.8');

			$teams = LeagueTeam::query()->where('id_league = %i', $league->id)->get();
			$path[2] = 'team';
			foreach ($teams as $team) {
				$path[3] = $team->id;
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
		}
	}

}