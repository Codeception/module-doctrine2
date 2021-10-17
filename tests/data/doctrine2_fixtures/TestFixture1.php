<?php

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TestFixture1 implements FixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $entity = new PlainEntity();
        $entity->setName('from TestFixture1');

        $manager->persist($entity);
        $manager->flush();
    }
}