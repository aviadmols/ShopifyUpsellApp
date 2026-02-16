<?php

namespace App\Services;

/**
 * Computes runtime template variables ("shortcodes") for block text interpolation.
 *
 * These variables are defined in block config under `runtime_variables`, and can be referenced
 * in text fields using `{var_name}` (normalized) or `{Var Name}` etc.
 *
 * Supported definition types (each var is an object with "type"):
 * - unique_line_item_property_values: { property, case_insensitive_unique?, max?, separator? }
 * - plural_message_from_list: { list, singular, plural, empty?, separator? }
 * - plural_message_from_property: { property, singular, plural, empty?, separator?, case_insensitive_unique?, max? }
 */
class RuntimeTemplateVarsService
{
    /**
     * @param  array<string, mixed>  $definitions  config['runtime_variables']
     * @param  array<string, mixed>  $context  checkout context with line_items
     * @return array<string, string> map of varName => string value
     */
    public function compute(array $definitions, array $context): array
    {
        if ($definitions === []) {
            return [];
        }

        $computed = [];
        $visiting = [];

        foreach ($definitions as $name => $_def) {
            if (! is_string($name) || trim($name) === '') {
                continue;
            }
            $val = $this->evalVar($name, $definitions, $context, $computed, $visiting);
            if ($val !== null) {
                $computed[$name] = $val;
            }
        }

        // Normalize outputs to strings.
        $out = [];
        foreach ($computed as $k => $v) {
            if (! is_string($k) || trim($k) === '') {
                continue;
            }
            $out[$k] = is_string($v) ? $v : (string) $v;
        }
        return $out;
    }

    /**
     * @param  array<string, mixed>  $definitions
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $memo
     * @param  array<string, bool>  $visiting
     */
    private function evalVar(string $name, array $definitions, array $context, array &$memo, array &$visiting): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        if (array_key_exists($name, $memo)) {
            return is_string($memo[$name]) ? $memo[$name] : (string) $memo[$name];
        }
        if (isset($visiting[$name])) {
            // cycle
            return null;
        }
        $visiting[$name] = true;

        $def = $definitions[$name] ?? null;
        $result = $this->evalDefinition($def, $definitions, $context, $memo, $visiting);

        unset($visiting[$name]);
        if ($result !== null) {
            $memo[$name] = $result;
        }
        return $result;
    }

    /**
     * @param  mixed  $def
     * @param  array<string, mixed>  $definitions
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $memo
     * @param  array<string, bool>  $visiting
     */
    private function evalDefinition(mixed $def, array $definitions, array $context, array &$memo, array &$visiting): ?string
    {
        if (! is_array($def)) {
            return null;
        }
        $type = isset($def['type']) ? trim((string) $def['type']) : '';
        if ($type === '') {
            return null;
        }

        return match ($type) {
            'unique_line_item_property_values' => $this->evalUniqueValues($def, $context),
            'plural_message_from_list' => $this->evalPluralMessageFromList($def, $definitions, $context, $memo, $visiting),
            'plural_message_from_property' => $this->evalPluralMessageFromProperty($def, $context),
            default => null,
        };
    }

    /**
     * Returns joined unique values (string).
     *
     * @param  array<string, mixed>  $def
     * @param  array<string, mixed>  $context
     */
    private function evalUniqueValues(array $def, array $context): ?string
    {
        $property = trim((string) ($def['property'] ?? ''));
        if ($property === '') {
            return null;
        }
        $separator = (string) ($def['separator'] ?? ', ');
        $max = isset($def['max']) ? (int) $def['max'] : 0;
        $ci = (bool) ($def['case_insensitive_unique'] ?? true);

        $values = $this->uniqueLineItemPropertyValues($context, $property, $ci);
        if ($values === []) {
            return '';
        }
        if ($max > 0) {
            $values = array_slice($values, 0, $max);
        }
        return implode($separator, $values);
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array<string, mixed>  $definitions
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $memo
     * @param  array<string, bool>  $visiting
     */
    private function evalPluralMessageFromList(array $def, array $definitions, array $context, array &$memo, array &$visiting): ?string
    {
        $listVar = trim((string) ($def['list'] ?? ''));
        if ($listVar === '') {
            return null;
        }
        $singular = (string) ($def['singular'] ?? '');
        $plural = (string) ($def['plural'] ?? '');
        $empty = (string) ($def['empty'] ?? '');
        $separator = (string) ($def['separator'] ?? ', ');

        // List var is expected to be a computed var holding joined string, or another definition.
        $listJoined = $this->evalVar($listVar, $definitions, $context, $memo, $visiting) ?? '';
        $items = array_values(array_filter(array_map('trim', $listJoined !== '' ? explode($separator, $listJoined) : []), fn ($v) => $v !== ''));
        $count = count($items);
        if ($count === 0) {
            return $empty;
        }
        if ($count === 1) {
            return str_replace('{value}', $items[0], $singular);
        }
        return str_replace('{values}', implode($separator, $items), $plural);
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  array<string, mixed>  $context
     */
    private function evalPluralMessageFromProperty(array $def, array $context): ?string
    {
        $property = trim((string) ($def['property'] ?? ''));
        if ($property === '') {
            return null;
        }
        $singular = (string) ($def['singular'] ?? '');
        $plural = (string) ($def['plural'] ?? '');
        $empty = (string) ($def['empty'] ?? '');
        $separator = (string) ($def['separator'] ?? ', ');
        $ci = (bool) ($def['case_insensitive_unique'] ?? true);
        $max = isset($def['max']) ? (int) $def['max'] : 0;

        $values = $this->uniqueLineItemPropertyValues($context, $property, $ci);
        if ($values === []) {
            return $empty;
        }
        if ($max > 0) {
            $values = array_slice($values, 0, $max);
        }
        if (count($values) === 1) {
            return str_replace('{value}', $values[0], $singular);
        }
        return str_replace('{values}', implode($separator, $values), $plural);
    }

    /**
     * @return array<int, string>
     */
    private function uniqueLineItemPropertyValues(array $context, string $property, bool $caseInsensitiveUnique): array
    {
        $lines = $context['line_items'] ?? $context['lineItems'] ?? [];
        if (! is_array($lines)) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $props = $this->extractLineItemProperties($line);
            if ($props === []) {
                continue;
            }
            $val = trim((string) ($props[$property] ?? ''));
            if ($val === '') {
                continue;
            }
            $key = $caseInsensitiveUnique ? mb_strtolower($val) : $val;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $val;
        }
        return $out;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, string>
     */
    private function extractLineItemProperties(array $line): array
    {
        $candidates = [
            $line['properties'] ?? null,
            $line['attributes'] ?? null,
            $line['customAttributes'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $isAssoc = array_keys($candidate) !== range(0, count($candidate) - 1);
            if ($isAssoc) {
                $out = [];
                foreach ($candidate as $k => $v) {
                    $key = trim((string) $k);
                    $value = trim((string) $v);
                    if ($key !== '' && $value !== '') {
                        $out[$key] = $value;
                    }
                }
                if ($out !== []) {
                    return $out;
                }
                continue;
            }

            $out = [];
            foreach ($candidate as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $key = trim((string) ($row['key'] ?? $row['name'] ?? ''));
                $value = trim((string) ($row['value'] ?? ''));
                if ($key !== '' && $value !== '') {
                    $out[$key] = $value;
                }
            }
            if ($out !== []) {
                return $out;
            }
        }

        return [];
    }
}

