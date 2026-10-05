<?php

namespace Manuel\Bundle\UploadDataBundle\Profiler;

use Psr\Log\LoggerInterface;

/**
 * Publica las medidas del Stopwatch. Si el servicio no existe, no hace nada.
 */
class UploadPerformance
{
    public function __construct(
        private ?LoggerInterface $logger = null,
        private mixed $stopwatch = null,
    ) {
    }

    public function checkpoint(string $name, ?int $uploadId): void
    {
        $event = $this->start($name);
        if (null === $event) {
            return;
        }

        $event->stop();
        $this->log($event, $uploadId);
    }

    public function start(string $name): ?object
    {
        if (!is_object($this->stopwatch) || !method_exists($this->stopwatch, 'start')) {
            return null;
        }

        return $this->stopwatch->start($name);
    }

    public function stop(?object $event, ?int $uploadId, array $context = []): void
    {
        if (!is_object($event) || !method_exists($event, 'stop')) {
            return;
        }

        $event->stop();
        $this->log($event, $uploadId, $context);
    }

    private function log(object $event, ?int $uploadId, array $context = []): void
    {
        $this->logger?->debug((string) $event, ['upload_id' => $uploadId] + $context);
    }
}
