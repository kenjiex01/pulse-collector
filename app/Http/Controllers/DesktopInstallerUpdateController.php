<?php

namespace App\Http\Controllers;

use App\Services\DesktopInstallerUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DesktopInstallerUpdateController extends Controller
{
    public function download(Request $request, DesktopInstallerUpdateService $updater): JsonResponse|RedirectResponse
    {
        abort_unless($updater->isEnabled(), 404);

        try {
            $check = $updater->checkIfNeeded();
            $filename = is_array($check)
                ? (string) ($check['filename'] ?? 'Pulse-setup')
                : 'Pulse-setup';

            if ($check === null || ! ($check['available'] ?? false)) {
                if ($this->wantsJson($request)) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'No newer installer is available for download right now.',
                    ], 404);
                }

                return redirect()
                    ->route('dashboard')
                    ->with('error', 'No newer installer is available for download right now.');
            }

            // Prefer a short-lived S3 URL. Proxying ~150MB through NativePHP PHP
            // (stream route + XHR blob) OOMs / 500s on Windows desktop builds.
            $downloadUrl = $updater->temporaryDownloadUrl();
            $viaStream = false;

            if ($downloadUrl === null || $downloadUrl === '') {
                $downloadUrl = route('desktop.update.stream');
                $viaStream = true;
            }

            if ($this->wantsJson($request)) {
                return response()->json([
                    'ok' => true,
                    'url' => $downloadUrl,
                    'filename' => $filename,
                    'stream' => $viaStream,
                ]);
            }

            return redirect()->away($downloadUrl);
        } catch (Throwable $exception) {
            report($exception);

            if ($this->wantsJson($request)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Unable to prepare the installer download. Use the AWS S3 console link or ask for a new pre-signed URL.',
                ], 500);
            }

            return redirect()
                ->route('dashboard')
                ->with('error', 'Unable to prepare the installer download.');
        }
    }

    public function stream(DesktopInstallerUpdateService $updater): StreamedResponse
    {
        abort_unless($updater->isEnabled(), 404);

        $payload = $updater->openLatestInstallerStream();
        abort_if($payload === null, 404, 'No newer installer is available for download right now.');

        $filename = $payload['filename'];
        $bytes = $payload['bytes'];
        $stream = $payload['stream'];

        return response()->streamDownload(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, $filename, array_filter([
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => $bytes !== null ? (string) $bytes : null,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]));
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->wantsJson() || $request->ajax();
    }
}
