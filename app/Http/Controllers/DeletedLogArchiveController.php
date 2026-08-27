<?php

namespace App\Http\Controllers;

use App\Services\BiometricDeletedLogArchiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class DeletedLogArchiveController extends Controller
{
    public function __construct(
        private readonly BiometricDeletedLogArchiveService $archives,
    ) {}

    public function index(): View
    {
        return view('collector.archives.index', [
            'archives' => $this->archives->listArchives(),
            'archiveDirectory' => app(\App\Services\BiometricLogRetentionService::class)->archiveDirectoryName(),
        ]);
    }

    public function download(string $filename)
    {
        try {
            return $this->archives->downloadResponse($filename);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('archives.index')
                ->with('error', $exception->getMessage());
        }
    }

    public function uploadForm(): View
    {
        return view('collector.archives.upload', [
            'archiveDirectory' => app(\App\Services\BiometricLogRetentionService::class)->archiveDirectoryName(),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'backup_json' => ['required', 'file', 'max:51200'],
        ]);

        try {
            $result = $this->archives->importUploadedJson($validated['backup_json']);

            $message = sprintf(
                'Backup JSON imported: %d punch(es) restored, %d duplicate(s) skipped, %d row(s) could not be matched to a local device.',
                $result['inserted'],
                $result['skipped_duplicates'],
                $result['skipped_unmatched'],
            );

            if ($result['stored_copy'] && $result['filename']) {
                $message .= ' A copy was saved as '.$result['filename'].'.';
            }

            return redirect()
                ->route('archives.upload')
                ->with('success', $message);
        } catch (Throwable $exception) {
            return redirect()
                ->route('archives.upload')
                ->with('error', $exception->getMessage());
        }
    }
}
