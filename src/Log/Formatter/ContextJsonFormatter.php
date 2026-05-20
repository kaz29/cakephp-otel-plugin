<?php
declare(strict_types=1);

namespace OtelInstrumentation\Log\Formatter;

use Cake\Log\Formatter\AbstractFormatter;

class ContextJsonFormatter extends AbstractFormatter
{
    protected array $_defaultConfig = [
        'dateFormat' => null,
        'flags' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        'appendNewline' => true,
    ];

    public function format(mixed $level, string $message, array $context = []): string
    {
        $json = json_encode(
            array_merge(
                $context,
                ['level' => (string)$level, 'message' => $message],
                $this->_config['dateFormat'] !== null ? ['date' => date($this->_config['dateFormat'])] : [],
            ),
            JSON_THROW_ON_ERROR | $this->_config['flags'],
        );

        return $this->_config['appendNewline'] ? $json . "\n" : $json;
    }
}
