<?php

namespace Tests\Security;

use App\Middleware\CsrfMiddleware;
use App\Security\CsrfTokenManager;
use Tests\TestCase;

class CsrfMiddlewareTest extends TestCase
{
    private array $originalServer;
    private array $originalPost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
        $this->originalPost = $_POST;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    protected function tearDown(): void
    {
        http_response_code(200);
        $_SERVER = $this->originalServer;
        $_POST = $this->originalPost;
        parent::tearDown();
    }

    public function testVerifyReturnsFalseForArrayPostToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = ['malformed'];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);

        $this->assertFalse(CsrfMiddleware::verify());
    }

    public function testVerifyReturnsFalseForMissingToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_POST['_csrf_token']);
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);

        $this->assertFalse(CsrfMiddleware::verify());
    }

    public function testVerifyReturnsTrueForValidStringToken(): void
    {
        $validToken = CsrfTokenManager::getToken();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = $validToken;
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);

        $this->assertTrue(CsrfMiddleware::verify());
    }

    public function testVerifyReturnsFalseForInvalidStringToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = 'invalid_token_string';
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);

        $this->assertFalse(CsrfMiddleware::verify());
    }

    public function testVerifyReturnsFalseForMalformedHeaderToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_POST['_csrf_token']);
        $_SERVER['HTTP_X_CSRF_TOKEN'] = ['malformed_header_array'];

        $this->assertFalse(CsrfMiddleware::verify());
    }

    public function testHandleAcceptsValidToken(): void
    {
        $validToken = CsrfTokenManager::getToken();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = $validToken;
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);

        $this->assertTrue(CsrfMiddleware::handle());
    }

    public function testHandleSafelyRejectsArrayTokenWithoutTypeError(): void
    {
        $res = $this->runHandleInSubprocess(['_csrf_token' => ['malformed']], ['REQUEST_METHOD' => 'POST']);
        $this->assertSame(0, $res['exit_code']);
        $this->assertStringContainsString('419 Page Expired', $res['stdout']);
        $this->assertStringNotContainsString('TypeError', $res['stderr']);
        $this->assertStringNotContainsString('Fatal error', $res['stderr']);
    }

    public function testHandleSafelyRejectsMissingToken(): void
    {
        $res = $this->runHandleInSubprocess([], ['REQUEST_METHOD' => 'POST']);
        $this->assertSame(0, $res['exit_code']);
        $this->assertStringContainsString('419 Page Expired', $res['stdout']);
        $this->assertStringNotContainsString('TypeError', $res['stderr']);
    }

    public function testHandleSafelyRejectsInvalidStringToken(): void
    {
        $res = $this->runHandleInSubprocess(['_csrf_token' => 'invalid_token_string'], ['REQUEST_METHOD' => 'POST']);
        $this->assertSame(0, $res['exit_code']);
        $this->assertStringContainsString('419 Page Expired', $res['stdout']);
        $this->assertStringNotContainsString('TypeError', $res['stderr']);
    }

    public function testHandleJsonResponseForMalformedArrayToken(): void
    {
        $res = $this->runHandleInSubprocess(
            ['_csrf_token' => ['malformed']],
            ['REQUEST_METHOD' => 'POST', 'HTTP_ACCEPT' => 'application/json']
        );

        $this->assertSame(0, $res['exit_code']);
        $this->assertStringContainsString('"success":false', $res['stdout']);
        $this->assertStringContainsString('"error":"Invalid or expired CSRF token.', $res['stdout']);
        $this->assertStringNotContainsString('TypeError', $res['stderr']);
    }

    public function testSendHttpStatusNormalCode(): void
    {
        send_http_status(200);
        $this->assertSame(200, http_response_code());

        send_http_status(404);
        $this->assertSame(404, http_response_code());
    }

    public function testSendHttpStatus419Code(): void
    {
        send_http_status(419);
        $this->assertSame(419, http_response_code());
    }

    private function runHandleInSubprocess(array $post = [], array $server = []): array
    {
        $bootstrapPath = addslashes(dirname(__DIR__, 2) . '/bootstrap/init.php');
        $postExport = var_export($post, true);
        $serverExport = var_export($server, true);

        $code = "<?php
require_once '{$bootstrapPath}';
\$_POST = {$postExport};
\$_SERVER = array_merge(\$_SERVER, {$serverExport});
\App\Middleware\CsrfMiddleware::handle();
";

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = proc_open('php', $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            $this->fail('Failed to spawn sub-process for CsrfMiddleware::handle() test.');
        }

        fwrite($pipes[0], $code);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'stdout' => $stdout,
            'stderr' => $stderr,
            'exit_code' => $exitCode
        ];
    }
}
