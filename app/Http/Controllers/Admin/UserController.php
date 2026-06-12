<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /** GET /api/records/users */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int)min(max((int)$request->integer('perPage', 10), 1), 100);
        $page = (int)max((int)$request->integer('page', 1), 1);
        $search = trim((string)$request->string('search', ''));
        $sortBy = $request->string('sortBy', 'id')->toString();
        $sortDirection = strtolower($request->string('sortDirection', 'desc')->toString()) === 'asc'
            ? 'asc'
            : 'desc';

        $allowedSorts = ['id', 'name', 'email', 'role', 'is_active', 'last_login_at', 'created_at'];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'id';
        }

        $query = User::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortBy, $sortDirection);

        $users = $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(User $user) => $this->format($user));

        return response()->json($users);
    }

    /** GET /api/records/users/{id} */
    public function show(User $user): JsonResponse
    {
        return response()->json($this->format($user));
    }

    /** POST /api/records/users */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:121', 'unique:users,email'],
            'role' => ['required', Rule::in(['super_admin', 'admin', 'organizer', 'credential'])],
        ]);

        $plainPassword = Str::password(12, true, true, true, false);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($plainPassword),
            'role' => $data['role'],
            'is_active' => true,
        ]);

        return response()->json([
            'user' => $this->format($user),
            'generated_password' => $plainPassword,
        ], 201);
    }

    /** PUT/PATCH /api/records/users/{id} */
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'email' => ['sometimes', 'email', 'max:121', Rule::unique('users')->ignore($user->id)],
            'role' => ['sometimes', Rule::in(['super_admin', 'admin', 'organizer', 'credential'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user->update($data);

        return response()->json($this->format($user->fresh()));
    }

    /** PATCH /api/records/users/{id}/reset-password */
    public function resetPassword(User $user): JsonResponse
    {
        $plainPassword = Str::password(12, true, true, true, false);

        $user->update([
            'password' => Hash::make($plainPassword),
        ]);

        return response()->json([
            'user' => $this->format($user->fresh()),
            'generated_password' => $plainPassword,
        ]);
    }

    /** DELETE /api/records/users/{id} */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Não é possível excluir o próprio usuário.',
            ], 422);
        }

        $user->update([
            'is_active' => false,
        ]);

        $user->delete();

        return response()->json(null, 204);
    }

    private function format(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'isActive' => (bool)($user->is_active ?? true),
            'lastLogin' => $user->last_login_at?->diffForHumans(),
        ];
    }
}
