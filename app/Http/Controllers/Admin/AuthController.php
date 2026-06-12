<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/auth/register
     * Create a new user account.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'presented', Password::defaults()],
            // 'role' => ['sometimes', Rule::in([User::ROLE_STORE_OWNER, User::ROLE_CUSTOMER])],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            // 'role' => $validated['role'] ?? User::ROLE_STORE_OWNER,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
            'message' => 'Usuário criado com sucesso',
        ], 201);
    }

    /**
     * POST /api/auth/login
     * Returns a Sanctum token on success.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Conta inativa. Entre em contato com o suporte.'],
            ]);
        }

        // Revoke all previous tokens (single-session policy)
        $user->tokens()->delete();

        $token = $user->createToken('admin-session')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        // return response()->json($request->user()->only(['id', 'name', 'email', 'role']));
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'isActive' => (bool)$user->is_active,
        ]);
    }

    /**
     * PUT /api/auth/profile
     * Update user profile data.
     */
    public function updateProfile(Request $request): JsonResponse
    {
//        $user = $request->user();
//
//        $validated = $request->validate([
//            'name' => ['sometimes', 'string', 'max:255'],
//            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
//        ]);
//
//        $user->update($validated);
//
//        return response()->json([
//            'success' => true,
//            'data' => $user->fresh(),
//            'message' => 'Perfil atualizado com sucesso',
//        ]);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => [
                'required',
                'string',
                'email',
                'max:121',
                Rule::unique('users', 'email')
                    ->ignore($user->id)
                    ->whereNull('deleted_at'),
            ],
        ], [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso.',
        ]);

        $data['name'] = trim($data['name']);
        $data['email'] = mb_strtolower(trim($data['email']));

        if ($data['name'] === '') {
            return response()->json([
                'message' => 'O nome não pode ficar em branco.',
            ], 422);
        }

        if ($data['email'] === '') {
            return response()->json([
                'message' => 'O e-mail não pode ficar em branco.',
            ], 422);
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        return response()->json([
            'id' => $user->fresh()->id,
            'name' => $user->fresh()->name,
            'email' => $user->fresh()->email,
            'role' => $user->fresh()->role,
            'isActive' => (bool)$user->fresh()->is_active,
        ]);
    }

    /**
     * PUT /api/auth/password
     * Change the current user password.
     */
    public function changePassword(Request $request): JsonResponse
    {
//        $request->validate([
//            'current_password' => ['required'],
//            'password' => ['required', 'presented', Password::defaults()],
//        ]);
//
//        $user = $request->user();
//
//        if (!Hash::check($request->current_password, $user->password)) {
//            throw ValidationException::withMessages([
//                'current_password' => ['Senha atual incorreta'],
//            ]);
//        }
//
//        $user->update([
//            'password' => $request->password,
//        ]);
//
//        // Invalidar outros tokens (opcional).
//        // $user->tokens()->where('id', '!=', $user->currentAccessTokenId())->delete();
//
//        return response()->json([
//            'success' => true,
//            'message' => 'Senha alterada com sucesso',
//        ]);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => [
                'required',
                'presented',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ], [
            'current_password.required' => 'A senha atual é obrigatória.',
            'current_password.current_password' => 'A senha atual está incorreta.',
            'password.required' => 'A nova senha é obrigatória.',
            'password.presented' => 'A confirmação da nova senha não confere.',
        ]);

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        return response()->json([
            'message' => 'Senha alterada com sucesso.',
        ]);
    }

    /**
     * POST /api/auth/forgot-password
     * Send password reset instructions.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Aqui você enviaria o email com o link de reset.
        // Por agora, retornamos sucesso simulado.
        return response()->json([
            'success' => true,
            'message' => 'Se o email existir, você receberá um link de recuperação.',
        ]);
    }

    /**
     * POST /api/auth/reset-password
     * Reset the user password using a token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'presented', Password::defaults()],
        ]);

        // Aqui você validaria o token e resetaria a senha.
        // Por agora, retornamos sucesso simulado.
        return response()->json([
            'success' => true,
            'message' => 'Senha resetada com sucesso',
        ]);
    }
}
