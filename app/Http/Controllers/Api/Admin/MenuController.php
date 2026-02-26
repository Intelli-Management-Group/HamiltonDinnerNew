<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuDetail;
use App\Services\MenuDetailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MenuController extends Controller
{
    public function __construct(
        private MenuDetailService $menuDetailService
    ) {}

    /**
     * Display a listing of menu details.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $result = $this->menuDetailService->list($request->all());

        return response()->json($result['payload'], $result['statusCode']);
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
            'date' => 'required|date|unique:menu_details,date,NULL,id,deleted_at,NULL',
            'items' => 'nullable|array',
            'is_allday' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->menuDetailService->store($request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Display the specified menu detail.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $result = $this->menuDetailService->findMenuById($id);

        return response()->json($result['payload'], $result['statusCode']);
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
        $findResult = $this->menuDetailService->findMenuById($id);

        if ($findResult['statusCode'] === 404) {
            return response()->json($findResult['payload'], 404);
        }

        /** @var MenuDetail $menu */
        $menu = $findResult['payload']['data'];

        $validator = Validator::make($request->all(), [
            'date' => 'required|date|unique:menu_details,date,'.$id,  // TODO: look into concatenated $id
            'items' => 'nullable|array',
            'is_allday' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->menuDetailService->update($menu, $request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Remove the specified menu detail.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $findResult = $this->menuDetailService->findMenuById($id);

        if ($findResult['statusCode'] === 404) {
            return response()->json($findResult['payload'], 404);
        }

        /** @var MenuDetail $menu */
        $menu = $findResult['payload']['data'];

        $result = $this->menuDetailService->destroy($menu);

        return response()->json($result['payload'], $result['statusCode']);
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

        $result = $this->menuDetailService->bulkDestroy($request->input('ids'));

        return response()->json($result['payload'], $result['statusCode']);
    }
}