#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/chat-helpers.php';

final class TelecrmWebSocketServer
{
    private const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    private PDO $pdo;
    private $server;
    private string $host;
    private int $port;
    /** @var array<int, array{socket: resource, handshake: bool, buffer: string, token: string|null, user_id: int|null, name: string, email: string, last_activity: float}> */
    private array $clients = [];
    private int $lastEventId = 0;
    private float $lastEventPoll = 0.0;
    private float $lastPing = 0.0;

    public function __construct(PDO $pdo, string $host, int $port)
    {
        $this->pdo = $pdo;
        $context = stream_context_create(['socket' => ['backlog' => 128]]);
        $server = @stream_socket_server(sprintf('tcp://%s:%d', $host, $port), $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        if ($server === false) {
            throw new RuntimeException(sprintf('Unable to start WebSocket server: %s (%d)', $errstr ?: 'unknown error', $errno));
        }
        stream_set_blocking($server, false);
        $this->server = $server;
        $this->host = $host;
        $this->port = $port;
        $this->lastEventId = $this->detectLastEventId();
    }

    public function run(): void
    {
        fwrite(STDOUT, sprintf("[telecrm] WebSocket server listening on %s:%d\n", $this->host, $this->port));
        while (true) {
            $read = [$this->server];
            foreach ($this->clients as $client) {
                $read[] = $client['socket'];
            }

            $write = null;
            $except = null;
            $timeoutSec = 1;

            @stream_select($read, $write, $except, $timeoutSec, 0);

            foreach ($read as $stream) {
                if ($stream === $this->server) {
                    $this->acceptClient();
                } else {
                    $this->handleClientStream($stream);
                }
            }

            $this->pollEvents();
            $this->cleanupInactiveClients();
            $this->sendPingIfNeeded();
        }
    }

    private function acceptClient(): void
    {
        $socket = @stream_socket_accept($this->server, 0);
        if ($socket === false) {
            return;
        }
        stream_set_blocking($socket, false);
        $id = (int) $socket;
        $this->clients[$id] = [
            'socket'        => $socket,
            'handshake'     => false,
            'buffer'        => '',
            'token'         => null,
            'user_id'       => null,
            'name'          => '',
            'email'         => '',
            'last_activity' => microtime(true),
        ];
    }

    private function handleClientStream($stream): void
    {
        $id = (int) $stream;
        if (!isset($this->clients[$id])) {
            return;
        }
        $data = @fread($stream, 8192);
        if ($data === '' || $data === false) {
            $this->removeClient($id);
            return;
        }
        $this->clients[$id]['buffer'] .= $data;
        if (!$this->clients[$id]['handshake']) {
            $this->attemptHandshake($id);
            return;
        }
        $frames = $this->extractFrames($id);
        foreach ($frames as $frame) {
            $this->handleFrame($id, $frame);
        }
    }

    /**
     * @return array<int, array{opcode:int,payload:string}>
     */
    private function extractFrames(int $clientId): array
    {
        $frames = [];
        $buffer = &$this->clients[$clientId]['buffer'];

        while (strlen($buffer) >= 2) {
            $bytes = unpack('Cfirst/Csecond', substr($buffer, 0, 2));
            if (!$bytes) {
                break;
            }
            $fin = ($bytes['first'] >> 7) & 1;
            $opcode = $bytes['first'] & 0x0F;
            $masked = ($bytes['second'] >> 7) & 1;
            $length = $bytes['second'] & 0x7F;
            $index = 2;

            if ($length === 126) {
                if (strlen($buffer) < 4) {
                    break;
                }
                $extended = unpack('n', substr($buffer, 2, 2));
                if (!$extended) {
                    break;
                }
                $length = $extended[1];
                $index = 4;
            } elseif ($length === 127) {
                if (strlen($buffer) < 10) {
                    break;
                }
                $extended = unpack('J', substr($buffer, 2, 8));
                if (!$extended) {
                    break;
                }
                $length = (int) $extended[1];
                $index = 10;
            }

            if ($masked === 0) {
                $this->removeClient($clientId);
                break;
            }

            if (strlen($buffer) < $index + 4 + $length) {
                break;
            }

            $mask = substr($buffer, $index, 4);
            $index += 4;
            $payload = substr($buffer, $index, $length);
            $buffer = substr($buffer, $index + $length);

            $decoded = '';
            for ($i = 0; $i < $length; $i++) {
                $decoded .= $payload[$i] ^ $mask[$i % 4];
            }

            $frames[] = [
                'opcode'  => $opcode,
                'payload' => $decoded,
            ];

            if ($fin === 0) {
                continue;
            }
        }

        return $frames;
    }

    private function handleFrame(int $clientId, array $frame): void
    {
        $opcode = $frame['opcode'];
        switch ($opcode) {
            case 8: // close
                $this->removeClient($clientId);
                break;
            case 9: // ping
                $this->sendFrame($this->clients[$clientId]['socket'], $frame['payload'], 10);
                break;
            case 1: // text frame
                $this->clients[$clientId]['last_activity'] = microtime(true);
                // Currently no client-originated commands are required beyond pong/keep-alive.
                break;
            default:
                break;
        }
    }

    private function attemptHandshake(int $clientId): void
    {
        $buffer = $this->clients[$clientId]['buffer'];
        if (!str_contains($buffer, "\r\n\r\n")) {
            return;
        }
        [$headerText] = explode("\r\n\r\n", $buffer, 2);
        $lines = explode("\r\n", $headerText);
        $requestLine = array_shift($lines);
        if (!$requestLine || !preg_match('#GET\s+(.*?)\s+HTTP/1\.1#i', $requestLine, $matches)) {
            $this->removeClient($clientId);
            return;
        }
        $path = $matches[1];
        $headers = [];
        foreach ($lines as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($key))] = trim($value);
        }
        if (!isset($headers['sec-websocket-key'])) {
            $this->removeClient($clientId);
            return;
        }
        $query = [];
        $parts = parse_url($path);
        if ($parts !== false && isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $token = isset($query['token']) && is_string($query['token']) ? $query['token'] : '';
        $socket = $this->clients[$clientId]['socket'];
        $accept = base64_encode(sha1($headers['sec-websocket-key'] . self::GUID, true));
        $response = "HTTP/1.1 101 Switching Protocols\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept: {$accept}\r\n\r\n";
        @fwrite($socket, $response);
        $this->clients[$clientId]['handshake'] = true;
        $this->clients[$clientId]['buffer'] = '';
        $this->clients[$clientId]['token'] = $token;
        if (!$this->authenticateClient($clientId, $token)) {
            $this->send($clientId, ['type' => 'error', 'code' => 'auth_failed']);
            $this->removeClient($clientId);
            return;
        }
        $this->send($clientId, ['type' => 'ready']);
    }

    private function authenticateClient(int $clientId, string $token): bool
    {
        if ($token === '') {
            return false;
        }
        $details = chat_validate_websocket_token($this->pdo, $token);
        if ($details === null) {
            return false;
        }
        $this->clients[$clientId]['user_id'] = $details['user_id'];
        $this->clients[$clientId]['name'] = $details['name'];
        $this->clients[$clientId]['email'] = $details['email'];
        $this->clients[$clientId]['last_activity'] = microtime(true);
        $extend = $this->pdo->prepare('UPDATE chat_ws_tokens SET expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE token = :token');
        $extend->execute(['token' => $token]);
        chat_record_presence($this->pdo, $details['user_id']);
        return true;
    }

    private function removeClient(int $clientId): void
    {
        if (!isset($this->clients[$clientId])) {
            return;
        }
        $socket = $this->clients[$clientId]['socket'];
        @fclose($socket);
        unset($this->clients[$clientId]);
    }

    private function send(int $clientId, array $payload): void
    {
        if (!isset($this->clients[$clientId])) {
            return;
        }
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return;
        }
        $socket = $this->clients[$clientId]['socket'];
        $frame = $this->buildFrame($encoded);
        @fwrite($socket, $frame);
    }

    private function broadcast(array $recipients, array $payload): void
    {
        if (!isset($payload['type'])) {
            return;
        }
        foreach ($this->clients as $id => $client) {
            if ($client['user_id'] === null) {
                continue;
            }
            if (!in_array($client['user_id'], $recipients, true)) {
                continue;
            }
            $personalized = $payload;
            if ($payload['type'] === 'message' && isset($payload['message']['sender_id'])) {
                $personalized['message']['is_mine'] = ($payload['message']['sender_id'] === $client['user_id']);
            }
            $this->send($id, $personalized);
        }
    }

    private function pollEvents(): void
    {
        $now = microtime(true);
        if ($now - $this->lastEventPoll < 0.5) {
            return;
        }
        $this->lastEventPoll = $now;
        $stmt = $this->pdo->prepare('SELECT * FROM chat_ws_events WHERE id > :id ORDER BY id ASC LIMIT 100');
        $stmt->execute(['id' => $this->lastEventId]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$events) {
            return;
        }
        $ids = [];
        foreach ($events as $event) {
            $this->lastEventId = max($this->lastEventId, (int) $event['id']);
            $ids[] = (int) $event['id'];
            $recipients = json_decode($event['recipients'], true);
            $payload = json_decode($event['payload'], true);
            if (!is_array($recipients) || !is_array($payload)) {
                continue;
            }
            if (!isset($payload['type'])) {
                $payload['type'] = $event['event_type'];
            }
            $this->broadcast($recipients, $payload);
        }
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $delete = $this->pdo->prepare('DELETE FROM chat_ws_events WHERE id IN (' . $placeholders . ')');
            $delete->execute($ids);
        }
    }

    private function cleanupInactiveClients(): void
    {
        $threshold = microtime(true) - 120;
        foreach ($this->clients as $id => $client) {
            if ($client['last_activity'] < $threshold) {
                $this->removeClient($id);
            }
        }
    }

    private function sendPingIfNeeded(): void
    {
        $now = microtime(true);
        if ($now - $this->lastPing < 25) {
            return;
        }
        $this->lastPing = $now;
        foreach ($this->clients as $id => $client) {
            $this->send($id, ['type' => 'ping']);
        }
    }

    private function buildFrame(string $payload, int $opcode = 1): string
    {
        $frameHead = chr(0x80 | ($opcode & 0x0F));
        $length = strlen($payload);
        if ($length <= 125) {
            $frameHead .= chr($length);
        } elseif ($length <= 0xFFFF) {
            $frameHead .= chr(126) . pack('n', $length);
        } else {
            $frameHead .= chr(127) . pack('J', $length);
        }
        return $frameHead . $payload;
    }

    private function sendFrame($socket, string $payload, int $opcode = 1): void
    {
        $frame = $this->buildFrame($payload, $opcode);
        @fwrite($socket, $frame);
    }

    private function detectLastEventId(): int
    {
        $stmt = $this->pdo->query('SELECT MAX(id) AS last_id FROM chat_ws_events');
        if (!$stmt) {
            return 0;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && isset($row['last_id']) ? (int) $row['last_id'] : 0;
    }
}

$bindHost = getenv('TELECRM_CHAT_WS_BIND') ?: '0.0.0.0';
$port = chat_websocket_port();

try {
    $server = new TelecrmWebSocketServer(hh_db(), $bindHost, $port);
    $server->run();
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("[telecrm] WebSocket server error: %s\n", $exception->getMessage()));
    exit(1);
}
