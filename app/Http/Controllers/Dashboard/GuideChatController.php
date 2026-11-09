<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Chatbot\GuideChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuideChatController extends Controller
{
    public function __construct(
        protected GuideChatbotService $chatbotService
    ) {}

    /**
     * Send a question to NexaBot guide chatbot.
     */
    public function message(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
            'current_path' => ['nullable', 'string', 'max:255'],
            'page_title' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        /** @var Business|null $business */
        $business = $request->attributes->get('dashboard_business');
        $businessType = $request->attributes->get('dashboard_business_type');
        $businessRole = $request->attributes->get('dashboard_business_role');

        $context = [
            'user_name' => $user?->name ?? 'Pengguna',
            'business_name' => $business?->name ?? 'Bisnis Net Kasir',
            'business_type' => $businessType ?? ($business?->normalizedBusinessType() ?? 'Umum'),
            'user_role' => $businessRole ?? 'Pengguna',
            'current_path' => $validated['current_path'] ?? $request->header('Referer', '/dashboard'),
            'page_title' => $validated['page_title'] ?? 'Net Kasir',
        ];

        $history = $validated['history'] ?? [];

        $result = $this->chatbotService->ask(
            message: $validated['message'],
            history: $history,
            context: $context
        );

        return response()->json($result);
    }
}
