<?php

namespace App\Services;

use Symfony\Component\Ldap\Ldap;

class LdapService
{
    private string $baseDn;
    private string $admin;
    private string $password;
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
    )
    {
        $uid = $this->secureString($uid);
        $ldap = $this->getClient();
        $ldap->bind("uid={$this->admin},ou=people,{$this->baseDn}", $this->password);
        $query = $ldap->query($this->baseDn, "(&(objectClass=person)(uid={$uid}))");
        $results = $query->execute()->toArray();
        if (count($results) === 0) {
            throw new \Exception('User not found');
        }
        $user = $results[0];
        $dn = $user->getDn();
        $ldap->bind($dn, $password);
        $result = $ldap->query($this->baseDn, "(&(objectClass=person)(uid={$uid}))")->execute()->toArray();
        $uid = $result[0]->getAttributes()['uid'][0];
        $userInfos = [
            'uid' => $uid,
            'displayName' => $result[0]->getAttributes()['cn'][0] ?? $uid,
            'groups' => [],
            'email' => $result[0]->getAttributes()['mail'][0],
        ];
        $query = $ldap->query($this->baseDn,
            '(&(objectClass=groupOfNames)(member='
            . $result[0]->getDn()
            . '))'
        );
        $groups = $query->execute()->toArray();
        foreach ($groups as $group) {
            $userInfos['groups'][] = $group->getAttributes()['cn'][0];
        }
        if ($this->mustHaveGroup !== null) {
            if (!in_array($this->mustHaveGroup, $userInfos['groups'])) {
                throw new \Exception('User not in required group');
            }
        }
        return $userInfos;
    }

    private function secureString(string $input): string
    {
        return preg_replace('/[,\(\)\\\*<>\[\]\{\}&|!^~]/', '', $input);
    }

    public function getClient(): Ldap
    {
        return Ldap::create('ext_ldap', [
            'connection_string' => $_ENV['LDAP_QUERY_STRING'],
            'encryption' => str_contains($_ENV['LDAP_QUERY_STRING'], 'ldaps:') ? 'ssl' : 'none',
        ]);

    }
}
