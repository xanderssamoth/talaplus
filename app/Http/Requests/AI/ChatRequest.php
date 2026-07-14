<?php

namespace App\Http\Requests\AI;

use App\Data\AI\ChatRequestData;
use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string'],
            'conversation_id' => ['nullable', 'integer', 'exists:ai_conversations,id'],
            'assistant' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:255'],
            'context' => ['nullable', 'array'],
            'options' => ['nullable', 'array'],
        ];
    }

    public function toData(): ChatRequestData
    {
        return new ChatRequestData(
            message: $this->string('message')->toString(),
            conversationId: $this->integer('conversation_id') ?: null,
            assistant: $this->input('assistant'),
            title: $this->input('title'),
            context: $this->input('context', []),
            options: $this->input('options', []),
        );
    }
}