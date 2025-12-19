<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItemOption;
use App\Services\ItemOptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemOptionController extends Controller
{
    public function __construct(
        private ItemOptionService $itemOptionService
    ) {}
    
    /**
     * Display a listing of the item options.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $result = $this->itemOptionService->list($request->all());

        return response()->json($result['payload'], $result['statusCode']);
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
            'option_name' => 'required|string|max:127',
            'option_name_cn' => 'nullable|string|max:255',
            'is_paid_item' => 'nullable|boolean',
            
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->itemOptionService->store($request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Display the specified item option.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $result = $this->itemOptionService->findItemOptionById($id);

        return response()->json($result['payload'], $result['statusCode']);
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
        $findResult = $this->itemOptionService->findItemOptionById($id);

        if ($findResult['statusCode'] == 404) {
            return response()->json($findResult['payload'], 404);
        }

        /** @var ItemOption $option */
        $option = $findResult['payload']['data'];

        $validator = Validator::make($request->all(), [
            'option_name' => 'required|string|max:127',
            'option_name_cn' => 'nullable|string|max:255',
            'is_paid_item' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->itemOptionService->update($option, $request->all());
        
        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Remove the specified item option.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $findResult = $this->itemOptionService->findItemOptionById($id);
        
        if ($findResult['statusCode'] == 404) {
            return response()->json($findResult['payload'], 404);
        }

        /** @var ItemOption $option */
        $option = $findResult['payload']['data'];

        $result = $this->itemOptionService->destroy($option);

        return response()->json([
            'success' => true,
            'message' => 'Item option deleted successfully'
        ], 200);
    }

    /**
     * Remove multiple item options at once.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:item_options,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->itemOptionService->bulkDestroy($request->input('ids'));

        return response()->json($result['payload'], $result['statusCode']);
    }
}