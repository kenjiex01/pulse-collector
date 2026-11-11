<?php

namespace App\Services;

use App\Models\BiometricDeviceUser;
use App\Models\Department;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;

class DeviceUserDepartmentUploadService
{
    public function assignToUser(string $userId, int $departmentId): void
    {
        $department = Department::query()->findOrFail($departmentId);
        $matches = BiometricDeviceUser::query()
            ->with('department')
            ->where('user_id', $userId)
            ->orderByDesc('synced_at')
            ->orderBy('id')
            ->get();

        if ($matches->isEmpty()) {
            return;
        }

        $this->applyDepartment($userId, $department, $matches);
    }

    public function template(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Departments');
        $sheet->fromArray(['User ID', 'Name', 'Department'], null, 'A1');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);

        $line = 2;
        foreach ($this->templateRows() as $row) {
            $sheet->setCellValueExplicit('A'.$line, $row['user_id'], DataType::TYPE_STRING);
            $sheet->setCellValue('B'.$line, $row['name']);
            $sheet->setCellValue('C'.$line, $row['department'] ?? '');
            $line++;
        }

        foreach (['A', 'B', 'C'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * @return array{assigned: int, updated: int, unchanged: int, skipped: int, errors: list<string>}
     */
    public function import(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $headers = [];

        for ($column = 1; $column <= $highestColumn; $column++) {
            $label = mb_strtolower(trim((string) $sheet->getCell([$column, 1])->getValue()));
            if ($label !== '') {
                $headers[$label] = $column;
            }
        }

        if (! isset($headers['user id'], $headers['department'])) {
            throw new RuntimeException('The file must include User ID and Department columns.');
        }

        $departments = Department::query()->get()->keyBy(
            fn (Department $department): string => mb_strtolower($department->name),
        );

        $result = [
            'assigned' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $lastRow = (int) $sheet->getHighestDataRow();
        for ($row = 2; $row <= $lastRow; $row++) {
            $userId = trim((string) $sheet->getCell([$headers['user id'], $row])->getFormattedValue());
            $departmentName = trim((string) $sheet->getCell([$headers['department'], $row])->getFormattedValue());

            if ($userId === '' && $departmentName === '') {
                continue;
            }

            if ($userId === '') {
                $result['errors'][] = 'Row '.$row.': User ID is required.';

                continue;
            }

            if ($departmentName === '') {
                $result['skipped']++;

                continue;
            }

            $department = $departments->get(mb_strtolower($departmentName));
            if ($department === null) {
                $result['errors'][] = 'Row '.$row.': Unknown department "'.$departmentName.'".';

                continue;
            }

            $matches = BiometricDeviceUser::query()
                ->with('department')
                ->where('user_id', $userId)
                ->orderByDesc('synced_at')
                ->orderBy('id')
                ->get();

            if ($matches->isEmpty()) {
                $result['errors'][] = 'Row '.$row.': User ID '.$userId.' was not found.';

                continue;
            }

            $change = $this->applyDepartment($userId, $department, $matches);

            if ($change === 'unchanged') {
                $result['unchanged']++;
            } elseif ($change === 'assigned') {
                $result['assigned']++;
            } else {
                $result['updated']++;
            }
        }

        return $result;
    }

    /**
     * @param  Collection<int, BiometricDeviceUser>  $matches
     */
    private function applyDepartment(string $userId, Department $department, Collection $matches): string
    {
        $current = $matches->first(fn (BiometricDeviceUser $user): bool => $user->department_id !== null)?->department;
        $previousName = $current?->name;
        $alreadySet = $matches->every(
            fn (BiometricDeviceUser $user): bool => (int) $user->department_id === (int) $department->id,
        );

        if ($alreadySet) {
            return 'unchanged';
        }

        BiometricDeviceUser::query()
            ->where('user_id', $userId)
            ->update(['department_id' => $department->id]);

        if ($previousName !== null && mb_strtolower($previousName) === mb_strtolower($department->name)) {
            return 'unchanged';
        }

        SysLogService::record(
            'update',
            'biometric_device_users',
            (int) $matches->first()->id,
            [
                'user_id' => $userId,
                'department' => $previousName,
            ],
            [
                'user_id' => $userId,
                'department' => $department->name,
            ],
            $previousName === null ? 'Assigned department' : 'Updated department',
        );

        return $previousName === null ? 'assigned' : 'updated';
    }

    /**
     * @return list<array{user_id: string, name: string, department: ?string}>
     */
    private function templateRows(): array
    {
        $grouped = BiometricDeviceUser::query()
            ->with('department')
            ->orderBy('user_id')
            ->get()
            ->groupBy('user_id');

        $rows = [];
        foreach ($grouped as $userId => $users) {
            $primary = $users->sortByDesc(fn (BiometricDeviceUser $user): int => $user->synced_at?->getTimestamp() ?? 0)->first();
            $named = $users->first(fn (BiometricDeviceUser $user): bool => $user->department_id !== null);

            $rows[] = [
                'user_id' => (string) $userId,
                'name' => (string) ($primary?->name ?? ''),
                'department' => $named?->department?->name,
            ];
        }

        return $rows;
    }
}
