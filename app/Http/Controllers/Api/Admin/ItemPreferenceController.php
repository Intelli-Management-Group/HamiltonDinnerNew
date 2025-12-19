<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\ItemPreferenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemPreferenceController extends Controller
{
    public function __construct(
        private ItemPreferenceService $itemPreferenceService
    ) {}

    /**
     * Display a listing of item preferences.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $result = $this->itemPreferenceService->list($request->all());

        return response()->json($result['payload'], $result['statusCode']);
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

        $result = $this->itemPreferenceService->store($request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Display the specified item preference.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $result = $this->itemPreferenceService->findItemById($id);

        return response()->json($result['payload'], $result['statusCode']);
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
        $findResult = $this->itemPreferenceService->findItemById($id);

        if ($findResult['statusCode'] === 404) {
            return response()->json($findResult['payload'], 404);
        }

        /** @var ItemPreference $preference */
        $preference = $findResult['payload']['data'];

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

        $result = $this->itemPreferenceService->update($preference, $request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Remove the specified item preference.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $findResult = $this->itemPreferenceService->findItemById($id);

        if ($findResult['statusCode'] === 404) {
            return response()->json($findResult['payload'], 404);
        }

        /** @var ItemPreference $preference */
        $preference = $findResult['payload']['data'];

        $result = $this->itemPreferenceService->destroy($preference);

        return response()->json($result['payload'], $result['statusCode']);
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

        $result = $this->itemPreferenceService->bulkDestroy($request->input('ids'));

        return response()->json($result['payload'], $result['statusCode']);
    }
}