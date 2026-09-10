<?php

declare(strict_types=1);

namespace App\Tests\Triage\Infrastructure\Provider;

use App\Triage\Domain\Exception\RubricNotFoundException;
use App\Triage\Infrastructure\Provider\FileRubricProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FileRubricProviderTest extends TestCase
{
    /**
     * Keywords the structured-output schema validator rejects.
     *
     * A schema carrying one of these passes every local check and is then
     * refused by the API on every single item, which surfaces as a run that
     * scores nothing rather than as an obvious error. `maxItems` shipped
     * exactly that way once, after `maxLength` had already been removed for
     * the same reason and the array constraint was missed.
     *
     * @var string[]
     */
    private const UNSUPPORTED = [
        'format', 'maxItems', 'maxLength', 'maximum',
        'minItems', 'minLength', 'minimum', 'pattern', 'uniqueItems',
    ];

    private function provider(): FileRubricProvider
    {
        return new FileRubricProvider(__DIR__.'/../../../../src/Triage/Infrastructure/Resources');
    }

    public function testTheSchemaUsesOnlyKeywordsStructuredOutputsAccept(): void
    {
        $offenders = [];
        $this->collect($this->provider()->issueSchema(), 'issue', $offenders);

        $this->assertSame([], $offenders, 'these are rejected by the API on every item: '.implode(', ', $offenders));
    }

    public function testEveryRequiredPropertyIsDefined(): void
    {
        $schema = $this->provider()->issueSchema();

        $required = $schema['required'];
        $properties = $schema['properties'];
        $this->assertIsArray($required);
        $this->assertIsArray($properties);

        foreach ($required as $property) {
            $this->assertIsString($property);
            $this->assertArrayHasKey($property, $properties, $property.' is required but never defined');
        }
    }

    public function testTheSchemaIsClosed(): void
    {
        $this->assertFalse(
            $this->provider()->issueSchema()['additionalProperties'] ?? true,
            'an open schema lets the model invent fields nothing reads'
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rubricContentProvider(): array
    {
        return [
            'the four levels' => ['Critical'],
            'the mined examples' => ['Worked examples'],
            // The two markers measured to actually separate the classes.
            'the security clause' => ['Security'],
            'the data-loss clause' => ['Data loss'],
        ];
    }

    #[DataProvider('rubricContentProvider')]
    public function testTheRubricCarriesItsSubstance(string $expected): void
    {
        $this->assertStringContainsString($expected, $this->provider()->severityRubric());
    }

    public function testAMissingResourceSaysWhichOne(): void
    {
        $this->expectException(RubricNotFoundException::class);
        (new FileRubricProvider('/nonexistent'))->severityRubric();
    }

    /**
     * @param string[] $offenders
     */
    private function collect(mixed $node, string $path, array &$offenders): void
    {
        if (!is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            if (is_string($key) && in_array($key, self::UNSUPPORTED, true)) {
                $offenders[] = $path.'.'.$key;
            }
            $this->collect($value, $path.'.'.(is_string($key) ? $key : '[]'), $offenders);
        }
    }
}
