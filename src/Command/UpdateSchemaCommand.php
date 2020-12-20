<?php

declare(strict_types=1);

namespace Refugis\ODM\Elastica\Command;

use InvalidArgumentException;
use Refugis\ODM\Elastica\DocumentManagerInterface;
use Refugis\ODM\Elastica\Tools\SchemaGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Safe\preg_match;

class UpdateSchemaCommand extends Command
{
    private DocumentManagerInterface $documentManager;

    public function __construct(DocumentManagerInterface $documentManager)
    {
        $this->documentManager = $documentManager;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('update-schema');
        $this->addOption('filter-expression', null, InputOption::VALUE_REQUIRED, 'Filters the classes for schema updates via a regular expression');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Elastica ODM - update schema');

        $generator = new SchemaGenerator($this->documentManager);
        $schema = $generator->generateSchema();

        $expression = $input->getOption('filter-expression');
        if ($expression !== null) {
            if (preg_match($expression, '') === false) {
                throw new InvalidArgumentException('Filter expression is not a valid regex');
            }

            $filter = static fn ($value): bool => (bool) preg_match($expression, $value);
        } else {
            $filter = static fn ($value): bool => true;
        }

        foreach ($schema->getMapping() as $className => $mapping) {
            if (! $filter($className)) {
                continue;
            }

            $collection = $this->documentManager->getCollection($className);
            $collection->updateMapping($mapping->getMapping());
        }

        $io->success('All done.');

        return 0;
    }
}
