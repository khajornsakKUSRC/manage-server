<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
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
            'users' => User::orderBy('name')
                ->get(['id', 'name', 'email', 'is_admin', 'permissions', 'created_at', 'last_seen_at'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => $user->is_admin,
                    'permissions' => $user->permissions,
                    'created_at' => $user->created_at,
                    'is_online' => $user->isOnline(),
                ]),
            'pages' => Permissions::PAGES,
            'currentUserId' => Auth::id(),
        ]);
    }

    /**
     * Currently-online user ids, polled by the Manage Users page to keep
     * the online/offline column live without a full page reload.
     */
    public function onlineStatus(): JsonResponse
    {
        return response()->json([
            'online_ids' => User::where('last_seen_at', '>=', now()->subSeconds(User::ONLINE_THRESHOLD_SECONDS))
                ->pluck('id'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/create', [
            'pages' => Permissions::PAGES,
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $request->validate([
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'is_admin' => ['boolean'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permissions::keys())],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_admin' => $validated['is_admin'] ?? false,
            'permissions' => $validated['permissions'] ?? [],
        ]);

        $activityLogger->record(
            action: 'created',
            description: "Created user '{$user->email}'",
            subjectType: 'user',
            subjectLabel: $user->email,
        );

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

    public function update(Request $request, User $user, ActivityLogger $activityLogger): RedirectResponse
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

        $activityLogger->record(
            action: 'updated',
            description: "Updated user '{$user->email}'",
            subjectType: 'user',
            subjectLabel: $user->email,
        );

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user, ActivityLogger $activityLogger): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        $email = $user->email;

        $user->delete();

        $activityLogger->record(
            action: 'deleted',
            description: "Deleted user '{$email}'",
            subjectType: 'user',
            subjectLabel: $email,
        );

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
