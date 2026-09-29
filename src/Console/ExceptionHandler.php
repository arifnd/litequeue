<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Console;

use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ExceptionHandler implements ExceptionHandlerContract
{
    protected ?OutputInterface $output = null;

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function report(Throwable $e): void
    {
        $this->renderTo($this->output(), $e);
    }

    public function shouldReport(Throwable $e): bool
    {
        return true;
    }

    public function render($request, Throwable $e)
    {
        throw $e;
    }

    public function renderForConsole($output, Throwable $e): void
    {
        $this->renderTo($output, $e);
    }

    protected function renderTo(OutputInterface $output, Throwable $e): void
    {
        $output->writeln(sprintf('<error>%s: %s</error>', $e::class, $e->getMessage()));

        if ($output->isVerbose()) {
            $output->writeln($e->getTraceAsString());
        }
    }

    protected function output(): OutputInterface
    {
        return $this->output ??= new ConsoleOutput(OutputInterface::VERBOSITY_NORMAL, true);
    }
}
