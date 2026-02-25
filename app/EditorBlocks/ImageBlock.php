<?php

namespace App\EditorBlocks;

use BumpCore\EditorPhp\Blocks\Image;

/**
 * Custom Image block that accepts both absolute and relative URLs.
 *
 * The default EditorPhp Image block uses 'url' validation which rejects
 * relative paths like /storage/editor-images/.... This override changes
 * the validation to 'nullable|string' so relative paths are accepted.
 */
class ImageBlock extends Image
{
    public function rules(): array
    {
        return [
            'file.url'       => 'nullable|string',
            'caption'        => 'nullable|string',
            'withBorder'     => 'boolean',
            'stretched'      => 'boolean',
            'withBackground' => 'boolean',
        ];
    }
}
