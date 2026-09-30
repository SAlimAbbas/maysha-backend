<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseApiRequest;

class RequestUploadUrlRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:image,video'],
            'filename' => ['required', 'string', 'max:255'],
            'mime' => ['required', 'string', 'in:image/jpeg,image/png,image/webp,image/avif,video/mp4,video/quicktime'],
            'size' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $type = $this->input('type');
            $size = (int) $this->input('size');

            // Image max 10MB (10485760 bytes)
            if ($type === 'image' && $size > 10485760) {
                $v->errors()->add('size', 'Image file size cannot exceed 10 MB.');
            }

            // Video max 500MB (524288000 bytes)
            if ($type === 'video' && $size > 524288000) {
                $v->errors()->add('size', 'Video file size cannot exceed 500 MB.');
            }
        });
    }
}
