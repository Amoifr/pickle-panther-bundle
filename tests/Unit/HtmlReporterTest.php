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

    public function testRendersGroupedReportWithStats(): void
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
        @unlink($file);

        HtmlReporter::generateReport($file);

        self::assertFileExists($file);
        $html = (string) file_get_contents($file);

        self::assertStringContainsString('demo_fr.yaml', $html);
        self::assertStringContainsString('Accueil', $html);
        // Argument value highlighted inside the sentence.
        self::assertStringContainsString('arg-value', $html);
        self::assertStringContainsString('/index.html', $html);
        // 2 total, 1 success, 1 fail.
        self::assertStringContainsString('2 étape(s)', $html);
        self::assertStringContainsString('status-badge fail', $html);

        @unlink($file);
    }
}
