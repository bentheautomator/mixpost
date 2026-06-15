<?php

namespace Inovector\Mixpost\Concerns\Job;

use Inovector\Mixpost\Support\Log;
use Inovector\Mixpost\Support\SocialProviderResponse;

trait SocialProviderJobFail
{
    public function makeFail(SocialProviderResponse $response): void
    {
        // Do not dump the full job payload (it can carry tokens/credentials). Log the
        // redacted provider response context plus the job uuid for correlation.
        Log::error($this->job->payload()['displayName'] ?? 'Social provider job failed', [
            'job' => $this->job->payload()['uuid'] ?? null,
            'context' => $this->redactSensitive($response->context()),
        ]);

        $this->fail();
    }

    /**
     * Recursively redact secret-bearing keys before they reach the log files.
     */
    protected function redactSensitive(array $data): array
    {
        $sensitiveKeys = [
            'access_token', 'page_access_token', 'oauth_token', 'oauth_token_secret',
            'client_secret', 'refresh_token', 'code', 'authorization', 'token',
        ];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redactSensitive($value);
            } elseif (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $data[$key] = '[REDACTED]';
            }
        }

        return $data;
    }
}
