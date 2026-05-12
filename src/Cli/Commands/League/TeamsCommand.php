<?php
declare(strict_types=1);

namespace App\Cli\Commands\League;

use App\Exceptions\ModelSaveFailedException;
use App\GameModels\Game\Evo5\Player as Evo5Player;
use App\GameModels\Game\Evo6\Player as Evo6Player;
use App\Models\DataObjects\Event\PlayerRegistrationDTO;
use App\Models\DataObjects\Event\TeamRegistrationDTO;
use App\Models\Events\EventPlayer;
use App\Models\Tournament\League\League;
use App\Models\Tournament\League\LeagueCategory;
use App\Models\Tournament\League\LeagueTeam;
use App\Models\Tournament\League\Player as LeaguePlayer;
use App\Models\Tournament\Player as TournamentPlayer;
use App\Models\Tournament\Team;
use App\Models\Tournament\Tournament;
use App\Services\EventRegistrationService;
use Lsr\Db\DB;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class TeamsCommand extends Command
{
	use LeagueCommand;

	public function __construct(
		private readonly EventRegistrationService $eventRegistrationService,
	) {
		parent::__construct();
	}

	public static function getDefaultName(): string {
		return 'league:teams';
	}

	public static function getDefaultDescription(): string {
		return 'Interactively manage league teams and tournament registrations.';
	}

	protected function configure(): void {
		$this->addArgument('league', InputArgument::OPTIONAL, 'League ID or slug');
		$this->addArgument('category', InputArgument::OPTIONAL, 'League category ID, slug or name');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$helper = $this->getHelper('question');
		assert($helper instanceof QuestionHelper);

		$league = $this->resolveLeague($input, $output, $helper);
		if (!isset($league)) {
			return self::FAILURE;
		}

		$category = $this->resolveCategory($league, $input, $output, $helper);
		if (!isset($category)) {
			return self::FAILURE;
		}

		$output->writeln(sprintf(
			'<info>League:</info> %s (#%d), <info>category:</info> %s (#%d)',
			$league->name,
			$league->id,
			$category->name,
			$category->id,
		));

		do {
			$this->renderTeams($category, $output);
			$action = $helper->ask(
				$input,
				$output,
				new ChoiceQuestion(
					'Choose action',
					[
						'list'        => 'List teams',
						'toggle'      => 'Disqualify / requalify team',
						'reregister'  => 'Re-register team for tournament',
						'deduplicate' => 'Deduplicate league/tournament players',
						'quit'        => 'Quit',
					],
					'list'
				)
			);
			$actionKey = $this->normalizeAction((string)$action);

			$output->writeln('Chosen action: '.$action, $output::VERBOSITY_VERBOSE);

			if ($actionKey === 'toggle') {
				$this->toggleDisqualification($category, $input, $output, $helper);
				$category = $this->reloadCategory($category);
				continue;
			}

			if ($actionKey === 'reregister') {
				$this->reregisterTeam($category, $input, $output, $helper);
				$category = $this->reloadCategory($category);
				continue;
			}

			if ($actionKey === 'deduplicate') {
				$this->deduplicatePlayers($category, $input, $output, $helper);
				$category = $this->reloadCategory($category);
				continue;
			}
		} while ($actionKey !== 'quit');

		return self::SUCCESS;
	}

	private function resolveLeague(InputInterface $input, OutputInterface $output, QuestionHelper $helper): ?League {
		$leagueId = $input->getArgument('league');
		if (is_string($leagueId) && $leagueId !== '') {
			$league = $this->getLeague($leagueId);
			if (!isset($league)) {
				$output->writeln('<error>League not found</error>');
			}
			return $league;
		}

		$leagues = League::query()->orderBy('id_league')->desc()->get();
		if ($leagues === []) {
			$output->writeln('<error>No leagues found</error>');
			return null;
		}

		$choices = [];
		foreach ($leagues as $league) {
			$choices[(string)$league->id] = sprintf('#%d %s', $league->id, $league->name);
		}
		$choice = $helper->ask($input, $output, new ChoiceQuestion('Choose league', $choices));
		foreach ($leagues as $league) {
			if ($choice === sprintf('#%d %s', $league->id, $league->name)) {
				return $league;
			}
		}
		return null;
	}

	private function resolveCategory(
		League $league,
		InputInterface $input,
		OutputInterface $output,
		QuestionHelper $helper,
	): ?LeagueCategory {
		$categories = $league->getCategories();
		if ($categories === []) {
			$output->writeln('<error>League has no categories</error>');
			return null;
		}

		$categoryId = $input->getArgument('category');
		if (is_string($categoryId) && $categoryId !== '') {
			foreach ($categories as $category) {
				if (
					(string)$category->id === $categoryId ||
					$category->getSlug() === $categoryId ||
					strtolower($category->name) === strtolower($categoryId)
				) {
					return $category;
				}
			}
			$output->writeln('<error>Category not found in selected league</error>');
			return null;
		}

		$choices = [];
		foreach ($categories as $category) {
			$choices[(string)$category->id] = sprintf('#%d %s', $category->id, $category->name);
		}
		$choice = $helper->ask($input, $output, new ChoiceQuestion('Choose category', $choices));
		foreach ($categories as $category) {
			if ($choice === sprintf('#%d %s', $category->id, $category->name)) {
				return $category;
			}
		}
		return null;
	}

	private function renderTeams(LeagueCategory $category, OutputInterface $output): void {
		$rows = [];
		foreach ($this->getTeams($category) as $team) {
			$rows[] = [
				$team->id,
				$team->name,
				count($team->players),
				count($team->teams),
				$team->disqualified ? 'yes' : 'no',
				implode(', ', array_map(
					static fn(Team $tournamentTeam) => '#' . $tournamentTeam->tournament->id . ' ' . $tournamentTeam->tournament->name,
					$team->teams
				)),
			];
		}

		$table = new Table($output);
		$table->setHeaders(['ID', 'Team', 'Players', 'Tournament teams', 'Disqualified', 'Tournaments']);
		$table->setRows($rows);
		$table->render();
	}

	/**
	 * @return LeagueTeam[]
	 */
	private function getTeams(LeagueCategory $category): array {
		return LeagueTeam::query()
		                 ->where('id_category = %i', $category->id)
		                 ->orderBy('points')
		                 ->desc()
		                 ->get();
	}

	private function normalizeAction(string $action): string {
		return match ($action) {
			'Disqualify / requalify team' => 'toggle',
			'Re-register team for tournament' => 'reregister',
			'Deduplicate league/tournament players' => 'deduplicate',
			'Quit' => 'quit',
			default => $action,
		};
	}

	private function toggleDisqualification(
		LeagueCategory $category,
		InputInterface $input,
		OutputInterface $output,
		QuestionHelper $helper,
	): void {
		$team = $this->chooseTeam($category, $input, $output, $helper);
		if (!isset($team)) {
			return;
		}

		$newState = !$team->disqualified;
		$applyToTournamentTeams = $helper->ask(
			$input,
			$output,
			new ConfirmationQuestion('Apply the same state to existing tournament teams? [Y/n] ', true)
		);

		DB::getConnection()->begin();
		try {
			$team->disqualified = $newState;
			if (!$team->save()) {
				throw new ModelSaveFailedException('League team cannot be saved');
			}

			if ($applyToTournamentTeams) {
				foreach ($team->teams as $tournamentTeam) {
					$tournamentTeam->disqualified = $newState;
					if (!$tournamentTeam->save()) {
						throw new ModelSaveFailedException('Tournament team cannot be saved');
					}
				}
			}
		} catch (ModelSaveFailedException|ValidationException $e) {
			DB::getConnection()->rollback();
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return;
		}

		DB::getConnection()->commit();
		$output->writeln(sprintf(
			'<info>Team #%d %s.</info>',
			$team->id,
			$newState ? 'disqualified' : 'requalified'
		));
	}

	private function chooseTeam(
		LeagueCategory $category,
		InputInterface $input,
		OutputInterface $output,
		QuestionHelper $helper,
	): ?LeagueTeam {
		$teams = $this->getTeams($category);
		if ($teams === []) {
			$output->writeln('<error>No teams found in this category</error>');
			return null;
		}

		$choices = [];
		foreach ($teams as $team) {
			$choices[(string)$team->id] = sprintf(
				'#%d %s%s',
				$team->id,
				$team->name,
				$team->disqualified ? ' (disqualified)' : ''
			);
		}

		$choice = $helper->ask($input, $output, new ChoiceQuestion('Choose team', $choices));
		foreach ($teams as $team) {
			if ($choice === sprintf('#%d %s%s', $team->id, $team->name, $team->disqualified ? ' (disqualified)' : '')) {
				return $team;
			}
		}
		return null;
	}

	private function reloadCategory(LeagueCategory $category): LeagueCategory {
		try {
			return LeagueCategory::get($category->id);
		} catch (ModelNotFoundException) {
			return $category;
		}
	}

	private function reregisterTeam(
		LeagueCategory $category,
		InputInterface $input,
		OutputInterface $output,
		QuestionHelper $helper,
	): void {
		$leagueTeam = $this->chooseTeam($category, $input, $output, $helper);
		if (!isset($leagueTeam)) {
			return;
		}

		$tournament = $this->chooseTournament($category, $input, $output, $helper);
		if (!isset($tournament)) {
			return;
		}

		if ($tournament->isFinished() || $tournament->isStarted()) {
			$confirmed = $helper->ask(
				$input,
				$output,
				new ConfirmationQuestion('Tournament already started or finished. Continue? [y/N] ', false)
			);
			if (!$confirmed) {
				return;
			}
		}

		$existingTeam = Team::query()
		                    ->where('id_tournament = %i AND id_league_team = %i', $tournament->id, $leagueTeam->id)
		                    ->first();
		if (isset($existingTeam)) {
			$confirmed = $helper->ask(
				$input,
				$output,
				new ConfirmationQuestion('Tournament team already exists. Update it from the league registration? [Y/n] ', true)
			);
			if (!$confirmed) {
				return;
			}
		}

		DB::getConnection()->begin();
		try {
			$data = $this->createTeamRegistrationData($leagueTeam);
			if (isset($existingTeam)) {
				$this->assignExistingTournamentPlayerIds($data, $existingTeam);
			}
			/** @var Team $tournamentTeam */
			$tournamentTeam = $this->eventRegistrationService->registerTeam(
				$tournament,
				$data,
				team: $existingTeam
			);
			$tournamentTeam->disqualified = $leagueTeam->disqualified;
			if (!$tournamentTeam->save()) {
				throw new ModelSaveFailedException('Tournament team cannot be saved');
			}
		} catch (ModelSaveFailedException|ModelNotFoundException|ValidationException $e) {
			DB::getConnection()->rollback();
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return;
		}

		DB::getConnection()->commit();
		$output->writeln(sprintf(
			'<info>Team #%d %s registered for tournament #%d %s as tournament team #%d.</info>',
			$leagueTeam->id,
			$leagueTeam->name,
			$tournament->id,
			$tournament->name,
			$tournamentTeam->id
		));
	}

	private function chooseTournament(
		LeagueCategory $category,
		InputInterface $input,
		OutputInterface $output,
		QuestionHelper $helper,
	): ?Tournament {
		$tournaments = $category->getTournaments();
		if ($tournaments === []) {
			$output->writeln('<error>No tournaments found in this category</error>');
			return null;
		}

		$choices = [];
		foreach ($tournaments as $tournament) {
			$choices[(string)$tournament->id] = sprintf(
				'#%d %s (%s)%s',
				$tournament->id,
				$tournament->name,
				$tournament->start->format('Y-m-d'),
				$tournament->isFinished() ? ' [finished]' : ($tournament->isStarted() ? ' [started]' : '')
			);
		}

		$choice = $helper->ask($input, $output, new ChoiceQuestion('Choose tournament', $choices));
		foreach ($tournaments as $tournament) {
			if ($choice === sprintf(
				'#%d %s (%s)%s',
				$tournament->id,
				$tournament->name,
				$tournament->start->format('Y-m-d'),
				$tournament->isFinished() ? ' [finished]' : ($tournament->isStarted() ? ' [started]' : '')
			)) {
				return $tournament;
			}
		}
		return null;
	}

	/**
	 * @throws ValidationException
	 */
	private function createTeamRegistrationData(LeagueTeam $leagueTeam): TeamRegistrationDTO {
		$data = new TeamRegistrationDTO($leagueTeam->name);
		$data->leagueTeam = $leagueTeam->id;
		$data->image = $leagueTeam->getImageObj();

		foreach ($leagueTeam->players as $leaguePlayer) {
			$data->players[] = $this->createPlayerRegistrationData($leaguePlayer);
		}

		return $data;
	}

	private function createPlayerRegistrationData(LeaguePlayer $leaguePlayer): PlayerRegistrationDTO {
		$data = new PlayerRegistrationDTO(
			$leaguePlayer->nickname,
			$leaguePlayer->name ?? '',
			$leaguePlayer->surname ?? '',
			$leaguePlayer->email,
			$leaguePlayer->phone,
			$leaguePlayer->parentEmail,
			$leaguePlayer->parentPhone,
			$leaguePlayer->birthYear,
			$leaguePlayer->skill,
			$leaguePlayer->user,
			$leaguePlayer->captain,
			$leaguePlayer->sub,
		);
		$data->leaguePlayer = $leaguePlayer;
		return $data;
	}

	private function assignExistingTournamentPlayerIds(TeamRegistrationDTO $data, Team $tournamentTeam): void {
		foreach ($data->players as $player) {
			$player->playerId = null;
			if (!isset($player->leaguePlayer)) {
				continue;
			}

			foreach ($tournamentTeam->players as $tournamentPlayer) {
				if ($tournamentPlayer->leaguePlayer?->id === $player->leaguePlayer->id) {
					$player->playerId = $tournamentPlayer->id;
					break;
				}
			}
		}
	}

	private function deduplicatePlayers(
		LeagueCategory $category,
		InputInterface $input,
		OutputInterface $output,
		QuestionHelper $helper,
	): void {
		$scope = $helper->ask(
			$input,
			$output,
			new ChoiceQuestion(
				'Deduplicate scope',
				[
					'selected' => 'Selected team',
					'all'      => 'All teams in category',
				],
				'selected'
			)
		);

		if ($scope === 'selected' || $scope === 'Selected team') {
			$team = $this->chooseTeam($category, $input, $output, $helper);
			if (!isset($team)) {
				return;
			}
			$teams = [$team];
		}
		else {
			$teams = $this->getTeams($category);
		}

		$confirmed = $helper->ask(
			$input,
			$output,
			new ConfirmationQuestion('Merge duplicate players and delete duplicate rows? [y/N] ', false)
		);
		if (!$confirmed) {
			return;
		}

		$leaguePlayersRemoved = 0;
		$tournamentPlayersRemoved = 0;

		DB::getConnection()->begin();
		try {
			foreach ($teams as $team) {
				$leaguePlayersRemoved += $this->deduplicateLeagueTeamPlayers($team);
				foreach ($team->teams as $tournamentTeam) {
					$tournamentPlayersRemoved += $this->deduplicateTournamentTeamPlayers($tournamentTeam);
				}
			}
		} catch (ModelSaveFailedException|ValidationException $e) {
			DB::getConnection()->rollback();
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return;
		}

		DB::getConnection()->commit();
		$output->writeln(sprintf(
			'<info>Removed %d duplicate league players and %d duplicate tournament players.</info>',
			$leaguePlayersRemoved,
			$tournamentPlayersRemoved
		));
	}

	/**
	 * @throws ModelSaveFailedException
	 * @throws ValidationException
	 */
	private function deduplicateLeagueTeamPlayers(LeagueTeam $team): int {
		$groups = [];
		foreach (LeaguePlayer::query()->where('id_team = %i', $team->id)->orderBy('id_player')->get() as $player) {
			$key = $this->getLeaguePlayerDedupeKey($player);
			if (!isset($key)) {
				continue;
			}
			$groups[$key][] = $player;
		}

		$removed = 0;
		foreach ($groups as $players) {
			if (count($players) < 2) {
				continue;
			}
			$keep = $this->chooseLeaguePlayerToKeep($players);
			foreach ($players as $duplicate) {
				if ($duplicate->id === $keep->id) {
					continue;
				}
				$this->mergeLeaguePlayer($duplicate, $keep);
				$removed++;
			}
		}
		return $removed;
	}

	private function getLeaguePlayerDedupeKey(LeaguePlayer $player): ?string {
		if (isset($player->user)) {
			return 'user:' . $player->user->id;
		}
		if (!empty($player->email)) {
			return 'email:' . strtolower(trim($player->email));
		}
		$identity = strtolower(trim(($player->nickname ?? '') . '|' . ($player->name ?? '') . '|' . ($player->surname ?? '') . '|' . ($player->sub ? 'sub' : 'player')));
		return trim($identity, '|') === '' ? null : 'name:' . $identity;
	}

	/**
	 * @param LeaguePlayer[] $players
	 */
	private function chooseLeaguePlayerToKeep(array $players): LeaguePlayer {
		usort($players, static function (LeaguePlayer $a, LeaguePlayer $b): int {
			return [$b->user?->id !== null, $b->captain, !$b->sub, -$b->id]
				<=> [$a->user?->id !== null, $a->captain, !$a->sub, -$a->id];
		});
		return $players[0];
	}

	/**
	 * @throws ModelSaveFailedException
	 * @throws ValidationException
	 */
	private function mergeLeaguePlayer(LeaguePlayer $duplicate, LeaguePlayer $keep): void {
		DB::update(EventPlayer::TABLE, ['id_league_player' => $keep->id], ['id_league_player = %i', $duplicate->id]);
		DB::update(TournamentPlayer::TABLE, ['id_league_player' => $keep->id], ['id_league_player = %i', $duplicate->id]);
		if (!$duplicate->delete()) {
			throw new ModelSaveFailedException('Duplicate league player cannot be deleted');
		}
	}

	/**
	 * @throws ModelSaveFailedException
	 * @throws ValidationException
	 */
	private function deduplicateTournamentTeamPlayers(Team $team): int {
		$groups = [];
		foreach (TournamentPlayer::query()->where('id_team = %i', $team->id)->orderBy('id_player')->get() as $player) {
			$key = $this->getTournamentPlayerDedupeKey($player);
			if (!isset($key)) {
				continue;
			}
			$groups[$key][] = $player;
		}

		$removed = 0;
		foreach ($groups as $players) {
			if (count($players) < 2) {
				continue;
			}
			$keep = $this->chooseTournamentPlayerToKeep($players);
			foreach ($players as $duplicate) {
				if ($duplicate->id === $keep->id) {
					continue;
				}
				$this->mergeTournamentPlayer($duplicate, $keep);
				$removed++;
			}
		}
		return $removed;
	}

	private function getTournamentPlayerDedupeKey(TournamentPlayer $player): ?string {
		if (isset($player->leaguePlayer)) {
			return 'league:' . $player->leaguePlayer->id;
		}
		if (isset($player->user)) {
			return 'user:' . $player->user->id;
		}
		if (!empty($player->email)) {
			return 'email:' . strtolower(trim($player->email));
		}
		$identity = strtolower(trim(($player->nickname ?? '') . '|' . ($player->name ?? '') . '|' . ($player->surname ?? '') . '|' . ($player->sub ? 'sub' : 'player')));
		return trim($identity, '|') === '' ? null : 'name:' . $identity;
	}

	/**
	 * @param TournamentPlayer[] $players
	 */
	private function chooseTournamentPlayerToKeep(array $players): TournamentPlayer {
		usort($players, static function (TournamentPlayer $a, TournamentPlayer $b): int {
			return [$b->leaguePlayer?->id !== null, $b->user?->id !== null, $b->captain, !$b->sub, -$b->id]
				<=> [$a->leaguePlayer?->id !== null, $a->user?->id !== null, $a->captain, !$a->sub, -$a->id];
		});
		return $players[0];
	}

	/**
	 * @throws ModelSaveFailedException
	 * @throws ValidationException
	 */
	private function mergeTournamentPlayer(TournamentPlayer $duplicate, TournamentPlayer $keep): void {
		DB::update('tournament_game_players', ['id_player' => $keep->id], ['id_player = %i', $duplicate->id]);
		DB::update(Evo5Player::TABLE, ['id_tournament_player' => $keep->id], ['id_tournament_player = %i', $duplicate->id]);
		DB::update(Evo6Player::TABLE, ['id_tournament_player' => $keep->id], ['id_tournament_player = %i', $duplicate->id]);
		if (!$duplicate->delete()) {
			throw new ModelSaveFailedException('Duplicate tournament player cannot be deleted');
		}
	}
}
