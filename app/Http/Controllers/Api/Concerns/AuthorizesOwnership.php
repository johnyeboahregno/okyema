<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesOwnership
{
    protected function currentUser(): User
    {
        return request()->user();
    }

    /**
     * Every per-user resource is strictly scoped to its owner. Non-owners get
     * a 403.
     */
    protected function authorizeModel(Model $model): void
    {
        abort_unless(
            method_exists($model, 'belongsToUser') && $model->belongsToUser($this->currentUser()),
            403,
            'You do not have access to this resource.'
        );
    }
}
