<?php

namespace Tests\Unit\Services;

use App\Services\TelegramMiniAppValidator;
use Tests\TestCase;

class TelegramMiniAppValidatorTest extends TestCase
{
    protected TelegramMiniAppValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = app(TelegramMiniAppValidator::class);
    }

    public function test_it_returns_false_for_empty_init_data(): void
    {
        $result = $this->validator->validate('');

        $this->assertFalse($result);
    }

    public function test_it_returns_false_for_missing_hash(): void
    {
        $initData = http_build_query([
            'auth_date' => time(),
            'user' => json_encode(['id' => 123, 'first_name' => 'John']),
        ]);

        $result = $this->validator->validate($initData);

        $this->assertFalse($result);
    }

    public function test_it_returns_false_for_missing_auth_date(): void
    {
        $initData = http_build_query([
            'hash' => 'invalid_hash',
            'user' => json_encode(['id' => 123, 'first_name' => 'John']),
        ]);

        $result = $this->validator->validate($initData);

        $this->assertFalse($result);
    }

    public function test_it_returns_false_for_expired_auth_date(): void
    {
        // auth_date is 2 days ago, max age is 1 day (86400)
        $initData = $this->createValidInitData(['auth_date' => time() - 172800]);

        $result = $this->validator->validate($initData, 86400);

        $this->assertFalse($result);
    }

    public function test_it_returns_false_for_invalid_hash(): void
    {
        $initData = http_build_query([
            'auth_date' => time(),
            'user' => json_encode(['id' => 123, 'first_name' => 'John']),
            'hash' => 'invalid_hash_value',
        ]);

        $result = $this->validator->validate($initData);

        $this->assertFalse($result);
    }

    public function test_it_validates_correct_init_data(): void
    {
        $initData = $this->createValidInitData();

        $result = $this->validator->validate($initData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('auth_date', $result);
    }

    public function test_it_parses_user_json(): void
    {
        $userData = [
            'id' => 123456789,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
        ];

        $initData = $this->createValidInitData(['user' => json_encode($userData)]);

        $result = $this->validator->validate($initData);

        $this->assertIsArray($result);
        $this->assertEquals(123456789, $result['user']['id']);
        $this->assertEquals('John', $result['user']['first_name']);
        $this->assertEquals('Doe', $result['user']['last_name']);
        $this->assertEquals('johndoe', $result['user']['username']);
    }

    public function test_extract_user_returns_user_data(): void
    {
        $userData = [
            'id' => 123456789,
            'first_name' => 'John',
        ];

        $initData = $this->createValidInitData(['user' => json_encode($userData)]);

        $validatedData = $this->validator->validate($initData);
        $user = $this->validator->extractUser($validatedData);

        $this->assertIsArray($user);
        $this->assertEquals(123456789, $user['id']);
        $this->assertEquals('John', $user['first_name']);
    }

    public function test_extract_user_returns_null_without_user(): void
    {
        $user = $this->validator->extractUser(['auth_date' => time()]);

        $this->assertNull($user);
    }

    /**
     * Create valid init data with correct HMAC signature.
     */
    private function createValidInitData(array $overrides = []): string
    {
        $data = array_merge([
            'auth_date' => (string) time(),
            'query_id' => 'AAHdF6IQAAAAAN0XohDhrOrc',
            'user' => json_encode([
                'id' => 123456789,
                'first_name' => 'Test',
                'last_name' => 'User',
                'username' => 'testuser',
                'language_code' => 'en',
            ]),
        ], $overrides);

        // Build data-check-string
        ksort($data);
        $dataCheckString = collect($data)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode("\n");

        // Create secret key using "WebAppData"
        $botToken = config('telegram.bot_token') ?? 'test_token:12345';
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);

        // Compute hash
        $data['hash'] = hash_hmac('sha256', $dataCheckString, $secretKey);

        return http_build_query($data);
    }
}
