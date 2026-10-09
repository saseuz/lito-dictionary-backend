<?php

use App\Actions\Admin\AssignAdminRole;
use App\Actions\Admin\ManageAdmins;
use App\Actions\Admin\ManageRoles;
use App\Models\Admin;
use App\Models\User;
use Database\Seeders\AdminAuthorizationSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesIsolatedDatabase;

uses(UsesIsolatedDatabase::class);

beforeEach(function () {
    $this->seed(AdminAuthorizationSeeder::class);
    $this->super = Admin::create(['name' => 'Super', 'email' => 'super@example.com', 'password' => 'long-password']);
    $this->superRole = Role::findByName('super-admin', 'admin');
    $this->editor = Role::create(['name' => 'editor', 'guard_name' => 'admin']);
    app(AssignAdminRole::class)->handle($this->super, $this->superRole);
});

test('super override applies only to Admins and does not bypass mutation invariants', function () {
    expect($this->super->can('an-unlisted-ability'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows('create', Admin::class))->toBeFalse();
    expect(fn () => app(ManageRoles::class)->delete($this->super, $this->superRole))->toThrow(ValidationException::class);
    expect(fn () => app(ManageRoles::class)->save($this->super, $this->superRole, 'renamed', null))->toThrow(ValidationException::class);
    expect(fn () => app(ManageAdmins::class)->delete($this->super, $this->super))->toThrow(ValidationException::class);
    expect(fn () => app(AssignAdminRole::class)->handle($this->super, $this->editor))->toThrow(ValidationException::class);
});

test('CLI cannot demote the final super administrator', function () {
    $this->artisan('app:assign-admin-role')
        ->expectsQuestion('Admin email', 'super@example.com')
        ->expectsChoice('Role', 'editor', ['editor', 'super-admin'], true)
        ->expectsOutput('Admin: super@example.com')
        ->expectsOutput('Current role: super-admin')
        ->expectsOutput('Proposed role: editor')
        ->expectsConfirmation('Change this Admin role?', 'yes')
        ->expectsOutput('The final super administrator cannot be deleted or demoted.')
        ->assertFailed();
    expect($this->super->fresh()->hasRole('super-admin'))->toBeTrue();
});

test('one super can be demoted when another remains and assigned roles cannot be deleted', function () {
    $another = Admin::create(['name' => 'Second', 'email' => 'second@example.com', 'password' => 'long-password']);
    app(AssignAdminRole::class)->handle($another, $this->superRole);
    app(AssignAdminRole::class)->handle($another, $this->editor, $this->super);
    expect($another->fresh()->hasRole('editor'))->toBeTrue();
    expect(fn () => app(ManageRoles::class)->delete($this->super, $this->editor))->toThrow(ValidationException::class);
});
