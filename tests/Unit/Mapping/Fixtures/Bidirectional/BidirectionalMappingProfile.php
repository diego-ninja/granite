<?php

namespace Tests\Unit\Mapping\Fixtures\Bidirectional;

use Ninja\Granite\Exceptions\GraniteException;
use Ninja\Granite\Mapping\MapperConfig;
use Ninja\Granite\Mapping\ObjectMapper;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Mapping\MappingProfile;

class BidirectionalMappingProfile extends MappingProfile
{
    /**
     * @throws GraniteException
     * @throws MappingException
     */
    public function configure(): void
    {
        // UserEntity <-> UserDTO mapping
        $this->createMap(UserEntity::class, UserDTO::class)
            ->forMember('fullName', function($mapping) {
                $mapping->using(function($value, $source) {
                    return trim(($source['firstName'] ?? '') . ' ' . ($source['lastName'] ?? ''));
                });
            })
            ->forMember('email', function($mapping) {
                $mapping->mapFrom('emailAddress');
            });

        $this->createMap(UserDTO::class, UserEntity::class)
            ->forMember('firstName', function($mapping) {
                $mapping->using(function($value, $source) {
                    $parts = explode(' ', $source['fullName'] ?? '', 2);
                    return $parts[0] ?? null;
                });
            })
            ->forMember('lastName', function($mapping) {
                $mapping->using(function($value, $source) {
                    $parts = explode(' ', $source['fullName'] ?? '', 2);
                    return $parts[1] ?? null;
                });
            })
            ->forMember('emailAddress', function($mapping) {
                $mapping->mapFrom('email');
            });

        // OrderItemEntity <-> OrderItemDTO mapping
        $this->createMap(OrderItemEntity::class, OrderItemDTO::class)
            ->forMember('product', function($mapping) {
                $mapping->mapFrom('productName');
            })
            ->forMember('qty', function($mapping) {
                $mapping->mapFrom('quantity');
            })
            ->forMember('price', function($mapping) {
                $mapping->mapFrom('unitPrice');
            })
            ->forMember('subtotal', function($mapping) {
                $mapping->using(function($value, $source) {
                    return $source['quantity'] * $source['unitPrice'];
                });
            });

        $this->createMap(OrderItemDTO::class, OrderItemEntity::class)
            ->forMember('id', function($mapping) {
                $mapping->defaultValue(0); // Use default value instead of ignore
            })
            ->forMember('productName', function($mapping) {
                $mapping->mapFrom('product');
            })
            ->forMember('quantity', function($mapping) {
                $mapping->mapFrom('qty');
            })
            ->forMember('unitPrice', function($mapping) {
                $mapping->mapFrom('price');
            });

        // OrderEntity <-> OrderDTO mapping
        $this->createMap(OrderEntity::class, OrderDTO::class)
            ->forMember('number', function($mapping) {
                $mapping->mapFrom('orderNumber');
            })
            ->forMember('customerName', function($mapping) {
                $mapping->using(function($value, $source) {
                    $customer = $source['customer'] ?? null;
                    if (!$customer) return '';
                    return trim(($customer->firstName ?? '') . ' ' . ($customer->lastName ?? ''));
                });
            })
            ->forMember('items', function($mapping) {
                $mapping->using(function($value, $source) {
                    $items = $source['items'] ?? [];
                    $mapper = new ObjectMapper(MapperConfig::create()->withProfile($this));
                    return $mapper->mapArray($items, OrderItemDTO::class);
                });
            })
            ->forMember('total', function($mapping) {
                $mapping->mapFrom('totalAmount');
            });

        $this->createMap(OrderDTO::class, OrderEntity::class)
            ->forMember('id', function($mapping) {
                $mapping->defaultValue(0); // Use default value instead of ignore
            })
            ->forMember('orderNumber', function($mapping) {
                $mapping->mapFrom('number');
            })
            ->forMember('customer', function($mapping) {
                $mapping->using(function($value, $source) {
                    // Create a minimal user entity from the customer name
                    $parts = explode(' ', $source['customerName'] ?? '', 2);
                    return new UserEntity(
                        id: 0, // Placeholder ID
                        firstName: $parts[0] ?? '',
                        lastName: $parts[1] ?? '',
                        emailAddress: '' // No email in the DTO
                    );
                });
            })
            ->forMember('items', function($mapping) {
                $mapping->using(function($value, $source) {
                    $items = $source['items'] ?? [];
                    $mapper = new ObjectMapper(MapperConfig::create()->withProfile($this));
                    return $mapper->mapArray($items, OrderItemEntity::class);
                });
            })
            ->forMember('totalAmount', function($mapping) {
                $mapping->mapFrom('total');
            });
    }
}
