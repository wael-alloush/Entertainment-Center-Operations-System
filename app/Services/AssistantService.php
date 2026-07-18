<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AssistantService
{
    private const MAX_TOOL_ROUNDS = 3;

    public function __construct(private AssistantToolExecutor $toolExecutor) {}

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array{reply: string, tools_used: list<string>}
     */
    public function chat(string $message, array $history = []): array
    {
        $apiKey = (string) config('services.openai.key');

        if ($apiKey === '') {
            throw new RuntimeException('مفتاح OpenAI غير مُعد. أضف OPENAI_API_KEY إلى ملف البيئة.');
        }

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
        ];

        foreach ($history as $item) {
            $role = $item['role'] ?? null;
            $content = trim((string) ($item['content'] ?? ''));

            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }

            $messages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $message,
        ];

        $toolsUsed = [];
        $baseUrl = rtrim((string) config('services.openai.base_url'), '/');
        $model = (string) config('services.openai.model');

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $response = $this->requestCompletion($baseUrl, $apiKey, $model, $messages);

            $choice = $response['choices'][0]['message'] ?? null;

            if (! is_array($choice)) {
                throw new RuntimeException('استجابة غير متوقعة من خدمة الذكاء الاصطناعي.');
            }

            $toolCalls = $choice['tool_calls'] ?? [];

            if ($toolCalls === [] || $toolCalls === null) {
                $reply = trim((string) ($choice['content'] ?? ''));

                if ($reply === '') {
                    $reply = 'تعذّر توليد رد مناسب. حاول إعادة صياغة السؤال.';
                }

                return [
                    'reply' => $reply,
                    'tools_used' => array_values(array_unique($toolsUsed)),
                ];
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $choice['content'] ?? null,
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $toolCall) {
                $name = (string) data_get($toolCall, 'function.name', '');
                $rawArguments = (string) data_get($toolCall, 'function.arguments', '{}');
                $arguments = json_decode($rawArguments, true);

                if (! is_array($arguments)) {
                    $arguments = [];
                }

                $result = $this->toolExecutor->execute($name, $arguments);
                $toolsUsed[] = $name;

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($toolCall['id'] ?? ''),
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ];
            }
        }

        return [
            'reply' => 'تعذّر إكمال الطلب بعد عدة محاولات. حاول مرة أخرى بسؤال أبسط.',
            'tools_used' => array_values(array_unique($toolsUsed)),
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
أنت مساعد ذكي لموظفي نظام buds لإدارة جلسات العملاء في منشأة ترفيهية.
أجب دائماً بالعربية الفصحى المبسّطة وبإيجاز واضح.
استخدم الأدوات المتاحة فقط للحصول على أرقام وحقائق من النظام. لا تخمّن أعداد الزيارات أو الفواتير أو البونصات.
لا تعدّل أي بيانات ولا تقترح تنفيذ عمليات كتابة (فتح فاتورة، تفعيل بونص، حذف...).
إذا لم تتوفر أداة للإجابة، وضّح ذلك بلطف واطلب من الموظف استخدام الشاشات المناسبة في التطبيق.
PROMPT;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    private function requestCompletion(string $baseUrl, string $apiKey, string $model, array $messages): array
    {
        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(45)
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'tools' => $this->toolExecutor->toolDefinitions(),
                    'tool_choice' => 'auto',
                    'temperature' => 0.2,
                ])
                ->throw()
                ->json();
        } catch (ConnectionException) {
            throw new RuntimeException('تعذّر الاتصال بخدمة الذكاء الاصطناعي.');
        } catch (RequestException $exception) {
            $status = $exception->response?->status();

            throw new RuntimeException(
                $status === 401
                    ? 'مفتاح OpenAI غير صالح.'
                    : 'فشلت خدمة الذكاء الاصطناعي'.($status ? " (رمز {$status})" : '').'.',
            );
        }

        if (! is_array($response)) {
            throw new RuntimeException('استجابة غير متوقعة من خدمة الذكاء الاصطناعي.');
        }

        return $response;
    }
}
