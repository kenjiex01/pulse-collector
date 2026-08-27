<?php

namespace App\Http\Controllers;

use App\Models\BiometricDevice;
use App\Models\BiometricDeviceUser;
use App\Services\DtrExportService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class DtrDownloadController extends Controller
{
    public function __construct(
        private readonly DtrExportService $dtrExport,
    ) {}

    public function index(Request $request): View
    {
        $deviceId = $request->integer('device_id') ?: null;
        $search = trim((string) $request->input('search', ''));
        $dateFrom = $this->parseFilterDate($request->input('date_from'))
            ?? now()->startOfMonth();
        $dateTo = $this->parseFilterDate($request->input('date_to'))
            ?? now();

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $selectedDevice = $deviceId
            ? BiometricDevice::query()->with('campus')->find($deviceId)
            : null;

        $users = BiometricDeviceUser::query()
            ->with(['device.campus'])
            ->when($deviceId, fn ($query) => $query->where('biometric_device_id', $deviceId))
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('user_id', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('card_number', 'like', $like);
                });
            })
            ->orderBy('biometric_device_id')
            ->orderBy('name')
            ->orderBy('user_id')
            ->get();

        $selectedUserKeys = collect(old('users', []))
            ->map(fn ($value) => (string) $value)
            ->filter()
            ->values()
            ->all();

        return view('collector.dtr.index', [
            'users' => $users,
            'devices' => BiometricDevice::query()->with('campus')->orderBy('name')->get(),
            'selectedDeviceId' => $deviceId,
            'selectedDevice' => $selectedDevice,
            'search' => $search,
            'dateFrom' => $dateFrom->format('Y-m-d'),
            'dateTo' => $dateTo->format('Y-m-d'),
            'selectedUserKeys' => $selectedUserKeys,
        ]);
    }

    public function download(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date'],
            'users' => ['required', 'array', 'min:1'],
            'users.*' => ['required', 'string', 'regex:/^\d+:.+$/'],
        ]);

        try {
            $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
            $dateTo = Carbon::parse($validated['date_to'])->startOfDay();

            return $this->dtrExport->downloadResponse(
                $dateFrom,
                $dateTo,
                $validated['users'],
            );
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('dtr.index', $request->only(['device_id', 'search']))
                ->withInput()
                ->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            return redirect()
                ->route('dtr.index', $request->only(['device_id', 'search']))
                ->withInput()
                ->with('error', 'Could not generate DTR export: '.$exception->getMessage());
        }
    }

    private function parseFilterDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
