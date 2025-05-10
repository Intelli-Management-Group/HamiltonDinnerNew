<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\RoomDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoomDetailController extends Controller
{
    /**
     * Display a listing of room details.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = RoomDetail::when($request->has('is_active'), function($query) use ($request) {
            return $query->where('is_active', $request->is_active);
        })
        ->latest();
    
        // Check if pagination parameters are specified
        if ($request->has('pagesize') || $request->has('pagenumber')) {
            $pageSize = $request->input('pagesize', 15);
            $pageNumber = $request->input('pagenumber', 1);
            
            $rooms = $query->paginate($pageSize, ['*'], 'page', $pageNumber);
            
            return response()->json([
                'success' => true,
                'data' => $rooms->items(),
                'pagination' => [
                    'total' => $rooms->total(),
                    'per_page' => $rooms->perPage(),
                    'current_page' => $rooms->currentPage(),
                    'last_page' => $rooms->lastPage(),
                    'from' => $rooms->firstItem(),
                    'to' => $rooms->lastItem()
                ]
            ], 200);
        } else {
            // Return all data without pagination
            $rooms = $query->get();
            
            return response()->json([
                'success' => true,
                'data' => $rooms,
                'count' => $rooms->count()
            ], 200);
        }
    }

    /**
     * Store a newly created room detail.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $roomData = $request->all();

        $room = RoomDetail::create($roomData);

        return response()->json([
            'success' => true,
            'message' => 'Room detail created successfully',
            'data' => $room
        ], 201);
    }

    /**
     * Display the specified room detail.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $room = RoomDetail::find($id);
        
        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'Room detail not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $room
        ], 200);
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
        $room = RoomDetail::find($id);
        
        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'Room detail not found'
            ], 404);
        }

       

        $roomData = $request->all();

        $room->update($roomData);

        return response()->json([
            'success' => true,
            'message' => 'Room detail updated successfully',
            'data' => $room
        ], 200);
    }

    /**
     * Remove the specified room detail.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $room = RoomDetail::find($id);
        
        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'Room detail not found'
            ], 404);
        }

        $room->delete();

        return response()->json([
            'success' => true,
            'message' => 'Room detail deleted successfully'
        ], 200);
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

        $count = RoomDetail::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => $count . ' rooms deleted successfully'
        ], 200);
    }
}