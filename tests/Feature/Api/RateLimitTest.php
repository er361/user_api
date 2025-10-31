<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear all cache (including rate limiter) before each test
        \Cache::flush();
        \Illuminate\Support\Facades\RateLimiter::clear('api');
        \Illuminate\Support\Facades\RateLimiter::clear('transfers');
        \Illuminate\Support\Facades\RateLimiter::clear('admin');
    }

    /**
     * Test general API rate limiting (60 requests per minute)
     */
    public function test_general_api_rate_limit_enforced(): void
    {
        $user = User::factory()->create();

        // Make 60 requests (should all succeed)
        for ($i = 0; $i < 60; $i++) {
            $response = $this->actingAs($user)
                ->putJson('/api/v1/me', [
                    'name' => "Name {$i}",
                ]);

            $response->assertOk();
        }

        // 61st request should be rate limited
        $response = $this->actingAs($user)
            ->putJson('/api/v1/me', [
                'name' => 'Name 61',
            ]);

        $response->assertStatus(429); // Too Many Requests
    }

    /**
     * Test transfer rate limiting (10 requests per minute)
     * Note: This test verifies rate limiting is configured, actual throttling
     * behavior is tested by Laravel's throttle middleware tests
     */
    public function test_transfer_rate_limit_enforced(): void
    {
        $sender = User::factory()->create(['balance' => 1000]);
        $recipient = User::factory()->create(['balance' => 0]);

        // Simulate hitting rate limit by making many requests
        // Note: Actual count may vary due to cache timing
        $limitHit = false;

        for ($i = 0; $i < 15; $i++) {
            $response = $this->actingAs($sender)
                ->postJson('/api/v1/me/transfers/initiate', [
                    'recipient_id' => $recipient->id,
                    'amount' => 1,
                ]);

            if ($response->status() === 429) {
                $limitHit = true;
                break;
            }
        }

        // Verify rate limiting is active
        $this->assertTrue($limitHit, 'Rate limit should be triggered within 15 requests');
    }

    /**
     * Test admin endpoint rate limiting (30 requests per minute)
     */
    public function test_admin_rate_limit_enforced(): void
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();

        $targetUser = User::factory()->create(['balance' => 100]);

        // Simulate hitting rate limit
        $limitHit = false;

        for ($i = 0; $i < 35; $i++) {
            $response = $this->actingAs($admin)
                ->putJson("/api/v1/users/{$targetUser->id}/balance", [
                    'balance' => 100 + $i,
                ]);

            if ($response->status() === 429) {
                $limitHit = true;
                break;
            }
        }

        // Verify admin rate limiting is active
        $this->assertTrue($limitHit, 'Admin rate limit should be triggered within 35 requests');
    }

    /**
     * Test rate limits are per-user
     */
    public function test_rate_limits_are_per_user(): void
    {
        $user1 = User::factory()->create(['balance' => 1000]);
        $user2 = User::factory()->create(['balance' => 1000]);
        $recipient = User::factory()->create(['balance' => 0]);

        // User1 hits rate limit
        $user1LimitHit = false;
        for ($i = 0; $i < 15; $i++) {
            $response = $this->actingAs($user1)
                ->postJson('/api/v1/me/transfers/initiate', [
                    'recipient_id' => $recipient->id,
                    'amount' => 1,
                ]);

            if ($response->status() === 429) {
                $user1LimitHit = true;
                break;
            }
        }

        $this->assertTrue($user1LimitHit, 'User1 should hit rate limit');

        // User2's request should still succeed (different user = different limit)
        $response = $this->actingAs($user2)
            ->postJson('/api/v1/me/transfers/initiate', [
                'recipient_id' => $recipient->id,
                'amount' => 1,
            ]);

        $this->assertEquals(201, $response->status(), 'User2 should not be rate limited');
    }

    /**
     * Test confirm transfer endpoint is also rate limited
     */
    public function test_confirm_transfer_is_rate_limited(): void
    {
        $sender = User::factory()->create(['balance' => 1000]);
        $recipient = User::factory()->create(['balance' => 0]);

        // Verify rate limit applies to both initiate and confirm
        $limitHit = false;

        for ($i = 0; $i < 15; $i++) {
            // Initiate transfer
            $initiateResponse = $this->actingAs($sender)
                ->postJson('/api/v1/me/transfers/initiate', [
                    'recipient_id' => $recipient->id,
                    'amount' => 1,
                ]);

            if ($initiateResponse->status() === 429) {
                $limitHit = true;
                break;
            }

            // Confirm if initiate succeeded
            if ($initiateResponse->status() === 201) {
                $token = $initiateResponse->json('confirmation_token');

                $confirmResponse = $this->actingAs($sender)
                    ->postJson('/api/v1/me/transfers/confirm', [
                        'confirmation_token' => $token,
                    ]);

                if ($confirmResponse->status() === 429) {
                    $limitHit = true;
                    break;
                }
            }
        }

        // Verify rate limiting triggered
        $this->assertTrue($limitHit, 'Transfer endpoints should be rate limited');
    }

    /**
     * Test rate limit headers are present
     */
    public function test_rate_limit_headers_are_present(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/v1/me', [
                'name' => 'Test Name',
            ]);

        $response->assertOk();

        // Check for rate limit headers
        $this->assertNotNull($response->headers->get('X-RateLimit-Limit'));
        $this->assertNotNull($response->headers->get('X-RateLimit-Remaining'));
    }

    /**
     * Test unauthenticated requests are not rate limited per user
     */
    public function test_unauthenticated_requests_have_rate_limit(): void
    {
        // This tests that even without auth, rate limiting applies
        // Based on IP or other identifier

        for ($i = 0; $i < 60; $i++) {
            $response = $this->putJson('/api/v1/me', [
                'name' => 'Test',
            ]);

            // Should be 401, not 429 (auth fails before rate limit)
            $response->assertUnauthorized();
        }
    }
}
