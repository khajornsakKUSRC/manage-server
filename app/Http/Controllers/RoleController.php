<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-managed catalogue of roles ("what a user manages" — Network, Server,
 * IP Phone, Developer, Computer, …). Roles are a descriptive label only and
 * never affect page access; that stays with users.permissions.
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('users/roles', [
            'roles' => Role::withCount('users')
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'color'])
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'color' => $role->color,
                    'users_count' => $role->users_count,
                ]),
        ]);
    }

    public function store(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $validated = $this->validateRole($request);

        $role = Role::create($validated);

        $activityLogger->record(
            action: 'created',
            description: "Created role '{$role->name}'",
            subjectType: 'role',
            subjectLabel: $role->name,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Role '{$role->name}' created."]);

        return back();
    }

    public function update(Request $request, Role $role, ActivityLogger $activityLogger): RedirectResponse
    {
        $role->update($this->validateRole($request, $role->id));

        $activityLogger->record(
            action: 'updated',
            description: "Updated role '{$role->name}'",
            subjectType: 'role',
            subjectLabel: $role->name,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Role '{$role->name}' updated."]);

        return back();
    }

    public function destroy(Role $role, ActivityLogger $activityLogger): RedirectResponse
    {
        $name = $role->name;

        // role_user rows cascade on delete (see the pivot migration), so
        // users simply lose this label and fall back to "general user".
        $role->delete();

        $activityLogger->record(
            action: 'deleted',
            description: "Deleted role '{$name}'",
            subjectType: 'role',
            subjectLabel: $name,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Role '{$name}' deleted."]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRole(Request $request, ?int $roleId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($roleId)],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
    }
}
