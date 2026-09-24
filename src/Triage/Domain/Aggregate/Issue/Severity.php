<?php

declare(strict_types=1);

namespace App\Triage\Domain\Aggregate\Issue;

use App\Triage\Domain\Exception\UnknownSeverityException;

/**
 * The four levels of the project's published bug severity classification.
 *
 * The vocabulary is fixed by the repository's own labels and by the `Severity`
 * field of boards 47 and 48, so it is an enum rather than a free string: a
 * proposal that does not name one of these cannot be acted on.
 *
 * @see https://build.prestashop-project.org/news/2019/severity-classification/
 */
enum Severity: string
{
    case Critical = 'Critical';
    case Major = 'Major';
    case Minor = 'Minor';
    case Trivial = 'Trivial';

    public static function fromName(string $name): self
    {
        return self::tryFrom($name) ?? throw new UnknownSeverityException($name);
    }

    /**
     * Distance in levels, used to tell a near miss from a real one.
     *
     * A Major proposed as Critical costs the sheriff one glance; a Critical
     * proposed as Minor is an issue they never see ranked. Calibration reports
     * both, and this is what separates them.
     */
    public function distanceTo(self $other): int
    {
        return abs($this->rank() - $other->rank());
    }

    /**
     * Whether this level is more serious than the other.
     *
     * The direction of a miss matters as much as its size. Rated too low, a
     * real problem can sit unseen at the bottom of the list; rated too high,
     * it costs the sheriff a look and nothing worse.
     */
    public function isMoreSevereThan(self $other): bool
    {
        return $this->rank() < $other->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::Major => 1,
            self::Minor => 2,
            self::Trivial => 3,
        };
    }
}
