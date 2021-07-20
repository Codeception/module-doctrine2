<?php

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

if (version_compare(PHP_VERSION, '7.1', '>')) {
    class TestFixture2 implements FixtureInterface
    {
        public function load(Doctrine\Persistence\ObjectManager $manager)
        {
            $entity = new PlainEntity();
            $entity->setName('from TestFixture2');
            $manager->persist($entity);
            $manager->flush();
        }
    }
} else {
    class TestFixture2 implements FixtureInterface
    {
        public function load(ObjectManager $manager)
        {
            $entity = new PlainEntity();
            $entity->setName('from TestFixture2');
            $manager->persist($entity);
            $manager->flush();
        }
    }
}

