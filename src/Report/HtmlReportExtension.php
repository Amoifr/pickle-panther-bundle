<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Report;

use PHPUnit\Event\TestRunner\Finished;
use PHPUnit\Event\TestRunner\FinishedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * PHPUnit (11/12) extension that writes the E2E HTML report once the whole test
 * runner has finished. Register it in phpunit.xml:
 *
 *   <extensions>
 *     <bootstrap class="Amoifr\PicklePantherBundle\Report\HtmlReportExtension">
 *       <parameter name="output_dir" value="var/pickle-panther"/>
 *     </bootstrap>
 *   </extensions>
 *
 * The output directory can also be set via the PICKLE_PANTHER_OUTPUT_DIR
 * environment variable. It should match `pickle_panther.report.output_dir`.
 */
final class HtmlReportExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $outputDir = $parameters->has('output_dir')
            ? $parameters->get('output_dir')
            : ($_SERVER['PICKLE_PANTHER_OUTPUT_DIR'] ?? $_ENV['PICKLE_PANTHER_OUTPUT_DIR'] ?? getcwd().'/var/pickle-panther');

        $reportFile = rtrim((string) $outputDir, '/').'/report.html';

        // Clear stale screenshots once, before the suite runs (BasePantherTest no
        // longer clears them per test, which would wipe earlier classes' captures).
        $capturesDir = rtrim((string) $outputDir, '/').'/captures';
        if (is_dir($capturesDir)) {
            foreach (glob($capturesDir.'/*.png') ?: [] as $f) {
                @unlink($f);
            }
        }

        $facade->registerSubscriber(new class($reportFile) implements FinishedSubscriber {
            public function __construct(private readonly string $reportFile)
            {
            }

            public function notify(Finished $event): void
            {
                HtmlReporter::generateReport($this->reportFile);
            }
        });
    }
}
