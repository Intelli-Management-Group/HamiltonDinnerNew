<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Services\UserService;

class UserController extends Controller
{   

    public function __construct(
        private UserService $userService
    )
    {
        ini_set('max_execution_time', 0);
    }

    /**
     * Display a listing of users.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $result = $this->userService->list($request->all());
        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Store a newly created user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'user_name' => 'required|string|max:255|unique:users,user_name,NULL,id,deleted_at,NULL',
            'email' => 'required|string|email|max:255|unique:users,email,NULL,id,deleted_at,NULL',
            'password' => 'required|string|min:4',
            'role_id' => 'required|exists:roles,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->userService->store($request->all());
        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Display the specified user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $result = $this->userService->show((int)$id);
        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Update the specified user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $findResult = $this->userService->findUserById((int)$id);

        if ($findResult['statusCode'] === 404) {
            return response()->json($findResult['payload'], 404);
        }
        $user = $findResult['payload']['data'];

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'user_name' => 'sometimes|string|max:255|unique:users,user_name,'.$id.',id,deleted_at,NULL',
            'email' => 'sometimes|string|email|max:255|unique:users,email,'.$id.',id,deleted_at,NULL',
            'password' => 'sometimes|string|min:4',
            'role_id' => 'sometimes|exists:roles,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->userService->update($user, $request->all());
        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Remove the specified user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $findResult = $this->userService->findUserById((int)$id);

        if ($findResult['statusCode'] === 404) {
            return response()->json($findResult['payload'], 404);
        }
        $user = $findResult['payload']['data'];

        $result = $this->userService->destroy($user);
        return response()->json($result['payload'], $result['statusCode']);
    }

    /**
     * Remove multiple users at once.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->userService->bulkDestroy($request->ids);

        return response()->json($result['payload'], $result['statusCode']);
    }
}