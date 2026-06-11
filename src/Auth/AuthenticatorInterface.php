<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Auth;

use Symfony\Component\Panther\Client;

/**
 * Authentication is project-specific. Implement this interface (and alias your
 * service to it) to let scenarios request a logged-in context via the
 * `identified`/`identifié` scenario key.
 *
 * The bundle ships a generic {@see FormLoginAuthenticator} that covers classic
 * form logins; enable it through the `pickle_panther.auth` configuration.
 */
interface AuthenticatorInterface
{
    /**
     * Logs in as the given role (the value of the scenario `identified` key,
     * e.g. "user" or "admin").
     */
    public function authenticate(string $role, Client $client): void;

    public function logout(Client $client): void;
}
