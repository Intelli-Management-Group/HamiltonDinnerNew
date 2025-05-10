<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ItemPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemPreferenceController extends Controller
{
    /**
     * Display a listing of item preferences.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = ItemPreference::latest();
    
        if ($request->has('pagesize') || $request->has('pagenumber')) {
            $pageSize = $request->input('pagesize', 15);
            $pageNumber = $request->input('pagenumber', 1);
            
            $preferences = $query->paginate($pageSize, ['*'], 'page', $pageNumber);
            
            return response()->json([
                'success' => true,
                'data' => $preferences->items(),
                'pagination' => [
                    'total' => $preferences->total(),
                    'per_page' => $preferences->perPage(),
                    'current_page' => $preferences->currentPage(),
                    'last_page' => $preferences->lastPage(),
                    'from' => $preferences->firstItem(),
                    'to' => $preferences->lastItem()
                ]
            ], 200);
        } else {
            
            $preferences = $query->get();
            
            return response()->json([
                'success' => true,
                'data' => $preferences,
                'count' => $preferences->count()
            ], 200);
        }
    }

    /**
     * Store a newly created item preference.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pname' => 'required|string|max:255',
            'pname_cn' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $preference = ItemPreference::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Item preference created successfully',
            'data' => $preference
        ], 201);
    }

    /**
     * Display the specified item preference.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $preference = ItemPreference::find($id);
        
        if (!$preference) {
            return response()->json([
                'success' => false,
                'message' => 'Item preference not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $preference
        ], 200);
    }

    /**
     * Update the specified item preference.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $preference = ItemPreference::find($id);
        
        if (!$preference) {
            return response()->json([
                'success' => false,
                'message' => 'Item preference not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'pname' => 'required|string|max:255',
            'pname_cn' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $preference->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Item preference updated successfully',
            'data' => $preference
        ], 200);
    }

    /**
     * Remove the specified item preference.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $preference = ItemPreference::find($id);
        
        if (!$preference) {
            return response()->json([
                'success' => false,
                'message' => 'Item preference not found'
            ], 404);
        }

        $preference->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item preference deleted successfully'
        ], 200);
    }

    /**
     * Remove multiple item preferences at once.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:item_preferences,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $count = ItemPreference::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => $count . ' preferences deleted successfully'
        ], 200);
    }
}