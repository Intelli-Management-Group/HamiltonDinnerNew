<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MenuController extends Controller
{
    /**
     * Display a listing of menu details.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
           
        $query = MenuDetail::latest();
    
        // Check if pagination parameters are specified
        if ($request->has('pagesize') || $request->has('pagenumber')) {
            $pageSize = $request->input('pagesize', 15); 
            $pageNumber = $request->input('pagenumber', 1);
            
            $menus = $query->paginate($pageSize, ['*'], 'page', $pageNumber);
            
            return response()->json([
                'success' => true,
                'data' => $menus->items(),
                'pagination' => [
                    'total' => $menus->total(),
                    'per_page' => $menus->perPage(),
                    'current_page' => $menus->currentPage(),
                    'last_page' => $menus->lastPage(),
                    'from' => $menus->firstItem(),
                    'to' => $menus->lastItem()
                ]
            ], 200);
        } else {
            // Return all data without pagination
            $menus = $query->get();
            
            return response()->json([
                'success' => true,
                'data' => $menus,
                'count' => $menus->count()
            ], 200);
        }
    }

    /**
     * Store a newly created menu detail.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'menu_name' => 'required|string',
            'date' => 'required|date|unique:menu_details,date',
            'items' => 'nullable|array',
            'is_allday' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $menu = MenuDetail::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Menu detail created successfully',
            'data' => $menu
        ], 201);
    }

    /**
     * Display the specified menu detail.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $menu = MenuDetail::find($id);
        
        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu detail not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $menu
        ]);
    }

    /**
     * Update the specified menu detail.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $menu = MenuDetail::find($id);
        
        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu detail not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'menu_name' => 'required|string',
            'date' => 'required|date|unique:menu_details,date,'.$id,
            'items' => 'nullable|array',
            'is_allday' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $menu->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Menu detail updated successfully',
            'data' => $menu
        ]);
    }

    /**
     * Remove the specified menu detail.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $menu = MenuDetail::find($id);
        
        if (!$menu) {
            return response()->json([
                'success' => false,
                'message' => 'Menu detail not found'
            ], 404);
        }

        $menu->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu detail deleted successfully'
        ]);
    }

    /**
     * Remove multiple menu details at once.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:menu_details,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $count = MenuDetail::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => $count . ' menu details deleted successfully'
        ], 200);
    }
}