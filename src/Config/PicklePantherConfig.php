<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Config;

/**
 * Immutable resolved configuration, exposed as a service so test code and the
 * runner can read it from the test container.
 *
 * @phpstan-type BrowserConfig array{
 *     headless: bool,
 *     chrome_args: list<string>,
 *     desktop: array{width: int, height: int, user_agent: string},
 *     mobile: array{width: int, height: int, pixel_ratio: float, user_agent: string}
 * }
 * @phpstan-type AuthConfig array{
 *     login_path: string,
 *     logout_path: string,
 *     form_selector: string,
 *     email_field: string,
 *     password_field: string,
 *     roles: array<string, array{email: string, password: string}>
 * }|null
 */
final class PicklePantherConfig
{
    /**
     * @param BrowserConfig $browser
     * @param AuthConfig    $auth
     */
    public function __construct(
        public readonly string $locale,
        public readonly string $scenariosDir,
        public readonly bool $reportEnabled,
        public readonly string $outputDir,
        public readonly array $browser,
        public readonly ?array $auth = null,
        // When true, a screenshot is captured after every step (like E2E_DEBUG=1).
        public readonly bool $debug = false,
    ) {
    }

    public function capturesDir(): string
    {
        return $this->outputDir.'/captures';
    }

    public function reportFile(): string
    {
        return $this->outputDir.'/report.html';
    }
}
