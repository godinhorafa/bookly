<?php

declare(strict_types=1);

namespace App\Http\Problem;

use Illuminate\Http\JsonResponse;

/**
 * RFC 9457 "Problem Details for HTTP APIs" response.
 * Every error the API returns has this shape; see docs/api/problems.md.
 */
final readonly class Problem
{
    private const string DOCS = 'https://github.com/godinhorafa/bookly/blob/main/docs/api/problems.md#';

    /**
     * @param  array<string, mixed>  $extensions  extra members, e.g. a list of violations
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public int $status,
        public string $type,
        public string $title,
        public string $detail,
        public array $extensions = [],
        public array $headers = [],
    ) {}

    public function toResponse(): JsonResponse
    {
        return new JsonResponse(
            [
                'type' => self::DOCS . $this->type,
                'title' => $this->title,
                'status' => $this->status,
                'detail' => $this->detail,
                ...$this->extensions,
            ],
            $this->status,
            ['Content-Type' => 'application/problem+json', ...$this->headers],
        );
    }
}
