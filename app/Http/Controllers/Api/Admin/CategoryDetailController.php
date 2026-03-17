<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\CategoryDetailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryDetailController extends Controller
{
    public function __construct(
        private CategoryDetailService $categoryDetailService
    ) {}

    /**
     * Display a listing of the category details.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $result = $this->categoryDetailService->list($request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Store a newly created category detail.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cat_name' => 'required|string|max:127',
            'category_chinese_name' => 'nullable|string|max:255',
            'type' => 'required|integer|in:1,2,3',
            'parent_id' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->categoryDetailService->store($request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Display the specified category detail.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $result = $this->categoryDetailService->findCategoryById($id);

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Update the specified category detail.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $findResult = $this->categoryDetailService->findCategoryById((int) $id);
        
        if (!$findResult['statusCode'] === 404) {
            return response()->json($findResult['payload'], 404);
        }

        /** @var CategoryDetail $category */
        $category = $findResult['payload']['data'];

        $validator = Validator::make($request->all(), [
            'cat_name' => 'required|string|max:127',
            'category_chinese_name' => 'nullable|string|max:255',
            'type' => 'required|integer|in:1,2,3',
            'parent_id' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->categoryDetailService->update($category, $request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Remove the specified category detail.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $findResult = $this->categoryDetailService->findCategoryById((int) $id);

        if (!$findResult['statusCode'] === 404) {
            return response()->json($findResult['payload'], 404);
        }

        /** @var CategoryDetail $category */
        $category = $findResult['payload']['data'];

        $result = $this->categoryDetailService->destroy($category);

        return response()->json($result['payload'], $result['statusCode']);
    }

    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:category_details,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->categoryDetailService->bulkDestroy($request->input('ids'));

        return response()->json($result['payload'], $result['statusCode']);
    }
}