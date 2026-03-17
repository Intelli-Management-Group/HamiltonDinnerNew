<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function __construct(
        private SettingService $settingService
    ) {}

    /** List all settings
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $result = $this->settingService->list($request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /** Private function to validate a Create payload.
     * 
     * @param array $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function validateCreatePayload(array $data)
    {
        return Validator::make($data, [
            'key' => 'required|string|max:127|unique:settings,key',
            'display_name' => 'required|string|max:127',
            'value' => 'nullable|string',
            'details' => 'nullable|string',
            'type' => 'required|string|max:127',
            'order' => 'nullable|integer',
            'group' => 'nullable|string|max:127',
        ]);
    }

    /** Private function to validate an Update payload.
     * 
     * @param array $data
     * @param int $settingId
     * @return \Illuminate\Contracts\Validation\Validator
     */
    private function validateUpdatePayload(array $data, int $settingId)
    {
        return Validator::make($data, [
            'key' => [
                'sometimes',
                'required',
                'string',
                'max:127',
                Rule::unique('settings')->ignore($settingId)
            ],
            'display_name' => 'sometimes|required|string|max:127',
            'value' => 'nullable|string',
            'details' => 'nullable|string',
            'type' => 'sometimes|required|string|max:127',
            'order' => 'nullable|integer',
            'group' => 'nullable|string|max:127',
        ]);
    }

    /**
     * Store new settings (supports single setting or array of settings)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $input = $request->all();

        // Check if input is an array of settings
        $isArray = is_array($input) && isset($input[0]);

        // Convert single item to array format for consistent processing
        // We only need to validate and prepare data here
        $settingsToCreate = $isArray ? $input : [$input];

        foreach ($settingsToCreate as $settingData) {
            // Validate each setting
            $validator = $this->validateCreatePayload($settingData);

            // Future reference: consider grouping validation errors for all items.
            // For now, we can just short-circuit on first failure.
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed for setting with key: ' . ($settingData['key'] ?? 'unknown'),
                    'errors' => $validator->errors()
                ], 422);
            }
        }

        $result = $this->settingService->store($settingsToCreate);

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Get a specific setting
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $result = $this->settingService->findSettingById($id);

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Update a single setting by ID.
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $findResult = $this->settingService->findSettingById($id);

        if ($findResult['statusCode'] === 404) {
            return response()->json($findResult['payload'], 404);
        }

        /** @var Setting $setting */
        $setting = $findResult['payload']['data'];

        $validator = $this->validateUpdatePayload($request->all(), $setting->id);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->settingService->update($setting, $request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Bulk update or insert settings by key.
     * Accepts an array of settings.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpsert(Request $request)
    {
        $input = $request->all();

        foreach ($input as $settingData) {

            $findResult = $this->settingService->findSettingByKey($settingData['key'] ?? '');
            $isUpdate = $findResult['statusCode'] === 200;

            // Validate each setting
            $validator = $isUpdate
                ? $this->validateUpdatePayload($settingData, $findResult['payload']['data']->id)
                : $this->validateCreatePayload($settingData);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed for setting with key: ' . ($settingData['key'] ?? 'unknown'),
                    'errors' => $validator->errors()
                ], 422);
            }
        }

        $result = $this->settingService->bulkUpsertByKey($input);

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Delete a setting
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $result = $this->settingService->findSettingById($id);
        
        if ($result['statusCode'] === 404) {
            return response()->json($result['payload'], 404);
        }

        /** @var Setting $setting */
        $setting = $result['payload']['data'];

        $result = $this->settingService->destroy($setting);

        return response()->json($result['payload'], $result['statusCode']);
    }

    /** 
     * Bulk delete settings
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:settings,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->settingService->bulkDestroy($request->input('ids', []));

        return response()->json($result['payload'], $result['statusCode']);
    }
}
