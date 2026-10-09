<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\User;

class DictionaryWordPolicy
{
    public function viewAny(Admin|User $actor): bool
    {
        return $actor instanceof Admin && $actor->can('dictionary.view');
    }
}
