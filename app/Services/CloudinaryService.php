<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudinary raw-file client.
 *
 * A database backup is two plain-text SQL files, not images, so they are
 * stored as Cloudinary *raw* resources. Raw files have their own upload and
 * destroy endpoints; the image endpoints the rest of the app uses cannot see
 * them.
 *
 * Only the three operations a backup needs live here: upload one file, list
 * everything under a prefix, delete one file. Every method reports why it
 * failed through lastError() so the caller can tell the super admin instead of
 * silently reporting success.
 */
class CloudinaryService
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;
    private ?string $lastError = null;

    public function __construct()
    {
        $this->cloudName = (string) config('cloudinary.cloud_name');
        $this->apiKey = (string) config('cloudinary.api_key');
        $this->apiSecret = (string) config('cloudinary.api_secret');
    }

    /** Why the last upload/list/delete failed. Null after a success. */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Upload one SQL file as a raw resource and return its secure URL.
     *
     * A public_id containing slashes creates the folders:
     * "backups/backup_2026-11-05/seed_2026-11-05.sql" lands in that folder.
     * `overwrite` means a re-run for the same stamp replaces the file instead
     * of failing with "already exists".
     */
    public function uploadRaw(string $contents, string $publicId, string $contentType = 'text/plain'): ?string
    {
        $this->lastError = null;

        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $timestamp = now()->timestamp;

            $signature = sha1($this->buildSignatureString([
                'overwrite' => 'true',
                'public_id' => $publicId,
                'timestamp' => (string) $timestamp,
            ]) . $this->apiSecret);

            // A seed is the whole database, so this is the one call that can
            // legitimately take minutes on a slow uplink. Generous, and
            // configurable, rather than pretending 180s is enough for any size.
            $timeout = max(60, (int) config('cloudinary.timeout', 900));

            $response = Http::timeout($timeout)
                ->attach('file', $contents, basename($publicId), ['Content-Type' => $contentType])
                ->post("https://api.cloudinary.com/v1_1/{$this->cloudName}/raw/upload", [
                    'api_key' => $this->apiKey,
                    'timestamp' => $timestamp,
                    'public_id' => $publicId,
                    'overwrite' => 'true',
                    'signature' => $signature,
                ]);

            if ($response->successful()) {
                return (string) $response->json('secure_url');
            }

            $this->lastError = (string) ($response->json('error.message') ?? ('HTTP ' . $response->status()));

            return null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            report($e);

            return null;
        }
    }

    /**
     * Every raw file under a prefix, following Cloudinary's cursor so a long
     * backup history is not cut off at the first page.
     *
     * @return list<array{public_id: string, bytes: int, url: string, created_at: ?string}>|null
     */
    public function listRaw(string $prefix, int $maxResults = 500): ?array
    {
        $this->lastError = null;

        if (! $this->isConfigured()) {
            return null;
        }

        $files = [];
        $cursor = null;

        try {
            // 20 pages of 500 is 10,000 backups: far past any real history, and
            // a hard stop so a broken cursor cannot loop forever.
            for ($page = 0; $page < 20; $page++) {
                $query = ['prefix' => $prefix, 'max_results' => $maxResults];

                if ($cursor !== null) {
                    $query['next_cursor'] = $cursor;
                }

                // The admin API wants both halves in the path:
                // /resources/raw/upload. /resources/raw alone answers
                // "Missing required parameter - type".
                $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                    ->timeout(60)
                    ->get("https://api.cloudinary.com/v1_1/{$this->cloudName}/resources/raw/upload", $query);

                if (! $response->successful()) {
                    $this->lastError = (string) ($response->json('error.message') ?? ('HTTP ' . $response->status()));

                    return $files === [] ? null : $files;
                }

                foreach ((array) $response->json('resources', []) as $resource) {
                    $publicId = (string) ($resource['public_id'] ?? '');

                    if ($publicId === '') {
                        continue;
                    }

                    $files[] = [
                        'public_id' => $publicId,
                        'bytes' => (int) ($resource['bytes'] ?? 0),
                        'url' => (string) ($resource['secure_url'] ?? $resource['url'] ?? ''),
                        'created_at' => $resource['created_at'] ?? null,
                    ];
                }

                $cursor = $response->json('next_cursor');

                if ($cursor === null || $cursor === '') {
                    break;
                }
            }
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            report($e);

            return $files === [] ? null : $files;
        }

        return $files;
    }

    /**
     * Delete one raw file.
     *
     * "not found" counts as success: the caller wanted the file gone, and
     * refusing it would leave a database row listing a file nobody can delete.
     */
    public function deleteRaw(string $publicId): bool
    {
        $this->lastError = null;

        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $timestamp = now()->timestamp;

            // Cloudinary signs every param that is sent, so `invalidate` must
            // be in the signature too — otherwise "Invalid Signature".
            $signature = sha1($this->buildSignatureString([
                'invalidate' => 'true',
                'public_id' => $publicId,
                'timestamp' => (string) $timestamp,
            ]) . $this->apiSecret);

            $response = Http::timeout(60)->post("https://api.cloudinary.com/v1_1/{$this->cloudName}/raw/destroy", [
                'public_id' => $publicId,
                'api_key' => $this->apiKey,
                'timestamp' => $timestamp,
                'invalidate' => 'true',
                'signature' => $signature,
            ]);

            if ($response->successful() && in_array($response->json('result'), ['ok', 'not found'], true)) {
                return true;
            }

            $this->lastError = (string) ($response->json('error.message')
                ?? $response->json('result')
                ?? ('HTTP ' . $response->status()));

            return false;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::error('Cloudinary raw delete failed', ['public_id' => $publicId, 'message' => $e->getMessage()]);

            return false;
        }
    }

    private function isConfigured(): bool
    {
        if ($this->cloudName === '' || $this->apiKey === '' || $this->apiSecret === '') {
            $this->lastError = 'Cloudinary is not configured (CLOUDINARY_CLOUD_NAME / CLOUDINARY_API_KEY / CLOUDINARY_API_SECRET are empty).';

            return false;
        }

        return true;
    }

    /** Cloudinary signs an alphabetically sorted, &-joined param string. */
    private function buildSignatureString(array $pairs): string
    {
        $keys = array_keys($pairs);
        sort($keys, SORT_STRING);

        $parts = [];
        foreach ($keys as $key) {
            $parts[] = $key . '=' . $pairs[$key];
        }

        return implode('&', $parts);
    }
}
