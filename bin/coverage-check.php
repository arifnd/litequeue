<?php

declare(strict_types=1);

/**
 * Fail when Clover line coverage is below the given minimum.
 *
 * Usage: php bin/coverage-check.php build/coverage.xml 80
 */
$file = $argv[1] ?? 'build/coverage.xml';
$minimum = (float) ($argv[2] ?? 80);

if (! is_file($file)) {
    fwrite(STDERR, "Coverage report [{$file}] not found. Run the suite with --coverage-clover first.".PHP_EOL);

    exit(1);
}

$xml = simplexml_load_file($file);

if ($xml === false || ! isset($xml->project->metrics)) {
    fwrite(STDERR, "Unable to parse coverage report [{$file}].".PHP_EOL);

    exit(1);
}

$metrics = $xml->project->metrics;
$total = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percentage = $total > 0 ? $covered / $total * 100 : 0.0;

printf('Line coverage: %.2f%% (minimum %.2f%%)%s', $percentage, $minimum, PHP_EOL);

exit($percentage + 0.0001 >= $minimum ? 0 : 1);
