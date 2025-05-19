<?php


namespace App\Http\Controllers\Api;

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
                $query->where(function($q) use ($searchTerm) {
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
     * Store a new setting
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
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
            //         'message' => 'Validation failed',
            //         'errors' => $validator->errors()
            //     ], 422);
            // }

            $setting = Setting::create($request->all());

            return response()->json([
                'success' => true,
                'data' => $setting,
                'message' => 'Setting created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create setting',
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
     * Update an existing setting
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $setting = Setting::findOrFail($id);
            
            // Validate request
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

            // if ($validator->fails()) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Validation failed',
            //         'errors' => $validator->errors()
            //     ], 422);
            // }

            $setting->update($request->all());

            return response()->json([
                'success' => true,
                'data' => $setting,
                'message' => 'Setting updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update setting',
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