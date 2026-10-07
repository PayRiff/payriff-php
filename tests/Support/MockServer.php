<?php

declare(strict_types=1);

namespace Payriff\Tests\Support;

use Payriff\Payriff;

final class MockServer
{
    private static ?self $instance = null;

    private string $dir;
    private int $port;
    private array $stubs = [];
    /** @var resource */
    private $process;

    private function __construct()
    {
        $this->dir = sys_get_temp_dir() . '/payriff-mock-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->port = (int) substr((string) strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $cmd = [PHP_BINARY, '-S', "127.0.0.1:{$this->port}", __DIR__ . '/router.php'];
        $this->process = proc_open($cmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, [
            'PAYRIFF_MOCK_DIR' => $this->dir,
        ] + getenv());
        for ($i = 0; $i < 100; $i++) {
            $conn = @fsockopen('127.0.0.1', $this->port);
            if ($conn) {
                fclose($conn);
                break;
            }
            usleep(20_000);
        }
        register_shutdown_function(function (): void {
            proc_terminate($this->process);
            array_map('unlink', glob($this->dir . '/*') ?: []);
            @rmdir($this->dir);
        });
    }

    public static function get(): self
    {
        $server = self::$instance ??= new self();
        $server->reset();
        return $server;
    }

    public function reset(): void
    {
        $this->stubs = [];
        @unlink($this->dir . '/stubs.json');
        @unlink($this->dir . '/requests.jsonl');
    }

    public function url(): string
    {
        return "http://127.0.0.1:{$this->port}";
    }

    public function stub(string $method, string $url, int $status = 200, array $headers = [], mixed $body = null): void
    {
        $raw = is_string($body) ? $body : ($body === null ? '' : json_encode($body));
        $this->stubs["$method $url"] = ['status' => $status, 'headers' => $headers, 'body' => base64_encode($raw)];
        file_put_contents($this->dir . '/stubs.json', json_encode($this->stubs));
    }

    public function ok(string $method, string $url, mixed $payload): void
    {
        $this->stub($method, $url, body: ['code' => '00000', 'message' => 'Operation performed successfully', 'payload' => $payload]);
    }

    public function requests(): array
    {
        $lines = @file($this->dir . '/requests.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        return array_map(static function (string $line): array {
            $r = json_decode($line, true);
            $r['body'] = base64_decode($r['body']);
            return $r;
        }, $lines);
    }

    public function last(): array
    {
        $requests = $this->requests();
        return $requests[count($requests) - 1];
    }

    public function lastJson(): mixed
    {
        return json_decode($this->last()['body'], true);
    }

    public function client(mixed ...$options): Payriff
    {
        return new Payriff(...($options + ['appKey' => 'app-key', 'merchantId' => 'ES1000000', 'baseUrl' => $this->url()]));
    }
}