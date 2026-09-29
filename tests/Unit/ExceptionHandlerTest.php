<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Tests\Unit;

use Arifnd\LiteQueue\Console\ExceptionHandler;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class ExceptionHandlerTest extends TestCase
{
    public function test_it_reports_the_exception_class_and_message(): void
    {
        $output = new BufferedOutput;
        $handler = new ExceptionHandler;
        $handler->setOutput($output);

        $handler->report(new RuntimeException('boom'));

        $this->assertStringContainsString('RuntimeException: boom', $output->fetch());
    }

    public function test_it_writes_the_stack_trace_when_verbose(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE);
        $handler = new ExceptionHandler;
        $handler->setOutput($output);

        $handler->report(new RuntimeException('boom'));

        $this->assertStringContainsString('#0', $output->fetch());
    }

    public function test_render_for_console_uses_the_given_output(): void
    {
        $output = new BufferedOutput;
        $handler = new ExceptionHandler;

        $handler->renderForConsole($output, new RuntimeException('kaboom'));

        $this->assertStringContainsString('kaboom', $output->fetch());
    }

    public function test_it_should_report_by_default(): void
    {
        $this->assertTrue((new ExceptionHandler)->shouldReport(new RuntimeException));
    }
}
