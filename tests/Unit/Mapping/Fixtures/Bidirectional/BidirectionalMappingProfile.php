<?php

namespace Tests\Unit\Mapping\Fixtures\Bidirectional;

use Ninja\Granite\Exceptions\GraniteException;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Mapping\MapperConfig;
use Ninja\Granite\Mapping\MappingProfile;
use Ninja\Granite\Mapping\ObjectMapper;

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
            ->forMember('fullName', function ($mapping): void {
                $mapping->using(fn($value, $source) => trim(($source['firstName'] ?? '') . ' ' . ($source['lastName'] ?? '')));
            })
            ->forMember('email', function ($mapping): void {
                $mapping->mapFrom('emailAddress');
            });

        $this->createMap(UserDTO::class, UserEntity::class)
            ->forMember('firstName', function ($mapping): void {
                $mapping->using(function ($value, $source) {
                    $parts = explode(' ', $source['fullName'] ?? '', 2);
                    return $parts[0] ?? null;
                });
            })
            ->forMember('lastName', function ($mapping): void {
                $mapping->using(function ($value, $source) {
                    $parts = explode(' ', $source['fullName'] ?? '', 2);
                    return $parts[1] ?? null;
                });
            })
            ->forMember('emailAddress', function ($mapping): void {
                $mapping->mapFrom('email');
            });

        // OrderItemEntity <-> OrderItemDTO mapping
        $this->createMap(OrderItemEntity::class, OrderItemDTO::class)
            ->forMember('product', function ($mapping): void {
                $mapping->mapFrom('productName');
            })
            ->forMember('qty', function ($mapping): void {
                $mapping->mapFrom('quantity');
            })
            ->forMember('price', function ($mapping): void {
                $mapping->mapFrom('unitPrice');
            })
            ->forMember('subtotal', function ($mapping): void {
                $mapping->using(fn($value, $source) => $source['quantity'] * $source['unitPrice']);
            });

        $this->createMap(OrderItemDTO::class, OrderItemEntity::class)
            ->forMember('id', function ($mapping): void {
                $mapping->defaultValue(0); // Use default value instead of ignore
            })
            ->forMember('productName', function ($mapping): void {
                $mapping->mapFrom('product');
            })
            ->forMember('quantity', function ($mapping): void {
                $mapping->mapFrom('qty');
            })
            ->forMember('unitPrice', function ($mapping): void {
                $mapping->mapFrom('price');
            });

        // OrderEntity <-> OrderDTO mapping
        $this->createMap(OrderEntity::class, OrderDTO::class)
            ->forMember('number', function ($mapping): void {
                $mapping->mapFrom('orderNumber');
            })
            ->forMember('customerName', function ($mapping): void {
                $mapping->using(function ($value, $source) {
                    $customer = $source['customer'] ?? null;
                    if ( ! $customer) {
                        return '';
                    }
                    return trim(($customer->firstName ?? '') . ' ' . ($customer->lastName ?? ''));
                });
            })
            ->forMember('items', function ($mapping): void {
                $mapping->using(function ($value, $source) {
                    $items = $source['items'] ?? [];
                    $mapper = new ObjectMapper(MapperConfig::create()->withProfile($this));
                    return $mapper->mapArray($items, OrderItemDTO::class);
                });
            })
            ->forMember('total', function ($mapping): void {
                $mapping->mapFrom('totalAmount');
            });

        $this->createMap(OrderDTO::class, OrderEntity::class)
            ->forMember('id', function ($mapping): void {
                $mapping->defaultValue(0); // Use default value instead of ignore
            })
            ->forMember('orderNumber', function ($mapping): void {
                $mapping->mapFrom('number');
            })
            ->forMember('customer', function ($mapping): void {
                $mapping->using(function ($value, $source) {
                    // Create a minimal user entity from the customer name
                    $parts = explode(' ', $source['customerName'] ?? '', 2);
                    return new UserEntity(
                        id: 0, // Placeholder ID
                        firstName: $parts[0] ?? '',
                        lastName: $parts[1] ?? '',
                        emailAddress: '', // No email in the DTO
                    );
                });
            })
            ->forMember('items', function ($mapping): void {
                $mapping->using(function ($value, $source) {
                    $items = $source['items'] ?? [];
                    $mapper = new ObjectMapper(MapperConfig::create()->withProfile($this));
                    return $mapper->mapArray($items, OrderItemEntity::class);
                });
            })
            ->forMember('totalAmount', function ($mapping): void {
                $mapping->mapFrom('total');
            });
    }
}
