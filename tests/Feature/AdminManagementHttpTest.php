<?php

use App\Actions\Admin\AssignAdminRole;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\AdminAuthorizationSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesIsolatedDatabase;

uses(UsesIsolatedDatabase::class);

beforeEach(function () {
    $this->seed(AdminAuthorizationSeeder::class);
    $this->superRole = Role::findByName('super-admin', 'admin');
    $this->super = Admin::create(['name' => 'Super', 'email' => 'super@example.com', 'password' => 'long-password']);
    app(AssignAdminRole::class)->handle($this->super, $this->superRole);
    $this->ordinaryRole = Role::create(['name' => 'operator', 'guard_name' => 'admin']);
    $this->ordinary = Admin::create(['name' => 'Operator', 'email' => 'operator@example.com', 'password' => 'long-password']);
    app(AssignAdminRole::class)->handle($this->ordinary, $this->ordinaryRole);
});

afterEach(function () {
    $this->assertDatabaseCount('model_has_permissions', 0);
});

test('management isolates guests web users and ordinary admins without permissions', function (string $path) {
    $this->get($path)->assertRedirect('/admin/login');
    $this->actingAs(User::factory()->create(), 'web')->get($path)->assertRedirect('/admin/login');
    $this->actingAs($this->ordinary, 'admin')->get($path)->assertForbidden();
    $this->actingAs($this->super, 'admin')->get($path)->assertOk();
})->with(['/admin/admins', '/admin/roles', '/admin/permissions', '/admin/admins/create', '/admin/roles/create']);

test('super can create search paginate update and delete ordinary admins', function () {
    $this->actingAs($this->super, 'admin')->post('/admin/admins', ['name' => 'New Person', 'email' => 'NEW@example.com', 'password' => 'long-test-password', 'password_confirmation' => 'long-test-password', 'role_id' => $this->ordinaryRole->id])->assertRedirect('/admin/admins');
    $created = Admin::where('email', 'new@example.com')->firstOrFail();
    expect($created->roles->modelKeys())->toBe([$this->ordinaryRole->id]);
    $this->get('/admin/admins?search=new@example.com')->assertInertia(fn (Assert $page) => $page->component('admin/admins/index', false)->has('admins.data', 1)->where('search', 'new@example.com'));
    for ($i = 0; $i < 16; $i++) {
        Admin::create(['name' => 'Person '.$i, 'email' => 'person'.$i.'@example.com', 'password' => 'long-password']);
    }
    $this->get('/admin/admins')->assertInertia(fn (Assert $page) => $page->has('admins.data', 15)->where('admins.total', 19));
    $this->patch('/admin/admins/'.$created->id, ['name' => 'Renamed'])->assertRedirect();
    expect($created->fresh()->name)->toBe('Renamed');
    $this->delete('/admin/admins/'.$created->id)->assertSessionHasErrors('confirmation');
    $this->delete('/admin/admins/'.$created->id, ['confirmation' => true])->assertRedirect('/admin/admins');
    $this->assertDatabaseMissing('admins', ['id' => $created->id]);
});

test('account updates and role assignments have separate authorizations', function () {
    $this->ordinaryRole->givePermissionTo('admins.update');
    $this->actingAs($this->ordinary, 'admin')->patch('/admin/admins/'.$this->ordinary->id, ['name' => 'Allowed'])->assertRedirect();
    $this->patch('/admin/admins/'.$this->ordinary->id, ['role_id' => $this->ordinaryRole->id])->assertForbidden();
    $this->ordinaryRole->syncPermissions(['admins.assign-role']);
    $this->patch('/admin/admins/'.$this->ordinary->id, ['name' => 'Forbidden'])->assertForbidden();
    $this->patch('/admin/admins/'.$this->ordinary->id, ['role_id' => $this->ordinaryRole->id])->assertRedirect();
    $this->patch('/admin/admins/'.$this->ordinary->id, ['role_id' => $this->superRole->id])->assertForbidden();
    $this->patch('/admin/admins/'.$this->super->id, ['role_id' => $this->ordinaryRole->id])->assertForbidden();
});

test('ordinary admins cannot grant capabilities through a role or its permissions', function () {
    $this->ordinaryRole->givePermissionTo(['admins.assign-role', 'roles.create', 'roles.update', 'roles.assign-permissions']);
    $powerful = Role::create(['name' => 'powerful', 'guard_name' => 'admin']);
    $powerful->givePermissionTo('admins.delete');
    $this->actingAs($this->ordinary, 'admin')->patch('/admin/admins/'.$this->ordinary->id, ['role_id' => $powerful->id])->assertForbidden();
    $deletePermission = Permission::findByName('admins.delete', 'admin');
    $this->patch('/admin/roles/'.$this->ordinaryRole->id, ['permission_ids' => [$deletePermission->id]])->assertForbidden();
    $this->post('/admin/roles', ['name' => 'escalated', 'permission_ids' => [$deletePermission->id]])->assertForbidden();
    $this->assertDatabaseMissing('roles', ['name' => 'escalated']);
    expect($this->ordinary->fresh()->hasRole('operator'))->toBeTrue();
});

test('forged direct permissions passwords and guard fields are rejected', function (array $fields) {
    $this->actingAs($this->super, 'admin')->patch('/admin/admins/'.$this->ordinary->id, $fields)->assertSessionHasErrors(array_keys($fields));
})->with([[['permissions' => []]], [['permission_ids' => []]], [['password' => 'new-password']], [['guard_name' => 'web']], [['roles' => []]]]);

test('validation rejects bad input and cross-guard selections', function () {
    $webRole = Role::create(['name' => 'web', 'guard_name' => 'web']);
    $webPermission = Permission::create(['name' => 'web.view', 'guard_name' => 'web']);
    $this->actingAs($this->super, 'admin')->post('/admin/admins', ['name' => '', 'email' => 'bad', 'password' => 'short', 'password_confirmation' => 'different', 'role_id' => $webRole->id])->assertSessionHasErrors(['name', 'email', 'password', 'role_id']);
    $this->patch('/admin/admins/'.$this->ordinary->id, ['email' => 'super@example.com'])->assertSessionHasErrors('email');
    $this->post('/admin/roles', ['name' => 'bad', 'permission_ids' => [$webPermission->id]])->assertSessionHasErrors('permission_ids.0');
    $this->patch('/admin/roles/'.$webRole->id, ['name' => 'renamed'])->assertForbidden();
    $this->delete('/admin/roles/'.$webRole->id, ['confirmation' => true])->assertForbidden();
});

test('role CRUD preserves integer IDs and permission changes affect can immediately', function () {
    $view = Permission::findByName('admins.view', 'admin');
    $this->actingAs($this->super, 'admin')->post('/admin/roles', ['name' => 'reader', 'permission_ids' => [$view->id]])->assertRedirect('/admin/roles');
    $reader = Role::findByName('reader', 'admin');
    app(AssignAdminRole::class)->handle($this->ordinary, $reader);
    expect($this->ordinary->can('admins.view'))->toBeTrue();
    $this->patch('/admin/roles/'.$reader->id, ['name' => 'reader-two', 'permission_ids' => []])->assertRedirect();
    expect($this->ordinary->can('admins.view'))->toBeFalse();
    $this->delete('/admin/roles/'.$reader->id, ['confirmation' => true])->assertSessionHasErrors('delete');
    app(AssignAdminRole::class)->handle($this->ordinary, $this->ordinaryRole);
    $this->delete('/admin/roles/'.$reader->id, ['confirmation' => true])->assertRedirect('/admin/roles');
});

test('role name updates and permission changes are separately authorized', function () {
    $this->ordinaryRole->givePermissionTo('roles.update');
    $target = Role::create(['name' => 'target', 'guard_name' => 'admin']);
    $this->actingAs($this->ordinary, 'admin')->patch('/admin/roles/'.$target->id, ['name' => 'renamed'])->assertRedirect();
    $this->patch('/admin/roles/'.$target->id, ['permission_ids' => []])->assertForbidden();
    $this->ordinaryRole->syncPermissions(['roles.assign-permissions']);
    $this->patch('/admin/roles/'.$target->id, ['name' => 'blocked'])->assertForbidden();
    $this->patch('/admin/roles/'.$target->id, ['permission_ids' => []])->assertRedirect();
});

test('HTTP cannot bypass protected roles self deletion and final super protections', function () {
    $this->actingAs($this->super, 'admin')->patch('/admin/roles/'.$this->superRole->id, ['name' => 'renamed'])->assertSessionHasErrors('name');
    $this->delete('/admin/roles/'.$this->superRole->id, ['confirmation' => true])->assertSessionHasErrors('delete');
    $this->delete('/admin/admins/'.$this->super->id, ['confirmation' => true])->assertSessionHasErrors('delete');
    $this->patch('/admin/admins/'.$this->super->id, ['role_id' => $this->ordinaryRole->id])->assertSessionHasErrors('role_id');
});
