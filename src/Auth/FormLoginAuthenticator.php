<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Auth;

use Symfony\Component\Panther\Client;

/**
 * Generic form-login authenticator, configured from `pickle_panther.auth`.
 * It performs a real browser login: visits the login page, fills the email and
 * password fields, submits, and waits for the redirect away from the login URL.
 *
 * Projects with a non-standard flow can either tweak the config or extend this
 * class (e.g. override {@see credentialsFor()} or {@see fillCredentials()}).
 *
 * @phpstan-type AuthConfig array{
 *     login_path: string,
 *     logout_path: string,
 *     form_selector: string,
 *     email_field: string,
 *     password_field: string,
 *     roles: array<string, array{email: string, password: string}>
 * }
 */
class FormLoginAuthenticator implements AuthenticatorInterface
{
    /**
     * @param AuthConfig $config
     */
    public function __construct(
        protected array $config,
    ) {
    }

    public function authenticate(string $role, Client $client): void
    {
        ['email' => $email, 'password' => $password] = $this->credentialsFor($role);

        // Always start from a clean session.
        $this->logout($client);

        $client->request('GET', $this->config['login_path']);
        $client->waitFor($this->config['form_selector'], 10);

        $this->fillCredentials($client, $email, $password);
        $client->wait(1);

        $client->executeScript(sprintf(
            "document.querySelector('%s').submit();",
            addslashes($this->config['form_selector'])
        ));

        // Wait until we are no longer on the login page, then for full load.
        $loginPath = $this->config['login_path'];
        $client->wait()->until(
            static fn () => !str_contains($client->getCurrentURL(), $loginPath),
            10
        );
        $client->wait()->until(
            static fn () => $client->executeScript('return document.readyState === "complete";')
        );
        $client->wait(1);
    }

    public function logout(Client $client): void
    {
        try {
            $client->request('GET', $this->config['logout_path']);
            // Purge cookies so a `remember_me` cookie cannot silently
            // re-authenticate the next request.
            $client->manage()->deleteAllCookies();
            $client->wait(2);
        } catch (\Throwable $e) {
            echo '⚠️ Logout error: '.$e->getMessage()."\n";
        }
    }

    /**
     * @return array{email: string, password: string}
     */
    protected function credentialsFor(string $role): array
    {
        if (!isset($this->config['roles'][$role])) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown auth role "%s". Configure it under pickle_panther.auth.roles (available: %s).',
                $role,
                implode(', ', array_keys($this->config['roles'])) ?: '<none>'
            ));
        }

        return $this->config['roles'][$role];
    }

    protected function fillCredentials(Client $client, string $email, string $password): void
    {
        $client->executeScript(sprintf(
            "document.querySelector('input[name=\"%s\"]').value = '%s';"
            ."document.querySelector('input[name=\"%s\"]').value = '%s';",
            $this->config['email_field'],
            addslashes($email),
            $this->config['password_field'],
            addslashes($password),
        ));
    }
}
