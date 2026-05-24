<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends SqlModel
{
    use SoftDeletes;

    protected function tableName(): string
    {
        return 'groups';
    }

    protected function fillableAttributes(): array
    {
        return ['group_name', 'user_id'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_user')->withPivot('is_admin')->withTimestamps();
    }
}
