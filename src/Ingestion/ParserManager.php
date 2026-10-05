<?php

declare(strict_types=1);

namespace AltDTO\Ingestion;

use AltDTO\Contracts\ParserInterface;
use AltDTO\Exceptions\ParserException;

/**
 * Registry of named input parsers, dispatched by DTO::from<Name>(...).
 *
 * A registered parser is either:
 * - A class-string implementing ParserInterface (instantiated lazily), or
 * - Any PHP callable (closure, invokable object, [object, method], function name).
 */
final class ParserManager
{
    /**
     * @var array<string,callable|ParserInterface|class-string<ParserInterface>>
     */
    private array $parsers = [];

    public function set(string $name, mixed $parser): self
    {
        $this->parsers[$this->normalize($name)] = $parser;

        return $this;
    }

    public function unset(string $name): self
    {
        unset($this->parsers[$this->normalize($name)]);

        return $this;
    }

    public function has(string $name): bool
    {
        return array_key_exists($this->normalize($name), $this->parsers);
    }

    public function invokeByName(string $name, mixed ...$arguments): mixed
    {
        $key = $this->normalize($name);
        if (!array_key_exists($key, $this->parsers)) {
            throw new ParserException(sprintf('No parser registered for "%s".', $name));
        }

        return $this->invoke($this->parsers[$key], ...$arguments);
    }

    /**
     * Resolves a list of rows for the given parser name via its required
     * parseMany(). Unlike invokeByName(), this never falls back to
     * wrapping a single parse() result as a one-item list - a single object
     * silently becoming "many of one" is ambiguous and not supported.
     *
     * @return list<array<string,mixed>>
     */
    public function invokeManyByName(string $name, mixed ...$arguments): array
    {
        $key = $this->normalize($name);
        if (!array_key_exists($key, $this->parsers)) {
            throw new ParserException(sprintf('No parser registered for "%s".', $name));
        }

        $instance = $this->resolveInstance($this->parsers[$key]);
        if ($instance === null) {
            throw new ParserException(sprintf(
                'Parser "%s" does not support multi-row parsing; register a ParserInterface implementation with parseMany().',
                $name
            ));
        }

        $rows = $instance->parseMany(...$arguments);
        if ($rows === null) {
            throw new ParserException(sprintf('Parser "%s" could not parse the given payload as multiple rows.', $name));
        }

        return $rows;
    }

    private function normalize(string $name): string
    {
        return strtolower(ltrim($name, '\\'));
    }

    private function resolveInstance(mixed $parser): ?ParserInterface
    {
        if ($parser instanceof ParserInterface) {
            return $parser;
        }

        if (is_string($parser) && is_subclass_of($parser, ParserInterface::class)) {
            return new $parser();
        }

        return null;
    }

    private function invoke(mixed $parser, mixed ...$arguments): mixed
    {
        $instance = $this->resolveInstance($parser);
        if ($instance !== null) {
            return $instance->parse(...$arguments);
        }

        if (is_callable($parser)) {
            return $parser(...$arguments);
        }

        throw new ParserException('Unsupported parser definition. Provide a callable or a ParserInterface implementation.');
    }
}
