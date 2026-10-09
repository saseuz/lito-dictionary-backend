<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\DictionaryController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware('auth:admin')->group(function () {
    Route::get('dictionary/lookup', [DictionaryController::class, 'lookup'])->name('dictionary.lookup');
    Route::resource('admins', AdminController::class)->except('show');
    Route::resource('roles', RoleController::class)->except('show');
    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
});
