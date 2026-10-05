<?php

declare(strict_types=1);

namespace AltDTO\Serialization;

use AltDTO\Collection;
use AltDTO\Configuration\DTOConfig;
use AltDTO\DTO;

/**
 * Recursive object normalizer used by DTO serialization APIs.
 *
 * This is a fixed pipeline (enum -> date/time -> Collection ->
 * JsonSerializable -> array -> plain object) with no runtime-registrable
 * hook. Types needing a custom serialized shape should implement
 * \JsonSerializable themselves.
 */
final class Serializer
{
    /**
     * Marker key added to an object's own output array, retroactively,
     * the moment a second reference to that same object is found anywhere
     * else in the graph.
     */
    private const ID_KEY = '$id';

    /**
     * Marker key emitted at the *repeat* encounter of an already-pinned
     * object, instead of re-serializing it. Its value matches the
     * self::ID_KEY stamped onto the original occurrence.
     */
    private const REF_KEY = '$ref';

    public function __construct(
        private readonly DTOConfig $config
    ) {
    }

    /**
     * @param array<int,array<string,mixed>> $pins spl_object_id => reference to
     *        that object's own output array, pinned in memory the first time
     *        it's encountered so a later repeat can stamp self::ID_KEY onto
     *        it retroactively.
     */
    public function normalize(mixed $value, array &$pins = []): mixed
    {
        if ($value instanceof \UnitEnum) {
            return $value instanceof \BackedEnum ? $value->value : $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format($this->config->dateFormat);
        }

        if ($value instanceof \DateInterval) {
            return $value->format('%RP%yY%mM%dDT%hH%iM%sS');
        }

        if ($value instanceof \DateTimeZone) {
            return $value->getName();
        }

        if ($value instanceof Collection) {
            $out = [];
            foreach ($value->all() as $k => $v) {
                $out[$k] = $this->normalize($v, $pins);
            }
            return $out;
        }

        // AltDTO\DTO instances are always walked directly via their properties
        // (with reference tracking) rather than through jsonSerialize(), since
        // DTO::jsonSerialize() just calls toArray() again with a brand new
        // $pins context and would otherwise recurse forever on circular
        // object graphs.
        if ($value instanceof DTO) {
            return $this->normalizeObject($value, $pins);
        }

        if ($value instanceof \JsonSerializable) {
            return $this->normalize($value->jsonSerialize(), $pins);
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->normalize($v, $pins);
            }
            return $out;
        }

        if (is_object($value)) {
            return $this->normalizeObject($value, $pins);
        }

        return $value;
    }

    /**
     * @param array<int,array<string,mixed>> $pins
     */
    private function normalizeObject(object $value, array &$pins): array
    {
        $id = spl_object_id($value);

        if (isset($pins[$id])) {
            $pins[$id][self::ID_KEY] = $id;
            return [self::REF_KEY => $id];
        }

        $out = [];
        $pins[$id] = &$out;

        foreach (get_object_vars($value) as $k => $v) {
            $out[$k] = $this->normalize($v, $pins);
        }

        return $out;
    }
}
