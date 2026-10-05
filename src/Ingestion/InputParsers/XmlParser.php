<?php /** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace AltDTO\Ingestion\InputParsers;

use AltDTO\Contracts\ParserInterface;
use AltDTO\Exceptions\ParserException;
use SimpleXMLElement;

/**
 * Parses XML payloads into arrays.
 */
class XmlParser implements ParserInterface
{
    public function parse(mixed $input): array
    {
        if (!is_string($input)) {
            throw new ParserException('XML parser expects a string payload.');
        }

        $xml = @simplexml_load_string($input, SimpleXMLElement::class, LIBXML_NOCDATA);
        if (!$xml instanceof SimpleXMLElement) {
            throw new ParserException('Invalid XML payload.');
        }

        $data = $this->toArray($xml);

        return is_array($data) ? $data : ['value' => $data];
    }

    /**
     * Unwraps a single repeated-element wrapper, e.g. <root><item/><item/></root>
     * decodes via parse() to ['item' => [[...], [...]]]; this returns the inner list.
     */
    public function parseMany(mixed $input): ?array
    {
        $decoded = $this->parse($input);
        if (count($decoded) !== 1) {
            return null;
        }

        $only = reset($decoded);

        return is_array($only) && $this->isListOfRows($only) ? $only : null;
    }

    /**
     * @param array<int|string,mixed> $data
     */
    private function isListOfRows(array $data): bool
    {
        if (!array_is_list($data)) {
            return false;
        }

        foreach ($data as $item) {
            if (!is_array($item)) {
                return false;
            }
        }

        return true;
    }

    private function toArray(SimpleXMLElement $element): string|array
    {
        $children = $element->children();
        if (!$children || $children->count() === 0) {
            return (string) $element;
        }

        $result = [];
        foreach ($children as $name => $child) {
            $value = $this->toArray($child);
            if (array_key_exists($name, $result)) {
                if (!is_array($result[$name]) || !array_is_list($result[$name])) {
                    $result[$name] = [$result[$name]];
                }
                $result[$name][] = $value;
                continue;
            }
            $result[$name] = $value;
        }

        return $result;
    }
}
