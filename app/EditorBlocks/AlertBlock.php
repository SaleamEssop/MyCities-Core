<?php

namespace App\EditorBlocks;

use BumpCore\EditorPhp\Block\Block;

class AlertBlock extends Block
{
    public function allows(): array
    {
        return [
            'type'    => [],
            'message' => ['b', 'i', 'a', 'u', 'mark', 'code', 'br'],
        ];
    }

    public function rules(): array
    {
        return [
            'type'    => 'nullable|string',
            'message' => 'nullable|string',
        ];
    }

    public function render(): string
    {
        $type    = htmlspecialchars($this->data->get('type', 'info'), ENT_QUOTES, 'UTF-8');
        $message = $this->data->get('message', '');

        return '<div class="alert alert-' . $type . '" role="alert">' . $message . '</div>';
    }
}
