<?php

namespace App\Http\Controllers;

use App\Models\BiometricDeviceUser;
use App\Models\Department;
use App\Services\SysLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        return view('collector.departments.index', [
            'departments' => Department::query()
                ->withCount('deviceUsers')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('collector.departments.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', $this->uniqueNameRule()],
        ]);

        $department = Department::query()->create([
            'name' => trim($validated['name']),
        ]);

        SysLogService::record(
            'create',
            'departments',
            (int) $department->id,
            null,
            ['name' => $department->name],
            'Added department '.$department->name,
        );

        return redirect()
            ->route('departments.index')
            ->with('success', 'Department added: '.$department->name);
    }

    public function edit(Department $department): View
    {
        return view('collector.departments.edit', [
            'department' => $department,
        ]);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', $this->uniqueNameRule($department->id)],
        ]);

        $previous = $department->name;
        $name = trim($validated['name']);

        if ($previous !== $name) {
            $department->update(['name' => $name]);

            SysLogService::record(
                'update',
                'departments',
                (int) $department->id,
                ['name' => $previous],
                ['name' => $name],
                'Renamed department',
            );
        }

        return redirect()
            ->route('departments.index')
            ->with('success', 'Department saved: '.$department->name);
    }

    public function destroy(Department $department): RedirectResponse
    {
        $inUse = BiometricDeviceUser::query()
            ->where('department_id', $department->id)
            ->exists();

        if ($inUse) {
            return redirect()
                ->route('departments.index')
                ->with('error', 'Cannot delete '.$department->name.' while device users still use it.');
        }

        $name = $department->name;
        $id = (int) $department->id;
        $department->delete();

        SysLogService::record(
            'delete',
            'departments',
            $id,
            ['name' => $name],
            null,
            'Deleted department '.$name,
        );

        return redirect()
            ->route('departments.index')
            ->with('success', 'Removed department: '.$name);
    }

    private function uniqueNameRule(?int $ignoreId = null): \Illuminate\Validation\Rules\Unique
    {
        $rule = Rule::unique('departments', 'name')->whereNull('deleted_at');

        if ($ignoreId !== null) {
            $rule->ignore($ignoreId);
        }

        return $rule;
    }
}
