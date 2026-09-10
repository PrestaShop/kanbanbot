<?php

declare(strict_types=1);

namespace App\Triage\Infrastructure\Provider;

use App\Triage\Domain\Exception\RubricNotFoundException;

/**
 * Reads the rubric and schema from the resource files next to this context.
 *
 * A single source on purpose: the weekly run and the calibration must send
 * byte-identical prompts. If they drift, the calibration measures something
 * the agent does not execute and the prompt cache silently stops being shared,
 * with nothing failing to signal it.
 */
final class FileRubricProvider implements RubricProviderInterface
{
    public function __construct(private string $resourcesDirectory)
    {
    }

    public function severityRubric(): string
    {
        return $this->read('severity_system.md')
            .PHP_EOL.PHP_EOL
            .$this->read('severity_examples.md');
    }

    public function issueSchema(): array
    {
        $decoded = json_decode($this->read('schemas.json'), true);

        if (!is_array($decoded) || !isset($decoded['issue']['schema']) || !is_array($decoded['issue']['schema'])) {
            throw new RubricNotFoundException('schemas.json does not define an issue schema');
        }

        return $decoded['issue']['schema'];
    }

    private function read(string $file): string
    {
        $path = $this->resourcesDirectory.'/'.$file;

        if (!is_file($path)) {
            throw new RubricNotFoundException(sprintf('Missing rubric resource: %s', $path));
        }

        return (string) file_get_contents($path);
    }
}
