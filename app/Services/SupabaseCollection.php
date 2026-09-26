<?php

namespace App\Services;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * A lightweight collection of SupabaseRow (or scalar) items exposing the
 * Collection-style methods the controllers rely on (toArray, pluck, keyBy,
 * filter, first, count, map, unique, values, slice, sum, all, etc.).
 */
class SupabaseCollection implements ArrayAccess, IteratorAggregate, Countable, JsonSerializable
{
    protected array $items;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function all(): array
    {
        return $this->items;
    }

    public function items(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return count($this->items) === 0;
    }

    public function isNotEmpty(): bool
    {
        return count($this->items) > 0;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function first(callable $callback = null)
    {
        if ($callback === null) {
            return $this->items[array_key_first($this->items)] ?? null;
        }
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return null;
    }

    public function last(callable $callback = null)
    {
        if ($callback === null) {
            return $this->items[array_key_last($this->items)] ?? null;
        }
        $filtered = array_reverse($this->items, true);
        foreach ($filtered as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return null;
    }

    public function toArray(): array
    {
        return array_map(function ($item) {
            if ($item instanceof SupabaseRow) {
                return $item->toArray();
            }
            return $item;
        }, $this->items);
    }

    public function pluck(string $column): self
    {
        $values = [];
        foreach ($this->items as $key => $item) {
            if (is_array($item)) {
                $values[$key] = $item[$column] ?? null;
            } elseif ($item instanceof SupabaseRow) {
                $values[$key] = $item->{$column};
            } elseif (is_object($item) && isset($item->{$column})) {
                $values[$key] = $item->{$column};
            } else {
                $values[$key] = null;
            }
        }

        return new self($values);
    }

    public function keyBy(string $keyBy): self
    {
        $keyed = [];
        foreach ($this->items as $item) {
            if (is_array($item)) {
                $key = $item[$keyBy] ?? null;
            } elseif ($item instanceof SupabaseRow) {
                $key = $item->{$keyBy};
            } else {
                $key = $item->{$keyBy} ?? null;
            }
            if ($key !== null) {
                $keyed[$key] = $item;
            }
        }

        return new self($keyed);
    }

    public function filter(callable $callback = null): self
    {
        if ($callback === null) {
            return new self(array_filter($this->items));
        }

        return new self(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    public function map(callable $callback): self
    {
        $mapped = [];
        foreach ($this->items as $key => $item) {
            $mapped[$key] = $callback($item, $key);
        }

        return new self($mapped);
    }

    public function unique(string $key = null): self
    {
        $seen = [];
        $out = [];
        foreach ($this->items as $k => $item) {
            $v = $key ? ($item instanceof SupabaseRow ? $item->{$key} : ($item[$key] ?? null)) : $item;
            if (!in_array($v, $seen, true)) {
                $seen[] = $v;
                $out[$k] = $item;
            }
        }

        return new self($out);
    }

    public function values(): self
    {
        return new self(array_values($this->items));
    }

    public function keys(): self
    {
        return new self(array_keys($this->items));
    }

    public function slice(int $offset, int $length = null): self
    {
        return new self(array_slice($this->items, $offset, $length, true));
    }

    public function take(int $limit): self
    {
        return $this->slice(0, $limit);
    }

    public function sum(callable $callback = null)
    {
        if ($callback === null) {
            return array_sum($this->items);
        }
        $sum = 0;
        foreach ($this->items as $key => $item) {
            $sum += $callback($item, $key);
        }

        return $sum;
    }

    public function avg(callable $callback = null)
    {
        $count = count($this->items);
        if ($count === 0) {
            return null;
        }

        return $this->sum($callback) / $count;
    }

    public function min(string $column = null)
    {
        $values = $column === null ? $this->items : $this->pluck($column)->all();
        return $values ? min($values) : null;
    }

    public function max(string $column = null)
    {
        $values = $column === null ? $this->items : $this->pluck($column)->all();
        return $values ? max($values) : null;
    }

    public function sortBy(string $column): self
    {
        $items = $this->items;
        usort($items, fn ($a, $b) => ($a instanceof SupabaseRow ? $a->{$column} : ($a[$column] ?? null)) <=> ($b instanceof SupabaseRow ? $b->{$column} : ($b[$column] ?? null)));

        return new self($items);
    }

    public function push($value): self
    {
        $this->items[] = $value;

        return $this;
    }

    public function merge(array $items): self
    {
        return new self(array_merge($this->items, $items));
    }

    public function contains($needle): bool
    {
        return in_array($needle, $this->items, true);
    }

    public function each(callable $callback): self
    {
        foreach ($this->items as $key => $item) {
            $result = $callback($item, $key);
            if ($result === false) {
                break;
            }
        }

        return $this;
    }

    public function mapWithKeys(callable $callback): self
    {
        $result = [];
        foreach ($this->items as $key => $item) {
            $r = $callback($item, $key);
            if (is_array($r)) {
                $result[key($r)] = current($r);
            }
        }

        return new self($result);
    }

    public function implode(string $glue, string $value = null): string
    {
        if ($value !== null) {
            $values = $this->pluck($value)->all();
        } else {
            $values = $this->items;
        }

        return implode($glue, $values);
    }

    public function groupBy(string $groupBy): self
    {
        $grouped = [];
        foreach ($this->items as $key => $item) {
            $k = $item instanceof SupabaseRow ? $item->{$groupBy} : ($item[$groupBy] ?? null);
            $grouped[$k][] = $item;
        }

        return new self($grouped);
    }

    public function firstWhere(string $key, $value)
    {
        return $this->first(fn ($item) => ($item instanceof SupabaseRow ? $item->{$key} : ($item[$key] ?? null)) === $value);
    }

    public function where(string $key, $value): self
    {
        return $this->filter(fn ($item) => ($item instanceof SupabaseRow ? $item->{$key} : ($item[$key] ?? null)) == $value);
    }

    public function get(string $key, $default = null)
    {
        return $this->items[$key] ?? $default;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->items);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }
}