<?php

namespace App\Http\Controllers\Api;

use App\Services\Supabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * TEMPORARY endpoint used once to snapshot the live Supabase schema + data so a
 * SYSTEM_SCHEMA.sql / SYSTEM_SEED.sql pair can be produced. Delete after use.
 */
class TempDumpController extends BaseController
{
    private function supabaseRoot(): string
    {
        return rtrim(config('supabase.url'), '/').'/rest/v1/';
    }

    private function headers(): array
    {
        $key = config('supabase.service_role_key');

        return [
            'apikey' => $key,
            'Authorization' => 'Bearer '.$key,
        ];
    }

    /** PostgREST OpenAPI document: every table/view + column names, types, pk. */
    public function schema(): JsonResponse
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(60)
                ->get($this->supabaseRoot());

            return $this->json([
                'ok' => $response->successful(),
                'status' => $response->status(),
                'spec' => $response->json(),
            ]);
        } catch (Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** List the table names PostgREST exposes. */
    public function tables(): JsonResponse
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(60)
                ->get($this->supabaseRoot());
            $spec = $response->json();

            $tables = [];
            if (isset($spec['definitions']) && is_array($spec['definitions'])) {
                $tables = array_keys($spec['definitions']);
            } elseif (isset($spec['components']['schemas']) && is_array($spec['components']['schemas'])) {
                $tables = array_keys($spec['components']['schemas']);
            }

            return $this->json(['ok' => true, 'tables' => $tables]);
        } catch (Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Row count for one table. */
    public function count(string $table): JsonResponse
    {
        try {
            return $this->json(['ok' => true, 'table' => $table, 'count' => Supabase::table($table)->count()]);
        } catch (Throwable $e) {
            return $this->json(['ok' => false, 'table' => $table, 'error' => $e->getMessage()], 500);
        }
    }

    /** Rows of one table, every column; supports ?offset=&limit= for big tables. */
    public function dump(string $table): JsonResponse
    {
        try {
            $offset = max(0, (int) request()->query('offset', 0));
            $limit = (int) request()->query('limit', 10000000);
            $query = Supabase::table($table);
            if ($offset) {
                $query->offset($offset);
            }
            $rows = $query->limit($limit)->get()->toArray();

            return $this->json([
                'ok' => true,
                'table' => $table,
                'offset' => $offset,
                'count' => count($rows),
                'rows' => $rows,
            ]);
        } catch (Throwable $e) {
            return $this->json(['ok' => false, 'table' => $table, 'error' => $e->getMessage()], 500);
        }
    }
}
