<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Cycle\Schema\Generator;

use Cycle\Database\Schema\AbstractTable;
use Cycle\Database\Schema\ComparatorInterface;
use Cycle\Schema\GeneratorInterface;
use Cycle\Schema\Registry;
use Symfony\Component\Console\Output\OutputInterface;

final class PrintChanges implements GeneratorInterface
{
    private OutputInterface $output;

    private array $changes = [];

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function run(Registry $registry): Registry
    {
        $this->output->writeln('<info>Detecting schema changes:</info>');
        $this->changes = [];
        foreach ($registry->getIterator() as $entity) {
            if ($registry->hasTable($entity)) {
                $table = $registry->getTableSchema($entity);
                if ($table->getComparator()->hasChanges()) {
                    $this->changes[] = [
                        'database' => $registry->getDatabase($entity),
                        'table' => $registry->getTable($entity),
                        'schema' => $table,
                    ];
                }
            }
        }
        if ($this->changes === []) {
            $this->output->writeln('<fg=yellow>no database changes has been detected</fg=yellow>');
            return $registry;
        }
        foreach ($this->changes as $change) {
            $this->output->write(sprintf('• <fg=cyan>%s.%s</fg=cyan>', $change['database'], $change['table']));
            $this->describeChanges($change['schema']);
        }
        return $registry;
    }

    public function hasChanges(): bool
    {
        return $this->changes !== [];
    }

    private function describeChanges(AbstractTable $table): void
    {
        if (!$this->output->isVerbose()) {
            $this->output->writeln(sprintf(
                ': <fg=green>%s</fg=green> change(s) detected',
                $this->numChanges($table)
            ));
            return;
        }
        $this->output->write("\n");
        if (!$table->exists()) {
            $this->output->writeln('    - create table');
        }
        if ($table->getStatus() === AbstractTable::STATUS_DECLARED_DROPPED) {
            $this->output->writeln('    - drop table');
            return;
        }
        $cmp = $table->getComparator();
        $this->describeColumns($cmp);
        $this->describeIndexes($cmp);
        $this->describeFKs($cmp);
    }

    private function describeColumns(ComparatorInterface $cmp): void
    {
        foreach ($cmp->addedColumns() as $column) {
            $this->output->writeln("    - add column <fg=yellow>{$column->getName()}</fg=yellow>");
        }
        foreach ($cmp->droppedColumns() as $column) {
            $this->output->writeln("    - drop column <fg=yellow>{$column->getName()}</fg=yellow>");
        }
        foreach ($cmp->alteredColumns() as $column) {
            $column = $column[0];
            $this->output->writeln("    - alter column <fg=yellow>{$column->getName()}</fg=yellow>");
        }
    }

    private function describeIndexes(ComparatorInterface $cmp): void
    {
        foreach ($cmp->addedIndexes() as $index) {
            $index = implode(', ', $index->getColumns());
            $this->output->writeln("    - add index on <fg=yellow>[{$index}]</fg=yellow>");
        }
        foreach ($cmp->droppedIndexes() as $index) {
            $index = implode(', ', $index->getColumns());
            $this->output->writeln("    - drop index on <fg=yellow>[{$index}]</fg=yellow>");
        }
        foreach ($cmp->alteredIndexes() as $index) {
            $index = $index[0];
            $index = implode(', ', $index->getColumns());
            $this->output->writeln("    - alter index on <fg=yellow>[{$index}]</fg=yellow>");
        }
    }

    private function describeFKs(ComparatorInterface $cmp): void
    {
        foreach ($cmp->addedForeignKeys() as $fk) {
            $fkColumns = implode(', ', $fk->getColumns());
            $this->output->writeln("    - add foreign key on <fg=yellow>{$fkColumns}</fg=yellow>");
        }
        foreach ($cmp->droppedForeignKeys() as $fk) {
            $fkColumns = implode(', ', $fk->getColumns());
            $this->output->writeln("    - drop foreign key <fg=yellow>{$fkColumns}</fg=yellow>");
        }
        foreach ($cmp->alteredForeignKeys() as $fk) {
            $fk = $fk[0];
            $fkColumns = implode(', ', $fk->getColumns());
            $this->output->writeln("    - alter foreign key <fg=yellow>{$fkColumns}</fg=yellow>");
        }
    }

    private function numChanges(AbstractTable $table): int
    {
        $cmp = $table->getComparator();
        return count($cmp->addedColumns())
            + count($cmp->droppedColumns())
            + count($cmp->alteredColumns())
            + count($cmp->addedIndexes())
            + count($cmp->droppedIndexes())
            + count($cmp->alteredIndexes())
            + count($cmp->addedForeignKeys())
            + count($cmp->droppedForeignKeys())
            + count($cmp->alteredForeignKeys());
    }
}
