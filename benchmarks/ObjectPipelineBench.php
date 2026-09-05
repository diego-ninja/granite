<?php

// ABOUTME: Measures Granite object hydration, validation, serialization, and mapping hot paths.
// ABOUTME: Emits human-readable output by default or stable JSON for before/after comparisons.

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Ninja\Granite\Granite;
use Ninja\Granite\Mapping\ObjectMapper;
use Ninja\Granite\Validation\Attributes\Email;
use Ninja\Granite\Validation\Attributes\Min;
use Ninja\Granite\Validation\Attributes\Required;

final readonly class PipelinePlainUser
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public int $age,
        public string $role,
        public bool $active,
    ) {}
}

final readonly class PipelineUser extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public int $age,
        public string $role,
        public bool $active,
    ) {}
}

final readonly class PipelineTags extends Granite
{
    /** @param list<string> $tags */
    public function __construct(public int $id, public array $tags) {}
}

final readonly class PipelineOrder extends Granite
{
    public function __construct(
        public int $id,
        public PipelineUser $customer,
    ) {}
}

final readonly class PipelineValidatedUser extends Granite
{
    public function __construct(
        #[Required]
        public int $id,
        #[Required]
        #[Min(2)]
        public string $name,
        #[Required]
        #[Email]
        public string $email,
        #[Required]
        #[Min(0)]
        public int $age,
        #[Required]
        public string $role,
        public bool $active = true,
    ) {}
}

final class PipelineBenchmark
{
    /** @var array<string, array{median_us: float, samples_us: list<float>}> */
    private array $results = [];

    public function __construct(
        private readonly int $iterations,
        private readonly int $repetitions,
    ) {}

    public function measure(string $name, callable $operation): void
    {
        for ($i = 0; $i < min(1_000, $this->iterations); ++$i) {
            $operation();
        }

        $samples = [];
        for ($repetition = 0; $repetition < $this->repetitions; ++$repetition) {
            gc_collect_cycles();
            gc_disable();
            $start = hrtime(true);

            for ($i = 0; $i < $this->iterations; ++$i) {
                $operation();
            }

            $elapsedNanoseconds = hrtime(true) - $start;
            gc_enable();
            $samples[] = ($elapsedNanoseconds / $this->iterations) / 1_000;
        }

        sort($samples);
        $middle = intdiv(count($samples), 2);
        $median = 1 === count($samples) % 2
            ? $samples[$middle]
            : ($samples[$middle - 1] + $samples[$middle]) / 2;

        $this->results[$name] = [
            'median_us' => $median,
            'samples_us' => $samples,
        ];
    }

    public function report(bool $json): void
    {
        if ($json) {
            echo json_encode([
                'environment' => [
                    'php' => PHP_VERSION,
                    'os' => PHP_OS_FAMILY,
                    'opcache_cli' => filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOL),
                    'xdebug_loaded' => extension_loaded('xdebug'),
                    'xdebug_mode' => getenv('XDEBUG_MODE') ?: (ini_get('xdebug.mode') ?: 'off'),
                    'iterations' => $this->iterations,
                    'repetitions' => $this->repetitions,
                ],
                'results' => $this->results,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;

            return;
        }

        printf(
            "Granite object pipeline benchmark\nPHP %s | %s | %d iterations x %d repetitions\n\n",
            PHP_VERSION,
            PHP_OS_FAMILY,
            $this->iterations,
            $this->repetitions,
        );
        printf("%-38s %12s %14s\n", 'Scenario', 'median µs', 'ops/sec');
        echo str_repeat('-', 68) . PHP_EOL;

        foreach ($this->results as $name => $result) {
            printf(
                "%-38s %12.3f %14s\n",
                $name,
                $result['median_us'],
                number_format(1_000_000 / $result['median_us']),
            );
        }
    }
}

$options = getopt('', ['json', 'iterations:', 'repetitions:']);
$iterations = max(1, (int) ($options['iterations'] ?? 50_000));
$repetitions = max(1, (int) ($options['repetitions'] ?? 5));
$json = array_key_exists('json', $options);

$data = [
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
    'role' => 'admin',
    'active' => true,
];
$jsonData = json_encode($data, JSON_THROW_ON_ERROR);
$objectData = (object) $data;
$tagsData = ['id' => 1, 'tags' => ['php', 'dto', 'performance']];
$user = PipelineUser::from($data);
$order = PipelineOrder::from(['id' => 10, 'customer' => $data]);
$tags = PipelineTags::from($tagsData);
$mapper = new ObjectMapper();

$benchmark = new PipelineBenchmark($iterations, $repetitions);

$benchmark->measure('plain.constructor', static fn() => new PipelinePlainUser(...$data));
$benchmark->measure('hydrate.scalar.array', static fn() => PipelineUser::from($data));
$benchmark->measure('hydrate.scalar.named', static fn() => PipelineUser::from(...$data));
$benchmark->measure('hydrate.scalar.json', static fn() => PipelineUser::from($jsonData));
$benchmark->measure('hydrate.scalar.object', static fn() => PipelineUser::from($objectData));
$benchmark->measure('hydrate.scalar.granite', static fn() => PipelineUser::from($user));
$benchmark->measure('hydrate.array_property', static fn() => PipelineTags::from($tagsData));
$benchmark->measure('hydrate.validated', static fn() => PipelineValidatedUser::from($data));
$benchmark->measure('serialize.scalar.array_cached', static fn() => $user->array());
$benchmark->measure('serialize.scalar.json_cached', static fn() => $user->json());
$benchmark->measure('serialize.nested.array_cached', static fn() => $order->array());
$benchmark->measure('serialize.array_property', static fn() => $tags->array());
$benchmark->measure('mapper.plain', static fn() => $mapper->map($data, PipelinePlainUser::class));
$benchmark->measure('mapper.granite', static fn() => $mapper->map($data, PipelineUser::class));

$benchmark->report($json);
