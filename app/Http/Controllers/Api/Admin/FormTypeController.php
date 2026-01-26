<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\FormTypeService;
use App\Models\FormType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FormTypeController extends Controller
{

    public function __construct(
        private FormTypeService $formTypeService
    ) {}

    /**
     * List all form-types (with optional pagination)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $result = $this->formTypeService->list($request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Create a new form-type
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'allow_print'  => 'required|boolean',
            'allow_mail'   => 'required|boolean',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success'=>false,
                'errors'=>$v->errors()
            ], 422);
        }

        $formType = $this->formTypeService->store(
            $request->only([
                'name','allow_print','allow_mail'
            ])
        );
        
        return response()->json($formType['payload'], $formType['statusCode']);
    }

    /**
     * Get a single form-type by ID
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $result = $this->formTypeService->findById($id);

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Update a form-type
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $findResult = $this->formTypeService->findById($id);

        if ($findResult['statusCode'] === 404) {
            return response()->json($findResult['payload'], 404);
        }

        /** @var FormType $formType */
        $formType = $findResult['payload']['data'];

        $v = Validator::make($request->all(), [
            'name'         => ['sometimes','required','string','max:255'],
            'allow_print'  => ['sometimes','required','boolean'],
            'allow_mail'   => ['sometimes','required','boolean'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'success'=>false,
                'errors'=>$v->errors()
            ], 422);
        }

        $result = $this->formTypeService->update(
            $formType,
            $request->only([
                'name', 'allow_print', 'allow_mail'
            ])
        );

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Delete a form-type
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $result = $this->formTypeService->destroy($id);

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Bulk-delete form-types
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDestroy(Request $request)
    {
        $v = Validator::make($request->all(), [
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:form_types,id',
        ]);

        if ($v->fails()) {
            return response()->json([
                'success'=>false,
                'errors'=>$v->errors()
            ], 422);
        }

        $result = $this->formTypeService->bulkDestroy($request->ids);

        return response()->json($result['payload'], $result['statusCode']);
    }
}