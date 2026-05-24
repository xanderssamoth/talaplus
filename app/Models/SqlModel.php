<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

abstract class SqlModel extends Model
{
    use HasFactory;

    /**
     * The table that backs the model.
     */
    abstract protected function tableName(): string;

    /**
     * The attributes that are mass assignable.
     *
     * @return array<int, string>
     */
    abstract protected function fillableAttributes(): array;

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
        return $this->fillableAttributes();
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
