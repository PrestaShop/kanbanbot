<?php

declare(strict_types=1);

namespace App\PullRequest\Domain\Aggregate\PullRequest;

class PullRequestDiff
{
    public function __construct(
        private readonly PullRequestId $pullRequestId,
        /** @var PullRequestDiffFile[] $files */
        private readonly array $files
    ) {
    }

    public function getPullRequestId(): PullRequestId
    {
        return $this->pullRequestId;
    }

    /**
     * @return PullRequestDiffFile[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    public static function parseDiff(PullRequestId $pullRequestId, string $diff): PullRequestDiff
    {
        if (empty($diff)) {
            return new self($pullRequestId, []);
        }

        $lines = explode("\n", $diff);
        $currentFileName = null;
        $currentFileHunks = [];

        // First we need to parse the diff into files and hunks
        foreach ($lines as $line) {
            if (
                !preg_match('/^diff --git \w\/(.*) \w\/(.*)$/', $line)
                && !preg_match('/^index (.*)\.\.(.*)$/', $line)
                && !preg_match('/^\-{3} \w\/(.*)$/', $line)
            ) {
                if (preg_match('/^\+{3} \w\/(.*)$/', $line, $matches)) {
                    $currentFileName = $matches[1];
                    $currentFileHunks[$currentFileName] = [];
                } else {
                    if (preg_match('/^(@@ -(\d)+,\d+ \+\d+,\d+ @@).*$/', $line, $matches)) {
                        $currentFileHunks[$currentFileName][] = $matches[1];
                        $currentFileHunks[$currentFileName][] = '';
                    } else {
                        $currentFileHunks[$currentFileName][array_key_last($currentFileHunks[$currentFileName])] .= $line."\n";
                    }
                }
            }
        }

        // Then, we need to transform previous information into PullRequestDiffFile and PullRequestDiffHunk
        $files = [];

        foreach ($currentFileHunks as $filename => $hunks) {
            $hunks = array_chunk($hunks, 2);
            $hunks = array_map(
                static function (array $hunk): PullRequestDiffHunk {
                    return new PullRequestDiffHunk($hunk[0], $hunk[1]);
                },
                $hunks
            );

            $files[] = new PullRequestDiffFile($filename, $hunks);
        }

        return new self($pullRequestId, $files);
    }

    /**
     * @return array<string, array<int, string|null>>
     */
    public function getTranslations(): array
    {
        $translations = [];
        $domains = [];

        // We need first to extract all translations from all the hunks
        foreach ($this->files as $file) {
            foreach ($file->getHunks() as $hunk) {
                $new = str_replace("\n", '', $hunk->getNew());

                if (
                    preg_match_all('/trans\(\s*\'([^\']*)\'(,+\s*\[[^\]]*\])?,\s*\'([^\']*)\'\s*\)/', $new, $matches)
                    || preg_match_all('/trans\(\s*\"([^\"]*)\"(,+\s*\[[^\]]*\])?,\s*\"([^\"]*)\"\s*\)/', $new, $matches)
                    || preg_match_all('/{l\s*s=\s*\"([^\"]*)\"(\s*)d=\s*\"([^\"]*)\"[^}]*?}/', $new, $matches)
                    || preg_match_all('/{l\s*s=\s*\'([^\']*)\'(\s*)d=\s*\'([^\']*)\'[^}]*?}/', $new, $matches)
                    || preg_match_all('/\{\{\s*\'([^\']*)\'\|trans\((\{.*\}),\s*\'([^\']*)\'\s*\)\s*\}\}/', $new, $matches)
                    || preg_match_all('/\{\{\s*\"([^\"]*)\"\|trans\((\{.*\}),\s*\'([^\']*)\'\s*\)\s*\}\}/', $new, $matches)
                ) {
                    $translations = array_merge($translations, $matches[1]);
                    $domains = array_merge($domains, $matches[3]);
                }
            }
        }

        // Then we reformat then properly
        $out = [];
        foreach ($translations as $idx => $translation) {
            $domain = trim($domains[$idx]);
            if (false === in_array($translation, $out[$domain] ?? [])) {
                $out[$domain][] = preg_replace('/\s+/', ' ', trim($translation));
            }
        }

        return $out;
    }

    public function hasHooksModifications(): bool
    {
        $found = false;

        foreach ($this->files as $file) {
            foreach ($file->getHunks() as $hunk) {
                $new = str_replace("\n", '', $hunk->getNew());

                if (
                    preg_match('/dispatchHookWithParameters/', $new)
                    || preg_match('/dispatchWithParameters/', $new)
                    || preg_match('/Hook::exec/', $new)
                ) {
                    $found = true;
                    break;
                }
            }

            if ($found) {
                break;
            }
        }

        return $found;
    }
}
