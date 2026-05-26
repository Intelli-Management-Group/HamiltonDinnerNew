<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\RoleService;

class RoleController extends Controller
{
    public function __construct(
        private RoleService $roleService
    ) {}

    /**
     * Display a listing of roles.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {   
        $result = $this->roleService->list($request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Store a newly created role in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles,name,NULL,id,deleted_at,NULL',
            'permissions' => 'array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->roleService->store($request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Display the specified role.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $result = $this->roleService->show((int) $id);

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Update the specified role in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles,name,' . $id . ',id,deleted_at,NULL',
            'permissions' => 'array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if the name is already used by a deleted role
        $conflict = $this->roleService->nameConflictWithDeleted($request->name, (int) $id);

        if ($conflict) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'errors' => ['name' => 'A deleted role already uses this name. Restore it by creating a new role with its name.']
            ], 422);
        }

        $findResult = $this->roleService->findRoleById((int) $id);

        if ($findResult['statusCode'] === 404) {
            return response()->json($findResult['payload'], 404);
        }

        /** @var Role $role */
        $role = $findResult['payload']['data'];
        
        $permissions = $request->input('permissions', []); // should be permission array ['edit articles', 'delete articles']
        
        $result = $this->roleService->update($role, [
            'name' => $request->name,
            'permissions' => $permissions
        ]);

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Remove the specified role from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $findResult = $this->roleService->findRoleById((int) $id);
        if ($findResult['statusCode'] === 404) {
            return response()->json($findResult['payload'], 404);
        }

        /** @var Role $role */
        $role = $findResult['payload']['data'];

        $result = $this->roleService->delete($role);

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Bulk destroy roles
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:roles,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->roleService->bulkDestroy($request->input('ids', []));

        return response()->json($result['payload'], $result['statusCode']);
    }

    // currently usused. Consider deleting
    // public function getUserTree(){
    //     try {
    //         $list = Role::with('userList')->get();
    //         return response()->json([ 'list' =>  $list], 200);
            
    //     }
    //     catch (\Exception $e){
    //         return $this->sendResultJSON("0", $e->getMessage());
    //     }
    // }

    // currently usused. Consider deleting
    // public function syncPermission(Request $request){
        
    //     try {
    //         $roleId = $request->input('roleId');
    //         $permissions = $request->input('permissions'); // should be permission array ['edit articles', 'delete articles']
    //         $role = Role::find($roleId);
    //         $role->syncPermissions($permissions);
    //         return response()->json(['message' =>  "Permissions Synced Successfully"], 200);
    //     }
        
    //     catch (\Exception $e){
    //         return response()->json([ 'message' => $e->getMessage()], 500);
    //     }
    // }
}