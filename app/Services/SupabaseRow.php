<?php

namespace App\Services;

use ArrayAccess;
use IteratorAggregate;
use JsonSerializable;
use ArrayIterator;
use Countable;

/**
 * A single row from the Supabase REST API, exposing property access
 * ($row->id), array access ($row['id']), ->toArray() and ->jsonSerialize()
 * so existing controller code keeps working unchanged.
 */
class SupabaseRow implements ArrayAccess, IteratorAggregate, JsonSerializable, Countable
{
    protected array $attributes;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function __get(string $name)
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]) || array_key_exists($name, $this->attributes);
    }

    public function setAttribute(string $key, $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function getAttribute(string $key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->attributes);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->attributes[] = $value;
        } else {
            $this->attributes[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->attributes);
    }

    public function jsonSerialize(): mixed
    {
        return $this->attributes;
    }

    public function count(): int
    {
        return count($this->attributes);
    }
}