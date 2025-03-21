<?php
declare(strict_types=1);

namespace App\Cron;

use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Tools\Lasermaxx\RegressionStatCalculator;
use App\Models\Arena;
use Lsr\Db\DB;
use Orisai\Scheduler\Job\Job;
use Orisai\Scheduler\Job\JobLock;

final readonly class RegressionUpdateJob implements Job
{

	public function getName(): string {
		return 'Update regression data';
	}

	public function run(JobLock $lock): void {
		// Update materialized view
		$db = DB::getConnection();
		// Evo6
		$db->query("TRUNCATE TABLE mvEvo5RegressionData;");
		$db->query("INSERT INTO mvEvo5RegressionData SELECT * FROM vEvo5RegressionData;");
		$db->query("ALTER TABLE mvEvo5RegressionData OPTIMIZE PARTITION ALL;");
		// Evo6
		$db->query("TRUNCATE TABLE mvEvo6RegressionData;");
		$db->query("INSERT INTO mvEvo6RegressionData SELECT * FROM vEvo6RegressionData;");
		$db->query("ALTER TABLE mvEvo6RegressionData OPTIMIZE PARTITION ALL;");

		// Update regression models
		$arenas = Arena::getAll();
		$modes = GameModeFactory::getAll(['rankable' => false]);

		RegressionStatCalculator::updateAll($arenas, $modes);
	}
}