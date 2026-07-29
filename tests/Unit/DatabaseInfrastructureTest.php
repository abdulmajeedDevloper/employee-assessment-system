<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Support\Database;
use App\Security\EncryptionService;
use App\Security\HashManager;
use App\Security\CsrfTokenManager;

class DatabaseInfrastructureTest extends TestCase
{
    public function testDatabaseConnectionAndStrictUtcState(): void
    {
        $pdo = Database::getInstance();
        $this->assertNotNull($pdo);

        $isVerified = Database::verifyConnectionState($pdo);
        $this->assertTrue($isVerified);
    }

    public function testEncryptionServiceAes256Gcm(): void
    {
        $encryption = new EncryptionService('e2f5a8b1c4d7e0f3a6b9c2d5e8f1a4b7');
        $plaintext = "Sensitive National ID: 1234567890";

        $encrypted = $encryption->encrypt($plaintext);
        $this->assertArrayHasKey('ciphertext', $encrypted);
        $this->assertArrayHasKey('iv', $encrypted);
        $this->assertArrayHasKey('tag', $encrypted);

        $decrypted = $encryption->decrypt($encrypted['ciphertext'], $encrypted['iv'], $encrypted['tag']);
        $this->assertEquals($plaintext, $decrypted);
    }

    public function testHashManagerHmacSha256(): void
    {
        $nationalId = "1234567890";
        $hash1 = HashManager::hashNationalId($nationalId);
        $hash2 = HashManager::hashNationalId($nationalId);

        $this->assertEquals(32, strlen($hash1));
        $this->assertEquals($hash1, $hash2);

        $rateHash = HashManager::hashRateLimitIdentifier("user@org.gov");
        $this->assertEquals(64, strlen($rateHash));
    }

    public function testCsrfTokenManager(): void
    {
        $token = CsrfTokenManager::getToken();
        $this->assertNotEmpty($token);
        $this->assertTrue(CsrfTokenManager::validate($token));
        $this->assertFalse(CsrfTokenManager::validate('invalid_token'));
    }
}
