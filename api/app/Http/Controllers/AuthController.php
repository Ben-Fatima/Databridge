<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\User;


class AuthController extends Controller
{
    /*
    * Register a new user.
    * @param \Illuminate\Http\Request $request
    * @return \Illuminate\Http\JsonResponse
    */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|confirmed|min:8',
        ]);

        $user  = User::create([
            ...$data,
            'password' => bcrypt($data['password']),
        ]);

        $token = $user->createToken('api');

        return response()->json([
            'user'  => $user,
            'token' => $token->plainTextToken,
        ], 201);
    }

    /*
    * Login an existing user.
    * @param \Illuminate\Http\Request $request
    * @return array
    */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email|exists:users',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return [
                'errors' => [
                    'email' => ['The provided credentials are incorrect.']
                ]
            ];
        }

        $token = $user->createToken('api');

        return [
            'user'  => $user,
            'token' => $token->plainTextToken,
        ];
    }

    /*
    * Logout the authenticated user.
    * @param \Illuminate\Http\Request $request
    * @return \Illuminate\Http\JsonResponse
    */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
