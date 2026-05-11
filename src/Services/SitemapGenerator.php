<?php

namespace App\Services;

use App\GameModels\Factory\GameFactory;
use App\Models\Arena;
use App\Models\Auth\LigaPlayer;
use App\Models\Blog\Post;
use App\Models\Blog\Tag;
use App\Models\DataObjects\Game\MinimalGameRow;
use App\Models\Events\Event;
use App\Models\GameGroup;
use App\Models\Tournament\League\League;
use App\Models\Tournament\League\LeagueTeam;
use App\Models\Tournament\Tournament;
use Dibi\Exception;
use Iterator;
use Lsr\Core\App;
use Lsr\Core\Exceptions\InvalidLanguageException;
use Lsr\Core\Routing\Route;
use Lsr\Core\Routing\RouteParameter;
use Lsr\Core\Routing\Router;
use Lsr\Enums\RequestMethod;
use Lsr\Interfaces\RouteInterface;
use Lsr\Orm\Exceptions\ValidationException;
use RuntimeException;
use SimpleXMLElement;

class SitemapGenerator
{

	public const string SITEMAP_INDEX_FILE = ROOT . 'sitemap_index.xml';
	public const string SITEMAP_FILE       = ROOT . 'sitemap.xml';
	public const string SITEMAP_GAMES_FILE = ROOT . 'sitemap_games.xml';
	public const string SITEMAP_BLOG_FILE = ROOT . 'sitemap_blog.xml';
	public const string SITEMAP_USERS_FILE = ROOT . 'sitemap_users.xml';
	public const string SITEMAP_SEMRUSH_FILE = ROOT . 'sitemap_semrush.xml';

	private const int GAMES_LIMIT = 2000;
	private const int GROUPS_LIMIT = 50;

	public const array  IGNORE_PATHS      = [
		'.well-known',
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
		'lang',
		'google',
		'dropbox',
		'photos',
		'webhook',
		'dotaznik',
		'league',
		'manifest_kiosk.json',
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
	/** @var Post[] */
	private static array $posts;
	/** @var Tag[] */
	private static array $tags;

	public static function generate(): string {
		self::generateGamesSitemap();
		self::generateUsersSitemap();
		self::generateBlogSitemap();
		self::generateSitemap();
		self::generateSemrushSitemap();
		return self::generateIndex();
	}

	public static function generateSemrushSitemap() : string {
		$xml = new SimpleXMLElement(
			'<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:xhtml="http://www.w3.org/1999/xhtml"></urlset>'
		);

		self::generateSitemap($xml, false);
		self::generateBlogSitemap($xml, false);
		self::generateUsersSitemap($xml, false, 5);
		self::generateGamesSitemap($xml, false, 10, 5);

		$content = $xml->asXML();
		assert($content !== false);
		file_put_contents(self::SITEMAP_SEMRUSH_FILE, $content);
		return $content;
	}

	public static function generateGamesSitemap(?SimpleXMLElement $xml = null, bool $save = true, int $limit = self::GAMES_LIMIT, int $groupLimit = self::GROUPS_LIMIT): string {
		$xml ??= new SimpleXMLElement(
			'<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:xhtml="http://www.w3.org/1999/xhtml"></urlset>'
		);

		$routesRaw = Router::$availableRoutes;
		$routes = [];
		self::getRoutes($routesRaw, $routes);

		foreach ($routes as $route) {
			if (!($route instanceof Route) || $route->getMethod() !== RequestMethod::GET) {
				continue;
			}
			$path = array_values($route->getPath());

			if (preg_match('/\[lang(?:=[^\[]*)?\]/', $path[0])) {
				array_shift($path);
			}

			if (($path[0] ?? '') !== 'game') {
				continue;
			}

			if (($path[1] ?? '') === 'group' && ($path[2] ?? '') === '{groupid}' && count($path) === 3) {
				self::updateGameGroups($xml, $path, $groupLimit);
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
				self::updateGames($xml, $path, $limit);
			}
		}

		$content = $xml->asXML();
		assert($content !== false);
		if ($save) {
			file_put_contents(self::SITEMAP_GAMES_FILE, $content);
		}

		return $content;
	}

	public static function generateBlogSitemap(?SimpleXMLElement $xml = null, bool $save = true): string {
		$xml ??= new SimpleXMLElement(
			'<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:xhtml="http://www.w3.org/1999/xhtml"></urlset>'
		);

		$routesRaw = Router::$availableRoutes;
		$routes = [];
		self::getRoutes($routesRaw, $routes);

		foreach ($routes as $route) {
			if (!($route instanceof Route) || $route->getMethod() !== RequestMethod::GET) {
				continue;
			}
			$path = array_values($route->getPath());

			if (preg_match('/\[lang(?:=[^\[]*)?\]/', $path[0])) {
				array_shift($path);
			}

			if (($path[0] ?? '') !== 'blog' || (($path[1] ?? '') === 'admin')) {
				continue;
			}

			if (($path[1] ?? '') === 'post' && ($path[2] ?? '') === '{slug}' && count($path) === 3) {
				self::updateBlogPosts($xml, $path);
				continue;
			}

			if (($path[1] ?? '') === 'tag' && ($path[2] ?? '') === '{slug}' && count($path) === 3) {
				self::updateBlogTags($xml, $path);
				continue;
			}

			try {
				$elem = self::findOrCreateUrl($path, $xml);
				self::updateUrl($elem);
			} catch (InvalidLanguageException) {

			}
		}

		$content = $xml->asXML();
		assert($content !== false);
		if ($save) {
			file_put_contents(self::SITEMAP_BLOG_FILE, $content);
		}

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
			elseif ($value instanceof RouteParameter) {
				self::getRoutes($value->routes, $out);
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
	private static function updateGameGroups(SimpleXMLElement $parent, array $path, int $limit = self::GROUPS_LIMIT): void {
		self::$groups ??= GameGroup::query()->orderBy('created_at')->desc()->get();

		$count = 0;
		foreach (self::$groups as $group) {
			$path[2] = $group->encodedId;
			$element = self::findOrCreateUrl($path, $parent);
			self::updateUrl($element, count($path) > 3 ? '0.6' : '0.8', lastMod: $group->getLastDate()?->format('Y-m-d'));
			if (++$count >= $limit) {
				break;
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
	private static function updateBlogPosts(SimpleXMLElement $parent, array $path): void {
		self::$posts ??= Post::getAll();

		foreach (self::$posts as $post) {
			$path[2] = $post->slug;
			$element = self::findOrCreateUrl($path, $parent, false);

			$translations = App::getInstance()->translations;
			foreach ($translations->supportedLanguages as $lang => $country) {
				$translatedSlug = $post->getTranslatedSlug($lang);
				if ($translatedSlug !== $post->slug) { // Translation exists
					$path['lang'] = $lang;
					$path[2] = $translatedSlug;
					$alt = $element->addChild('link', App::getLink($path), 'http://www.w3.org/1999/xhtml');
					assert($alt !== null);
					$alt->addAttribute('rel', 'alternate');
					$alt->addAttribute('hreflang', $lang . '_' . $country);
				}
			}

			self::updateUrl($element, '1.0', 'yearly', $post->updatedAt?->format('Y-m-d'));
		}
	}

	/**
	 * @param SimpleXMLElement $parent
	 * @param string[]         $path
	 *
	 * @return void
	 * @throws ValidationException
	 */
	private static function updateBlogTags(SimpleXMLElement $parent, array $path): void {
		self::$tags ??= Tag::getAll();

		foreach (self::$tags as $post) {
			$path[2] = $post->slug;
			$element = self::findOrCreateUrl($path, $parent);
			self::updateUrl($element, '0.9', 'monthly');
		}
	}

	/**
	 * @param array<string|int, string|numeric> $path
	 * @param SimpleXMLElement                  $parent
	 * @param bool                              $includeLang
	 *
	 * @return SimpleXMLElement
	 * @throws InvalidLanguageException
	 */
	private static function findOrCreateUrl(array $path, SimpleXMLElement $parent, bool $includeLang = true): SimpleXMLElement {
		$url = App::getLink($path);
		$new = $parent->addChild('url');
		assert($new !== null);
		$new->addChild('loc', $url);
		$new->addChild('lastmod', date('Y-m-d'));
		if ($includeLang) {
			$translations = App::getInstance()->translations;
			foreach ($translations->supportedLanguages as $lang => $country) {
				$path['lang'] = $lang;
				$alt = $new->addChild('link', App::getLink($path), 'http://www.w3.org/1999/xhtml');
				assert($alt !== null);
				$alt->addAttribute('rel', 'alternate');
				$alt->addAttribute('hreflang', $lang . '_' . $country);
			}
		}
		return $new;
	}

	private static function updateUrl(SimpleXMLElement $element, string $priority = '1.0', string $changeFreq = 'monthly', ?string $lastMod = null, bool $includeLang = true): void {
		$hasModifiedAt = $hasPriority = $hasChangeFrequency = false;
		$lastMod ??= date('Y-m-d');
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
	private static function updateGames(SimpleXMLElement $parent, array $path, int $limit = self::GAMES_LIMIT): void {
		$i = 0;
		foreach (self::getLastGames() as $row) {
			$path[1] = $row->code;
			$element = self::findOrCreateUrl($path, $parent);
			self::updateUrl($element, '0.6', 'never', $row->end->format('Y-m-d'));

			$imgPath = $path;
			$imgPath[2] = 'thumb.png';
			self::addImage($element, App::getLink($imgPath));

			++$i;
			if ($i > $limit) {
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

	public static function generateUsersSitemap(?SimpleXMLElement $xml = null, bool $save = true, ?int $limit = null): string {
		$xml ??= new SimpleXMLElement(
			'<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:xhtml="http://www.w3.org/1999/xhtml"></urlset>'
		);

		$routesRaw = Router::$availableRoutes;
		$routes = [];
		self::getRoutes($routesRaw, $routes);

		foreach ($routes as $route) {
			if (!($route instanceof Route) || $route->getMethod() !== RequestMethod::GET) {
				continue;
			}
			$path = array_values($route->getPath());

			if (preg_match('/\[lang(?:=[^\[]*)?\]/', $path[0])) {
				array_shift($path);
			}

			if (
				($path[0] ?? '') !== 'user'
				|| ($path[1] ?? '') !== '{code}'
				|| in_array($path[2] ?? '', self::USER_IGNORE_PATHS, true)
			) {
				continue;
			}
			self::updateUsers($xml, $path, $limit);
		}

		$content = $xml->asXML();
		assert($content !== false);
		if ($save) {
			file_put_contents(self::SITEMAP_USERS_FILE, $content);
		}

		return $content;
	}

	/**
	 * @param SimpleXMLElement $parent
	 * @param string[]         $path
	 *
	 * @return void
	 * @throws ValidationException
	 */
	private static function updateUsers(SimpleXMLElement $parent, array $path, ?int $limit = null): void {
		self::$users ??= LigaPlayer::getAll();

		$count = 0;
		foreach (self::$users as $player) {
			$path[1] = $player->getCode();
			$element = self::findOrCreateUrl($path, $parent);
			self::updateUrl($element, count($path) > 3 ? '0.6' : '0.8');
			if (count($path) === 2) {
				$imgPath = $path;
				$imgPath[2] = 'img.png';
				self::addImage($element, App::getLink($imgPath));
			}
			if ($limit !== null && ++$count > $limit) {
				break;
			}
		}
	}

	public static function generateSitemap(?SimpleXMLElement $xml = null, bool $save = true): string {
		$xml ??= new SimpleXMLElement(
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

			if (preg_match('/\[lang(?:=[^\[]*)?\]/', $path[0])) {
				array_shift($path);
			}

//			echo json_encode($path).PHP_EOL;
			if (in_array($path[0] ?? '', self::IGNORE_PATHS, true)) {
				continue;
			}
			switch ($path[0] ?? '') {
				case 'game':
				case 'blog':
					break;
				case 'arena':
					if (!isset($path[1])) {
						self::updateUrl(self::findOrCreateUrl(['arena'], $xml), '0.9');
						break;
					}
					if ($path[1] === '{id}' && !isset($path[2])) {
						self::updateArenas($xml, $path);
					}
					break;
				case 'tournament':
					if (!isset($path[1])) {
						self::updateUrl(self::findOrCreateUrl(['tournament'], $xml), '0.9');
						break;
					}
					if (isset($path[2]) && $path[2] === 'register') {
						break; // Skip
					}
					if ($path[1] === '{id}') {
						self::updateTournaments($xml, $path);
					}
					break;
				case 'events':
					if (isset($path[2]) && in_array($path[2], ['substitute', 'register'], true)) {
						break;
					}
					if (!isset($path[1])) {
						self::updateUrl(self::findOrCreateUrl(['events'], $xml), '0.9');
						break;
					}
					if ($path[1] === '{id}') {
						self::updateEvents($xml, $path);
					}
					break;
//				case 'league':
//					if (isset($path[2]) && in_array($path[2], ['team', 'substitute', 'register'], true)) {
//						break;
//					}
//					if (!isset($path[1])) {
//						self::updateUrl(self::findOrCreateUrl(['league'], $xml), '0.9');
//						break;
//					}
//					if ($path[1] === '{id}') {
//						self::updateLeagues($xml, $path);
//					}
//					break;
				case 'liga':
					if (isset($path[2]) && in_array($path[2], ['team', 'substitute', 'register'], true)) {
						break;
					}
					if (!isset($path[1])) {
						self::updateUrl(self::findOrCreateUrl(['liga'], $xml), '0.9');
						break;
					}
					if ($path[1] === '{slug}') {
						self::updateLeaguesSlugs($xml, $path);
					}
					break;
				case 'user':
					if (!isset($path[1]) || $path[1] === '{code}') {
						break;
					}

					if ($path[1] === 'leaderboard') {
						if (isset($path[2]) && in_array($path[2], ['{arenaid}', '{arenaslug}'], true)) {
							if ($path[2] === '{arenaid}') {
								break;
							}
							$arenas = Arena::getAll();
							foreach ($arenas as $arena) {
//								if ($path[2] === '{arenaid}') {
//									assert($arena->id !== null);
//									$path[2] = (string)$arena->id;
//								}
//								elseif ($path[2] === '{arenaslug}') {
									$path[2] = $arena->slug;
//								}
								$elem = self::findOrCreateUrl($path, $xml);
								self::updateUrl($elem);
							}
							break;
						}
						$elem = self::findOrCreateUrl($path, $xml);
						self::updateUrl($elem);
					}
					break;
				case 'login':
					if (count($path) > 2 || ($path[1] ?? '') === 'confirm') {
						break;
					}
				default:
					try {
						$elem = self::findOrCreateUrl($path, $xml);
						self::updateUrl($elem);
					} catch (RuntimeException) {
					}
					break;
			}
		}

		$content = $xml->asXML();
		assert($content !== false);
		if ($save) {
			file_put_contents(self::SITEMAP_FILE, $content);
		}

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
			$path[1] = $arena->slug;
			$element = self::findOrCreateUrl($path, $parent);
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
			$element = self::findOrCreateUrl($path, $parent);
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
			$element = self::findOrCreateUrl($path, $parent);
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
//			assert($league->id !== null);
//			$path[1] = (string)$league->id;
//			$url = App::getLink($path);
//			$element = self::findOrCreateUrl($url, $parent);
//			self::updateUrl($element, '0.8');

			$teams = LeagueTeam::query()->where('id_league = %i', $league->id)->get();
			$path[1] = 'team';
			foreach ($teams as $team) {
				assert($team->id !== null);
				$path[2] = (string)$team->id;
				$element = self::findOrCreateUrl($path, $parent);
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
			$element = self::findOrCreateUrl($path, $parent);
			self::updateUrl($element, '0.8');

			$teams = LeagueTeam::query()->where('id_league = %i', $league->id)->get();
			$path[1] = 'team';
			foreach ($teams as $team) {
				assert($team->id !== null);
				$path[2] = (string)$team->id;
				$element = self::findOrCreateUrl($path, $parent);
				self::updateUrl($element, '0.8');
			}
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
		$new = $xmlIndex->addChild('sitemap');
		assert($new !== null);
		$new->addChild('loc', str_replace(ROOT, $baseUrl, self::SITEMAP_BLOG_FILE));

		$content = $xmlIndex->asXML();
		assert($content !== false);
		file_put_contents(self::SITEMAP_INDEX_FILE, $content);
		return $content;
	}

}