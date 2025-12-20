<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\RoomDetailService;
use App\Models\RoomDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoomDetailController extends Controller
{
    public function __construct(
        private RoomDetailService $roomDetailService
    ) {}

    /**
     * Display a listing of room details.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $result = $this->roomDetailService->list($request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Store a newly created room detail.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $result = $this->roomDetailService->store($request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Display the specified room detail.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $result = $this->roomDetailService->findRoomById($id);

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Update the specified room detail.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $findResult = $this->roomDetailService->findRoomById($id);

        if ($findResult['statusCode'] === 404) {
            return response()->json($findResult['payload'], 404);
        }

        $result = $this->roomDetailService->update($id, $request->all());

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Remove the specified room detail.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $result = $this->roomDetailService->findRoomById($id);

        if ($result['statusCode'] === 404) {
            return response()->json($result['payload'], 404);
        }

        $result = $this->roomDetailService->delete($id);

        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Remove multiple room details at once.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:room_details,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->roomDetailService->bulkDelete($request->input('ids'));

        return response()->json($result['payload'], $result['statusCode']);
    }
}