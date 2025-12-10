<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Http\Controllers\Api\Admin\Controller;
use App\Services\Auth\AdminAuthService;

class AuthController extends Controller
{
    public function __construct(
        private AdminAuthService $authService
    )
    {
        $this->middleware('api', ['except' => ['login', 'register']]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $result = $this->authService->login(
            $request->only('email', 'password')
        );

        // Old error response structure if needed:
        // [
        //     'error' => 'An error occurred while processing your request.',
        //     'message' => $e->getMessage(),
        //     "ResponseCode" => "11",
        //     "ResponseText" => "Error",
        // ]

        return response()->json($result['payload'], $result['statusCode']);
    }

    public function register(Request $request)
    {
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

        $result = $this->authService->register($request->all());
        return response()->json($result['payload'], $result['statusCode']);
    }

    public function me()
    {
        return response()->json(auth()->user());
    }

    public function logout()
    {
        $result = $this->authService->logout();
        return response()->json($result['payload'], $result['statusCode']);
    }

    public function refresh()
    {
        $result = $this->authService->refresh();
        return response()->json($result['payload'], $result['statusCode']);
    }
}