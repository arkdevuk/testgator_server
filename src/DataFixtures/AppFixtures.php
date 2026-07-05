<?php

declare(strict_types=1);

namespace App\DataFixtures;

use DateTime;
use App\Classes\TestPlanState;
use App\Entity\Project;
use App\Entity\Release;
use App\Entity\TestPlan;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // $product = new Product();
        // $manager->persist($product);

        $project = new Project();
        $project
            ->setName('Test Project')
            ->setDescription('This is a test project');

        $manager->persist($project);
        $manager->flush();

        $project = new Project();
        $project
            ->setName('My Awesome Application')
            ->setDescription('This is a project made for testing purposes. It is a simple application that allows you to manage your projects and releases.');

        $manager->persist($project);
        $manager->flush();

        $releases = [
            '0.1.0',
            '0.2.0',
            '0.3.0',
            '0.4.0',
            '0.5.0',
        ];

        foreach ($releases as $releaseName) {
            $release = new Release();
            $release
                ->setName($releaseName)
                ->setProject($project);

            $manager->persist($release);
        }
        $manager->flush();

        $testers = [
            'test1@email-test.fr',
            'test2@email-test.fr',
            'test3@email-test.fr',
            'test4@email-test.fr',
            'test5@email-test.fr',
            'test6@email-test.fr',
            'test7@email-test.fr',
            'test8@email-test.fr',
        ];

        $testerEntities = [];

        foreach ($testers as $tester) {
            $t = User::createTester($tester);
            $manager->persist($t);
            $testerEntities[] = $t;
        }
        $manager->flush();


        // datetime now + 30 days
        $dueDate = new DateTime();
        $dueDate->modify('+30 days');

        $testPlan = new TestPlan();
        $testPlan
            ->setName('Frontend new UI integration')
            ->setDescription('Testing the new UI integration')
            ->setRelease($release)
            ->setDueDate($dueDate)
            ->setKey('613b913edc08f5fc72bab4c755e049c321ea172e8115be31113c95c50993995b9224a27a70a1e79c60a3ffd9a699a4d9f55b03d0a1aebe4b2ac2cbd2235cfe72')
            ->setState(TestPlanState::PUBLISHED);

        foreach ($testerEntities as $tester) {
            $testPlan->addTestersEnrolled($tester);
        }

        $manager->persist($testPlan);

        // datetime now + 30 days
        $dueDate = new DateTime();
        $dueDate->modify('+5 days');

        $testPlan = new TestPlan();
        $testPlan
            ->setName('Backend API ACL testing')
            ->setDescription('Testing the API access control list')
            ->setRelease($release)
            ->setDueDate($dueDate)
            ->setKey('613b913edc08f5fc72bab4c755e049c321ea172e8115be31113c95c50993995b9224a27a70a1e79c60a3ffd9a699a4d9f55b03d0a1aebe4b2ac2cbd2235cfe72')
            ->setState(TestPlanState::PUBLISHED);

        foreach ($testerEntities as $tester) {
            $testPlan->addTestersEnrolled($tester);
        }
        $manager->persist($testPlan);

        // datetime now + 30 days
        $dueDate = new DateTime();
        $dueDate->modify('-5 days');

        $testPlan = new TestPlan();
        $testPlan
            ->setName('New login LDAP integration')
            ->setDescription('Testing the new LDAP integration')
            ->setState(TestPlanState::ARCHIVED)
            ->setRelease($release)
            ->setDueDate($dueDate)
            ->setKey('613b913edc08f5fc72bab4c755e049c321ea172e8115be31113c95c50993995b9224a27a70a1e79c60a3ffd9a699a4d9f55b03d0a1aebe4b2ac2cbd2235cfe72');

        foreach ($testerEntities as $tester) {
            $testPlan->addTestersEnrolled($tester);
        }
        $manager->persist($testPlan);

        $manager->flush();
    }
}
