<?php

namespace App\Services\Authentification;

use Exception;
use InvalidArgumentException;
use Symfony\Component\Ldap\Ldap;

class LdapService
{
    private readonly string $baseDn;
    private readonly string $admin;
    private readonly string $password;
    private ?string $mustHaveGroup;

    public function __construct()
    {
        $this->baseDn = $_ENV['LDAP_BASE_DN'] ?? 'dc=example,dc=com';
        $this->admin = $_ENV['LDAP_ADMIN_UID'] ?? 'admin';
        $this->password = $_ENV['LDAP_ADMIN_PASSWORD'] ?? 'admin';
        $this->mustHaveGroup = $_ENV['LDAP_MUST_HAVE_GROUP'] ?? null;
        if ($this->mustHaveGroup === '') {
            $this->mustHaveGroup = null;
        }
    }

    public function checkUserLogin(
        string $uid,
        string $password
    ): array
    {
        // Validate inputs before touching LDAP
        $this->validatePassword($password);

        // Escape uid for safe embedding in an LDAP filter (RFC 4515)
        $escapedUid = $this->escapeForFilter($uid);

        $ldap = $this->getClient();
        $ldap->bind("uid={$this->admin},ou=people,{$this->baseDn}", $this->password);
        $query = $ldap->query($this->baseDn, "(&(objectClass=person)(uid={$escapedUid}))");
        $results = $query->execute()->toArray();
        if (count($results) === 0) {
            throw new Exception('User not found');
        }
        $user = $results[0];
        $dn = $user->getDn();
        $ldap->bind($dn, $password);
        $result = $ldap->query($this->baseDn, "(&(objectClass=person)(uid={$escapedUid}))")->execute()->toArray();
        $uid = $result[0]->getAttributes()['uid'][0];
        $userInfos = [
            'uid' => $uid,
            'displayName' => $result[0]->getAttributes()['cn'][0] ?? $uid,
            'groups' => [],
            'email' => $result[0]->getAttributes()['mail'][0],
        ];

        // The DN is used as a filter value in member=<dn>, so it must be
        // escaped for the filter context (RFC 4515 §4), not the DN context.
        $escapedMemberDn = $this->escapeForFilter($result[0]->getDn());
        $query = $ldap->query($this->baseDn,
            '(&(objectClass=groupOfNames)(member=' . $escapedMemberDn . '))'
        );
        $groups = $query->execute()->toArray();
        foreach ($groups as $group) {
            $userInfos['groups'][] = $group->getAttributes()['cn'][0];
        }
        if ($this->mustHaveGroup !== null && !in_array($this->mustHaveGroup, $userInfos['groups'])) {
            throw new Exception('User not in required group');
        }
        return $userInfos;
    }

    /**
     * Escape a value for safe embedding inside an LDAP search filter (RFC 4515).
     * Uses PHP's native ldap_escape() which handles all special characters
     * including null bytes — unlike a character blocklist.
     */
    private function escapeForFilter(string $input): string
    {
        return ldap_escape($input, '', LDAP_ESCAPE_FILTER);
    }

    /**
     * Reject passwords that contain null bytes or exceed a reasonable length.
     * Bind passwords are not interpolated into filter strings, but null bytes
     * can truncate strings in some LDAP server implementations.
     *
     * @throws InvalidArgumentException
     */
    private function validatePassword(string $password): void
    {
        if ($password === '') {
            throw new InvalidArgumentException('Password must not be empty');
        }

        if (strlen($password) > 1024) {
            throw new InvalidArgumentException('Password exceeds maximum length');
        }

        if (str_contains($password, "\x00")) {
            throw new InvalidArgumentException('Password contains invalid characters');
        }
    }

    public function getClient(): Ldap
    {
        return Ldap::create('ext_ldap', [
            'connection_string' => $_ENV['LDAP_QUERY_STRING'],
            'encryption' => str_contains($_ENV['LDAP_QUERY_STRING'], 'ldaps:') ? 'ssl' : 'none',
        ]);

    }
}
