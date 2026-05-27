<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

abstract class SqlModel extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * The table that backs the model.
     */
    abstract protected function tableName(): string;

    /**
     * The model attribute casts.
     *
     * @return array<string, string>
     */
    protected function castsAttributes(): array
    {
        return [];
    }

    /**
     * The model hidden attributes.
     *
     * @return array<int, string>
     */
    protected function hiddenAttributes(): array
    {
        return [];
    }

    public function getTable(): string
    {
        return $this->tableName();
    }

    public function getFillable(): array
    {
        return collect(Schema::getColumnListing($this->getTable()))
            ->reject(fn (string $column): bool => in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at'], true))
            ->values()
            ->all();
    }

    protected function casts(): array
    {
        return $this->castsAttributes();
    }

    public function getHidden(): array
    {
        return $this->hiddenAttributes();
    }
}
