<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ManageAdmins;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeleteResourceRequest;
use App\Http\Requests\Admin\SaveAdminRequest;
use App\Models\Admin;
use App\Support\AdminManagementData;
use App\Support\AdminMutations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Admin::class);
        $search = $request->validate(['search' => ['nullable', 'string', 'max:255']])['search'] ?? '';
        $actor = $request->user('admin');
        $admins = Admin::with('roles')->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString()->through(function ($admin) use ($actor) {
                $mutable = ! AdminMutations::isSuper($admin) || AdminMutations::isSuper($actor);

                return ['id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email, 'role' => $admin->roles->first()?->name,
                    'can_edit' => $mutable && ($actor->can('update', $admin) || $actor->can('assignRole', $admin)),
                    'can_delete' => $mutable && ! $actor->is($admin) && $actor->can('delete', $admin)];
            });

        return Inertia::render('admin/admins/index', compact('admins', 'search'));
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Admin::class);
        Gate::authorize('assignRole', new Admin);

        return Inertia::render('admin/admins/create', ['roles' => AdminManagementData::roles($request->user('admin'))]);
    }

    public function store(SaveAdminRequest $request, ManageAdmins $action): RedirectResponse
    {
        $data = $request->validated();
        $action->create($request->user('admin'), $request->safe()->only(['name', 'email', 'password']), Role::whereKey($data['role_id'])->firstOrFail());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Admin created.']);

        return to_route('admin.admins.index');
    }

    public function edit(Request $request, Admin $admin): Response
    {
        $actor = $request->user('admin');
        abort_unless($actor->can('update', $admin) || $actor->can('assignRole', $admin), 403);
        AdminMutations::protectTarget($actor, $admin);

        return Inertia::render('admin/admins/edit', ['admin' => ['id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email, 'role_id' => $admin->roles->first()?->id], 'roles' => AdminManagementData::roles($actor)]);
    }

    public function update(SaveAdminRequest $request, Admin $admin, ManageAdmins $action): RedirectResponse
    {
        $data = $request->validated();
        $action->update($request->user('admin'), $admin, $request->safe()->only(['name', 'email']), isset($data['role_id']) ? Role::whereKey($data['role_id'])->firstOrFail() : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Admin updated.']);

        return back();
    }

    public function destroy(DeleteResourceRequest $request, Admin $admin, ManageAdmins $action): RedirectResponse
    {
        $action->delete($request->user('admin'), $admin);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Admin deleted.']);

        return to_route('admin.admins.index');
    }
}
