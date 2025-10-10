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
    /**
     * Apply API middleware to all methods except login and register.
     */
    public function __construct()
    {
        $this->middleware('api', ['except' => ['login', 'register']]);
    }

    /**
     * Authenticate user and return JWT token with permissions.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        try {
            // Validate login credentials
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors(), 422);
            }

            // Attempt authentication and generate JWT token
            $credentials = $request->only('email', 'password');
            if (!$token = auth()->attempt($credentials)) {
                return $this->errorResponse(['error' => 'Email or Password is incorrect'], 401);
            }

            // Build permissions map for the authenticated user
            $user = auth()->user();
            $allPermissionsResult = Permission::select('name')->pluck('name')->toArray();
            $allPermissions = [];
            foreach ($allPermissionsResult as $item) {
                $allPermissions[$item] = 0; // Default: no permission
            }
            
            $loggedInUser = User::with('permissionList')->where('id', $user->id)->get()->toArray();
            foreach ($loggedInUser as $result) {
                foreach ($result['permission_list'] as $permission) {
                    $allPermissions[$permission['name']] = 1; // User has this permission
                }
            }

            return $this->respondWithToken($token, $allPermissions);

        } catch (\Exception $e) {
            return $this->errorResponse(['error' => 'An error occurred while processing your request.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Register a new user and return user info.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        try {
            // Validate registration fields
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

            // Create new user record
            $user = User::create([
                'name' => $request->name,
                'user_name' => $request->user_name,
                'email' => $request->email,
                'password' => bcrypt($request->password), // Hash password
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

    /**
     * Return the authenticated user's info.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        try {
            return response()->json(auth()->user());
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while processing your request.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Log out the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            auth()->logout();
            return response()->json(['message' => 'Successfully logged out']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while processing your request.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Refresh JWT token for the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        try {
            return $this->respondWithToken(auth()->refresh());
        } catch (\Exception $e) {
            return $this->errorResponse(['error' => 'An error occurred while processing your request.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Build a standard token response for authentication endpoints.
     *
     * @param string $token
     * @param array|null $permissions
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token, $permissions = null)
    {
        try {
            $response = [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl', 60) * 60,
                'user' => auth('api')->user(),
                'ResponseCode' => '1',
                'ResponseText' => 'success',
            ];
            if ($permissions !== null) {
                $response['permissions'] = $permissions;
            }
            return response()->json($response);
        } catch (\Exception $e) {
            return $this->errorResponse(['error' => 'An error occurred while processing your request.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Build a standard error response for API endpoints.
     *
     * @param array $data
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse($data, $code = 500)
    {
        $response = $data;
        if (!isset($response['ResponseCode'])) {
            $response['ResponseCode'] = '11';
        }
        if (!isset($response['ResponseText'])) {
            $response['ResponseText'] = 'Error';
        }
        return response()->json($response, $code);
    }
}