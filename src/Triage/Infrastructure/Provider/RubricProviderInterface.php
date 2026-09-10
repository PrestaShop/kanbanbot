<?php

declare(strict_types=1);

namespace App\Triage\Infrastructure\Provider;

interface RubricProviderInterface
{
    /**
     * The severity rubric, with the mined worked examples appended.
     *
     * One string rather than two, because the classifier sends it as a single
     * cached block: both halves are equally stable across a run, so both
     * belong on the cached side of the breakpoint.
     */
    public function severityRubric(): string;

    /**
     * The structured-output schema constraining a verdict.
     *
     * @return array<string, mixed>
     */
    public function issueSchema(): array;
}
