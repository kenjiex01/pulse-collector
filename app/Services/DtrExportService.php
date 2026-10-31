<?php

namespace App\Services;

use App\Models\BiometricAttendanceLog;
use App\Models\BiometricDeviceUser;
use App\Support\SpreadsheetDownload;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class DtrExportService
{
    private const MAX_IN_OUT_PAIRS = 6;

    private const COMPANY_NAME = 'ICCT COLLEGES FOUNDATION INC.';

    private const COMPANY_ADDRESS = 'ICCT Bldg., Cabrera Rd. Cor. Manila East Rd. Brgy. San Juan, Taytay, Rizal';

    private const COMPANY_CONTACT = 'Telephone: 8.423.18.41 / 7.978.42.28, Website: www.icct.edu.ph, Email: taytay@icct.edu.ph';

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

    public function downloadResponse(Carbon $dateFrom, Carbon $dateTo, array $userKeys): StreamedResponse|BinaryFileResponse
    {
        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $selections = $this->parseUserKeys($userKeys);

        if ($selections === []) {
            throw new RuntimeException('Select at least one enrolled user.');
        }

        $built = $this->buildWorkbooks($dateFrom, $dateTo, $selections);

        if (count($built) === 1) {
            return SpreadsheetDownload::stream($built[0]['spreadsheet'], $built[0]['filename']);
        }

        return $this->zipResponse($built, sprintf(
            '%s_to_%s_timesheets',
            $dateFrom->format('Y-m-d'),
            $dateTo->format('Y-m-d'),
        ));
    }

    /**
     * @param  list<array{device_id: int, user_id: string}>  $selections
     * @return list<array{spreadsheet: Spreadsheet, filename: string}>
     */
    public function buildWorkbooks(Carbon $dateFrom, Carbon $dateTo, array $selections): array
    {
        $profiles = $this->loadUserProfiles($selections);
        $logsByUser = $this->loadLogsGroupedByUser($dateFrom, $dateTo, $selections);
        $workbooks = [];

        foreach ($selections as $selection) {
            $key = self::userKey($selection['device_id'], $selection['user_id']);
            $profile = $profiles->get($key);
            $displayName = trim((string) ($profile['name'] ?? ''));
            $displayName = $displayName !== '' ? $displayName : $selection['user_id'];
            $workbooks[] = [
                'spreadsheet' => $this->buildTimesheet(
                    $dateFrom,
                    $dateTo,
                    $displayName,
                    $selection['user_id'],
                    $logsByUser->get($key, collect()),
                ),
                'filename' => $this->workbookFilename($dateFrom, $dateTo, $displayName),
            ];
        }

        return $workbooks;
    }

    public function workbookFilename(Carbon $dateFrom, Carbon $dateTo, string $employeeName): string
    {
        $name = trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $employeeName), '_');
        $name = $name !== '' ? $name : 'employee';

        return sprintf(
            '%s_to_%s_%s',
            $dateFrom->format('Y-m-d'),
            $dateTo->format('Y-m-d'),
            $name,
        );
    }

    /**
     * @param  Collection<string, Collection<int, BiometricAttendanceLog>>  $logsByDay
     */
    public function buildTimesheet(
        Carbon $dateFrom,
        Carbon $dateTo,
        string $employeeName,
        string $userId,
        Collection $logsByDay,
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Str::limit($employeeName, 31, ''));

        $lastColumn = 'R';
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->mergeCells("A3:{$lastColumn}3");
        $sheet->mergeCells("A5:{$lastColumn}5");
        $sheet->mergeCells("A6:{$lastColumn}6");

        $sheet->setCellValue('A1', self::COMPANY_NAME);
        $sheet->setCellValue('A2', self::COMPANY_ADDRESS);
        $sheet->setCellValue('A3', self::COMPANY_CONTACT);
        $sheet->setCellValue('A5', 'TIME SHEET REPORT');
        $sheet->setCellValue('A6', sprintf(
            'From %s to %s',
            $dateFrom->format('F j, Y'),
            $dateTo->format('F j, Y'),
        ));
        $sheet->setCellValue('A8', 'Employee: '.$employeeName.' ('.$userId.')');

        $headers = ['Date'];
        for ($pair = 1; $pair <= self::MAX_IN_OUT_PAIRS; $pair++) {
            $headers[] = 'In '.$pair;
            $headers[] = 'Out '.$pair;
        }
        array_push($headers, 'Hrs Required', 'TotHrs Break', 'Hrs Worked', 'Hrs OT', 'Hrs UT');

        $headerRow = 9;
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, $headerRow], $header);
        }

        $period = CarbonPeriod::create($dateFrom->copy()->startOfDay(), $dateTo->copy()->startOfDay());
        $row = $headerRow + 1;
        $daysPresent = 0;
        $daysAbsent = 0;
        $totalWorked = 0.0;

        foreach ($period as $date) {
            /** @var Carbon $date */
            $dayKey = $date->format('Y-m-d');
            $pairs = $this->pairPunchesForDay($logsByDay->get($dayKey, collect())->all());
            $worked = $this->workedHours($pairs);
            $hasPunch = $pairs !== [];

            if ($hasPunch) {
                $daysPresent++;
            } else {
                $daysAbsent++;
            }

            $totalWorked += $worked;
            $values = [$date->format('m/d/Y')];

            for ($pairIndex = 0; $pairIndex < self::MAX_IN_OUT_PAIRS; $pairIndex++) {
                $pair = $pairs[$pairIndex] ?? ['in' => '', 'out' => ''];
                $values[] = $this->displayTime($pair['in']);
                $values[] = $this->displayTime($pair['out']);
            }

            array_push($values, '0.00', '0.00', number_format($worked, 2, '.', ''), '0.00', '0.00');

            foreach ($values as $index => $value) {
                $sheet->setCellValue([$index + 1, $row], $value);
            }

            $row++;
        }

        $totalRow = $row;
        $sheet->setCellValue("A{$totalRow}", 'Total:');
        $sheet->setCellValue("N{$totalRow}", '0.00');
        $sheet->setCellValue("O{$totalRow}", '0.00');
        $sheet->setCellValue("P{$totalRow}", number_format($totalWorked, 2, '.', ''));
        $sheet->setCellValue("Q{$totalRow}", '0.00');
        $sheet->setCellValue("R{$totalRow}", '0.00');

        $summaryRow = $totalRow + 2;
        $sheet->setCellValue("A{$summaryRow}", 'Days Present: '.$daysPresent);
        $sheet->setCellValue("D{$summaryRow}", 'Days Absent: '.$daysAbsent);

        $footer = $summaryRow + 2;
        $workingDays = $daysPresent + $daysAbsent;
        $sheet->setCellValue("A{$footer}", 'TOTAL EMPLOYEES: 1');
        $sheet->setCellValue('A'.($footer + 1), 'Total Working Days: '.$workingDays);
        $sheet->setCellValue('A'.($footer + 2), 'Total Holidays W/in the Period: 0');
        $sheet->setCellValue('A'.($footer + 3), 'Total Hours Required: 0.00');

        $sheet->getStyle("A1:{$lastColumn}3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A5:A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A5')->getFont()->setBold(true);
        $sheet->getStyle('A8')->getFont()->setBold(true);
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$totalRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);
        $sheet->getStyle("A{$totalRow}")->getFont()->setBold(true);
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * @param  list<array{spreadsheet: Spreadsheet, filename: string}>  $workbooks
     */
    private function zipResponse(array $workbooks, string $baseFilename): BinaryFileResponse
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'dtr-zip-');

        if ($zipPath === false) {
            throw new RuntimeException('Could not create the DTR download.');
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the DTR download.');
        }

        $tempFiles = [];

        try {
            foreach ($workbooks as $workbook) {
                $temp = tempnam(sys_get_temp_dir(), 'dtr-xlsx-');

                if ($temp === false) {
                    throw new RuntimeException('Could not create the DTR download.');
                }

                $tempFiles[] = $temp;
                [$writer, $entryName] = SpreadsheetDownload::writerFor($workbook['spreadsheet'], $workbook['filename']);
                $writer->save($temp);
                $zip->addFile($temp, $entryName);
                $workbook['spreadsheet']->disconnectWorksheets();
            }
        } finally {
            $zip->close();
        }

        return response()->download($zipPath, $baseFilename.'.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
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
     * @param  list<array{in: string, out: string}>  $pairs
     */
    private function workedHours(array $pairs): float
    {
        $minutes = 0;

        foreach ($pairs as $pair) {
            if ($pair['in'] === '' || $pair['out'] === '') {
                continue;
            }

            $in = Carbon::createFromFormat('H:i:s', $pair['in']);
            $out = Carbon::createFromFormat('H:i:s', $pair['out']);

            if ($in === false || $out === false || $out->lessThan($in)) {
                continue;
            }

            $minutes += $in->diffInMinutes($out);
        }

        return round($minutes / 60, 2);
    }

    private function displayTime(string $time): string
    {
        if ($time === '') {
            return '';
        }

        $parsed = Carbon::createFromFormat('H:i:s', $time);

        return $parsed === false ? $time : strtolower($parsed->format('g:i a'));
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
