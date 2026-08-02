<?php

namespace Tests\Security;

use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\SessionMiddleware;
use Tests\TestCase;

class SecurityMiddlewareTest extends TestCase
{
    private array $originalServer;
    private array $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
        $this->originalEnv = $_ENV;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_ENV = $this->originalEnv;
        parent::tearDown();
    }

    public function testSessionMiddlewareConfiguresStrictAndSecureSessionOptions(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = '80';
        $_ENV['APP_URL'] = 'http://assessment.local';

        SessionMiddleware::handle();

        $this->assertSame('1', ini_get('session.use_strict_mode'));
        $this->assertSame('1', ini_get('session.use_cookies'));
        $this->assertSame('1', ini_get('session.use_only_cookies'));
        $this->assertSame('0', ini_get('session.use_trans_sid'));
        $this->assertSame('1', ini_get('session.cookie_httponly'));
        $this->assertSame('Lax', ini_get('session.cookie_samesite'));
        $this->assertSame('/', ini_get('session.cookie_path'));
        $this->assertSame('0', ini_get('session.cookie_secure'));
    }

    public function testHttpsDetectionEnablesSecureCookieAttribute(): void
    {
        // 1. Check $_SERVER['HTTPS'] = 'on'
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SERVER['HTTPS'] = 'on';
        unset($_SERVER['SERVER_PORT']);
        $_ENV['APP_URL'] = 'http://assessment.local';

        SessionMiddleware::handle();
        $this->assertSame('1', ini_get('session.cookie_secure'));

        // 2. Check SERVER_PORT = 443
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = 443;
        $_ENV['APP_URL'] = 'http://assessment.local';

        SessionMiddleware::handle();
        $this->assertSame('1', ini_get('session.cookie_secure'));

        // 3. Check APP_URL with https scheme
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = 80;
        $_ENV['APP_URL'] = 'https://assessment.local';

        SessionMiddleware::handle();
        $this->assertSame('1', ini_get('session.cookie_secure'));
    }

    public function testHttpXForwardedProtoIsNotBlindlyTrusted(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_ENV['APP_URL'] = 'http://assessment.local';

        SessionMiddleware::handle();
        $this->assertSame('0', ini_get('session.cookie_secure'));
    }

    public function testSecurityHeadersMiddlewareGlobalHeadersConfigured(): void
    {
        $_ENV['APP_ENV'] = 'development';
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = 80;

        $headers = SecurityHeadersMiddleware::getHeaders();

        $this->assertSame('nosniff', $headers['X-Content-Type-Options'] ?? null);
        $this->assertSame('SAMEORIGIN', $headers['X-Frame-Options'] ?? null);
        $this->assertSame('strict-origin-when-cross-origin', $headers['Referrer-Policy'] ?? null);
        $this->assertSame('camera=(), microphone=(), geolocation=()', $headers['Permissions-Policy'] ?? null);
        $this->assertSame('none', $headers['X-Permitted-Cross-Domain-Policies'] ?? null);
        $this->assertArrayNotHasKey('Content-Security-Policy', $headers);
    }

    public function testHstsOnlySetInProductionHttps(): void
    {
        // Case A: testing env + HTTP -> No HSTS
        $_ENV['APP_ENV'] = 'testing';
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = 80;
        $_ENV['APP_URL'] = 'http://assessment.local';
        $headersA = SecurityHeadersMiddleware::getHeaders();
        $this->assertArrayNotHasKey('Strict-Transport-Security', $headersA);

        // Case B: production env + HTTP -> No HSTS
        $_ENV['APP_ENV'] = 'production';
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = 80;
        $_ENV['APP_URL'] = 'http://assessment.local';
        $headersB = SecurityHeadersMiddleware::getHeaders();
        $this->assertArrayNotHasKey('Strict-Transport-Security', $headersB);

        // Case C: production env + HTTPS -> HSTS set to max-age=31536000 only
        $_ENV['APP_ENV'] = 'production';
        $_SERVER['HTTPS'] = 'on';
        $headersC = SecurityHeadersMiddleware::getHeaders();
        $this->assertSame('max-age=31536000', $headersC['Strict-Transport-Security'] ?? null);
        $this->assertStringNotContainsString('includeSubDomains', $headersC['Strict-Transport-Security']);
        $this->assertStringNotContainsString('preload', $headersC['Strict-Transport-Security']);
    }

    public function testSecurityHeadersMiddlewareHandleRunsSafely(): void
    {
        // Must not throw any exception when executed
        SecurityHeadersMiddleware::handle();
        $this->assertTrue(true);
    }

    public function testSessionRegenerationBehaviorPreserved(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            SessionMiddleware::handle();
        }

        $_SESSION['test_key'] = 'test_val';
        $oldId = session_id();

        SessionMiddleware::regenerate();

        $newId = session_id();
        $this->assertNotEmpty($newId);
        $this->assertNotEquals($oldId, $newId);
        $this->assertSame('test_val', $_SESSION['test_key'] ?? null);
    }
}
