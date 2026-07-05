<?php
declare(strict_types=1);

namespace PayXCommerce\Payment\Model;

use Psr\Log\LoggerInterface;

class Logger
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($this->redact($message), $this->redactContext($context));
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($this->redact($message), $this->redactContext($context));
    }

    public function redact(string $message): string
    {
        return preg_replace('/(secret|token|signature|authorization|password|key)([^\s:=]*)?([:=]\s*)?([A-Za-z0-9_\-.!@$%^&*+\/=]+)/i', '$1$2$3[redacted]', $message) ?: $message;
    }

    private function redactContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if (preg_match('/secret|token|signature|authorization|password|key/i', (string) $key)) {
                $context[$key] = '[redacted]';
            } elseif (is_string($value)) {
                $context[$key] = $this->redact($value);
            }
        }

        return $context;
    }
}
