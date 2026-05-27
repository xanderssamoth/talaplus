<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Specification extends SqlModel
{
    use SoftDeletes;

    protected function tableName(): string
    {
        return 'specifications';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
