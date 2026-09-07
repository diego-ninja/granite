<?php

// ABOUTME: Verifies quality-gate CLI scripts through isolated subprocesses.
// ABOUTME: Covers exact ABOUTME headers and Clover line/method thresholds.

declare(strict_types=1);

namespace Tests\Unit\Tools;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Helpers\TestCase;

#[CoversNothing]
final class QualityToolingTest extends TestCase
{
    private string $projectRoot;

    private string $temporaryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectRoot = dirname(__DIR__, 3);
        $this->temporaryRoot = sys_get_temp_dir() . '/granite-tooling-' . bin2hex(random_bytes(8));

        self::assertTrue(mkdir($this->temporaryRoot, 0o700, true));
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->temporaryRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($this->temporaryRoot);

        parent::tearDown();
    }

    #[Test]
    public function it_accepts_exactly_two_aboutme_lines_at_the_start(): void
    {
        $script = $this->copyAboutmeCheckerToIsolatedProject();
        $this->writeSourceFile(<<<'PHP'
            <?php
            // ABOUTME: Describes the fixture source file.
            // ABOUTME: Provides the required second purpose line.

            declare(strict_types=1);
            PHP);

        $result = $this->runPhpScript($script);

        self::assertSame(0, $result['exitCode'], $result['stderr']);
    }

    #[Test]
    public function it_rejects_a_third_contiguous_aboutme_line(): void
    {
        $script = $this->copyAboutmeCheckerToIsolatedProject();
        $this->writeSourceFile(<<<'PHP'
            <?php
            // ABOUTME: Describes the fixture source file.
            // ABOUTME: Provides the required second purpose line.
            // ABOUTME: This duplicate must be rejected.

            declare(strict_types=1);
            PHP);

        $result = $this->runPhpScript($script);

        self::assertSame(1, $result['exitCode']);
    }

    #[Test]
    public function it_accepts_clover_coverage_above_both_thresholds(): void
    {
        $clover = $this->writeCloverReport(
            methods: 10,
            coveredMethods: 10,
            statements: 100,
            coveredStatements: 100,
        );

        $result = $this->runCoverageChecker($clover);

        self::assertSame(0, $result['exitCode'], $result['stderr']);
    }

    #[Test]
    public function it_rejects_clover_coverage_below_the_line_threshold(): void
    {
        $clover = $this->writeCloverReport(
            methods: 10,
            coveredMethods: 10,
            statements: 100,
            coveredStatements: 0,
        );

        $result = $this->runCoverageChecker($clover);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString('line', strtolower($result['stdout'] . $result['stderr']));
    }

    #[Test]
    public function it_rejects_clover_coverage_below_the_method_threshold(): void
    {
        $clover = $this->writeCloverReport(
            methods: 10,
            coveredMethods: 0,
            statements: 100,
            coveredStatements: 100,
        );

        $result = $this->runCoverageChecker($clover);

        self::assertSame(1, $result['exitCode']);
        self::assertStringContainsString('method', strtolower($result['stdout'] . $result['stderr']));
    }

    private function copyAboutmeCheckerToIsolatedProject(): string
    {
        $toolsDirectory = $this->temporaryRoot . '/aboutme/tools';

        self::assertTrue(mkdir($toolsDirectory, 0o700, true));
        self::assertTrue(mkdir($this->temporaryRoot . '/aboutme/src', 0o700, true));

        $script = $toolsDirectory . '/check-aboutme.php';
        self::assertTrue(copy($this->projectRoot . '/tools/check-aboutme.php', $script));

        return $script;
    }

    private function writeSourceFile(string $contents): void
    {
        self::assertNotFalse(file_put_contents(
            $this->temporaryRoot . '/aboutme/src/Example.php',
            $contents,
        ));
    }

    private function writeCloverReport(
        int $methods,
        int $coveredMethods,
        int $statements,
        int $coveredStatements,
    ): string {
        $path = $this->temporaryRoot . '/clover.xml';
        $xml = sprintf(
            <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <coverage generated="0">
                  <project timestamp="0">
                    <metrics files="1" classes="1" methods="%d" coveredmethods="%d" statements="%d" coveredstatements="%d"/>
                  </project>
                </coverage>
                XML,
            $methods,
            $coveredMethods,
            $statements,
            $coveredStatements,
        );

        self::assertNotFalse(file_put_contents($path, $xml));

        return $path;
    }

    /**
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function runCoverageChecker(string $clover): array
    {
        return $this->runPhpScript(
            $this->projectRoot . '/tools/check-coverage.php',
            [$clover],
        );
    }

    /**
     * @param list<string> $arguments
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function runPhpScript(string $script, array $arguments = []): array
    {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, $script, ...$arguments],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $this->projectRoot,
        );

        self::assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'exitCode' => proc_close($process),
            'stdout' => false === $stdout ? '' : $stdout,
            'stderr' => false === $stderr ? '' : $stderr,
        ];
    }
}
