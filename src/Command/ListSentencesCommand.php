<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Command;

use Amoifr\PicklePantherBundle\Attribute\Sentence;
use Amoifr\PicklePantherBundle\Sentence\SentenceProviderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Documents every available scenario sentence by introspecting the registered
 * sentence providers (the services tagged by the bundle) for their #[Sentence]
 * attributes. Output is Markdown, grouped by provider, to stdout or a file.
 *
 * Because it reads the same providers the runner uses, the documentation always
 * matches what scenarios can actually call — no hardcoded list to maintain.
 */
#[AsCommand(
    name: 'pickle-panther:sentences',
    description: 'List the available scenario sentences (Markdown) by introspecting the registered providers.',
)]
final class ListSentencesCommand extends Command
{
    /**
     * @param iterable<SentenceProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('locale', 'l', InputOption::VALUE_REQUIRED, 'Only sentences declared for this locale (e.g. fr, en).')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write the Markdown to this file instead of stdout.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $localeFilter = $input->getOption('locale');
        $localeFilter = \is_string($localeFilter) && '' !== $localeFilter ? $localeFilter : null;

        $markdown = "# Scenario sentences\n\n";
        $total = 0;

        foreach ($this->providers as $provider) {
            $reflection = new \ReflectionObject($provider);

            $lines = [];
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(Sentence::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                    $sentence = $attribute->newInstance();
                    if (null !== $localeFilter && null !== $sentence->locale && $localeFilter !== $sentence->locale) {
                        continue;
                    }

                    $params = array_map(
                        static fn (\ReflectionParameter $p): string => $p->getName(),
                        $method->getParameters(),
                    );

                    $lines[] = sprintf(
                        '- `%s`%s%s',
                        $sentence->pattern,
                        null !== $sentence->locale ? ' _('.$sentence->locale.')_' : '',
                        [] !== $params ? ' — '.implode(', ', $params) : '',
                    );
                    ++$total;
                }
            }

            if ([] === $lines) {
                continue;
            }

            $markdown .= sprintf("## %s\n\n", $reflection->getShortName());
            $markdown .= implode("\n", $lines)."\n\n";
        }

        $outputFile = $input->getOption('output');
        if (\is_string($outputFile) && '' !== $outputFile) {
            file_put_contents($outputFile, $markdown);
            $io->success(sprintf('%d sentence(s) written to %s', $total, $outputFile));
        } else {
            $output->write($markdown);
        }

        return Command::SUCCESS;
    }
}