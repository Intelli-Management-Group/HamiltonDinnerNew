<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\ItemDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemDetailController extends Controller
{   

    public function __construct()
    {
        ini_set('max_execution_time', 0);
    }

    /**
     * Display a listing of the item options.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = ItemDetail::when($request->has('cat_id'), function($query) use ($request) {
            return $query->where('cat_id', $request->cat_id);
        })
        ->latest();
    
        
        if ($request->has('pagesize') || $request->has('pagenumber')) {
            $pageSize = $request->input('pagesize', 15);
            $pageNumber = $request->input('pagenumber', 1);
            
            $items = $query->paginate($pageSize, ['*'], 'page', $pageNumber);
            
            return response()->json([
                'success' => true,
                'data' => $items->items(),
                'pagination' => [
                    'total' => $items->total(),
                    'per_page' => $items->perPage(),
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'from' => $items->firstItem(),
                    'to' => $items->lastItem()
                ]
            ], 200);
        } else {
            
            $items = $query->get();
            
            return response()->json([
                'success' => true,
                'data' => $items,
                'count' => $items->count()
            ], 200);
        }
    }

    /**
     * Store a newly created item option.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_name' => 'required|string|max:127',
            'item_chinese_name' => 'nullable|string|max:255',
            'cat_id' => 'nullable|integer',
            'is_allday' => 'nullable|boolean',
            'item_image' => 'nullable|string|max:127',
            'options' => 'nullable|string',
            'preference' => 'nullable|string',
            'item_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $item = ItemDetail::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Item option created successfully',
            'data' => $item
        ], 201);
    }

    /**
     * Display the specified item option.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $item = ItemDetail::find($id);
        
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item option not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $item
        ], 200);
    }

    /**
     * Update the specified item option.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $item = ItemDetail::find($id);
        
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item option not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'item_name' => 'required|string|max:127',
            'item_chinese_name' => 'nullable|string|max:255',
            'cat_id' => 'nullable|integer',
            'is_allday' => 'nullable|boolean',
            'item_image' => 'nullable|string|max:127',
            'options' => 'nullable|string',
            'preference' => 'nullable|string',
            'item_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $item->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Item option updated successfully',
            'data' => $item
        ], 200);
    }

    /**
     * Remove the specified item option.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $item = ItemDetail::find($id);
        
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item option not found'
            ], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item option deleted successfully'
        ], 200);
    }

    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:item_details,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $count = ItemDetail::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => $count . ' items deleted successfully'
        ], 200);
    }
}