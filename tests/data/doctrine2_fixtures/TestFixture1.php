<?php

use Doctrine\Common\DataFixtures\FixtureInterface;

if (class_exists('Doctrine\Persistence\ObjectManager')) {
    class TestFixture1 implements FixtureInterface
    {
        public function load(Doctrine\Persistence\ObjectManager $manager)
        {
            $entity = new PlainEntity();
            $entity->setName('from TestFixture1');
            $manager->persist($entity);
            $manager->flush();
        }
    }
} elseif (class_exists('Doctrine\Common\Persistence\ObjectManager')) {
    class TestFixture1 implements FixtureInterface
    {
        public function load(Doctrine\Common\Persistence\ObjectManager $manager)
        {
            $entity = new PlainEntity();
            $entity->setName('from TestFixture1');
            $manager->persist($entity);
            $manager->flush();
        }
    }
}
