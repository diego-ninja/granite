<?php

namespace Tests\Unit\Mapping;

use Ninja\Granite\Mapping\AutoMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\TestCase;
use Tests\Unit\Mapping\Fixtures\Collection\ArticleDTO;
use Tests\Unit\Mapping\Fixtures\Collection\CollectionMappingProfile;
use Tests\Unit\Mapping\Fixtures\Collection\CommentDTO;
use Tests\Unit\Mapping\Fixtures\Collection\ConfigDTO;
use Tests\Unit\Mapping\Fixtures\Collection\DepartmentDTO;
use Tests\Unit\Mapping\Fixtures\Collection\KeyValueDTO;
use Tests\Unit\Mapping\Fixtures\Collection\MixedCollectionDTO;
use Tests\Unit\Mapping\Fixtures\Collection\OrganizationDTO;
use Tests\Unit\Mapping\Fixtures\Collection\ProjectDTO;
use Tests\Unit\Mapping\Fixtures\Collection\TeamDTO;
use Tests\Unit\Mapping\Fixtures\Collection\TeamMemberDTO;
use Tests\Unit\Mapping\Fixtures\Collection\TeamMemberNestedDTO;
use Tests\Unit\Mapping\Fixtures\Collection\TeamNestedDTO;
use Tests\Unit\Mapping\Fixtures\Collection\TodoListDTO;

#[CoversClass(AutoMapper::class)]
class CollectionMappingTest extends TestCase
{
    private AutoMapper $mapper;

    protected function setUp(): void
    {
        $profile = new CollectionMappingProfile();
        $this->mapper = new AutoMapper([$profile]);
        parent::setUp();
    }

    #[Test]
    public function it_maps_simple_arrays(): void
    {
        $source = [
            'id' => 1,
            'name' => 'Project X',
            'tags' => ['php', 'testing', 'mapping']
        ];

        $result = $this->mapper->map($source, ProjectDTO::class);

        $this->assertInstanceOf(ProjectDTO::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('Project X', $result->name);
        $this->assertEquals(['php', 'testing', 'mapping'], $result->tags);
    }

    #[Test]
    public function it_maps_nested_arrays(): void
    {
        $source = [
            'id' => 1,
            'name' => 'Team Alpha',
            'members' => [
                ['id' => 1, 'name' => 'John', 'role' => 'Developer'],
                ['id' => 2, 'name' => 'Jane', 'role' => 'Designer'],
                ['id' => 3, 'name' => 'Bob', 'role' => 'Manager']
            ]
        ];

        $result = $this->mapper->map($source, TeamDTO::class);

        $this->assertInstanceOf(TeamDTO::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('Team Alpha', $result->name);
        $this->assertCount(3, $result->members);
        
        $this->assertInstanceOf(TeamMemberDTO::class, $result->members[0]);
        $this->assertEquals(1, $result->members[0]->id);
        $this->assertEquals('John', $result->members[0]->name);
        $this->assertEquals('Developer', $result->members[0]->role);
        
        $this->assertInstanceOf(TeamMemberDTO::class, $result->members[1]);
        $this->assertEquals(2, $result->members[1]->id);
        $this->assertEquals('Jane', $result->members[1]->name);
        $this->assertEquals('Designer', $result->members[1]->role);
        
        $this->assertInstanceOf(TeamMemberDTO::class, $result->members[2]);
        $this->assertEquals(3, $result->members[2]->id);
        $this->assertEquals('Bob', $result->members[2]->name);
        $this->assertEquals('Manager', $result->members[2]->role);
    }

    #[Test]
    public function it_maps_entities_to_dtos_with_collections(): void
    {
        $source = [
            'id' => 1,
            'title' => 'My Article',
            'comments' => [
                ['id' => 1, 'text' => 'Great article!', 'author' => 'John'],
                ['id' => 2, 'text' => 'Thanks for sharing', 'author' => 'Jane']
            ]
        ];

        $result = $this->mapper->map($source, ArticleDTO::class);

        $this->assertInstanceOf(ArticleDTO::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('My Article', $result->title);
        $this->assertCount(2, $result->comments);
        
        $this->assertInstanceOf(CommentDTO::class, $result->comments[0]);
        $this->assertEquals(1, $result->comments[0]->id);
        $this->assertEquals('Great article!', $result->comments[0]->text);
        $this->assertEquals('John', $result->comments[0]->author);
        
        $this->assertInstanceOf(CommentDTO::class, $result->comments[1]);
        $this->assertEquals(2, $result->comments[1]->id);
        $this->assertEquals('Thanks for sharing', $result->comments[1]->text);
        $this->assertEquals('Jane', $result->comments[1]->author);
    }

    #[Test]
    public function it_maps_with_custom_transformer(): void
    {
        $source = [
            'id' => 1,
            'name' => 'My Todo List',
            'items' => [
                ['id' => 1, 'text' => 'Buy groceries', 'done' => true],
                ['id' => 2, 'text' => 'Clean house', 'done' => false],
                ['id' => 3, 'text' => 'Do laundry']
            ]
        ];

        $result = $this->mapper->map($source, TodoListDTO::class);

        $this->assertInstanceOf(TodoListDTO::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('My Todo List', $result->name);
        $this->assertCount(3, $result->items);
        
        $this->assertEquals('Buy groceries', $result->items[0]['text']);
        $this->assertTrue($result->items[0]['completed']);
        
        $this->assertEquals('Clean house', $result->items[1]['text']);
        $this->assertFalse($result->items[1]['completed']);
        
        $this->assertEquals('Do laundry', $result->items[2]['text']);
        $this->assertFalse($result->items[2]['completed']);
    }

    #[Test]
    public function it_maps_deeply_nested_collections(): void
    {
        $source = [
            'id' => 1,
            'name' => 'ACME Corp',
            'departments' => [
                [
                    'name' => 'Engineering',
                    'teams' => [
                        [
                            'name' => 'Frontend',
                            'members' => [
                                ['name' => 'Alice', 'role' => 'Lead'],
                                ['name' => 'Bob', 'role' => 'Developer']
                            ]
                        ],
                        [
                            'name' => 'Backend',
                            'members' => [
                                ['name' => 'Charlie', 'role' => 'Lead'],
                                ['name' => 'Dave', 'role' => 'Developer']
                            ]
                        ]
                    ]
                ],
                [
                    'name' => 'Marketing',
                    'teams' => [
                        [
                            'name' => 'Digital',
                            'members' => [
                                ['name' => 'Eve', 'role' => 'Lead'],
                                ['name' => 'Frank', 'role' => 'Specialist']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->mapper->map($source, OrganizationDTO::class);

        $this->assertInstanceOf(OrganizationDTO::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('ACME Corp', $result->name);
        $this->assertCount(2, $result->departments);
        
        // Engineering department
        $this->assertInstanceOf(DepartmentDTO::class, $result->departments[0]);
        $this->assertEquals('Engineering', $result->departments[0]->name);
        $this->assertCount(2, $result->departments[0]->teams);
        
        // Frontend team
        $this->assertInstanceOf(TeamNestedDTO::class, $result->departments[0]->teams[0]);
        $this->assertEquals('Frontend', $result->departments[0]->teams[0]->name);
        $this->assertCount(2, $result->departments[0]->teams[0]->members);
        
        $this->assertInstanceOf(TeamMemberNestedDTO::class, $result->departments[0]->teams[0]->members[0]);
        $this->assertEquals('Alice', $result->departments[0]->teams[0]->members[0]->name);
        $this->assertEquals('Lead', $result->departments[0]->teams[0]->members[0]->role);
        
        // Backend team
        $this->assertInstanceOf(TeamNestedDTO::class, $result->departments[0]->teams[1]);
        $this->assertEquals('Backend', $result->departments[0]->teams[1]->name);
        
        // Marketing department
        $this->assertInstanceOf(DepartmentDTO::class, $result->departments[1]);
        $this->assertEquals('Marketing', $result->departments[1]->name);
        $this->assertCount(1, $result->departments[1]->teams);
        
        // Digital team
        $this->assertInstanceOf(TeamNestedDTO::class, $result->departments[1]->teams[0]);
        $this->assertEquals('Digital', $result->departments[1]->teams[0]->name);
    }

    #[Test]
    public function it_preserves_keys_in_collections(): void
    {
        $source = [
            'id' => 1,
            'name' => 'App Config',
            'settings' => [
                'debug' => true,
                'cache' => false,
                'timeout' => 30,
                'retries' => 3
            ]
        ];

        $result = $this->mapper->map($source, ConfigDTO::class);

        $this->assertInstanceOf(ConfigDTO::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('App Config', $result->name);
        $this->assertCount(4, $result->settings);
        
        $this->assertArrayHasKey('debug', $result->settings);
        $this->assertTrue($result->settings['debug']);
        
        $this->assertArrayHasKey('cache', $result->settings);
        $this->assertFalse($result->settings['cache']);
        
        $this->assertArrayHasKey('timeout', $result->settings);
        $this->assertEquals(30, $result->settings['timeout']);
        
        $this->assertArrayHasKey('retries', $result->settings);
        $this->assertEquals(3, $result->settings['retries']);
    }

    #[Test]
    public function it_extracts_keys_and_values(): void
    {
        $source = [
            'id' => 1,
            'mappings' => [
                'key1' => 'value1',
                'key2' => 'value2',
                'key3' => 'value3'
            ]
        ];

        $result = $this->mapper->map($source, KeyValueDTO::class);

        $this->assertInstanceOf(KeyValueDTO::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals(['key1', 'key2', 'key3'], $result->keys);
        $this->assertEquals(['value1', 'value2', 'value3'], $result->values);
    }
}
