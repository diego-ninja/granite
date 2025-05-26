<?php

namespace Ninja\Granite\Mapping\Conventions;

use Ninja\Granite\Mapping\Contracts\NamingConvention;

class ConventionRegistry
{
    /**
     * @var array<string, NamingConvention>
     */
    private array $conventions = [];
    
    public function __construct()
    {
        // Registrar convenciones predeterminadas
        $this->register(new CamelCaseConvention());
        $this->register(new PascalCaseConvention());
        $this->register(new SnakeCaseConvention());
        $this->register(new KebabCaseConvention());
        $this->register(new PrefixConvention());
        $this->register(new AbbreviationConvention());
    }
    
    /**
     * Registra una nueva convención.
     */
    public function register(NamingConvention $convention): void
    {
        $this->conventions[$convention->getName()] = $convention;
    }
    
    /**
     * Obtiene una convención por su nombre.
     */
    public function get(string $name): ?NamingConvention
    {
        return $this->conventions[$name] ?? null;
    }
    
    /**
     * Obtiene todas las convenciones registradas.
     */
    public function getAll(): array
    {
        return $this->conventions;
    }
}
