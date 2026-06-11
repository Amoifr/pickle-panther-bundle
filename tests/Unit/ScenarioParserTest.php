<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Tests\Unit;

use Amoifr\PicklePantherBundle\Runner\ScenarioParser;
use PHPUnit\Framework\TestCase;

final class ScenarioParserTest extends TestCase
{
    private const SCENARIO_DIR = __DIR__.'/../Application/Scenario';

    public function testParsesFrenchKeys(): void
    {
        $scenarios = (new ScenarioParser())->parseFile(self::SCENARIO_DIR.'/demo_fr.yaml');

        self::assertCount(2, $scenarios);

        $first = $scenarios[0];
        self::assertSame("Navigation depuis l'accueil (FR)", $first['name']);
        self::assertSame('desktop', $first['context']['browser']);
        self::assertNull($first['context']['identity']);
        self::assertSame("Visite la page avec l'[url]", $first['steps'][0]['action']);
        self::assertSame(['url' => '/index.html'], $first['steps'][0]['args']);
        self::assertSame("Le titre d'accueil est affiché", $first['steps'][3]['title']);

        // The authenticated scenario carries the identity from `identifié`.
        self::assertSame('admin', $scenarios[1]['context']['identity']);
    }

    public function testParsesEnglishKeys(): void
    {
        $scenarios = (new ScenarioParser())->parseFile(self::SCENARIO_DIR.'/demo_en.yaml');

        self::assertCount(1, $scenarios);
        $first = $scenarios[0];
        self::assertSame('Navigation from home (EN)', $first['name']);
        self::assertSame('desktop', $first['context']['browser']);
        self::assertSame('Visit the page at [url]', $first['steps'][0]['action']);
        self::assertSame('Navigate to page 2', $first['steps'][3]['title']);
    }

    public function testRejectsFileWithoutScenarios(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'scn').'.yaml';
        file_put_contents($tmp, "foo: bar\n");

        $this->expectException(\InvalidArgumentException::class);
        try {
            (new ScenarioParser())->parseFile($tmp);
        } finally {
            @unlink($tmp);
        }
    }
}
