<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin PostgREST client for the DukaMkononi Supabase project.
 *
 * Provides a small Eloquent-like fluent API (select/where/whereRaw/whereIn/
 * orWhere/orderBy/limit/first/get/pluck/count/create/update/...) that the Api
 * controllers already use, so migrating them to the REST API requires only
 * swapping the active model source (App\Models\* switch to Supabase-backed
 * proxies below). Rows are returned as SupabaseRow objects exposing both
 * property and array access plus ->toArray().
 *
 * Filter semantics:
 *  - every where()/whereRaw()/whereIn() is ANDed together;
 *  - where(Closure) builds a nested group; within it where(...)->orWhere(...)
 *    chains collapse into a single PostgREST `or=(...)` group;
 *  - that group is ANDed with the outer filters, matching Eloquent behaviour.
 */
class Supabase
{
    protected string $table;

    protected array $select = ['*'];

    /** @var list<array{col:string,op:string,val:mixed}> */
    protected array $wheres = [];

    /** @var list<list<array{col:string,op:string,val:mixed}>> one OR group per entry */
    protected array $orGroups = [];

    protected array $orderBy = [];

    protected ?int $limit = null;

    protected ?int $offset = null;

    /**
     * Set when a query can never match (e.g. whereIn with an empty list).
     */
    protected bool $impossible = false;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public static function table(string $table): static
    {
        return new static($table);
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function query(): static
    {
        return $this;
    }

    /* ------------------------------------------------------------------ */
    /* Query building                                                     */
    /* ------------------------------------------------------------------ */

    public function select(...$columns): static
    {
        $flat = [];
        foreach ($columns as $col) {
            if (is_array($col)) {
                $flat = array_merge($flat, array_values($col));
            } else {
                $flat[] = $col;
            }
        }
        $this->select = $flat ?: ['*'];

        return $this;
    }

    public function where($column, $operator = null, $value = null): static
    {
        if ($column instanceof \Closure) {
            $nested = new static($this->table);
            $column($nested);

            $group = $nested->wheres;
            foreach ($nested->orGroups as $orGroup) {
                if ($group) {
                    // Chain like where(a)->orWhere(b): collapse into one OR group.
                    $group = array_merge($group, $orGroup);
                } else {
                    $group = $orGroup;
                }
            }
            if ($group) {
                $this->orGroups[] = $group;
            }

            return $this;
        }

        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = $this->compileNode($column, $operator ?: '=', $value);

        return $this;
    }

    public function orWhere($column, $operator = null, $value = null): static
    {
        if ($column instanceof \Closure) {
            return $this->where($column);
        }

        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $node = $this->compileNode($column, $operator ?: '=', $value);

        if ($this->wheres && !$this->orGroups) {
            // where(a)->orWhere(b): pull a into the new OR group.
            $this->orGroups[] = [array_pop($this->wheres)];
        }
        if (!$this->orGroups) {
            $this->orGroups[] = [];
        }
        $this->orGroups[count($this->orGroups) - 1][] = $node;

        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        if (count($values) === 0) {
            $this->impossible = true;

            return $this;
        }
        $this->wheres[] = ['col' => $column, 'op' => 'in', 'val' => array_values($values)];

        return $this;
    }

    public function whereNotIn(string $column, array $values): static
    {
        if (count($values) === 0) {
            return $this;
        }
        $this->wheres[] = ['col' => $column, 'op' => 'nin', 'val' => array_values($values)];

        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = ['col' => $column, 'op' => 'is', 'val' => 'null'];

        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = ['col' => $column, 'op' => 'not.is', 'val' => 'null'];

        return $this;
    }

    /**
     * Translate the handful of whereRaw() shapes used across the Api
     * controllers into PostgREST filters.
     */
    public function whereRaw(string $sql, array $bindings = [], bool $or = false): static
    {
        $filter = $this->compileRaw($sql, $bindings);
        if ($filter === null) {
            return $this;
        }

        return $or ? $this->orWhere($filter['col'], $filter['op'], $filter['val']) : $this->pushWhere($filter);
    }

    public function orWhereRaw(string $sql, array $bindings = []): static
    {
        return $this->whereRaw($sql, $bindings, true);
    }

    protected function compileRaw(string $sql, array $bindings): ?array
    {
        if (preg_match('/LOWER\(\s*([\w.]+)\s*\)\s*=\s*LOWER\(\s*\?\s*\)/i', $sql, $m)) {
            return ['col' => $m[1], 'op' => 'ilike', 'val' => strtolower((string) ($bindings[0] ?? ''))];
        }

        if (preg_match('/LOWER\(\s*([\w.]+)\s*\)\s*=\s*\?/i', $sql, $m)) {
            return ['col' => $m[1], 'op' => 'ilike', 'val' => strtolower((string) ($bindings[0] ?? ''))];
        }

        if (preg_match('/LOWER\(\s*([\w.]+)\s*\)\s*LIKE\s*\?/i', $sql, $m) && count($bindings) === 1) {
            return ['col' => $m[1], 'op' => 'ilike', 'val' => $this->wildcard(strtolower((string) $bindings[0]))];
        }

        if (preg_match('/([\w.]+)\s*=\s*\?/i', $sql, $m)) {
            return ['col' => $m[1], 'op' => 'eq', 'val' => $bindings[0] ?? null];
        }

        if (config('app.debug')) {
            throw new RuntimeException('Unsupported whereRaw for REST: ' . $sql);
        }

        return null;
    }

    protected function pushWhere(array $filter): static
    {
        $this->wheres[] = $filter;

        return $this;
    }

    public function orderBy($column, string $direction = 'asc'): static
    {
        $this->orderBy[$column] = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $this;
    }

    public function orderByDesc($column): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;

        return $this;
    }

    public function take(int $limit): static
    {
        return $this->limit($limit);
    }

    public function skip(int $offset): static
    {
        return $this->offset($offset);
    }

    /* ------------------------------------------------------------------ */
    /* Reads                                                              */
    /* ------------------------------------------------------------------ */

    public function get(): SupabaseCollection
    {
        if ($this->impossible) {
            return new SupabaseCollection([]);
        }

        // PostgREST caps every response (server "max-rows", typically 1000),
        // silently truncating larger tables — rows beyond the cap just vanish.
        // Page through with offset/limit until a short page is returned.
        // An explicit ->limit() is still honoured exactly.
        $maxRows = $this->limit ?? 100000; // hard safety cap when unbounded
        $pageSize = min(1000, $maxRows);
        $items = [];

        for ($page = 0; count($items) < $maxRows; $page++) {
            $query = clone $this;
            $query->limit = $pageSize;
            $query->offset = $page * $pageSize;
            // Offset paging is only correct with a stable sort; when the caller
            // didn't request an order, pin one to avoid skipped/duplicate rows
            // across pages.
            if ($query->orderBy === []) {
                $query->orderBy['id'] = 'asc';
            }

            $rows = $query->request('GET', $query->readUrl(), $query->authHeaders()) ?? [];
            if (!is_array($rows) || $rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $items[] = new SupabaseRow(is_array($row) ? $row : []);
            }

            if (count($rows) < $pageSize) {
                break;
            }
        }

        return new SupabaseCollection($items);
    }

    public function first(): ?SupabaseRow
    {
        $rows = $this->get();

        return $rows->first();
    }

    public function value(string $column)
    {
        $row = $this->select($column)->first();

        return $row ? $row->{$column} : null;
    }

    public function pluck(string $column): SupabaseCollection
    {
        $values = [];
        foreach ($this->select($column)->get() as $row) {
            $values[] = $row->{$column};
        }

        return new SupabaseCollection($values);
    }

    public function count(): int
    {
        if ($this->impossible) {
            return 0;
        }

        $response = Http::withHeaders(array_merge(
            $this->authHeaders(),
            [
                'Prefer' => 'count=exact',
                'Range' => '0-0',
            ]
        ))->timeout(config('supabase.timeout'))->get($this->absoluteUrl($this->readUrl()));

        if (!$response->successful()) {
            throw new RuntimeException("Supabase count failed: {$response->status()} {$response->body()}");
        }

        $contentRange = (string) $response->header('Content-Range', '');
        if (preg_match('#/(\d+)$#', $contentRange, $m)) {
            return (int) $m[1];
        }

        return count($response->json() ?? []);
    }

    public function exists(): bool
    {
        if ($this->impossible) {
            return false;
        }

        return $this->select('id')->first() !== null;
    }

    public function sum(string $column)
    {
        if ($this->impossible) {
            return 0;
        }

        $total = 0;
        foreach ($this->pluck($column) as $value) {
            $total += is_numeric($value) ? (float) $value : 0;
        }

        return $total;
    }

    /* ------------------------------------------------------------------ */
    /* Writes                                                             */
    /* ------------------------------------------------------------------ */

    public function insert(array $data)
    {
        $rows = $this->request('POST', $this->absoluteUrl('/' . $this->table), $this->writeHeaders('return=representation'), $data);
        if (!is_array($rows)) {
            return new SupabaseRow([]);
        }
        if (isset($rows[0]) && is_array($rows[0])) {
            return new SupabaseCollection(array_map(fn ($r) => new SupabaseRow($r), $rows));
        }

        return new SupabaseRow($rows);
    }

    public function create(array $data): SupabaseRow
    {
        $inserted = $this->insert($data);
        if ($inserted instanceof SupabaseCollection) {
            return $inserted->first() ?? new SupabaseRow([]);
        }
        if ($inserted instanceof SupabaseRow) {
            return $inserted;
        }
        throw new RuntimeException('Supabase insert failed');
    }

    public function update(array $data): int
    {
        if ($this->impossible) {
            return 0;
        }

        $response = Http::withHeaders(array_merge(
            $this->authHeaders(),
            [
                'Prefer' => 'return=minimal',
                'Content-Type' => 'application/json',
            ]
        ))->timeout(config('supabase.timeout'))->patch($this->absoluteUrl($this->writeUrl()), $data);

        if (!$response->successful() && !in_array($response->status(), [204, 200], true)) {
            throw new RuntimeException("Supabase update failed: {$response->status()} {$response->body()}");
        }

        return 1;
    }

    public function delete(): bool
    {
        if ($this->impossible) {
            return false;
        }

        $response = Http::withHeaders(array_merge(
            $this->authHeaders(),
            [
                'Prefer' => 'return=minimal',
            ]
        ))->timeout(config('supabase.timeout'))->delete($this->absoluteUrl($this->writeUrl()));

        if (!$response->successful() && !in_array($response->status(), [204, 200], true)) {
            throw new RuntimeException("Supabase delete failed: {$response->status()} {$response->body()}");
        }

        return true;
    }

    /* ------------------------------------------------------------------ */
    /* URL building                                                       */
    /* ------------------------------------------------------------------ */

    protected function queryParams(bool $includeSelect = true): array
    {
        $params = [];
        if ($includeSelect && $this->select !== ['*']) {
            $params['select'] = implode(',', $this->select);
        }

        // Each where becomes its own [column, value] pair appended to a LIST.
        // They must NOT be stored keyed by column name: two filters on the
        // same column (e.g. sale_date=gte.X & sale_date=lte.Y) would otherwise
        // overwrite each other and silently drop one side of a date range.
        // PostgREST accepts repeated params for the same column and ANDs them.
        foreach ($this->wheres as $w) {
            $qs = $this->filterToString($w);
            [$key, $val] = array_pad(explode('=', $qs, 2), 2, null);
            $params[] = [$key, $val];
        }

        $ors = [];
        foreach ($this->orGroups as $group) {
            $ors[] = implode(',', array_map([$this, 'filterToString'], $group));
        }
        if ($ors) {
            $params['or'] = '(' . implode(',', $ors) . ')';
        }

        if ($this->orderBy) {
            $parts = [];
            foreach ($this->orderBy as $col => $dir) {
                $parts[] = $col . '.' . $dir;
            }
            $params['order'] = implode(',', $parts);
        }

        if ($this->limit !== null) {
            $params['limit'] = $this->limit;
        }
        if ($this->offset !== null) {
            $params['offset'] = $this->offset;
        }

        return $params;
    }

    protected function filterToString(array $f): string
    {
        $col = $f['col'];
        $op = $f['op'];
        $val = $f['val'] ?? '';

        if ($op === 'in') {
            $list = implode(',', array_map(fn ($v) => $this->ticket((string) $v), $val));

            return $col . '=in.(' . $list . ')';
        }
        if ($op === 'nin') {
            $list = implode(',', array_map(fn ($v) => $this->ticket((string) $v), $val));

            return $col . '=not.in.(' . $list . ')';
        }

        if (in_array($op, ['ilike', 'like', 'not.ilike', 'not.like'], true)) {
            return $col . '=' . $op . '.' . (string) $val;
        }

        if ($val === null || $val === 'null') {
            return $col . '=is.null';
        }

        return $col . '=' . $op . '.' . $this->safeScalar((string) $val);
    }

    /**
     * Escape a literal value for use INSIDE a PostgREST list
     * (in.(...), not.in.(...), or=(...)): double quotes are required there,
     * otherwise commas/spaces in the value would split the list.
     */
    protected function ticket(string $value): string
    {
        if ($value === '') {
            return '""';
        }
        if (preg_match('/^[A-Za-z0-9_@.\-:]+$/', $value)) {
            return $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * Format a scalar comparison value (eq/neq/gt/lt/...). Values must NOT be
     * double-quoted: this project's PostgREST build treats quotes as literal
     * characters, so eq."Jerald Stationary" matched nothing while
     * eq.Jerald Stationary matched — the whole reason sellers saw
     * "Hakuna bidhaa zilizopo" while the Node backend (supabase-js, which
     * never quotes) returned the same rows fine.
     */
    protected function safeScalar(string $value): string
    {
        return $value;
    }

    protected function buildQs(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $val) {
            if (is_array($val)) {
                // [column, value] pair from a where filter — emitted as its
                // own param so duplicate columns are preserved.
                [$k, $v] = $val;
                $parts[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
            } else {
                $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $val);
            }
        }

        return implode('&', $parts);
    }

    protected function readUrl(): string
    {
        $qs = $this->buildQs($this->queryParams(true));

        return '/' . $this->table . ($qs ? '?' . $qs : '');
    }

    protected function writeUrl(): string
    {
        $qs = $this->buildQs($this->queryParams(false));

        return '/' . $this->table . ($qs ? '?' . $qs : '');
    }

    protected function absoluteUrl(string $path): string
    {
        return config('supabase.url') . '/rest/v1' . $path;
    }

    protected function authHeaders(): array
    {
        $key = config('supabase.service_role_key');

        return [
            'apikey' => $key,
            'Authorization' => 'Bearer ' . $key,
        ];
    }

    protected function writeHeaders(string $prefer): array
    {
        $headers = $this->authHeaders();
        $headers['Prefer'] = $prefer;
        $headers['Content-Type'] = 'application/json';

        return $headers;
    }

    protected function request(string $method, string $url, array $headers, $body = null)
    {
        // Resolve relative paths (e.g. "/users?select=...") against the
        // configured Supabase project so Http::get() always receives an
        // absolute URI (Guzzle throws otherwise).
        if (!str_starts_with($url, 'http')) {
            $url = $this->absoluteUrl($url);
        }

        $http = Http::withHeaders($headers)->timeout(config('supabase.timeout'));

        $response = $method === 'GET'
            ? $http->get($url)
            : $http->withBody(json_encode($body ?? []), 'application/json')->post($url);

        if (!$response->successful()) {
            throw new RuntimeException("Supabase {$method} {$this->table} failed: {$response->status()} {$response->body()}", $response->status());
        }

        return $response->json();
    }

    protected function compileNode(string $column, string $operator, $value): array
    {
        $opMap = [
            '=' => 'eq',
            'eq' => 'eq',
            '!=' => 'neq',
            '<>' => 'neq',
            '>' => 'gt',
            '>=' => 'gte',
            '<' => 'lt',
            '<=' => 'lte',
            'like' => 'ilike',
            'ilike' => 'ilike',
            'not like' => 'not.ilike',
        ];

        $op = $opMap[$operator] ?? 'eq';

        if ($value === null) {
            return ['col' => $column, 'op' => 'is', 'val' => 'null'];
        }

        if (is_bool($value)) {
            return ['col' => $column, 'op' => $op, 'val' => $value ? 'true' : 'false'];
        }

        if (in_array($op, ['ilike', 'like', 'not.ilike', 'not.like'], true)) {
            return ['col' => $column, 'op' => $op, 'val' => $this->wildcard((string) $value)];
        }

        return ['col' => $column, 'op' => $op, 'val' => $value];
    }

    protected function wildcard(string $pattern): string
    {
        return str_contains($pattern, '%') ? str_replace('%', '*', $pattern) : $pattern;
    }
}