<?php
declare(strict_types=1);

namespace App\CQRS\Commands;

use App\CQRS\CommandHandlers\ProcessPhotoCommandHandler;
use App\Models\Arena;
use App\Models\Photos\Photo;
use Lsr\CQRS\CommandInterface;
use Lsr\Logging\Logger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @implements CommandInterface<string|Photo>
 */
class ProcessPhotoCommand implements CommandInterface
{

	/**
	 * @param list<int<1,max>> $optimizeSizes
	 */
	public function __construct(
		public Arena            $arena,
		public string           $filename,
		public ?string          $fileType = null,
		public ?string          $filePublicName = null,
		public array            $optimizeSizes = [
			150,
		],
		public ?OutputInterface $output = null,
		public ?Logger          $logger = null,
	) {
		assert(file_exists($this->filename) && is_readable($this->filename));
	}

	/**
	 * @inheritDoc
	 */
	public function getHandler(): string {
		return ProcessPhotoCommandHandler::class;
	}
}