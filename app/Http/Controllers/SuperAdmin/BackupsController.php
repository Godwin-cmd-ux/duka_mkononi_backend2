<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\BackupManager;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The Super Admin's Backups page.
 *
 * Two kinds of backup live in Cloudinary and in the backups table: the nightly
 * one (backup_<date>/), and an induced one
 * (induced/backup_<date>_<time>/). Reading, importing, inducing and deleting
 * all happen in BackupManager, so this controller only decides how to present
 * them: a Blade page, a flash message, a streamed file.
 */
class BackupsController extends Controller
{
    private BackupManager $manager;

    public function __construct(?BackupManager $manager = null)
    {
        $this->manager = $manager ?? new BackupManager();
    }

    public function index(Request $request): View
    {
        $import = $this->manager->import();

        $from = BackupManager::dateOrNull($request->query('date_from'));
        $to = BackupManager::dateOrNull($request->query('date_to'));

        $tableWarning = null;

        try {
            $listing = $this->manager->list($from, $to);
        } catch (Throwable $e) {
            // A deployment whose Supabase project has not had SYSTEM_SCHEMA.sql
            // (or the migration) applied yet still gets a usable page that says
            // exactly what is missing, instead of a 500.
            $listing = [
                'days' => [],
                'total_stored' => 0,
                'total_bytes' => 0,
                'matching_bytes' => 0,
            ];
            $tableWarning = 'The backups table is not available yet: ' . $e->getMessage();
        }

        return view('system_admin.backup', [
            'days' => $listing['days'],
            'totalStored' => $listing['total_stored'],
            'totalBytes' => $listing['total_bytes'],
            'matchingBytes' => $listing['matching_bytes'],
            'latest' => $listing['days'][0]->stamp ?? null,
            'imported' => $import['imported'],
            'cloudinaryWarning' => $import['warning'],
            'tableWarning' => $tableWarning,
            'cloudinaryFolder' => DatabaseBackupService::rootFolder(),
            'scheduleTimezone' => DatabaseBackupService::TIMEZONE,
            'filters' => ['date_from' => $from, 'date_to' => $to],
            'filtered' => $from !== null || $to !== null,
        ]);
    }

    /** "Induce Backup": take one now, independent of the nightly run. */
    public function induce(): RedirectResponse
    {
        $result = $this->manager->induce();

        return redirect()->route('system_admin.backup.index')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Hand one of a backup's two files to the browser as a download.
     *
     * It is proxied rather than linked so the file always arrives as an
     * attachment (Cloudinary serves raw text inline) and so the download stays
     * behind the super-admin check.
     */
    public function download(string $stamp, string $file): StreamedResponse|RedirectResponse
    {
        if (! in_array($file, BackupManager::KINDS, true)) {
            abort(404);
        }

        $row = $this->manager->find($stamp);

        if ($row === null) {
            return redirect()->route('system_admin.backup.index')
                ->with('error', "There is no backup recorded for {$this->manager->label($stamp)}.");
        }

        try {
            $response = Http::timeout(180)->get($this->manager->fileUrl($row, $stamp, $file));
        } catch (Throwable $e) {
            Log::error('Backup download failed', ['stamp' => $stamp, 'file' => $file, 'message' => $e->getMessage()]);

            return redirect()->route('system_admin.backup.index')
                ->with('error', "Could not reach Cloudinary for {$this->manager->label($stamp)}: " . $e->getMessage());
        }

        if (! $response->successful()) {
            return redirect()->route('system_admin.backup.index')->with(
                'error',
                "Cloudinary could not return the {$file} file for {$this->manager->label($stamp)} (HTTP " . $response->status() . ').'
            );
        }

        $body = $response->body();

        // The seed is stored gzipped (under its .sql name, see
        // DatabaseBackupService::storedName) to fit Cloudinary's 10 MB raw-file
        // limit. The super admin still downloads plain SQL, so restore is one
        // command. Backups imported from older folders are plain and pass through.
        if ($file === 'seed' && str_starts_with($body, "\x1f\x8b")) {
            $expanded = @gzdecode($body);

            if ($expanded === false) {
                return redirect()->route('system_admin.backup.index')->with(
                    'error',
                    "The seed file for {$this->manager->label($stamp)} could not be expanded. It is stored in Cloudinary, but gzip reported a corrupt file."
                );
            }

            $body = $expanded;
        }

        $filename = DatabaseBackupService::fileName($stamp, $file);

        return response()->streamDownload(function () use ($body) {
            echo $body;
        }, $filename, ['Content-Type' => 'application/sql']);
    }

    /** Delete one backup: both files, then the row. */
    public function destroy(string $stamp): RedirectResponse
    {
        $result = $this->manager->delete($stamp);

        return redirect()->route('system_admin.backup.index')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
