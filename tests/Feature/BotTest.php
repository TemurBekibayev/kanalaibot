<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Channel;
use App\Models\Post;
use App\Services\Duplicate\FuzzyDuplicateDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Fuzzy Duplicate matching engine.
     */
    public function test_fuzzy_duplicate_detector_spots_similar_texts(): void
    {
        // 1. Create owner and channel
        $user = User::create([
            'telegram_id' => 123456,
            'name' => 'Test User',
            'plan' => 'free',
        ]);

        $channel = Channel::create([
            'telegram_id' => -100123456789,
            'title' => 'Test Channel',
            'owner_id' => $user->id,
        ]);

        // 2. Create an already published post
        Post::create([
            'channel_id' => $channel->id,
            'draft_content' => 'Cobalt sotiladi. Rangi oq, yili 2022. Narxi 13000 UZS. Holati a\'lo darajada, kraska toza.',
            'final_content' => 'Cobalt sotiladi. Rangi oq, yili 2022. Narxi 13000 UZS. Holati a\'lo darajada, kraska toza.',
            'status' => 'posted',
        ]);

        // 3. Create a new similar draft
        $draft = Post::create([
            'channel_id' => $channel->id,
            'draft_content' => 'Cobalt sotiladi. Rangi oq, yili 2022. Narxi 13000 UZS. Holati a\'lo darajada, kraska toza!',
            'status' => 'draft',
        ]);

        // 4. Run detector
        $detector = new FuzzyDuplicateDetector();
        $result = $detector->checkDuplicate($draft);

        $this->assertTrue($result['is_duplicate']);
        $this->assertGreaterThan(75.0, $result['similarity_score']);
        $this->assertEquals(1, $result['compared_post_id']);
    }

    /**
     * Test InitData Security Middleware blocks unauthorized payloads.
     */
    public function test_init_data_middleware_blocks_missing_or_bad_signatures(): void
    {
        // Send request without header/query params - should return 401
        $response = $this->getJson('/api/mini-app/posts');
        $response->assertStatus(401);

        // Send request with bad hash - should return 401
        $response2 = $this->withHeaders([
            'X-Telegram-Init-Data' => 'query_id=AA&user=BB&hash=wrong_hash'
        ])->getJson('/api/mini-app/posts');
        
        $response2->assertStatus(401);
    }

    /**
     * Test InitData Security Middleware accepts valid signatures.
     */
    public function test_init_data_middleware_authorizes_correct_signatures(): void
    {
        // Set a mock bot token in environment configuration
        $mockToken = '123456:ABC-DEF1234ghIkl-zyx';
        config(['services.telegram.bot_token' => $mockToken]);

        // Build mock user initData
        $userJson = json_encode([
            'id' => 987654,
            'first_name' => 'John',
            'username' => 'john_doe',
        ]);

        $queryArr = [
            'auth_date' => '1620000000',
            'query_id' => 'AAH987654',
            'user' => $userJson,
        ];

        // Construct data-check-string (sorted alphabetically)
        ksort($queryArr);
        $dataCheckString = "auth_date={$queryArr['auth_date']}\nquery_id={$queryArr['query_id']}\nuser={$queryArr['user']}";

        // Compute valid HMAC hash
        $secretKey = hash_hmac('sha256', $mockToken, 'WebAppData', true);
        $validHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        // Create query payload with hash
        $initDataPayload = "auth_date={$queryArr['auth_date']}&query_id={$queryArr['query_id']}&user=" . urlencode($userJson) . "&hash={$validHash}";

        // Verify route allows access and registers user
        $response = $this->withHeaders([
            'X-Telegram-Init-Data' => $initDataPayload
        ])->getJson('/api/mini-app/posts');

        // Since it passes middleware, it will hit controller, which queries DB and returns empty posts array (200 success)
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'posts']);

        // Verify user was registered in database
        $this->assertDatabaseHas('users', [
            'telegram_id' => 987654,
            'username' => 'john_doe',
            'name' => 'John'
        ]);
    }
}
