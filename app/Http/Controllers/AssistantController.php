<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatAssistantRequest;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class AssistantController extends Controller
{
    public function __construct(private AssistantService $assistantService) {}

    public function chat(ChatAssistantRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->assistantService->chat(
                (string) $validated['message'],
                $validated['history'] ?? [],
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        } catch (Throwable) {
            return response()->json([
                'message' => 'حدث خطأ غير متوقع أثناء معالجة الطلب.',
            ], 500);
        }

        return response()->json([
            'reply' => $result['reply'],
            'tools_used' => $result['tools_used'],
        ]);
    }
}
