<?php

declare(strict_types=1);

namespace App\Tests\PullRequest\Domain\Aggregate\PullRequest;

use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestDiff;
use App\PullRequest\Domain\Aggregate\PullRequest\PullRequestId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PullRequestDiffTest extends KernelTestCase
{
    public function testParseDiff(): void
    {
        $diffContent = file_get_contents(__DIR__.'/../../../../fixtures/35380.diff');
        $pr = new PullRequestId('prestashop', 'prestashop', '35380');
        $prDiff = PullRequestDiff::parseDiff($pr, $diffContent); // @phpstan-ignore-line

        $this->assertNotNull($prDiff);
        $this->assertEquals(3, count($prDiff->getFiles()));

        $files = [
            'src/Adapter/Hosting/HostingInformation.php',
            'src/Adapter/System/SystemInformation.php',
            'src/PrestaShopBundle/Resources/views/Admin/Configure/AdvancedParameters/system_information.html.twig',
        ];

        foreach ($prDiff->getFiles() as $key => $file) {
            $this->assertEquals($files[$key], $file->getFileName());
        }

        $this->assertStringContainsString('\'hostname\' => $this->hostingInformation->getHostname(),',
            $prDiff->getFiles()[1]->getHunks()[0]->getNew()
        );
        $this->assertStringNotContainsString('\'hostname\' => $this->hostingInformation->getHostname(),',
            $prDiff->getFiles()[1]->getHunks()[0]->getOld()
        );
    }

    public function testParseDiffWithEmptyDiff(): void
    {
        $pr = new PullRequestId('prestashop', 'prestashop', 'ko');
        $prDiff = PullRequestDiff::parseDiff($pr, '');

        $this->assertNotNull($prDiff);
        $this->assertEquals(0, count($prDiff->getFiles()));
    }

    public function testParseDiffWithTranslations(): void
    {
        $diffContent = file_get_contents(__DIR__.'/../../../../fixtures/30510.diff');
        $pr = new PullRequestId('prestashop', 'prestashop', '30510');
        $prDiff = PullRequestDiff::parseDiff($pr, $diffContent); // @phpstan-ignore-line

        $this->assertNotNull($prDiff);

        $translations = $prDiff->getTranslations();

        $needles = [
            'Admin.Design.Notification' => [
                'By deleting this image format, the theme will not be able to use it. This will result in a degraded experience on your front office.',
                'Delete the images linked to this image setting',
            ],
            'Admin.Design.Feature' => [
                'Are you sure you want to delete this image setting?',
            ],
            'Admin.Actions' => [
                'Cancel',
                'Delete',
            ],
        ];
        $this->assertEquals($needles, $translations);
    }
}
