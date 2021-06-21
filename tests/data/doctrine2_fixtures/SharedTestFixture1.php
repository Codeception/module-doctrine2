<?php

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;

class SharedTestFixture1 extends AbstractFixture
{
    public function load(ObjectManager $manager)
    {
        $entity = new PlainEntity();
        $entity->setName('from SharedTestFixture1');
        $manager->persist($entity);
        $manager->flush();

        $this->addReference('shared-testfixture-1', $entity);
    }
}
