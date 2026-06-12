<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Tests\Unit;

use Amoifr\PicklePantherBundle\Report\HtmlReporter;
use Amoifr\PicklePantherBundle\Report\StepResult;
use PHPUnit\Framework\TestCase;

final class HtmlReporterTest extends TestCase
{
    protected function setUp(): void
    {
        HtmlReporter::reset();
    }

    public function testRendersHomePageLinkingToOnePagePerYamlFile(): void
    {
        $reporter = new HtmlReporter();
        $reporter->addStep(new StepResult(
            action: "Visite la page avec l'[url]",
            success: true,
            args: ['url' => '/index.html'],
            scenarioFile: 'demo_fr.yaml',
            scenarioName: 'Accueil',
            browser: 'desktop',
        ));
        $reporter->addStep(new StepResult(
            action: 'Étape en échec',
            success: false,
            scenarioFile: 'demo_fr.yaml',
            scenarioName: 'Accueil',
        ));

        $file = sys_get_temp_dir().'/pickle-panther-report-'.getmypid().'.html';
        $filePage = sys_get_temp_dir().'/pickle-panther-report-'.getmypid().'-1.html';
        @unlink($file);
        @unlink($filePage);

        HtmlReporter::generateReport($file);

        // Home page: one link per YAML file, with overall stats.
        self::assertFileExists($file);
        $index = (string) file_get_contents($file);
        self::assertStringContainsString('demo_fr.yaml', $index);
        self::assertStringContainsString('2 étape(s)', $index);
        self::assertStringContainsString('class="scenario-link"', $index);
        self::assertStringContainsString('href="'.basename($filePage).'"', $index);
        // Step-level detail (argument values) is NOT on the home page.
        self::assertStringNotContainsString('/index.html', $index);

        // Dedicated YAML-file page: breadcrumb back home + every scenario of the file.
        self::assertFileExists($filePage);
        $page = (string) file_get_contents($filePage);
        self::assertStringContainsString('class="breadcrumb"', $page);
        self::assertStringContainsString('href="'.basename($file).'"', $page);
        self::assertStringContainsString('Accueil', $page);
        self::assertStringContainsString('arg-value', $page);
        self::assertStringContainsString('/index.html', $page);
        self::assertStringContainsString('status-badge fail', $page);

        @unlink($file);
        @unlink($filePage);
    }
}