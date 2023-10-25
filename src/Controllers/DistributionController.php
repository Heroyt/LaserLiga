<?php

namespace App\Controllers;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\Services\PlayerDistribution\DistributionParam;
use App\Services\PlayerDistribution\PlayerDistributionService;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use JsonException;
use Lsr\Core\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Helpers\Tools\Strings;
use Throwable;

class DistributionController extends Controller
{

	public function __construct(Latte $latte, private readonly PlayerDistributionService $playerDistributionService) {
		parent::__construct($latte);
	}

	/**
	 * @param string  $code
	 * @param int     $id
	 * @param string  $param
	 * @param Request $request
	 *
	 * @return never
	 * @throws GameModeNotFoundException
	 * @throws JsonException
	 * @throws Throwable
	 */
	public function distribution(string $code, int $id, string $param, Request $request): never {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			$this->respond(['error' => lang('Hra neexistuje')], 404);
		}
		$player = $game->getPlayers()->query()->filter('id', $id)->first();
		if (!isset($player)) {
			$this->respond(['error' => lang('Hráč neexistuje')], 404);
		}

		$enum = DistributionParam::tryFrom($param);
		if (!isset($enum)) {
			$this->respond(['error' => lang('Neznámý parametr')], 404);
		}

		[$min, $max, $step] = match ($enum) {
			DistributionParam::accuracy                        => [0, 100, 5],
			DistributionParam::score                           => [-1000, 10000, 500],
			DistributionParam::hits, DistributionParam::deaths => [0, 200, null],
			DistributionParam::shots                           => [0, 2000, null],
			default                                            => [null, null, null]
		};

		$query = $this->playerDistributionService->queryDistribution($enum, $min, $max, $step);

		if (isset($game->arena)) {
			$query->arena($game->arena);
		}

		if ($game->getMode()?->rankable) {
			$query->onlyRankable();
		}
		else if ($game->getMode() !== null) {
			$query->where('g.id_mode = %i', $game->getMode()->id);
		}

		$dates = $request->getGet('dates', 'all');

		switch ($dates) {
			case 'year':
				$from = new DateTimeImmutable($game->start?->format('Y-01-01') ?? date('Y-01-01'));
				$to = new DateTimeImmutable($game->start?->format('Y-12-31') ?? date('Y-12-31'));
				$query->dateBetween($from, $to);
				break;
			case 'month':
				$from = new DateTimeImmutable($game->start?->format('Y-m-1') ?? date('Y-m-1'));
				$to = new DateTimeImmutable($game->start?->format('Y-m-t') ?? date('Y-m-t'));
				$query->dateBetween($from, $to);
				break;
			case 'week':
				$day = (int)($game->start?->format('N') ?? date('N'));
				/** @var DateTimeImmutable $start */
				$start = $game->start instanceof DateTime ? DateTimeImmutable::createFromMutable(
					$game->start
				) : $game->start;
				$from = $start->sub(new DateInterval('P' . ($day - 1) . 'D'));
				$to = $start->add(new DateInterval('P' . (7 - $day) . 'D'));
				$query->dateBetween($from, $to);
				break;
			case 'day':
				$query->date($game->start ?? new DateTimeImmutable());
				break;
		}

		$playerParam = Strings::toCamelCase($param);
		$distribution = $query->get();
		$percentile = $query->getPercentile($player->$playerParam);
		$value = $player->$playerParam;

		// Normalize values
		if ($percentile === 100) {
			$percentile = 99;
		}
		else if ($percentile === 0) {
			$percentile = 1;
		}

		if ($value > $query->max) {
			$value = $query->max;
		}
		else if ($value < $query->min) {
			$value = $query->min;
		}

		$this->respond(
			[
				'player'       => $player,
				'distribution' => $distribution,
				'percentile'   => $percentile,
				'min'          => $query->min,
				'max'          => $query->max,
				'value'        => $value,
			]
		);
	}

}