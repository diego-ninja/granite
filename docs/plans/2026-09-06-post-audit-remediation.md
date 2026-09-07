# Granite Post-Audit Remediation Implementation Plan

> **For agents:** REQUIRED SUB-SKILL: Use viterbit:executing-plans to implement this plan task-by-task.

**Goal:** Resolver todos los defectos de seguridad, aislamiento, inmutabilidad, validación, mapping, Carbon, tooling y documentación detectados tras la primera remediación.

**Architecture:** La caché de mapping se tratará como datos no confiables y se segmentará por una huella determinista de configuración/código. El estado de objetos se extraerá por nombres PHP y valores raw para separar dominio de serialización. Pebble aplicará snapshots defensivos y los contratos inválidos fallarán temprano con excepciones contextuales.

**Tech Stack:** PHP 8.3-8.5, PHPUnit 11, PHPStan max, Laravel Pint, Composer y GitHub Actions.

---

## Decisiones de compatibilidad

1. `Granite::with()` se añade al tipo base y reutiliza el pipeline `from()`, preservando propiedades ocultas, aliases, enums y precisión temporal.
2. La caché persistente nunca reconstruye callables desde disco. Las configuraciones con `transformer` o `condition` no nulos permanecen sólo en memoria.
3. Los caches shared/persistent conservan reutilización únicamente entre configuraciones con la misma huella; mappings mutados generan una huella nueva.
4. `ObjectFactory::populate()` mantiene identidad y rollback cuando el runtime permite escritura raw; cualquier rollback imposible termina en `MappingException`, nunca en estado parcial silencioso.
5. `fromNamedParameters()` conserva `null` explícito. Los wrappers que usen `get_defined_vars()` deben pasar sólo los argumentos que deseen sobrescribir.
6. Los cambios observables se documentan como siguiente major/minor coherente con los tags existentes; no se reescribe el historial de `v1.7.0`.

## Trazabilidad

| ID | Finding | Task |
|---|---|---|
| P01 | Cache persistente permite strings callable ejecutables | 1 |
| P02 | Cache shared contamina mappers con configuración distinta | 1 |
| P03 | Cache persistente carece de fingerprint de config/código | 1 |
| P04 | Ruta temporal global y extensión `.php` predecible | 1 |
| P05 | Reglas heredadas permiten un fast path sin validación | 2 |
| P06 | `GraniteObject` mutable se considera cacheable | 2 |
| P07 | `Granite::with()` está documentado pero no existe | 2 |
| P08 | `GraniteVO::equals()/with()` usan proyección serializada | 2 |
| P09 | Validación pública usa aliases/hidden serializados | 2 |
| P10 | Pebble retiene referencias mutables y fingerprint stale | 3 |
| P11 | Fingerprint colisiona entre fechas/enums semánticamente distintos | 3 |
| P12 | `Pebble::get()` confunde clave presente a null con ausencia | 3 |
| P13 | Pebble llama `toArray()` privado | 3 |
| P14 | Mutar un `MappingProfile` no invalida configuración | 4 |
| P15 | Rollback de `ObjectFactory` puede fallar con property hooks | 4 |
| P16 | `CollectionTransformer` ignora item transformer inválido | 4 |
| P17 | `SerializationConvention` acepta clases inválidas/no instanciables | 4 |
| P18 | Posicional `null` se descarta en deserialización | 4 |
| P19 | Carbon global no se aplica sin atributos | 5 |
| P20 | Detección relativa confunde offsets ISO | 5 |
| P21 | `CarbonRange`/`CarbonRelative` no componen precedencia | 5 |
| P22 | `RuleParser` rompe regex con alternación y regex inválida avisa tarde | 5 |
| P23 | `ValueComparator` usa `serialize()` sobre objetos arbitrarios | 5 |
| P24 | Branches `develop` no ejecutan todos los workflows | 6 |
| P25 | Xdebug se habilita en toda la matriz | 6 |
| P26 | Integraciones UUID quedan skipped | 6 |
| P27 | PHPUnit no falla por warning/risky ni hay suelo de cobertura | 6 |
| P28 | Tests Carbon/alias contienen aserciones vacuas o ambiguas | 6 |
| P29 | Versiones y ejemplos de docs/changelog divergen | 6 |
| P30 | Headers ABOUTME duplicados y checker permisivo | 6 |
| P31 | Dependencias dev directas no utilizadas | 6 |

### Task 1: Blindar y aislar las cachés de mapping

**Files:**

- Modify: `src/Mapping/Cache/PersistentMappingCache.php`
- Modify: `src/Mapping/Cache/CacheFactory.php`
- Modify: `src/Mapping/Core/ConfigurationBuilder.php`
- Modify: `src/Mapping/ObjectMapper.php`
- Modify: `src/Mapping/MappingPreloader.php`
- Modify: `src/Mapping/MappingProfile.php`
- Modify: `src/Mapping/Traits/MappingStorageTrait.php`
- Test: `tests/Unit/Mapping/Cache/PersistentMappingCacheTest.php`
- Test: `tests/Unit/Mapping/Cache/CacheFactoryTest.php`
- Test: `tests/Unit/Mapping/Cache/SharedMappingCacheTest.php`
- Test: `tests/Unit/Mapping/Core/ConfigurationBuilderTest.php`
- Create: `tests/Unit/Mapping/MappingPreloaderTest.php`

**Steps:**

1. Añadir una regresión que inyecte `transformer: "strtoupper"` y `condition: "system"` en JSON; `get()` debe ser miss y ningún callable debe ejecutarse.
2. Añadir dos `ObjectMapper` shared con mappings incompatibles; cada uno debe conservar su resultado tras alternar llamadas.
3. Añadir un test de cache persistente que cambie mapping/profile o contenido de clase y confirme miss por huella.
4. Ejecutar los tests y confirmar los fallos por ejecución/contaminación actuales.
5. Versionar el payload y validar un schema cerrado por propiedad: claves conocidas, tipos exactos y `transformer`/`condition` nulos.
6. Calcular en `ConfigurationBuilder` una huella estable de convenciones, profiles, mappings declarativos y archivos de clases; usarla como namespace de la pareja source/destination.
7. Exponer un revision counter en `MappingStorageTrait`/`MappingProfile` para detectar mutaciones sin reflection.
8. Cambiar el default a un `.json` por proyecto bajo temp; crear directorio privado cuando sea posible.
9. Encapsular la consulta namespaced detrás de `ObjectMapper::hasCachedConfiguration()` y hacer que `MappingPreloader` deje de inspeccionar claves raw.
10. Ejecutar:

   ```bash
   vendor/bin/phpunit tests/Unit/Mapping/Cache tests/Unit/Mapping/Core/ConfigurationBuilderTest.php
   ```

### Task 2: Unificar estado raw, updates, comparación y validación

**Files:**

- Create: `src/Support/ObjectState.php`
- Modify: `src/Granite.php`
- Modify: `src/GraniteVO.php`
- Modify: `src/Traits/HasValidation.php`
- Modify: `src/Support/ClassProfile.php`
- Modify: `src/Serialization/SerializationCachePolicy.php`
- Test: `tests/Unit/GraniteVOTest.php`
- Test: `tests/Unit/Traits/HasValidationTest.php`
- Test: `tests/Unit/Support/ClassProfileTest.php`
- Test: `tests/Unit/Serialization/MetadataCacheTest.php`

**Steps:**

1. Escribir regresiones para reglas heredadas, implementor mutable de `GraniteObject`, `Granite::with()`, hidden/alias y fechas con microsegundos.
2. Confirmar RED en los test files afectados.
3. Implementar `ObjectState::extract()` con propiedades públicas inicializadas y nombres PHP canónicos.
4. Añadir `Granite::with()` como merge raw + `static::from()`; hacer que GraniteVO delegue comparación Granite al comparador profundo y updates al padre.
5. Usar estado raw en `validate()`, `getValidationErrors()` y `getValidationException()`.
6. Deshabilitar fast path cuando `rules()` esté declarado en cualquier subclase de `Granite`.
7. Exigir `ReflectionClass::isReadOnly()` para cachear implementores de `GraniteObject`.
8. Ejecutar los tests focalizados y PHPStan sobre los archivos.

### Task 3: Convertir Pebble en snapshot defensivo real

**Files:**

- Modify: `src/Pebble.php`
- Test: `tests/Unit/PebbleTest.php`

**Steps:**

1. Añadir tests que muten arrays/objetos/`DateTime` después de `Pebble::from()` y a través de valores devueltos por `array()`, `get()`, `__get()` y `offsetGet()`.
2. Añadir fingerprints distintos para microsegundos/timezone/clase de fecha y clase de enum.
3. Añadir test de clave presente con `null` y default no nulo.
4. Añadir objeto con `private toArray()` y confirmar que cae al adapter público disponible.
5. Ejecutar y observar RED.
6. Snapshotear recursivamente input mutable y devolver copias defensivas en todos los accessors; rechazar recursos no snapshotables.
7. Canonicalizar fechas con clase, `U.u` y timezone; enums con clase y `name/value`; objetos con clase y estado público snapshotteado.
8. Sustituir `method_exists()` por `is_callable()` para adapters.
9. Ejecutar `vendor/bin/phpunit tests/Unit/PebbleTest.php`.

### Task 4: Endurecer mutaciones y contratos del mapper

**Files:**

- Modify: `src/Mapping/Core/ConfigurationBuilder.php`
- Modify: `src/Mapping/Core/ObjectFactory.php`
- Modify: `src/Transformers/CollectionTransformer.php`
- Modify: `src/Serialization/Attributes/SerializationConvention.php`
- Modify: `src/Traits/HasNamingConventions.php`
- Modify: `src/Traits/HasDeserialization.php`
- Test: `tests/Unit/Mapping/Core/ConfigurationBuilderTest.php`
- Test: `tests/Unit/Mapping/Core/ObjectFactoryTest.php`
- Test: `tests/Unit/Transformers/CollectionTransformerTest.php`
- Test: `tests/Unit/Serialization/SerializationConventionTest.php`
- Test: `tests/Unit/Traits/HasDeserializationTest.php`

**Steps:**

1. Escribir tests para mutación tardía de profile, rollback con property hook, transformer inválido, convention inválida y null explícito posicional/named.
2. Confirmar RED.
3. Refrescar cache namespace cuando cambie revision de profile.
4. Aplicar rollback con `ReflectionProperty::setRawValue()` cuando exista y fallback seguro en PHP 8.3; conservar la excepción original como `previous`.
5. Validar `itemTransformer` en constructor y convention class en `getConvention()` (subtipo, instanciable, constructor válido).
6. Usar `array_key_exists()` para argumentos posicionales y preservar null en `fromNamedParameters()`.
7. Ejecutar tests focalizados en PHP actual y conservar compatibilidad PHP 8.3 por feature detection.

### Task 5: Corregir Carbon, regex y comparación estructural

**Files:**

- Modify: `src/Serialization/CarbonTransformerFactory.php`
- Modify: `src/Traits/HasCarbonSupport.php`
- Modify: `src/Transformers/CarbonTransformer.php`
- Modify: `src/Validation/RuleParser.php`
- Modify: `src/Validation/Rules/Regex.php`
- Modify: `src/Support/ValueComparator.php`
- Test: `tests/Unit/Serialization/CarbonIntegrationTest.php`
- Test: `tests/Unit/Traits/HasCarbonSupportTest.php`
- Test: `tests/Unit/Transformers/CarbonTransformerTest.php`
- Test: `tests/Unit/Validation/RuleParserTest.php`
- Test: `tests/Unit/Validation/Rules/RegexTest.php`
- Test: `tests/Unit/Support/ValueComparatorTest.php`

**Steps:**

1. Añadir regresiones exactas para global Carbon sin atributos, ISO con offset, composición de atributos y base date.
2. Añadir regex con `|`, delimitadores escapados e inválida que debe lanzar sin warning.
3. Añadir objetos con closure/recurso/ciclos a `ValueComparator` y confirmar que no ejecuta serialización mágica ni lanza.
4. Confirmar RED.
5. Crear transformer desde config global cuando el target sea Carbon o exista configuración efectiva; hacer que atributos especializados prevalezcan campo a campo.
6. Usar `Carbon::hasRelativeKeywords()` cuando esté disponible, con fallback léxico estricto.
7. Tokenizar reglas respetando el delimitador regex y validar patrón en constructor.
8. Reemplazar `serialize()` por comparación estructural recursiva con guard de pares visitados.
9. Ejecutar `vendor/bin/phpunit --filter='Carbon|RuleParser|Regex|ValueComparator'`.

### Task 6: Alinear calidad, dependencias, CI y documentación

**Files:**

- Modify: `composer.json`
- Modify: `tests/bootstrap.php`
- Modify: `phpunit.xml`
- Create: `tools/check-coverage.php`
- Modify: `tools/check-aboutme.php`
- Modify: `.github/workflows/tests.yml`
- Modify: `.github/workflows/security.yml`
- Modify: `.github/workflows/static-analysis.yml`
- Modify: `.github/workflows/code-style.yml`
- Modify: `CHANGELOG.md`
- Modify: `README.md`
- Modify: `docs/api_reference.md`
- Modify: `docs/serialization.md`
- Modify: `docs/hydration.md`
- Modify: `docs/automapper.md`
- Modify: affected weak tests and duplicate ABOUTME source files

**Steps:**

1. Añadir `ramsey/uuid` y `symfony/uid` como dev integrations; quitar Faker, Mockery bootstrap, Rector y PHPCS si `composer why`/`rg` confirman que no son usados por scripts.
2. Resolver dependencias y ejecutar las integraciones UUID sin skips.
3. Poner `failOnRisky`/`failOnWarning` a true y añadir un checker Clover con el baseline vigente.
4. Separar coverage en un job PHP 8.4 con Xdebug; dejar matriz normal con coverage none.
5. Añadir `develop` a security/static/code-style.
6. Reescribir aserciones Carbon/alias para esperar un único resultado exacto.
7. Endurecer ABOUTME a exactamente dos líneas contiguas y eliminar duplicados existentes.
8. Corregir deprecations/versiones/ejemplos y documentar los cambios observables.
9. Ejecutar composer validate/audit, PHPUnit, coverage checker, ABOUTME, Pint y PHPStan.

### Task 7: Verificación y review final

1. Ejecutar:

   ```bash
   composer validate --strict --no-check-lock
   composer audit
   composer run check:aboutme
   composer run cs:check
   composer run analyse
   vendor/bin/phpunit --no-coverage --display-phpunit-deprecations --display-deprecations --display-warnings
   XDEBUG_MODE=coverage composer run test:coverage-clover
   php tools/check-coverage.php coverage/clover.xml
   git diff --check
   ```

2. Ejecutar review de seguridad, spec y calidad sobre el diff completo.
3. Corregir findings del review y repetir todos los gates.
4. Preservar `.DS_Store` y `graphify-out/` como artefactos locales sin incluirlos.

### Follow-up de revisión independiente

La segunda revisión añadió seis casos límite, resueltos dentro de esta misma tarea:

1. Los lectores persistentes existentes refrescan la generación cuando otra instancia ejecuta `clear()`.
2. El rollback captura y restaura propiedades mutables públicas, protegidas y privadas de toda la jerarquía, incluidas las modificadas indirectamente por property hooks.
3. Pebble detecta y rechaza arrays recursivos sin recursión infinita ni mutar referencias del input.
4. Ramsey UUID se construye con su factory no-lazy y valida el subtipo concreto solicitado.
5. La documentación de IDs custom refleja que el heuristic se aplica a sufijos del nombre de clase.
6. La documentación refleja que un factory de ID reconocido que falla produce `SerializationException`.
7. La invalidación persistente usa una revisión O(1) entre instancias del mismo proceso y limita los sondeos inter-proceso con reloj monotónico, evitando I/O por cada hit sin permitir que una escritura resucite datos anteriores a `clear()`.
