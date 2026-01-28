<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DocumentTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = DocumentType::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $documentTypes = $query->orderBy('name')->paginate(10)->withQueryString();

        return Inertia::render('DocumentTypes/Index', [
            'documentTypes' => $documentTypes,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:document_types,name',
            'is_required' => 'boolean',
        ]);

        DocumentType::create([
            'name' => $request->name,
            'is_required' => $request->is_required ?? false,
        ]);

        return redirect()->back()->with('success', 'Document Type added successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DocumentType $documentType)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:document_types,name,' . $documentType->id,
            'is_required' => 'boolean',
        ]);

        $documentType->update([
            'name' => $request->name,
            'is_required' => $request->is_required ?? false,
        ]);

        return redirect()->back()->with('success', 'Document Type updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DocumentType $documentType)
    {
        // Optional: Check if used in documents
        // if ($documentType->documents()->exists()) {
        //     return redirect()->back()->with('error', 'Cannot delete in-use document type.');
        // }

        $documentType->delete();

        return redirect()->back()->with('success', 'Document Type deleted successfully.');
    }
}
