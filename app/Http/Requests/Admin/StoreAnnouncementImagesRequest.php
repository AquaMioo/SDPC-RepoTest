<?php

namespace App\Http\Requests\Admin;

use App\Models\AnnouncementImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The pictures an administrator posts as the Announcement: images only, at
 * most AnnouncementImage::MAX in all (owner, 2026-10-18), each a JPG, PNG or
 * WebP up to 5 MB.
 */
class StoreAnnouncementImagesRequest extends FormRequest
{
    /** Per picture, in kilobytes. */
    public const MAX_KILOBYTES = 5120;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $room = max(0, AnnouncementImage::MAX - AnnouncementImage::query()->count());

        return [
            'images' => ['required', 'array', 'min:1', 'max:'.$room],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.required' => __('Choose at least one image.'),
            'images.max' => __('An announcement takes at most :max images. Remove some before adding more.', ['max' => AnnouncementImage::MAX]),
            'images.*.image' => __('Every file must be an image.'),
            'images.*.mimes' => __('Images must be JPG, PNG or WebP.'),
            'images.*.max' => __('Each image can be at most 5 MB.'),
        ];
    }
}
