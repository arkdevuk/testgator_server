<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Routing\Attribute\Route;

final class DefaultController extends AbstractController
{
    #[Route('/', name: 'rome')]
    public function index(): Response
    {
        $users = [];


        // load ENV vars
        $baseDn = $_ENV['LDAP_BASE_DN'];
        $admin = $_ENV['LDAP_ADMIN_UID'];
        $password = $_ENV['LDAP_ADMIN_PASSWORD'];

        $ldap = Ldap::create('ext_ldap', [
            'connection_string' => $_ENV['LDAP_QUERY_STRING'],
            'encryption' => str_contains($_ENV['LDAP_QUERY_STRING'], 'ldaps:') ? 'ssl' : 'none',
        ]);

        $ldap->bind("uid={$admin},ou=people,{$baseDn}", $password);
        $query = $ldap->query($baseDn, '(&(objectClass=person))');
        $results = $query->execute()->toArray();
        foreach ($results as $result) {
            //$user = $result->getAttributes();
            $user = [];
            $user['username'] = $result->getAttributes()['uid'][0];
            $user['displayName'] = $result->getAttributes()['cn'][0];
            $user['groups'] = [];
            // get all the groups for the user
            $query = $ldap->query($baseDn,
                '(&(objectClass=groupOfNames)(member='
                . $result->getDn()
                . '))'
            );
            $groups = $query->execute()->toArray();
            foreach ($groups as $group) {
                //$groupName = $group->getAttributes()['cn'][0];
                //$user['groups'][$groupName] = $group->getAttributes();
                $user['groups'][] = $group->getAttributes()['cn'][0];
            }
            //dump($user);
            $users[] = $user;
        }


        //die;

        return $this->json([
            'users' => $users,
        ]);
    }
}
