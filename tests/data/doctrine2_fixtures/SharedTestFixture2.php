<?php

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;

class SharedTestFixture2 extends AbstractFixture
{
    public function load(ObjectManager $manager)
    {
        $entity = new PlainEntity();
        $entity->setName('from SharedTestFixture2');
        $manager->persist($entity);
        $manager->flush();

        $this->addReference('shared-testfixture-2', $entity);
    }
}
