<?php

// ABOUTME: Enforces the project's statement and method coverage baselines.
// ABOUTME: Reads aggregate metrics from a PHPUnit Clover coverage report.

declare(strict_types=1);

const MINIMUM_STATEMENT_COVERAGE = 83.00;
const MINIMUM_METHOD_COVERAGE = 72.00;

$reportPath = $argv[1] ?? null;

if (null === $reportPath || ! is_file($reportPath)) {
    fwrite(STDERR, "Usage: php tools/check-coverage.php <clover.xml>\n");
    exit(2);
}

libxml_use_internal_errors(true);
$report = simplexml_load_file($reportPath);

if (false === $report) {
    fwrite(STDERR, "Unable to parse Clover report: {$reportPath}\n");
    exit(2);
}

$metrics = $report->project->metrics;

if (0 === $metrics->count()) {
    fwrite(STDERR, "Clover report does not contain project metrics: {$reportPath}\n");
    exit(2);
}

$statements = (int) $metrics['statements'];
$coveredStatements = (int) $metrics['coveredstatements'];
$methods = (int) $metrics['methods'];
$coveredMethods = (int) $metrics['coveredmethods'];

if (0 === $statements || 0 === $methods) {
    fwrite(STDERR, "Clover report contains no measurable statements or methods.\n");
    exit(2);
}

$statementCoverage = 100 * $coveredStatements / $statements;
$methodCoverage = 100 * $coveredMethods / $methods;
$failures = [];

if ($statementCoverage < MINIMUM_STATEMENT_COVERAGE) {
    $failures[] = sprintf(
        'Line coverage %.2f%% is below %.2f%%.',
        $statementCoverage,
        MINIMUM_STATEMENT_COVERAGE,
    );
}

if ($methodCoverage < MINIMUM_METHOD_COVERAGE) {
    $failures[] = sprintf(
        'Method coverage %.2f%% is below %.2f%%.',
        $methodCoverage,
        MINIMUM_METHOD_COVERAGE,
    );
}

if ([] !== $failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(
    STDOUT,
    sprintf(
        "Coverage baseline satisfied: %.2f%% lines, %.2f%% methods.\n",
        $statementCoverage,
        $methodCoverage,
    ),
);
