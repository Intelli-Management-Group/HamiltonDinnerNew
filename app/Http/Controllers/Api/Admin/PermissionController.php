<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use App\Models\Permission;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\PermissionService;

class PermissionController extends Controller
{

    public function __construct(
        private PermissionService $permissionService
    ) {}
    
    /**
     * Display a listing of permissions.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $result = $this->permissionService->list($request->all());
        return response()->json($result['payload'], $result['statusCode']);
    }

    // /**
    //  * Store a newly created permission in storage.
    //  *
    //  * @param  \Illuminate\Http\Request  $request
    //  * @return \Illuminate\Http\JsonResponse
    //  */
    // public function store(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'name' => 'required|string|max:255|unique:permissions,name',
    //         'display_name' => 'required|string|max:255'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Validation failed',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     try {
    //         $permission = Permission::create([
    //             'name' => $request->name,
    //             'display_name' => $request->display_name,
    //             'guard_name' => 'api' // Default guard for this application
    //         ]);

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Permission created successfully',
    //             'data' => $permission
    //         ], 201);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Failed to create permission',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // /**
    //  * Display the specified permission.
    //  *
    //  * @param  int  $id
    //  * @return \Illuminate\Http\JsonResponse
    //  */
    // public function show($id)
    // {
    //     try {
    //         $permission = Permission::with('rolesList')->findOrFail($id);
    //         return response()->json([
    //             'status' => 'success',
    //             'data' => $permission
    //         ]);
    //     } catch (ModelNotFoundException $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Permission not found'
    //         ], 404);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Failed to retrieve permission',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // /**
    //  * Update the specified permission in storage.
    //  *
    //  * @param  \Illuminate\Http\Request  $request
    //  * @param  int  $id
    //  * @return \Illuminate\Http\JsonResponse
    //  */
    // public function update(Request $request, $id)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'name' => 'required|string|max:255|unique:permissions,name,' . $id,
    //         'display_name' => 'required|string|max:255'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Validation failed',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     try {
    //         $permission = Permission::findOrFail($id);
    //         $permission->name = $request->name;
    //         $permission->display_name = $request->display_name;
    //         $permission->save();

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Permission updated successfully',
    //             'data' => $permission
    //         ]);
    //     } catch (ModelNotFoundException $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Permission not found'
    //         ], 404);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Failed to update permission',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // /**
    //  * Remove the specified permission from storage.
    //  *
    //  * @param  int  $id
    //  * @return \Illuminate\Http\JsonResponse
    //  */
    // public function destroy($id)
    // {
    //     try {
    //         $permission = Permission::findOrFail($id);
    //         $permission->delete();

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Permission deleted successfully'
    //         ]);
    //     } catch (ModelNotFoundException $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Permission not found'
    //         ], 404);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Failed to delete permission',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // /**
    //  * Bulk destroy permissions
    //  *
    //  * @param  \Illuminate\Http\Request  $request
    //  * @return \Illuminate\Http\JsonResponse
    //  */
    // public function bulkDestroy(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'ids' => 'required|array',
    //         'ids.*' => 'integer|exists:permissions,id'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Validation failed',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     try {
    //         Permission::whereIn('id', $request->ids)->delete();

    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Permissions deleted successfully'
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Failed to delete permissions',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
}