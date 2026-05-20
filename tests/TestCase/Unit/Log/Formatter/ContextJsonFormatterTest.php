<?php
declare(strict_types=1);

namespace OtelInstrumentation\Test\TestCase\Unit\Log\Formatter;

use OtelInstrumentation\Log\Formatter\ContextJsonFormatter;
use PHPUnit\Framework\TestCase;

class ContextJsonFormatterTest extends TestCase
{
    private ContextJsonFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ContextJsonFormatter();
    }

    public function testFormatIncludesBaseFields(): void
    {
        $result = $this->formatter->format('info', 'Hello', []);
        $data = json_decode(trim($result), true);

        $this->assertArrayNotHasKey('date', $data);
        $this->assertSame('info', $data['level']);
        $this->assertSame('Hello', $data['message']);
    }

    public function testFormatIncludesDateWhenDateFormatConfigured(): void
    {
        $formatter = new ContextJsonFormatter(['dateFormat' => DATE_ATOM]);
        $result = $formatter->format('info', 'Hello', []);
        $data = json_decode(trim($result), true);

        $this->assertArrayHasKey('date', $data);
        $this->assertSame('date', array_key_first($data));
    }

    public function testFormatIncludesContextFields(): void
    {
        $context = [
            'trace_id' => 'abcdef1234567890abcdef1234567890',
            'span_id' => 'abcdef1234567890',
            'user_id' => 42,
        ];

        $result = $this->formatter->format('error', 'Something failed', $context);
        $data = json_decode(trim($result), true);

        $this->assertSame('abcdef1234567890abcdef1234567890', $data['trace_id']);
        $this->assertSame('abcdef1234567890', $data['span_id']);
        $this->assertSame(42, $data['user_id']);
    }

    public function testFormatEmptyContext(): void
    {
        $result = $this->formatter->format('debug', 'msg', []);
        $data = json_decode(trim($result), true);

        $this->assertSame(['level', 'message'], array_keys($data));
    }

    public function testBaseFieldsNotOverwrittenByContext(): void
    {
        $context = ['level' => 'injected', 'message' => 'injected'];

        $result = $this->formatter->format('warning', 'real message', $context);
        $data = json_decode(trim($result), true);

        $this->assertSame('warning', $data['level']);
        $this->assertSame('real message', $data['message']);
    }

    public function testAppendNewlineDefault(): void
    {
        $result = $this->formatter->format('info', 'msg', []);
        $this->assertStringEndsWith("\n", $result);
    }

    public function testAppendNewlineFalse(): void
    {
        $formatter = new ContextJsonFormatter(['appendNewline' => false]);
        $result = $formatter->format('info', 'msg', []);
        $this->assertStringNotContainsString("\n", $result);
    }

    public function testOutputIsValidJson(): void
    {
        $context = ['trace_id' => 'abc', 'span_id' => 'def', 'user_id' => 1];
        $result = $this->formatter->format('error', 'test', $context);

        $data = json_decode(trim($result), true);
        $this->assertIsArray($data);
        $this->assertJsonStringEqualsJsonString(trim($result), json_encode($data));
    }
}
