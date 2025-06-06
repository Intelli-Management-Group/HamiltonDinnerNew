<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FormTypeController extends Controller
{
    /**
     * List all form-types (with optional pagination)
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $query = FormType::orderBy('id', 'desc');

        if ($request->has('page') || $request->has('per_page')) {
            $page = $query->paginate($perPage);
            return response()->json([
                'success' => true,
                'data'    => $page->items(),
                'meta'    => [
                    'current_page' => $page->currentPage(),
                    'last_page'    => $page->lastPage(),
                    'per_page'     => $page->perPage(),
                    'total'        => $page->total(),
                ],
                'message' => 'Form types retrieved'
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->get(),
            'message' => 'Form types retrieved'
        ]);
    }

    /**
     * Create a new form-type
     */
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'allow_print'  => 'required|boolean',
            'allow_mail'   => 'required|boolean',
        ]);

        if ($v->fails()) {
            return response()->json(['success'=>false,'errors'=>$v->errors()], 422);
        }

        $formType = FormType::create($request->only(['name','allow_print','allow_mail']));
        return response()->json([
            'success' => true,
            'data'    => $formType,
            'message' => 'Form type created'
        ], 201);
    }

    /**
     * Get a single form-type by ID
     */
    public function show($id)
    {
        try {
            $formType = FormType::findOrFail($id);
            return response()->json([
                'success' => true,
                'data'    => $formType,
                'message' => 'Form type retrieved'
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success'=>false,'message'=>'Not found'], 404);
        }
    }

    /**
     * Update a form-type
     */
    public function update(Request $request, $id)
    {
        try {
            $formType = FormType::findOrFail($id);

            $v = Validator::make($request->all(), [
                'name'         => ['sometimes','required','string','max:255'],
                'allow_print'  => ['sometimes','required','boolean'],
                'allow_mail'   => ['sometimes','required','boolean'],
            ]);

            if ($v->fails()) {
                return response()->json(['success'=>false,'errors'=>$v->errors()], 422);
            }

            $formType->update($request->only(['name','allow_print','allow_mail']));
            return response()->json([
                'success' => true,
                'data'    => $formType,
                'message' => 'Form type updated'
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success'=>false,'message'=>'Not found'], 404);
        }
    }

    /**
     * Delete a form-type
     */
    public function destroy($id)
    {
        try {
            $formType = FormType::findOrFail($id);
            $formType->delete();
            return response()->json(['success'=>true,'message'=>'Form type deleted']);
        } catch (\Throwable $e) {
            return response()->json(['success'=>false,'message'=>'Not found'], 404);
        }
    }

    /**
     * Bulk-delete form-types
     */
    public function bulkDestroy(Request $request)
    {
        $v = Validator::make($request->all(), [
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:form_types,id',
        ]);

        if ($v->fails()) {
            return response()->json(['success'=>false,'errors'=>$v->errors()], 422);
        }

        FormType::whereIn('id', $request->ids)->delete();
        return response()->json(['success'=>true,'message'=>'Form types deleted']);
    }
}