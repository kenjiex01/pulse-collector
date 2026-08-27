<?php

namespace App\Services;

use App\Models\BiometricAttendanceLog;
use App\Models\BiometricDeviceUser;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DtrExportService
{
    private const MAX_IN_OUT_PAIRS = 5;

    /** @var list<string> */
    private const IN_PUNCH_STATES = [
        'CheckIn',
        'BreakIn',
        'OvertimeIn',
    ];

    /** @var list<string> */
    private const OUT_PUNCH_STATES = [
        'CheckOut',
        'BreakOut',
        'OvertimeOut',
    ];

    public static function userKey(int $deviceId, string $userId): string
    {
        return $deviceId.':'.$userId;
    }

    /**
     * @param  list<string>  $userKeys
     * @return list<array{device_id: int, user_id: string}>
     */
    public function parseUserKeys(array $userKeys): array
    {
        $parsed = [];

        foreach ($userKeys as $key) {
            $key = trim((string) $key);

            if ($key === '' || ! str_contains($key, ':')) {
                continue;
            }

            [$deviceId, $userId] = explode(':', $key, 2);
            $deviceId = (int) $deviceId;
            $userId = trim($userId);

            if ($deviceId <= 0 || $userId === '') {
                continue;
            }

            $parsed[] = [
                'device_id' => $deviceId,
                'user_id' => $userId,
            ];
        }

        return $parsed;
    }

    public function downloadResponse(Carbon $dateFrom, Carbon $dateTo, array $userKeys): StreamedResponse
    {
        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $selections = $this->parseUserKeys($userKeys);

        if ($selections === []) {
            throw new RuntimeException('Select at least one enrolled user.');
        }

        $filename = sprintf(
            'dtr_%s_%s.csv',
            $dateFrom->format('Y-m-d'),
            $dateTo->format('Y-m-d'),
        );

        return response()->streamDownload(function () use ($dateFrom, $dateTo, $selections): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new RuntimeException('Could not open DTR export stream.');
            }

            $this->writeCsv($handle, $dateFrom, $dateTo, $selections);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  resource  $handle
     * @param  list<array{device_id: int, user_id: string}>  $selections
     */
    public function writeCsv($handle, Carbon $dateFrom, Carbon $dateTo, array $selections): void
    {
        $profiles = $this->loadUserProfiles($selections);
        $logsByUser = $this->loadLogsGroupedByUser($dateFrom, $dateTo, $selections);
        $period = CarbonPeriod::create($dateFrom->copy()->startOfDay(), $dateTo->copy()->startOfDay());

        $firstBlock = true;

        foreach ($selections as $selection) {
            $key = self::userKey($selection['device_id'], $selection['user_id']);
            $profile = $profiles->get($key);
            $displayName = trim((string) ($profile['name'] ?? ''));
            $displayName = $displayName !== '' ? $displayName : $selection['user_id'];
            $userLogs = $logsByUser->get($key, collect());

            if (! $firstBlock) {
                fputcsv($handle, []);
            }

            $firstBlock = false;

            fputcsv($handle, ['Employee: '.$displayName.' ('.$selection['user_id'].')']);
            fputcsv($handle, $this->timesheetHeaderRow());

            foreach ($period as $date) {
                /** @var Carbon $date */
                $dayKey = $date->format('Y-m-d');
                $dayLogs = $userLogs->get($dayKey, collect());
                fputcsv($handle, $this->timesheetDataRow($dayKey, $dayLogs));
            }
        }
    }

    /**
     * @param  list<array{device_id: int, user_id: string}>  $selections
     * @return Collection<string, array{name: ?string, device_name: ?string}>
     */
    private function loadUserProfiles(array $selections): Collection
    {
        $query = BiometricDeviceUser::query()->with('device');

        $query->where(function ($builder) use ($selections): void {
            foreach ($selections as $selection) {
                $builder->orWhere(function ($inner) use ($selection): void {
                    $inner->where('biometric_device_id', $selection['device_id'])
                        ->where('user_id', $selection['user_id']);
                });
            }
        });

        return $query->get()->mapWithKeys(function (BiometricDeviceUser $user): array {
            $key = self::userKey((int) $user->biometric_device_id, (string) $user->user_id);

            return [
                $key => [
                    'name' => $user->name,
                    'device_name' => $user->device?->name,
                ],
            ];
        });
    }

    /**
     * @param  list<array{device_id: int, user_id: string}>  $selections
     * @return Collection<string, Collection<string, Collection<int, BiometricAttendanceLog>>>
     */
    private function loadLogsGroupedByUser(Carbon $dateFrom, Carbon $dateTo, array $selections): Collection
    {
        $logs = BiometricAttendanceLog::query()
            ->where(function ($builder) use ($selections): void {
                foreach ($selections as $selection) {
                    $builder->orWhere(function ($inner) use ($selection): void {
                        $inner->where('biometric_device_id', $selection['device_id'])
                            ->where('user_id', $selection['user_id']);
                    });
                }
            })
            ->whereDate('punched_at', '>=', $dateFrom)
            ->whereDate('punched_at', '<=', $dateTo)
            ->orderBy('punched_at')
            ->get();

        return $logs->groupBy(function (BiometricAttendanceLog $log): string {
            return self::userKey((int) $log->biometric_device_id, (string) $log->user_id);
        })->map(function (Collection $userLogs): Collection {
            return $userLogs->groupBy(function (BiometricAttendanceLog $log): string {
                return $log->punched_at->format('Y-m-d');
            });
        });
    }

    /**
     * @return list<string>
     */
    private function timesheetHeaderRow(): array
    {
        $columns = ['Date', ''];

        for ($pairIndex = 0; $pairIndex < self::MAX_IN_OUT_PAIRS; $pairIndex++) {
            $columns[] = 'In';

            if ($pairIndex < self::MAX_IN_OUT_PAIRS - 1) {
                $columns[] = 'Out';
            }
        }

        return $columns;
    }

    /**
     * @param  Collection<int, BiometricAttendanceLog>  $dayLogs
     * @return list<string>
     */
    private function timesheetDataRow(string $date, Collection $dayLogs): array
    {
        $row = [$date, ''];
        $pairs = $this->pairPunchesForDay($dayLogs->all());

        for ($pairIndex = 0; $pairIndex < self::MAX_IN_OUT_PAIRS; $pairIndex++) {
            $pair = $pairs[$pairIndex] ?? ['in' => '', 'out' => ''];
            $row[] = $pair['in'];

            if ($pairIndex < self::MAX_IN_OUT_PAIRS - 1) {
                $row[] = $pair['out'];
            }
        }

        return $row;
    }

    /**
     * @param  list<BiometricAttendanceLog>  $punches
     * @return list<array{in: string, out: string}>
     */
    public function pairPunchesForDay(array $punches): array
    {
        usort($punches, function (BiometricAttendanceLog $left, BiometricAttendanceLog $right): int {
            return $left->punched_at <=> $right->punched_at;
        });

        $pairs = [];
        $current = ['in' => '', 'out' => ''];
        $expectIn = true;

        foreach ($punches as $punch) {
            $time = $punch->punched_at->format('H:i:s');
            $punchState = (string) ($punch->punch_state ?? '');

            if ($this->isInPunchState($punchState)) {
                if ($current['in'] !== '' && $current['out'] === '') {
                    $pairs[] = $current;
                    $current = ['in' => $time, 'out' => ''];
                } elseif ($current['in'] === '') {
                    $current['in'] = $time;
                } else {
                    $pairs[] = $current;
                    $current = ['in' => $time, 'out' => ''];
                }

                $expectIn = false;

                continue;
            }

            if ($this->isOutPunchState($punchState)) {
                if ($current['in'] === '') {
                    $current['in'] = '';
                }

                $current['out'] = $time;
                $pairs[] = $current;
                $current = ['in' => '', 'out' => ''];
                $expectIn = true;

                continue;
            }

            if ($expectIn) {
                $current['in'] = $time;
                $expectIn = false;
            } else {
                $current['out'] = $time;
                $pairs[] = $current;
                $current = ['in' => '', 'out' => ''];
                $expectIn = true;
            }
        }

        if ($current['in'] !== '' || $current['out'] !== '') {
            $pairs[] = $current;
        }

        return array_slice($pairs, 0, self::MAX_IN_OUT_PAIRS);
    }

    private function isInPunchState(string $punchState): bool
    {
        return in_array($punchState, self::IN_PUNCH_STATES, true);
    }

    private function isOutPunchState(string $punchState): bool
    {
        return in_array($punchState, self::OUT_PUNCH_STATES, true);
    }
}
