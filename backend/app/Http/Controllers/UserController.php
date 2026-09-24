<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index()
    {
        return User::with('region')->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return response()->json(User::create($data)->load('region'), 201);
    }

    public function show(User $user)
    {
        return $user->load('region');
    }

    public function update(Request $request, User $user)
    {
        $user->update($this->validated($request, $user));

        return $user->load('region');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 409, 'You cannot delete your own account.');
        $user->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => [$user ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => [$user ? 'sometimes' : 'required', 'email', Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'sometimes' : 'required', Password::min(6)],
            'role' => [$user ? 'sometimes' : 'required', Rule::in(['administrator', 'operator'])],
            'region_id' => ['nullable', 'exists:regions,id'],
        ]);

        $role = $data['role'] ?? $user?->role;
        if ($role === 'operator' && ! ($data['region_id'] ?? $user?->region_id)) {
            throw ValidationException::withMessages(['region_id' => ['A region is required for operators.']]);
        }
        if ($role === 'administrator') {
            $data['region_id'] = null;
        }

        return $data;
    }
}
