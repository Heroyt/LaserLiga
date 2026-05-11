<?php
declare(strict_types=1);

namespace App\Models;

use App\Models\Auth\LigaPlayer;
use DateTimeInterface;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\ModelTraits\WithCreatedAt;

#[PrimaryKey('id_queue')]
class PlayerRankRecalculationQueue extends BaseModel
{
	use WithCreatedAt;

	public const string TABLE = 'player_rank_recalculation_queue';

	public DateTimeInterface $recalculateFrom;
	public string $reason;
	public ?string $code = null;
	#[ManyToOne]
	public ?LigaPlayer $user = null;
	public ?DateTimeInterface $processedAt = null;

}
