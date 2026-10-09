<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Support\AdminManagementData;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Permission::class);

        return Inertia::render('admin/permissions/index', ['groups' => AdminManagementData::groups()]);
    }
}
