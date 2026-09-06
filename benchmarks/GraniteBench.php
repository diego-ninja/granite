<?php

// ABOUTME: Benchmark comparing Granite DTOs vs plain PHP for object creation, serialization, and access.
// ABOUTME: Run with: php benchmarks/GraniteBench.php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Ninja\Granite\Granite;
use Ninja\Granite\Pebble;
use Ninja\Granite\Validation\Attributes\Email;
use Ninja\Granite\Validation\Attributes\Min;
use Ninja\Granite\Validation\Attributes\Required;

// --- Fixture classes ---

final readonly class PlainUser
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public int $age,
        public string $role,
        public bool $active,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'age' => $this->age,
            'role' => $this->role,
            'active' => $this->active,
        ];
    }
}

final readonly class PlainAddress
{
    public function __construct(
        public string $street,
        public string $city,
        public string $country,
        public string $zipCode,
    ) {}

    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'country' => $this->country,
            'zipCode' => $this->zipCode,
        ];
    }
}

final readonly class PlainOrder
{
    public function __construct(
        public int $id,
        public string $status,
        public PlainUser $customer,
        public PlainAddress $shippingAddress,
        public float $total,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'customer' => $this->customer->toArray(),
            'shippingAddress' => $this->shippingAddress->toArray(),
            'total' => $this->total,
        ];
    }
}

final readonly class GraniteUser extends Granite
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

final readonly class GraniteAddress extends Granite
{
    public function __construct(
        public string $street,
        public string $city,
        public string $country,
        public string $zipCode,
    ) {}
}

final readonly class GraniteOrder extends Granite
{
    public function __construct(
        public int $id,
        public string $status,
        public GraniteUser $customer,
        public GraniteAddress $shippingAddress,
        public float $total,
    ) {}
}

final readonly class ValidatedUser extends Granite
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

// --- Benchmark runner ---

final class BenchmarkRunner
{
    private array $results = [];

    public function bench(string $name, callable $fn, int $iterations = 10000): void
    {
        // Warmup
        for ($i = 0; $i < min(100, $iterations); $i++) {
            $fn();
        }

        // GC before timing
        gc_collect_cycles();
        gc_disable();

        $memBefore = memory_get_usage();
        $start = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $fn();
        }

        $elapsed = (hrtime(true) - $start) / 1e9;
        $memAfter = memory_get_usage();

        gc_enable();

        $perOp = ($elapsed / $iterations) * 1e6; // microseconds
        $opsPerSec = $iterations / $elapsed;
        $memDelta = ($memAfter - $memBefore) / 1024;

        $this->results[$name] = [
            'total_s' => $elapsed,
            'per_op_us' => $perOp,
            'ops_sec' => $opsPerSec,
            'mem_kb' => $memDelta,
            'iterations' => $iterations,
        ];
    }

    public function report(): void
    {
        echo str_repeat('=', 90) . "\n";
        echo "GRANITE BENCHMARK RESULTS\n";
        echo sprintf("PHP %s | %s\n", PHP_VERSION, PHP_OS);
        echo str_repeat('=', 90) . "\n\n";

        $groups = [];
        foreach ($this->results as $name => $data) {
            $parts = explode(' :: ', $name, 2);
            $group = $parts[0];
            $label = $parts[1] ?? $name;
            $groups[$group][$label] = $data;
        }

        foreach ($groups as $group => $benchmarks) {
            echo "── {$group} " . str_repeat('─', max(0, 86 - mb_strlen($group))) . "\n";
            echo sprintf(
                "  %-35s %10s %12s %10s\n",
                'Benchmark',
                'µs/op',
                'ops/sec',
                'mem (KB)',
            );
            echo str_repeat('─', 75) . "\n";

            $baseline = null;
            foreach ($benchmarks as $label => $data) {
                if (null === $baseline) {
                    $baseline = $data['per_op_us'];
                }
                $ratio = $data['per_op_us'] / $baseline;
                $ratioStr = $ratio > 1.05 ? sprintf(' (%.1fx)', $ratio) : '';

                echo sprintf(
                    "  %-35s %10.2f %12s %10.1f%s\n",
                    $label,
                    $data['per_op_us'],
                    number_format((int) $data['ops_sec']),
                    $data['mem_kb'],
                    $ratioStr,
                );
            }
            echo "\n";
        }
    }
}

// --- Test data ---

$simpleData = [
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
    'role' => 'admin',
    'active' => true,
];

$nestedData = [
    'id' => 100,
    'status' => 'confirmed',
    'customer' => [
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
        'role' => 'admin',
        'active' => true,
    ],
    'shippingAddress' => [
        'street' => '123 Main St',
        'city' => 'Springfield',
        'country' => 'US',
        'zipCode' => '62701',
    ],
    'total' => 299.99,
];

$runner = new BenchmarkRunner();
$iterations = 50_000;

// ============================================================
// 1. Simple object creation (6 fields)
// ============================================================

$runner->bench('Creation (simple, 6 fields) :: Plain PHP constructor', function () use ($simpleData): void {
    new PlainUser(
        $simpleData['id'],
        $simpleData['name'],
        $simpleData['email'],
        $simpleData['age'],
        $simpleData['role'],
        $simpleData['active'],
    );
}, $iterations);

$runner->bench('Creation (simple, 6 fields) :: Granite::from(array)', function () use ($simpleData): void {
    GraniteUser::from($simpleData);
}, $iterations);

$runner->bench('Creation (simple, 6 fields) :: Granite::from(named args)', function () use ($simpleData): void {
    GraniteUser::from(
        id: $simpleData['id'],
        name: $simpleData['name'],
        email: $simpleData['email'],
        age: $simpleData['age'],
        role: $simpleData['role'],
        active: $simpleData['active'],
    );
}, $iterations);

$runner->bench('Creation (simple, 6 fields) :: Pebble::from(array)', function () use ($simpleData): void {
    Pebble::from($simpleData);
}, $iterations);

$runner->bench('Creation (simple, 6 fields) :: Plain array (baseline)', function () use ($simpleData): void {
    $user = $simpleData;
}, $iterations);

// ============================================================
// 2. Nested object creation
// ============================================================

$runner->bench('Creation (nested, 3 objects) :: Plain PHP constructors', function () use ($nestedData): void {
    $customer = new PlainUser(
        $nestedData['customer']['id'],
        $nestedData['customer']['name'],
        $nestedData['customer']['email'],
        $nestedData['customer']['age'],
        $nestedData['customer']['role'],
        $nestedData['customer']['active'],
    );
    $address = new PlainAddress(
        $nestedData['shippingAddress']['street'],
        $nestedData['shippingAddress']['city'],
        $nestedData['shippingAddress']['country'],
        $nestedData['shippingAddress']['zipCode'],
    );
    new PlainOrder($nestedData['id'], $nestedData['status'], $customer, $address, $nestedData['total']);
}, $iterations);

$runner->bench('Creation (nested, 3 objects) :: Granite::from(array)', function () use ($nestedData): void {
    GraniteOrder::from($nestedData);
}, $iterations);

$runner->bench('Creation (nested, 3 objects) :: Pebble::from(array)', function () use ($nestedData): void {
    Pebble::from($nestedData);
}, $iterations);

// ============================================================
// 3. Creation with validation
// ============================================================

$runner->bench('Creation + validation :: Granite (no validation)', function () use ($simpleData): void {
    GraniteUser::from($simpleData);
}, $iterations);

$runner->bench('Creation + validation :: Granite (5 rules)', function () use ($simpleData): void {
    ValidatedUser::from($simpleData);
}, $iterations);

// ============================================================
// 4. Serialization (toArray)
// ============================================================

$graniteUser = GraniteUser::from($simpleData);
$plainUser = new PlainUser(...$simpleData);
$pebbleUser = Pebble::from($simpleData);
$graniteOrder = GraniteOrder::from($nestedData);
$plainOrder = new PlainOrder(
    $nestedData['id'],
    $nestedData['status'],
    new PlainUser(...$nestedData['customer']),
    new PlainAddress(...$nestedData['shippingAddress']),
    $nestedData['total'],
);

$runner->bench('Serialization (simple) :: Plain PHP toArray()', function () use ($plainUser): void {
    $plainUser->toArray();
}, $iterations);

$runner->bench('Serialization (simple) :: Granite array()', function () use ($graniteUser): void {
    $graniteUser->array();
}, $iterations);

$runner->bench('Serialization (simple) :: Pebble array()', function () use ($pebbleUser): void {
    $pebbleUser->array();
}, $iterations);

$runner->bench('Serialization (nested) :: Plain PHP toArray()', function () use ($plainOrder): void {
    $plainOrder->toArray();
}, $iterations);

$runner->bench('Serialization (nested) :: Granite array()', function () use ($graniteOrder): void {
    $graniteOrder->array();
}, $iterations);

// ============================================================
// 5. JSON serialization
// ============================================================

$runner->bench('JSON encoding :: Plain PHP json_encode(array)', function () use ($simpleData): void {
    json_encode($simpleData);
}, $iterations);

$runner->bench('JSON encoding :: Granite json()', function () use ($graniteUser): void {
    $graniteUser->json();
}, $iterations);

$runner->bench('JSON encoding :: Pebble json()', function () use ($pebbleUser): void {
    $pebbleUser->json();
}, $iterations);

// ============================================================
// 6. Property access (10 reads)
// ============================================================

$runner->bench('Property access (10 reads) :: Plain PHP readonly', function () use ($plainUser): void {
    $plainUser->id;
    $plainUser->name;
    $plainUser->email;
    $plainUser->age;
    $plainUser->role;
    $plainUser->active;
    $plainUser->id;
    $plainUser->name;
    $plainUser->email;
    $plainUser->age;
}, $iterations);

$runner->bench('Property access (10 reads) :: Granite readonly', function () use ($graniteUser): void {
    $graniteUser->id;
    $graniteUser->name;
    $graniteUser->email;
    $graniteUser->age;
    $graniteUser->role;
    $graniteUser->active;
    $graniteUser->id;
    $graniteUser->name;
    $graniteUser->email;
    $graniteUser->age;
}, $iterations);

$runner->bench('Property access (10 reads) :: Pebble __get()', function () use ($pebbleUser): void {
    $pebbleUser->id;
    $pebbleUser->name;
    $pebbleUser->email;
    $pebbleUser->age;
    $pebbleUser->role;
    $pebbleUser->active;
    $pebbleUser->id;
    $pebbleUser->name;
    $pebbleUser->email;
    $pebbleUser->age;
}, $iterations);

$runner->bench('Property access (10 reads) :: Plain array', function () use ($simpleData): void {
    $simpleData['id'];
    $simpleData['name'];
    $simpleData['email'];
    $simpleData['age'];
    $simpleData['role'];
    $simpleData['active'];
    $simpleData['id'];
    $simpleData['name'];
    $simpleData['email'];
    $simpleData['age'];
}, $iterations);

// ============================================================
// 7. Comparison
// ============================================================

$graniteUser2 = GraniteUser::from($simpleData);
$pebbleUser2 = Pebble::from($simpleData);

$runner->bench('Equality check :: Granite equals()', function () use ($graniteUser, $graniteUser2): void {
    $graniteUser->equals($graniteUser2);
}, $iterations);

$runner->bench('Equality check :: Pebble equals() (fingerprint)', function () use ($pebbleUser, $pebbleUser2): void {
    $pebbleUser->equals($pebbleUser2);
}, $iterations);

$runner->bench('Equality check :: Plain array (===)', function () use ($simpleData): void {
    $copy = $simpleData;
    $simpleData === $copy;
}, $iterations);

// ============================================================
// 8. Collection creation (100 items)
// ============================================================

$collectionData = [];
for ($i = 0; $i < 100; $i++) {
    $collectionData[] = [
        'id' => $i,
        'name' => "User {$i}",
        'email' => "user{$i}@example.com",
        'age' => 20 + ($i % 50),
        'role' => 'user',
        'active' => 0 === $i % 2,
    ];
}

$collectionIterations = 5_000;

$runner->bench('Collection (100 items) :: Plain PHP array_map', function () use ($collectionData): void {
    array_map(fn($d) => new PlainUser($d['id'], $d['name'], $d['email'], $d['age'], $d['role'], $d['active']), $collectionData);
}, $collectionIterations);

$runner->bench('Collection (100 items) :: Granite array_map', function () use ($collectionData): void {
    array_map(fn($d) => GraniteUser::from($d), $collectionData);
}, $collectionIterations);

$runner->bench('Collection (100 items) :: Pebble array_map', function () use ($collectionData): void {
    array_map(fn($d) => Pebble::from($d), $collectionData);
}, $collectionIterations);

// --- Report ---

$runner->report();
