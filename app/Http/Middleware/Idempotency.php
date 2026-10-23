<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Problem\Problem;
use App\Models\User;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Honours the Idempotency-Key header on unsafe requests, like Stripe's API:
 * retrying a request with the same key returns the original response instead
 * of booking or charging twice.
 *
 *  - first use: the key is reserved (a primary key insert, so concurrent
 *    duplicates can't both pass), the request runs, the response is stored
 *  - replay with the same request: the stored response, with Idempotent-Replayed: true
 *  - same key, different request: 422
 *  - same key while the first request is still running: 409
 *  - 5xx responses are not stored, so the client can retry with the same key
 */
final readonly class Idempotency
{
    public const int RETENTION_HOURS = 24;

    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        $user = $request->user();

        if (! is_string($key) || ! $user instanceof User) {
            return $next($request);
        }

        if (strlen($key) > 255 || trim($key) === '') {
            return $this->conflict(422, 'The Idempotency-Key header must be 1 to 255 characters long.');
        }

        $fingerprint = hash('sha256', $request->method() . ' ' . $request->path() . ' ' . $request->getContent());

        $reserved = $this->db->table('idempotency_keys')->insertOrIgnore([
            'user_id' => $user->id,
            'key' => $key,
            'fingerprint' => $fingerprint,
            'created_at' => now(),
        ]) === 1;

        if (! $reserved) {
            return $this->replay($user->id, $key, $fingerprint);
        }

        $response = $next($request);

        $row = $this->db->table('idempotency_keys')->where(['user_id' => $user->id, 'key' => $key]);
        if ($response->getStatusCode() >= 500) {
            $row->delete();
        } else {
            $row->update([
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
                'response_content_type' => $response->headers->get('Content-Type'),
            ]);
        }

        return $response;
    }

    private function replay(int $userId, string $key, string $fingerprint): Response
    {
        /** @var object{fingerprint: string, response_status: ?int, response_body: ?string, response_content_type: ?string}|null $stored */
        $stored = $this->db->table('idempotency_keys')->where(['user_id' => $userId, 'key' => $key])->first();

        if ($stored === null) {
            return $this->conflict(409, 'This Idempotency-Key is being processed. Retry shortly.');
        }

        if (! hash_equals($stored->fingerprint, $fingerprint)) {
            return $this->conflict(422, 'This Idempotency-Key was already used with a different request.');
        }

        if ($stored->response_status === null) {
            return $this->conflict(409, 'A request with this Idempotency-Key is still being processed.');
        }

        return new Response((string) $stored->response_body, $stored->response_status, [
            'Content-Type' => $stored->response_content_type ?? 'application/json',
            'Idempotent-Replayed' => 'true',
        ]);
    }

    private function conflict(int $status, string $detail): JsonResponse
    {
        return (new Problem($status, 'idempotency-conflict', 'Idempotency conflict', $detail))->toResponse();
    }
}
