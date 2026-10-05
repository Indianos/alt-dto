<?php

declare(strict_types=1);

namespace AltDTO\Configuration;

use AltDTO\Contracts\MetadataCacheInterface;
use DateInterval;
use Psr\SimpleCache\CacheInterface as Psr16CacheInterface;

/**
 * Main DTO behaviour configuration.
 */
final readonly class DTOConfig
{
    public function __construct(
        public bool                  $strictUnknownFields = false,
        public bool                  $throwOnMissingProperties = true,
        public bool                  $allowTypeCasting = true,
        public bool                  $allowNullCasting = false,
        public string                $dateFormat = DATE_ATOM,
        public ?string               $timezone = null,
        /**
         * MetadataCacheInterface instance/class-string, PSR-16
         * CacheInterface instance, getOrCompute-shaped callable, or null
         * for the default in-memory cache. `mixed` because `callable`
         * can't be a property type.
         *
         * @var MetadataCacheInterface|class-string<MetadataCacheInterface>|Psr16CacheInterface|callable(string, callable): mixed|null
         */
        public mixed                 $cache = null,
        /** @var null|int|DateInterval Time-to-live for caches (used for PSR-16 setter). */
        public null|int|DateInterval $cacheTtl = null,
    ) {}
}
