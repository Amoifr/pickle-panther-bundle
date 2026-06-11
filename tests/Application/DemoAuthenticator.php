<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Tests\Application;

use Amoifr\PicklePantherBundle\Auth\AuthenticatorInterface;
use Symfony\Component\Panther\Client;

/**
 * Spy authenticator used by the functional demo: it records the roles it was
 * asked to authenticate instead of performing a real login, so tests can assert
 * the pluggable auth path is wired and invoked.
 */
final class DemoAuthenticator implements AuthenticatorInterface
{
    /** @var list<string> */
    public static array $authenticated = [];

    public function authenticate(string $role, Client $client): void
    {
        self::$authenticated[] = $role;
    }

    public function logout(Client $client): void
    {
    }
}
