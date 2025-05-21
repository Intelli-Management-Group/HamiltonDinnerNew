<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Http\Controllers\Api\Admin\Controller;
use App\Models\Permission;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('api', ['except' => ['login', 'register']]);
    }

    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $credentials = $request->only('email', 'password');
            if (!$token = auth()->attempt($credentials)) {
                return response()->json(['error' => 'Email or Password is incorrect'], 401);
            }

            $user = auth()->user();

            $allPermissionsResult = Permission::select('name')->pluck('name')->toArray();

            $allPermissions = [];

            foreach ($allPermissionsResult as $item) {

                $allPermissions[$item] = 0;
            }

            $loggedInUser = User::with('permissionList')->where('id', $user->id)->get()->toArray();

            foreach ($loggedInUser as $result) {

                foreach ($result['permission_list'] as $permission) {

                    $allPermissions[$permission['name']] = 1;
                }

            }

            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl', 60) * 60, // Use config value instead of factory method
                'user' => auth()->user(),
                'permissions' => $allPermissions,
                "ResponseCode" => "1",
                "ResponseText" => "success",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while processing your request.',
                'message' => $e->getMessage(),
                "ResponseCode" => "11",
                "ResponseText" => "Error",
            ], 500);
        }
    }

    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'user_name' => 'required|string|max:255|unique:users',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string',
                'role_id' => 'required|exists:roles,id'
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $user = User::create([
                'name' => $request->name,
                'user_name' => $request->user_name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'role_id' => $request->role_id,
            ]);

            return response()->json([
                'message' => 'User successfully registered',
                'user' => $user
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while processing your request.', 'message' => $e->getMessage()], 500);
        }
    }

    public function me()
    {
        try {
            return response()->json(auth()->user());
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while processing your request.', 'message' => $e->getMessage()], 500);
        }
    }

    public function logout()
    {
        try {
            auth()->logout();
            return response()->json(['message' => 'Successfully logged out']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while processing your request.', 'message' => $e->getMessage()], 500);
        }
    }

    public function refresh()
    {
        try {
            return $this->respondWithToken(auth()->refresh());
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while processing your request.', 'message' => $e->getMessage()], 500);
        }
    }

    protected function respondWithToken($token)
    {
        try {
            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl', 60) * 60, // Use config value instead of factory method
                'user' => auth('api')->user()
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while processing your request.', 'message' => $e->getMessage()], 500);
        }
    }
}