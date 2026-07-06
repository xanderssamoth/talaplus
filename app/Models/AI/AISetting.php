<?php

namespace App\Models\AI;

use App\Models\SqlModel;

class AISetting extends SqlModel
{
    protected function tableName(): string
    {
        return 'ai_settings';
    }

    /**
     * @return array<string, string>
     */
    protected function castsAttributes(): array
    {
        return [
            'temperature' => 'float',
            'stream' => 'boolean',
            'enabled' => 'boolean'
        ];
    }
}
