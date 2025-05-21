<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    /**
     * List all settings
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = Setting::query();

            // Filter by group if provided
            if ($request->has('group')) {
                $query->where('group', $request->group);
            }

            // Filter by type if provided
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Add search functionality if provided
            if ($request->has('search')) {
                $searchTerm = $request->search;
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('key', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('display_name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('group', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Order settings
            $query->orderBy($request->get('sort_by', 'order'), $request->get('sort_direction', 'asc'));

            // Check if pagination is requested
            if ($request->has('page') || $request->has('per_page')) {
                // Apply pagination
                $perPage = $request->get('per_page', 10);
                $settings = $query->paginate($perPage);

                return response()->json([
                    'success' => true,
                    'data' => $settings->items(),
                    'meta' => [
                        'current_page' => $settings->currentPage(),
                        'last_page' => $settings->lastPage(),
                        'per_page' => $settings->perPage(),
                        'total' => $settings->total()
                    ],
                    'message' => 'Settings retrieved successfully'
                ]);
            } else {
                // Return all records without pagination
                $settings = $query->get();

                return response()->json([
                    'success' => true,
                    'data' => $settings,
                    'message' => 'Settings retrieved successfully'
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new settings (supports single setting or array of settings)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $input = $request->all();
            $isArray = is_array($input) && isset($input[0]);

            // Convert single item to array format for consistent processing
            $settingsToCreate = $isArray ? $input : [$input];
            $createdSettings = [];

            foreach ($settingsToCreate as $settingData) {
                // Validate each setting
                $validator = Validator::make($settingData, [
                    'key' => 'required|string|max:127|unique:settings,key',
                    'display_name' => 'required|string|max:127',
                    'value' => 'nullable',
                    'details' => 'nullable',
                    'type' => 'required|string|max:127',
                    'order' => 'nullable|integer',
                    'group' => 'nullable|string|max:127',
                ]);

                // if ($validator->fails()) {
                //     return response()->json([
                //         'success' => false,
                //         'message' => 'Validation failed for setting with key: ' . ($settingData['key'] ?? 'unknown'),
                //         'errors' => $validator->errors()
                //     ], 422);
                // }

                // Create the setting
                $setting = Setting::create($settingData);
                $createdSettings[] = $setting;
            }

            return response()->json([
                'success' => true,
                'data' => $isArray ? $createdSettings : $createdSettings[0],
                'message' => count($createdSettings) . ' setting(s) created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create setting(s)',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific setting
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $setting = Setting::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $setting,
                'message' => 'Setting retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Setting not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update existing settings (supports single ID update or batch update via array)
     * 
     * @param Request $request
     * @param int|null $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id = null)
    {
        try {
            $input = $request->all();

            // Check if this is a batch update (array of settings)
            if ($id === null && is_array($input) && isset($input[0])) {
                $updatedSettings = [];

                foreach ($input as $settingData) {
                    // Must have key to identify the setting
                    if (!isset($settingData['key'])) {
                        continue;
                    }

                    // Find setting by key
                    $setting = Setting::where('key', $settingData['key'])->first();

                    if ($setting) {
                        // Validate update data
                        $validator = Validator::make($settingData, [
                            'key' => [
                                'sometimes',
                                'string',
                                'max:127',
                                Rule::unique('settings')->ignore($setting->id)
                            ],
                            'display_name' => 'sometimes|required|string|max:127',
                            'value' => 'nullable',
                            'details' => 'nullable',
                            'type' => 'sometimes|required|string|max:127',
                            'order' => 'nullable|integer',
                            'group' => 'nullable|string|max:127',
                        ]);

                        // if ($validator->fails()) {
                        //     continue; // Skip invalid items
                        // }

                        // Update the setting
                        $setting->update($settingData);
                        $updatedSettings[] = $setting;
                    } else {
                        // Create new setting if it doesn't exist
                        $validator = Validator::make($settingData, [
                            'key' => 'required|string|max:127|unique:settings,key',
                            'display_name' => 'required|string|max:127',
                            'value' => 'nullable',
                            'details' => 'nullable',
                            'type' => 'required|string|max:127',
                            'order' => 'nullable|integer',
                            'group' => 'nullable|string|max:127',
                        ]);

                        // if ($validator->fails()) {
                        //     continue; // Skip invalid items
                        // }

                        $setting = Setting::create($settingData);
                        $updatedSettings[] = $setting;
                    }
                }

                return response()->json([
                    'success' => true,
                    'data' => $updatedSettings,
                    'message' => count($updatedSettings) . ' setting(s) updated successfully'
                ]);
            } else {
                // Single setting update via ID
                $setting = Setting::findOrFail($id);

                $validator = Validator::make($request->all(), [
                    'key' => [
                        'sometimes',
                        'required',
                        'string',
                        'max:127',
                        Rule::unique('settings')->ignore($id)
                    ],
                    'display_name' => 'sometimes|required|string|max:127',
                    'value' => 'nullable',
                    'details' => 'nullable',
                    'type' => 'sometimes|required|string|max:127',
                    'order' => 'nullable|integer',
                    'group' => 'nullable|string|max:127',
                ]);

                $setting->update($request->all());

                return response()->json([
                    'success' => true,
                    'data' => $setting,
                    'message' => 'Setting updated successfully'
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update setting(s)',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a setting
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $setting = Setting::findOrFail($id);
            $setting->delete();

            return response()->json([
                'success' => true,
                'message' => 'Setting deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete settings
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDestroy(Request $request)
    {
        try {
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

            Setting::whereIn('id', $request->ids)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Settings deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
