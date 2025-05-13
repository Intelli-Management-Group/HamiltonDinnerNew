<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {   

        $query = Role::with('permissionList')->when($request->has('search'), function($query) use ($request) {
                return $query->where('name', 'LIKE', '%' . $request->search . '%')
                      ->orWhere('display_name', 'LIKE', '%' . $request->search . '%');
            })
            ->latest();
        
        if ($request->has('pagesize') || $request->has('pagenumber')) {
            $pageSize = $request->input('pagesize', 15);
            $pageNumber = $request->input('pagenumber', 1);
            
            $roles = $query->paginate($pageSize, ['*'], 'page', $pageNumber);
            
            return response()->json([
                'success' => true,
                'data' => $roles->items(),
                'pagination' => [
                    'total' => $roles->total(),
                    'per_page' => $roles->perPage(),
                    'current_page' => $roles->currentPage(),
                    'last_page' => $roles->lastPage(),
                    'from' => $roles->firstItem(),
                    'to' => $roles->lastItem()
                ]
            ], 200);
        } else {
            $roles = $query->get();
            
            return response()->json([
                'success' => true,
                'data' => $roles,
                'count' => $roles->count()
            ], 200);
        }
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
            'name' => 'required|string|max:255|unique:roles,name',
            'display_name' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {

            $permissions = $request->input('permissions', []); // should be permission array ['edit articles', 'delete articles']

            $role = Role::create([
                'name' => $request->name,
                'display_name' => $request->display_name,
                'guard_name' => 'api' // Default guard for this application
            ]);

            $role->syncPermissions($permissions);

            $role->refresh();

            return response()->json([
                'status' => 'success',
                'message' => 'Role created successfully',
                'data' => $role
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified role.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $role = Role::with('permissionList')->findOrFail($id);
            return response()->json([
                'status' => 'success',
                'data' => $role
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve role',
                'error' => $e->getMessage()
            ], 500);
        }
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
            'name' => 'required|string|max:255|unique:roles,name,' . $id,
            'display_name' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $permissions = $request->input('permissions', []); // should be permission array ['edit articles', 'delete articles']
        
        try {
            $role = Role::findOrFail($id);
            $role->name = $request->name;
            $role->display_name = $request->display_name;
            $role->save();

            $role->syncPermissions($permissions);

            $role->refresh();

            return response()->json([
                'status' => 'success',
                'message' => 'Role updated successfully',
                'data' => $role
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified role from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $role = Role::findOrFail($id);
            $role->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Role deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete role',
                'error' => $e->getMessage()
            ], 500);
        }
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
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            Role::whereIn('id', $request->ids)->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Roles deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete roles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUserTree(){
        try {
            
            $list = Role::with('userList')->get();
            return response()->json([ 'list' =>  $list], 200);
            
        }
        catch (\Exception $e){
            return $this->sendResultJSON("0", $e->getMessage());
        }
    }
    
    public function syncPermission(Request $request){
        
        try{
            
            $roleId = $request->input('roleId');
            
            $permissions = $request->input('permissions'); // should be permission array ['edit articles', 'delete articles']
            
            $role = Role::find($roleId);
        
            $role->syncPermissions($permissions);
            
            return response()->json(['message' =>  "Permissions Synced Successfully"], 200);
            
        }
        
        catch (\Exception $e){
            return response()->json([ 'message' => $e->getMessage()], 500);
        }
        

    }
}