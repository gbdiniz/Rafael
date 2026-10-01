<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVoiceTurnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'audio' => [
                'required',
                'file',
                'mimetypes:'.implode(',', config('rafael.voice_turn.allowed_mimes')),
                'max:'.config('rafael.voice_turn.max_kilobytes'),
            ],
        ];
    }
}
