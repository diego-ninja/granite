<?php

// tests/Fixtures/DTOs/CustomSerializationDTO.php

declare(strict_types=1);

namespace Tests\Fixtures\DTOs;

use Ninja\Granite\GraniteDTO;

final readonly class CustomSerializationDTO extends GraniteDTO
{
    public function __construct(
        public string $name,
        public string $description,
        public array $settings = [],
        public string $internalCode = '',
        public \DateTimeInterface $createdAt = new \DateTimeImmutable()
    ) {}

    protected static function serializedNames(): array
    {
        return [
            'name' => 'display_name',
            'description' => 'desc',
            'createdAt' => 'created_timestamp'
        ];
    }

    protected static function hiddenProperties(): array
    {
        return ['internalCode'];
    }

    public function array(): array
    {
        $data = parent::array();

        // Custom serialization logic
        if (isset($data['settings'])) {
            $data['settings_json'] = json_encode($data['settings']);
            unset($data['settings']);
        }

        if (isset($data['created_timestamp'])) {
            $data['created_unix'] = strtotime($data['created_timestamp']);
        }

        return $data;
    }
}