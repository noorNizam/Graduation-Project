<?php

namespace App\Presentation\Requests;

use Illuminate\Http\UploadedFile;

class UpdateWorkGalleryItemRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $files = $this->files->get('files');

        if ($files instanceof UploadedFile) {
            $this->files->set('files', [$files]);
            $this->convertedFiles = null;
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:255', 'not_regex:/^\s*$/'],
            'description' => ['sometimes', 'nullable', 'string'],
            'date_of_achievement' => ['sometimes', 'nullable', 'date'],
            'files' => ['sometimes', 'nullable', 'array', 'max:10'],
            'files.*' => ['file', 'mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi,mkv,webm,pdf,doc,docx', 'max:5120'], // max in KB (5 MB)
        ];
    }

    public function messages(): array
    {
        return [
            'files.max' => 'You may upload a maximum of 10 files',
            'files.*.max' => 'Each file must not be greater than 5 MB',
            'files.*.mimes' => 'Each file must be a type of: video, image, pdf, or doc',
        ];
    }
}
