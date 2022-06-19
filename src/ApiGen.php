<?php declare(strict_types = 1);

namespace ApiGenX;

use ApiGenX\Analyzer\AnalyzeResult;
use ApiGenX\Index\Index;
use Nette\Utils\Finder;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Style\OutputStyle;

use function array_column;
use function array_keys;
use function array_map;
use function array_slice;
use function count;
use function hrtime;
use function implode;
use function iterator_to_array;
use function memory_get_peak_usage;
use function sprintf;


final class ApiGen
{
	/**
	 * @param string[] $paths   indexed by []
	 * @param string[] $include indexed by []
	 * @param string[] $exclude indexed by []
	 */
	public function __construct(
		private OutputStyle $output,
		private Analyzer $analyzer,
		private Indexer $indexer,
		private Renderer $renderer,
		private string $memoryLimit,
		private array $paths,
		private array $include,
		private array $exclude,
	) {
	}


	public function generate(): void
	{
		$this->setMemoryLimit();
		$files = $this->findFiles();

		$analyzeTime = -hrtime(true);
		$analyzeResult = $this->analyze($files);
		$analyzeTime += hrtime(true);

		$indexTime = -hrtime(true);
		$index = $this->index($analyzeResult);
		$indexTime += hrtime(true);

		$renderTime = -hrtime(true);
		$this->render($index);
		$renderTime += hrtime(true);

		$this->performance($analyzeTime, $indexTime, $renderTime);
		$this->finish($analyzeResult);
	}


	private function setMemoryLimit(): void
	{
		if (!ini_set('memory_limit', $this->memoryLimit)) {
			$this->output->warning('Failed to configure memory limit');
		}
	}


	/**
	 * @return string[] $files indexed by []
	 */
	private function findFiles(): array
	{
		$files = Finder::findFiles(...$this->include)
			->exclude(...$this->exclude)
			->from(...$this->paths);

		$files = array_map('realpath', array_keys(iterator_to_array($files)));

		if (!count($files)) {
			throw new \RuntimeException('No source files found.');

		} elseif ($this->output->isDebug()) {
			$this->output->text('<info>Matching source files:</info>');
			$this->output->newLine();
			$this->output->listing($files);

		} elseif ($this->output->isVerbose()) {
			$this->output->text(sprintf('Found %d source files.', count($files)));
		}

		return $files;
	}


	/**
	 * @param string[] $files indexed by []
	 */
	private function analyze(array $files): AnalyzeResult
	{
		$progressBar = $this->output->createProgressBar();
		$progressBar->setFormat(' <fg=green>Analyzing</> %current%/%max% [%bar%] %percent:3s%% %message%');

		$analyzeResult = $this->analyzer->analyze($progressBar, $files);

		$progressBar->setMessage('done');
		$progressBar->finish();
		$this->output->newLine(2);

		return $analyzeResult;
	}


	private function index(AnalyzeResult $analyzeResult): Index
	{
		$index = new Index();

		foreach ($analyzeResult->classLike as $info) {
			$this->indexer->indexFile($index, $info->file, $info->primary);
			$this->indexer->indexNamespace($index, $info->name->namespace, $info->name->namespaceLower, $info->primary, $info->isDeprecated());
			$this->indexer->indexClassLike($index, $info);
		}

		$this->indexer->postProcess($index);
		return $index;
	}


	private function render(Index $index): void
	{
		$progressBar = $this->output->createProgressBar();
		$progressBar->setFormat(' <fg=green>Rendering</> %current%/%max% [%bar%] %percent:3s%% %message%');

		$this->renderer->render($progressBar, $index);

		$progressBar->setMessage('done');
		$progressBar->finish();
		$this->output->newLine(2);
	}


	private function performance(float $analyzeTime, float $indexTime, float $renderTime): void
	{
		if ($this->output->isDebug()) {
			$this->output->definitionList(
				'Performance',
				new TableSeparator(),
				['Analyze Time' => sprintf('%6.0f ms', $analyzeTime / 1e6)],
				['Index Time' => sprintf('%6.0f ms', $indexTime / 1e6)],
				['Render Time' => sprintf('%6.0f ms', $renderTime / 1e6)],
				['Peak Memory' => sprintf('%6.0f MB', memory_get_peak_usage() / 1e6)],
			);
		}
	}


	private function finish(AnalyzeResult $analyzeResult): void
	{
		if (!$analyzeResult->error) {
			$this->output->success('Finished OK');
			return;
		}

		foreach ($analyzeResult->error as $errorKind => $errorGroup) {
			$errorLines = array_column($errorGroup, 'message');

			if (!$this->output->isVerbose() && count($errorLines) > 5) {
				$errorLines = array_slice($errorLines, 0, 5);
				$errorLines[] = '...';
				$errorLines[] = sprintf('and %d more (use --verbose to show all)', count($errorGroup) - 5);
			}

			$this->output->error(implode("\n\n", [sprintf('%dx %s:', count($errorGroup), $errorKind), ...$errorLines]));
		}

		$this->output->warning('Finished with errors');
	}
}
