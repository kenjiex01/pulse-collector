<?php

namespace App\Http\Controllers;

use App\Models\BiometricAttendanceLog;
use App\Models\BiometricDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollectedLogController extends Controller
{
    /** @var list<string> */
    private const SORTABLE_COLUMNS = [
        'punched_at',
        'user_id',
        'user_name',
        'card_number',
        'device',
        'ip',
        'verify_mode',
        'punch_state',
        's3',
        'created_at',
    ];

    public function index(Request $request): View
    {
        $deviceId = $request->integer('device_id') ?: null;
        $perPageOptions = config('biometric.logs.per_page_options', [10, 25, 50, 100]);
        $defaultPerPage = (int) config('biometric.logs.per_page', 25);
        $perPageInput = $request->input('per_page');
        $showAll = $perPageInput === 'all';

        if ($showAll) {
            $perPageSelection = 'all';
        } elseif ($perPageInput !== null && $perPageInput !== '') {
            $perPage = (int) $perPageInput;
            $perPageSelection = in_array($perPage, $perPageOptions, true)
                ? $perPage
                : $defaultPerPage;
        } else {
            $perPageSelection = $defaultPerPage;
        }

        $search = trim((string) $request->input('search', ''));

        $dateFrom = $this->parseFilterDate($request->input('date_from'));
        $dateTo = $this->parseFilterDate($request->input('date_to'));

        if ($dateFrom !== null && $dateTo !== null && $dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $selectedDevice = $deviceId
            ? BiometricDevice::query()->find($deviceId)
            : null;

        $query = BiometricAttendanceLog::query()
            ->with(['campus', 'device'])
            ->when($deviceId, fn ($q) => $q->where('biometric_device_id', $deviceId))
            ->when($search !== '', function ($q) use ($search): void {
                $like = '%'.$search.'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('user_id', 'like', $like)
                        ->orWhere('user_name', 'like', $like)
                        ->orWhere('card_number', 'like', $like);
                });
            })
            ->when($dateFrom !== null, fn ($q) => $q->whereDate('punched_at', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($q) => $q->whereDate('punched_at', '<=', $dateTo));

        $filteredTotal = (clone $query)->count();

        $perPage = $showAll ? max(1, $filteredTotal) : (int) $perPageSelection;

        $sortColumn = (string) $request->input('sort', 'punched_at');
        if (! in_array($sortColumn, self::SORTABLE_COLUMNS, true)) {
            $sortColumn = 'punched_at';
        }

        $sortDirection = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        $this->applySort($query, $sortColumn, $sortDirection);

        $logs = $query
            ->paginate($perPage)
            ->withQueryString();

        return view('collector.logs.index', [
            'logs' => $logs,
            'devices' => BiometricDevice::query()->with('campus')->orderBy('name')->get(),
            'selectedDeviceId' => $deviceId,
            'selectedDevice' => $selectedDevice,
            'filteredTotal' => $filteredTotal,
            'search' => $search,
            'dateFrom' => $dateFrom?->format('Y-m-d'),
            'dateTo' => $dateTo?->format('Y-m-d'),
            'perPageSelection' => $perPageSelection,
            'perPageOptions' => $perPageOptions,
            'sortColumn' => $sortColumn,
            'sortDirection' => $sortDirection,
        ]);
    }

    private function applySort(\Illuminate\Database\Eloquent\Builder $query, string $sortColumn, string $sortDirection): void
    {
        $table = (new BiometricAttendanceLog)->getTable();
        $idColumn = $table.'.id';

        if ($sortColumn === 'device') {
            $query
                ->leftJoin('biometric_devices as sort_devices', 'sort_devices.id', '=', $table.'.biometric_device_id')
                ->orderBy('sort_devices.name', $sortDirection)
                ->select($table.'.*');

            $query->orderBy($idColumn, $sortDirection);

            return;
        }

        if ($sortColumn === 'ip') {
            $query
                ->leftJoin('biometric_devices as sort_devices', 'sort_devices.id', '=', $table.'.biometric_device_id')
                ->orderBy('sort_devices.ip_address', $sortDirection)
                ->select($table.'.*');

            $query->orderBy($idColumn, $sortDirection);

            return;
        }

        if ($sortColumn === 's3') {
            $query->orderBy($table.'.s3_pushed_at', $sortDirection);
            $query->orderBy($idColumn, $sortDirection);

            return;
        }

        $query->orderBy($table.'.'.$sortColumn, $sortDirection);
        $query->orderBy($idColumn, $sortDirection);
    }

    private function parseFilterDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($value))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
