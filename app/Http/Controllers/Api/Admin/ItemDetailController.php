<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\ItemDetailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemDetailController extends Controller
{   

    public function __construct(
        private ItemDetailService $itemDetailService
    )
    {
        ini_set('max_execution_time', 0);
    }

    /**
     * Display a listing of the item details.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $result = $this->itemDetailService->list($request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Store a newly created item detail.
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
            'item_image' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->itemDetailService->store($request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Display the specified item detail.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $result = $this->itemDetailService->findItemById($id);

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Update the specified item detail.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $findResult = $this->itemDetailService->findItemById($id);

        if ($findResult['statusCode'] == 404) {
            return response()->json($findResult['payload'], 404);
        }
        
        /** @var ItemDetail $item */
        $item = $findResult['payload']['data'];

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

        $result = $this->itemDetailService->update($item, $request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Remove the specified item detail.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $findResult = $this->itemDetailService->findItemById((int) $id);
        
        if ($findResult['statusCode'] == 404) {
            return response()->json($findResult['payload'], 404);
        }
        
        /** @var ItemDetail $item */
        $item = $findResult['payload']['data'];
        
        $result = $this->itemDetailService->destroy($item);

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Bulk remove the specified item details.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
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

        $result = $this->itemDetailService->bulkDestroy($request->input('ids'));

        return response()->json($result['payload'], $result['statusCode']);
    }
}