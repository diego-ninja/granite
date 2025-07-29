<?php

namespace Tests\Unit\Mapping\Fixtures\Collection;

use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Mapping\MapperConfig;
use Ninja\Granite\Mapping\MappingProfile;
use Ninja\Granite\Mapping\ObjectMapper;

class CollectionMappingProfile extends MappingProfile
{
    private ?ObjectMapper $mapper = null;

    public function getMapper(): ObjectMapper
    {
        if (null === $this->mapper) {
            $this->mapper = new ObjectMapper(MapperConfig::create()->withProfile($this));
        }
        return $this->mapper;
    }

    /**
     * @throws MappingException
     */
    protected function configure(): void
    {
        // Simple array mapping
        $this->createMap('array', ProjectDTO::class);

        // Nested array mapping with collection
        $this->createMap('array', TeamDTO::class)
            ->forMember(
                'members',
                fn($mapping) =>
                $mapping->mapFrom('members')
                    ->using(function ($value) {
                        if ( ! is_array($value)) {
                            return [];
                        }
                        return $this->getMapper()->mapArray($value, TeamMemberDTO::class);
                    }),
            );

        // Entity to DTO with collection
        $this->createMap('array', ArticleDTO::class)
            ->forMember(
                'comments',
                fn($mapping) =>
                $mapping->mapFrom('comments')
                    ->using(function ($value) {
                        if ( ! is_array($value)) {
                            return [];
                        }
                        return $this->getMapper()->mapArray($value, CommentDTO::class);
                    }),
            );

        // Map with custom transformer
        $this->createMap('array', TodoListDTO::class);

        // Deeply nested collections
        $this->createMap('array', TeamNestedDTO::class)
            ->forMember(
                'members',
                fn($mapping) =>
                $mapping->mapFrom('members')
                    ->using(function ($value) {
                        if ( ! is_array($value)) {
                            return [];
                        }
                        return $this->getMapper()->mapArray($value, TeamMemberNestedDTO::class);
                    }),
            );

        $this->createMap('array', DepartmentDTO::class)
            ->forMember(
                'teams',
                fn($mapping) =>
                $mapping->mapFrom('teams')
                    ->using(function ($value) {
                        if ( ! is_array($value)) {
                            return [];
                        }
                        return $this->getMapper()->mapArray($value, TeamNestedDTO::class);
                    }),
            );

        $this->createMap('array', OrganizationDTO::class)
            ->forMember(
                'departments',
                fn($mapping) =>
                $mapping->mapFrom('departments')
                    ->using(function ($value) {
                        if ( ! is_array($value)) {
                            return [];
                        }
                        return $this->getMapper()->mapArray($value, DepartmentDTO::class);
                    }),
            );

        // Mixed collection types
        $this->createMap('array', MixedCollectionDTO::class);

        // Preserve keys in collection
        $this->createMap('array', ConfigDTO::class)
            ->forMember(
                'settings',
                fn($mapping) =>
                $mapping->mapFrom('settings'),
            );

        // Key-value mapping
        $this->createMap('array', KeyValueDTO::class)
            ->forMember(
                'mappings',
                fn($mapping) =>
                $mapping->mapFrom('mappings'),
            );
    }
}
