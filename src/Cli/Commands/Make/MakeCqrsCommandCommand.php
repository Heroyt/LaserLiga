<?php
declare(strict_types=1);

namespace App\Cli\Commands\Make;

use Lsr\CQRS\CommandHandlerInterface;
use Lsr\CQRS\CommandInterface;
use Lsr\Helpers\Tools\Strings;
use Nette\PhpGenerator\PhpFile;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MakeCqrsCommandCommand extends Command
{

	private bool $force = false;

	public static function getDefaultName(): ?string {
		return 'make:cqrs-command';
	}

	public static function getDefaultDescription(): ?string {
		return 'Create a new CQRS command class';
	}

	protected function configure(): void {
		$this->addArgument(
			'name',
			InputArgument::REQUIRED,
			'Command name. Can be prefixed with a namespace withing the App\\CQRS\\Commands namespace.'
		);

		$this->addOption('response', 'r', InputOption::VALUE_NONE, 'Also generate a command response DTO.');
		$this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force overwrite existing files.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$name = $input->getArgument('name');
		$this->force = (bool)$input->getOption('force');

		if (empty($name)) {
			$output->writeln("<error>Command name cannot be empty.</error>");
			return Command::FAILURE;
		}

		// Make sure the name is in PascalCase format
		$name = Strings::toPascalCase($name);

		// Extract namespace from name
		$parts = array_filter(explode('\\', str_replace('/', '\\', $name)));
		$namespace = '';
		if (count($parts) > 1) {
			$name = array_pop($parts);
			$namespace = implode('\\', $parts);

			// Namespace should not contain any part of the namespace prefix (App\CQRS\Commands)
			$namespace = preg_replace(
				'/(App\\\)?(CQRS\\\)?(Commands\\\)?/',
				'',
				$namespace,
			);
		}

		// Remove the "Command" suffix if it exists
		if (str_ends_with($name, 'Command')) {
			$name = substr($name, 0, -7);
		}

		$output->writeln("Creating a new CQRS command: <info>$name</info>");
		$output->writeln("Namespace: <info>$namespace</info>");

		$commandResponse = null;
		if ($input->getOption('response')) {
			$file = $this->createCommandResponseClass($name, $namespace);
			$commandResponse = $this->getCommandResponseClassName($name, $namespace);
			$output->writeln('<info>Created command response in: ' . $file . '</info>');
		}

		$file = $this->createCommandHandlerClass($name, $namespace, $commandResponse);
		$output->writeln('<info>Created command handler in: ' . $file . '</info>');

		$file = $this->createCommandClass($name, $namespace, $commandResponse);
		$output->writeln('<info>Created command in: ' . $file . '</info>');

		return Command::SUCCESS;
	}

	private function createCommandResponseClass(string $name, string $namespace): string {
		$file = new PhpFile();
		$file->setStrictTypes();

		$phpNamespace = $file->addNamespace($this->getCommandResponseNamespace($namespace));

		$class = $phpNamespace->addClass($this->getCommandResponseClassName($name));

		$class->setFinal()
		      ->setReadOnly();

		$class->addMethod('__construct')
		      ->setVisibility('public');

		return $this->saveGeneratedFile(
			$file,
			ROOT . 'src/CQRS/CommandResponses/',
			$this->getCommandResponseClassName($name),
			$namespace
		);
	}

	private function getCommandResponseNamespace(string $namespace): string {
		return $this->normalizeNamespace('App\\CQRS\\CommandResponses\\' . $this->normalizeNamespace($namespace));
	}

	private function normalizeNamespace(string $namespace): string {
		if (str_starts_with($namespace, '\\')) {
			$namespace = substr($namespace, 1);
		}
		if (str_ends_with($namespace, '\\')) {
			$namespace = substr($namespace, 0, -1);
		}
		return str_replace('/', '\\', $namespace);
	}

	private function getCommandResponseClassName(string $name, ?string $namespace = null): string {
		if ($namespace === null) {
			return $name . 'CommandResponse';
		}
		$commandNamespace = $this->getCommandResponseNamespace($namespace);
		return $commandNamespace . '\\' . $name . 'CommandResponse';
	}

	private function saveGeneratedFile(PhpFile $file, string $rootDir, string $name, string $namespace): string {
		$this->createNamespaceDirectory($rootDir, $namespace);
		$namespace = str_replace('\\', '/', $namespace);
		$fullPath = str_replace('//', '/', $rootDir . $namespace . '/' . $name . '.php');

		if (!$this->force && file_exists($fullPath)) {
			throw new RuntimeException(sprintf('File "%s" already exists', $fullPath));
		}

		file_put_contents($fullPath, $file->__toString());

		if (!file_exists($fullPath)) {
			throw new RuntimeException(sprintf('File "%s" was not created', $fullPath));
		}
		return $fullPath;
	}

	private function createNamespaceDirectory(string $rootDir, string $namespace): void {
		$namespace = str_replace('\\', '/', $namespace);
		$fullPath = $rootDir . $namespace;

		if (!is_dir($fullPath) && !mkdir($fullPath, 0755, true) && !is_dir($fullPath)) {
			throw new RuntimeException(sprintf('Directory "%s" was not created', $fullPath));
		}
	}

	/**
	 * @param class-string|null $commandResponse
	 */
	private function createCommandHandlerClass(string $name, string $namespace, ?string $commandResponse = null): string {
		$file = new PhpFile();
		$file->setStrictTypes();

		$phpNamespace = $file->addNamespace($this->getCommandHandlerNamespace($namespace));
		$phpNamespace->addUse(CommandHandlerInterface::class)
		             ->addUse(CommandInterface::class)
		             ->addUse($this->getCommandClassName($name, $namespace));

		if ($commandResponse !== null) {
			$phpNamespace->addUse($commandResponse);
		}

		$class = $phpNamespace->addClass($this->getCommandHandlerClassName($name));

		$class->setFinal()
		      ->setReadOnly()
		      ->addImplement(CommandHandlerInterface::class);

		$handle = $class->addMethod('handle')
		                ->setVisibility('public')
		                ->setReturnType($commandResponse ?? 'bool')
		                ->addComment('@param ' . $this->getCommandClassName($name) . ' $command');

		$handle->addParameter('command')
		       ->setType(CommandInterface::class);

		$handle->addBody(
			$commandResponse !== null ?
				'return new ' . $commandResponse . '();'
				: 'return true;'
		);

		return $this->saveGeneratedFile(
			$file,
			ROOT . 'src/CQRS/CommandHandlers/',
			$this->getCommandHandlerClassName($name),
			$namespace
		);
	}

	private function getCommandHandlerNamespace(string $namespace): string {
		return $this->normalizeNamespace('App\\CQRS\\CommandHandlers\\' . $this->normalizeNamespace($namespace));
	}

	private function getCommandClassName(string $name, ?string $namespace = null): string {
		if ($namespace === null) {
			return $name . 'Command';
		}
		$commandNamespace = $this->getCommandNamespace($namespace);
		return '\\' . $commandNamespace . '\\' . $name . 'Command';
	}

	private function getCommandNamespace(string $namespace): string {
		return $this->normalizeNamespace('App\\CQRS\\Commands\\' . $this->normalizeNamespace($namespace));
	}

	private function getCommandHandlerClassName(string $name, ?string $namespace = null): string {
		if ($namespace === null) {
			return $name . 'CommandHandler';
		}
		$commandNamespace = $this->getCommandHandlerNamespace($namespace);
		return $commandNamespace . '\\' . $name . 'CommandHandler';
	}

	private function createCommandClass(string $name, string $namespace, ?string $commandResponse = null): string {
		$file = new PhpFile();
		$file->setStrictTypes();

		$phpNamespace = $file->addNamespace($this->getCommandNamespace($namespace));
		$phpNamespace->addUse(CommandInterface::class)
		             ->addUse($this->getCommandHandlerClassName($name, $namespace));

		$class = $phpNamespace->addClass($this->getCommandClassName($name));

		$class->setFinal()
		      ->setReadOnly()
		      ->addImplement(CommandInterface::class)
		      ->addComment('@implements CommandInterface<' . ($commandResponse === null ? 'bool' : $this->getCommandResponseClassName($name)) . '>');

		$class->addMethod('__construct')
		      ->setVisibility('public');

		$class->addMethod('getHandler')
		      ->setVisibility('public')
		      ->setReturnType('string')
		      ->setBody('return ' . $this->getCommandHandlerClassName($name) . '::class;');

		return $this->saveGeneratedFile(
			$file,
			ROOT . 'src/CQRS/Commands/',
			$this->getCommandClassName($name),
			$namespace
		);
	}
}