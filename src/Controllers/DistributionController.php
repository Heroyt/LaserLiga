<?php

namespace App\Controllers;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Player;
use App\Services\PlayerDistribution\DistributionParam;
use App\Services\PlayerDistribution\PlayerDistributionService;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Helpers\Tools\Strings;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class DistributionController extends Controller
{

	public function __construct(
		private readonly PlayerDistributionService $playerDistributionService
	) {
		parent::__construct();
	}

	/**
	 * @param string  $code
	 * @param int     $id
	 * @param string  $param
	 * @param Request $request
	 *
	 * @return ResponseInterface
	 * @throws GameModeNotFoundException
	 * @throws Throwable
	 */
	public function distribution(string $code, int $id, string $param, Request $request): ResponseInterface {
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			return $this->respond(new ErrorResponse(lang('Hra neexistuje'), ErrorType::VALIDATION), 400);
		}
		/** @var Player|null $player */
		$player = $game->players->query()->filter('id', $id)->first();
		if (!isset($player)) {
			return $this->respond(new ErrorResponse(lang('Hráč neexistuje'), ErrorType::VALIDATION), 400);
		}

		$enum = DistributionParam::tryFrom($param);
		if (!isset($enum)) {
			return $this->respond(new ErrorResponse(lang('Neznámý parametr'), ErrorType::VALIDATION), 400);
		}

		[$min, $max, $step] = match ($enum) {
			DistributionParam::accuracy                        => [0, 100, 5],
			DistributionParam::score                           => [-1000, 10000, 500],
			DistributionParam::hits, DistributionParam::deaths => [0, 200, null],
			DistributionParam::shots                           => [0, 2000, null],
			DistributionParam::rank                            => [0, 1500, null],
			DistributionParam::kd                              => [0, 10, null],
		};


		$playerParam = Strings::toCamelCase($enum->getPlayersColumnName());
		$value = match ($enum) {
			DistributionParam::kd   => $player->getKd(),
			DistributionParam::rank => $player->getSkill(),
			default                 => $player->$playerParam ?? 0,
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
		$distribution = $query->get();
		$percentile = $query->getPercentile($value);

		$valueReal = $value;
		if ($value > $query->max) {
			$value = $query->max;
		}
		else if ($value < $query->min) {
			$value = $query->min;
		}

		// TODO: Replace with DTO
		return $this->respond(
			[
				'player'       => $player,
				'distribution' => $distribution,
				'percentile'   => $percentile,
				'min'          => $query->min,
				'max'          => $query->max,
				'value'        => $value,
				'valueReal'        => $valueReal,
				'playerParam'  => $playerParam,
			]
		);
	}

}