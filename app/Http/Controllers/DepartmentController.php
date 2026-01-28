<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Department::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $departments = $query->orderBy('name')->paginate(10)->withQueryString();

        return Inertia::render('Departments/Index', [
            'departments' => $departments,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
            'department_code' => 'nullable|string|max:50',
            'oms_code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        Department::create([
            'name' => strtoupper($request->name),
            'department_code' => $request->department_code ? strtoupper($request->department_code) : null,
            'oms_code' => $request->oms_code ? strtoupper($request->oms_code) : null,
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'Department created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Department $department)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:departments,name,' . $department->id,
            'department_code' => 'nullable|string|max:50',
            'oms_code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $department->update([
            'name' => strtoupper($request->name),
            'department_code' => $request->department_code ? strtoupper($request->department_code) : null,
            'oms_code' => $request->oms_code ? strtoupper($request->oms_code) : null,
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'Department updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department)
    {
        // Optional: Check if department has employees before deleting
        // if ($department->employees()->exists()) {
        //     return redirect()->back()->with('error', 'Cannot delete department with active employees.');
        // }

        $department->delete();

        return redirect()->back()->with('success', 'Department deleted successfully.');
    }
}
