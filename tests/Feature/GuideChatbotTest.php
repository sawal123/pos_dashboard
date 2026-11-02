<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Chatbot\NexaPosKnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GuideChatbotTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_guests_cannot_access_guide_chat(): void
    {
        $response = $this->postJson(route('guide-chat.message'), [
            'message' => 'Bagaimana cara membuka shift?',
        ]);

        $response->assertUnauthorized();
    }

    public function test_02_empty_or_whitespace_message_is_rejected(): void
    {
        [$business, $user] = $this->setupAuthenticatedUser();

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('guide-chat.message'), [
                'message' => '   ',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('message');
    }

    public function test_03_missing_openai_api_key_returns_friendly_informational_reply(): void
    {
        [$business, $user] = $this->setupAuthenticatedUser();
        Config::set('services.openai.api_key', '');

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('guide-chat.message'), [
                'message' => 'Bagaimana cara membuka shift kasir?',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertStringContainsString('OPENAI_API_KEY', $response->json('reply'));
    }

    public function test_04_successful_guide_question_returns_ai_reply(): void
    {
        [$business, $user] = $this->setupAuthenticatedUser();
        Config::set('services.openai.api_key', 'sk-mock-valid-key-123');
        Config::set('services.openai.model', 'gpt-4o-mini');

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-mock',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Untuk menghitung HPP resep di NexaPOS, buka menu **Produk & Layanan**, lalu pilih tab **Produk**...',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('guide-chat.message'), [
                'message' => 'Bagaimana cara menghitung HPP resep produk?',
                'history' => [
                    ['role' => 'user', 'content' => 'Halo'],
                    ['role' => 'assistant', 'content' => 'Halo! Ada yang bisa saya bantu terkait NexaPOS?'],
                ],
                'current_path' => '/products',
                'page_title' => 'Produk & Layanan',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertStringContainsString('Untuk menghitung HPP resep di NexaPOS', $response->json('reply'));

        // Verify sent payload to OpenAI
        Http::assertSent(function ($request) {
            $isTarget = $request->url() === 'https://api.openai.com/v1/chat/completions';
            $hasAuth = $request->hasHeader('Authorization', 'Bearer sk-mock-valid-key-123');
            $data = $request->data();

            return $isTarget && $hasAuth && $data['model'] === 'gpt-4o-mini' && count($data['messages']) >= 4;
        });
    }

    public function test_05_strict_off_topic_guardrails_are_embedded_in_knowledge_base(): void
    {
        $prompt = NexaPosKnowledgeBase::buildSystemPrompt([
            'business_name' => 'Kopi Nusantara',
            'business_type' => 'cafe',
            'user_role' => 'Owner',
            'user_name' => 'Budi',
            'current_path' => '/dashboard',
            'page_title' => 'Dashboard',
        ]);

        $this->assertStringContainsString('NexaBot', $prompt);
        $this->assertStringContainsString('STRICT OFF-TOPIC GUARDRAIL', $prompt);
        $this->assertStringContainsString('DILARANG KERAS MENJAWAB PERTANYAAN DI LUAR TOPIK', $prompt);
        $this->assertStringContainsString('TOLAK DENGAN SANGAT LEMBUT', $prompt);
        $this->assertStringContainsString('TAUTAN NAVIGASI CEPAT', $prompt);
        $this->assertStringContainsString('[Buka Menu Produk & Layanan](/products)', $prompt);
        $this->assertStringContainsString('Kopi Nusantara', $prompt);
        $this->assertStringContainsString('cafe', $prompt);
    }

    public function test_06_server_error_from_openai_returns_graceful_error_message(): void
    {
        [$business, $user] = $this->setupAuthenticatedUser();
        Config::set('services.openai.api_key', 'sk-mock-key');

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(['error' => 'Rate limit exceeded'], 429),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->postJson(route('guide-chat.message'), [
                'message' => 'Bagaimana cara menambahkan outlet baru?',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('Maaf, terjadi kendala', $response->json('reply'));
    }

    public function test_07_chatbot_widget_is_rendered_in_dashboard_and_pos_pages(): void
    {
        [$business, $user] = $this->setupAuthenticatedUser();
        Outlet::factory()->create([
            'business_id' => $business->id,
            'name' => 'Outlet Utama',
            'code' => 'MAIN',
            'status' => 'active',
        ]);

        // Dashboard
        $dashRes = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('dashboard'));

        $dashRes->assertOk();
        $dashRes->assertSee('nexabotWidgetContainer', false);
        $dashRes->assertSee('Tanya NexaBot');

        // POS
        $posRes = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('pos.index'));

        $posRes->assertOk();
        $posRes->assertSee('nexabotWidgetContainer', false);
        $posRes->assertSee('Tanya NexaBot');
    }

    /**
     * @return array{0: Business, 1: User}
     */
    private function setupAuthenticatedUser(): array
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'name' => 'Admin Pemilik']);
        $business = Business::factory()->create(['name' => 'Toko Barokah', 'business_type' => 'retail']);

        $business->users()->attach($user->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        return [$business, $user];
    }
}
