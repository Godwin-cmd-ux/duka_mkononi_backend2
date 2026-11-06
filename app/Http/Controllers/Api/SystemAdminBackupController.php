<?php

namespace App\Http\Controllers\Api;

use App\Services\BackupManager;
use App\Services\DatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * JSON twin of App\Http\Controllers\SuperAdmin\BackupsController.
 *
 * The mobile /system_admin/backup page renders the same Cloudinary snapshots
 * as the website, so this controller exposes the same list, induce, download
 * and delete actions over the JWT API. Every method re-checks the platform
 * role server-side: a hidden menu item is never the control.
 */
class SystemAdminBackupController extends BaseController
{
    private BackupManager $manager;

    public function __construct()
    {
        $this->manager = new BackupManager();
    }

    public function index(Request $request): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['success' => false, 'error' => 'Huna ruhusa ya kuona backups.'], 403);
        }

        $import = $this->manager->import();

        $from = BackupManager::dateOrNull($request->query('date_from'));
        $to = BackupManager::dateOrNull($request->query('date_to'));

        $tableWarning = null;

        try {
            $listing = $this->manager->list($from, $to);
        } catch (Throwable $e) {
            $listing = [
                'days' => [],
                'total_stored' => 0,
                'total_bytes' => 0,
                'matching_bytes' => 0,
            ];
            $tableWarning = 'The backups table is not available yet: ' . $e->getMessage();
        }

        return $this->json([
            'success' => true,
            'days' => $listing['days'],
            'total_stored' => $listing['total_stored'],
            'total_bytes' => $listing['total_bytes'],
            'matching_bytes' => $listing['matching_bytes'],
            'latest' => $listing['days'][0]->stamp ?? null,
            'imported' => $import['imported'],
            'cloudinary_warning' => $import['warning'],
            'table_warning' => $tableWarning,
            'cloudinary_folder' => DatabaseBackupService::rootFolder(),
            'schedule_timezone' => DatabaseBackupService::TIMEZONE,
            'filters' => ['date_from' => $from, 'date_to' => $to],
            'filtered' => $from !== null || $to !== null,
        ]);
    }

    public function induce(Request $request): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['success' => false, 'error' => 'Huna ruhusa ya kuunda backup.'], 403);
        }

        $result = $this->manager->induce();

        return $this->json($result, $result['ok'] ? 200 : 422);
    }

    public function download(Request $request, string $stamp, string $file): StreamedResponse|JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['success' => false, 'error' => 'Huna ruhusa ya kupakua backup.'], 403);
        }

        if (! in_array($file, BackupManager::KINDS, true)) {
            return $this->json(['success' => false, 'error' => 'Backup file not found.'], 404);
        }

        $row = $this->manager->find($stamp);

        if ($row === null) {
            return $this->json([
                'success' => false,
                'error' => "There is no backup recorded for {$this->manager->label($stamp)}.",
            ], 404);
        }

        try {
            $response = Http::timeout(180)->get($this->manager->fileUrl($row, $stamp, $file));
        } catch (Throwable $e) {
            Log::error('Backup download failed', ['stamp' => $stamp, 'file' => $file, 'message' => $e->getMessage()]);

            return $this->json([
                'success' => false,
                'error' => "Could not reach Cloudinary for {$this->manager->label($stamp)}: " . $e->getMessage(),
            ], 502);
        }

        if (! $response->successful()) {
            return $this->json([
                'success' => false,
                'error' => "Cloudinary could not return the {$file} file for {$this->manager->label($stamp)} (HTTP " . $response->status() . ').',
            ], 502);
        }

        $body = $response->body();

        if ($file === 'seed' && str_starts_with($body, "\x1f\x8b")) {
            $expanded = @gzdecode($body);

            if ($expanded === false) {
                return $this->json([
                    'success' => false,
                    'error' => "The seed file for {$this->manager->label($stamp)} could not be expanded.",
                ], 500);
            }

            $body = $expanded;
        }

        $filename = DatabaseBackupService::fileName($stamp, $file);

        return response()->streamDownload(function () use ($body) {
            echo $body;
        }, $filename, ['Content-Type' => 'application/sql']);
    }

    public function destroy(Request $request, string $stamp): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->json(['success' => false, 'error' => 'Huna ruhusa ya kufuta backup.'], 403);
        }

        $result = $this->manager->delete($stamp);

        return $this->json($result, $result['ok'] ? 200 : 422);
    }
}
