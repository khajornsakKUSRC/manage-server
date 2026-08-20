<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use PasswordValidationRules, ProfileValidationRules;

    public function index(): Response
    {
        return Inertia::render('users/index', [
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'is_admin', 'permissions', 'created_at']),
            'pages' => Permissions::PAGES,
            'currentUserId' => Auth::id(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/create', [
            'pages' => Permissions::PAGES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'is_admin' => ['boolean'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permissions::keys())],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_admin' => $validated['is_admin'] ?? false,
            'permissions' => $validated['permissions'] ?? [],
        ]);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('users/edit', [
            'user' => $user->only(['id', 'name', 'email', 'is_admin', 'permissions']),
            'pages' => Permissions::PAGES,
            'isSelf' => $user->id === Auth::id(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            ...$this->profileRules($user->id),
            'password' => ['nullable', 'string', 'confirmed', Password::default()],
            'is_admin' => ['boolean'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permissions::keys())],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'permissions' => $validated['permissions'] ?? [],
        ]);

        // Admins can't demote themselves through this form — that could
        // leave the app with no one able to manage users.
        if ($user->id !== Auth::id()) {
            $user->is_admin = $validated['is_admin'] ?? false;
        }

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
