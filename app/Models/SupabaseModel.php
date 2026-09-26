<?php

namespace App\Models;

use App\Services\Supabase;
use App\Services\SupabaseCollection;
use App\Services\SupabaseRow;

/**
 * Base model: proxies the static/Laravel-query style used by the Api
 * controllers onto the Supabase REST API. Every App\Models\* class now points
 * at the Supabase project's existing tables over PostgREST instead of a local
 * database.
 */
abstract class SupabaseModel
{
    /**
     * The table name (e.g. 'users'). Subclasses override.
     */
    protected static $table = '';

    public static function tableName(): string
    {
        return static::$table;
    }

    public static function query(): Supabase
    {
        return Supabase::table(static::$table);
    }

    public static function select(...$columns): Supabase
    {
        return static::query()->select(...$columns);
    }

    public static function where(...$args): Supabase
    {
        return static::query()->where(...$args);
    }

    public static function whereRaw(string $sql, array $bindings = [], bool $or = false): Supabase
    {
        return static::query()->whereRaw($sql, $bindings, $or);
    }

    public static function whereIn(string $column, array $values): Supabase
    {
        return static::query()->whereIn($column, $values);
    }

    public static function whereNotIn(string $column, array $values): Supabase
    {
        return static::query()->whereNotIn($column, $values);
    }

    public static function whereNull(string $column): Supabase
    {
        return static::query()->whereNull($column);
    }

    public static function whereNotNull(string $column): Supabase
    {
        return static::query()->whereNotNull($column);
    }

    public static function orderBy($column, string $direction = 'asc'): Supabase
    {
        return static::query()->orderBy($column, $direction);
    }

    public static function orderByDesc($column): Supabase
    {
        return static::query()->orderByDesc($column);
    }

    public static function create(array $attributes = []): SupabaseRow
    {
        return static::query()->create($attributes);
    }

    public static function insert(array $data)
    {
        return static::query()->insert($data);
    }

    public static function pluck(string $column): SupabaseCollection
    {
        return static::query()->pluck($column);
    }

    public static function count(): int
    {
        return static::query()->count();
    }

    public static function exists(): bool
    {
        return static::query()->exists();
    }

    /**
     * Return a fresh, REST-backed instance flavoured with data (used rarely;
     * mostly controllers go through static::query()).
     */
    public static function make(array $attributes = []): SupabaseRow
    {
        return new SupabaseRow($attributes);
    }
}