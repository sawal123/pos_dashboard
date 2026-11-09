<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GuideChatbotService
{
    /**
     * Ask NexaBot a guide question with conversational context.
     *
     * @param  list<array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $context
     * @return array{success: bool, reply: string}
     */
    public function ask(string $message, array $history = [], array $context = []): array
    {
        $cleanMessage = trim($message);
        if ($cleanMessage === '') {
            return [
                'success' => false,
                'reply' => 'Pertanyaan tidak boleh kosong. Silakan ketik pertanyaan seputar fitur atau penggunaan Net Kasir.',
            ];
        }

        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            return [
                'success' => true,
                'reply' => "Halo! NexaBot saat ini belum terhubung ke OpenAI API Key (`OPENAI_API_KEY` belum diisi di `.env`).\n\nUntuk mengaktifkannya, silakan tambahkan `OPENAI_API_KEY=sk-...` pada file konfigurasi `.env`. Jika Anda butuh navigasi fitur saat ini, Anda bisa menekan tombol `/` untuk mencari menu langsung di header dashboard.",
            ];
        }

        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $model = (string) config('services.openai.model', 'gpt-4o-mini');
        $timeout = (int) config('services.openai.timeout', 30);

        $systemPrompt = NexaPosKnowledgeBase::buildSystemPrompt($context);

        // Build messages payload
        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
        ];

        // Append recent sanitized history (last 6 messages max)
        $trimmedHistory = array_slice($history, -6);
        foreach ($trimmedHistory as $item) {
            $role = ($item['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($item['content'] ?? ''));
            if ($content !== '') {
                $messages[] = [
                    'role' => $role,
                    'content' => mb_substr($content, 0, 1000),
                ];
            }
        }

        // Append current user message
        $messages[] = [
            'role' => 'user',
            'content' => mb_substr($cleanMessage, 0, 1000),
        ];

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 0.3,
                    'max_tokens' => 850,
                ]);

            if ($response->successful()) {
                $reply = (string) $response->json('choices.0.message.content', '');
                if ($reply !== '') {
                    return [
                        'success' => true,
                        'reply' => trim($reply),
                    ];
                }
            }

            Log::warning('NexaBot OpenAI API error response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'reply' => 'Maaf, terjadi kendala saat memproses jawaban dari server AI. Mohon coba ulangi pertanyaan Anda beberapa saat lagi.',
            ];
        } catch (Throwable $e) {
            Log::error('NexaBot OpenAI connection exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'reply' => 'Maaf, koneksi ke asisten AI sedang terganggu. Pastikan jaringan internet stabil dan coba kembali.',
            ];
        }
    }
}
