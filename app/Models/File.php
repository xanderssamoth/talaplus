<?php

namespace App\Models;

use App\Models\AI\AiMessage;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;

class File extends SqlModel
{
    use SoftDeletes;

    protected function tableName(): string
    {
        return 'files';
    }

    protected function fillableAttributes(): array
    {
        return [
            'file_name',
            'file_url',
            'file_description',
            'file_type',
            'user_id',
            'comment_id',
            'product_id',
            'message_id',
            'mime_type',
            'file_size',
            'width',
            'height',
            'duration',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function castsAttributes(): array
    {
        return [
            'file_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'integer',
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    public static function metadataFromUploadedFile(UploadedFile $file): array
    {
        $metadata = [
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'width' => null,
            'height' => null,
            'duration' => null,
        ];

        $realPath = $file->getRealPath();

        if (str_starts_with((string) $metadata['mime_type'], 'image/') && $realPath !== false) {
            $dimensions = @getimagesize($realPath);

            if ($dimensions !== false) {
                $metadata['width'] = $dimensions[0] ?? null;
                $metadata['height'] = $dimensions[1] ?? null;
            }
        }

        return $metadata;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function aiMessages(): BelongsToMany
    {
        return $this->belongsToMany(AiMessage::class, 'ai_message_files', 'file_id', 'ai_message_id')->withTimestamps();
    }
}
