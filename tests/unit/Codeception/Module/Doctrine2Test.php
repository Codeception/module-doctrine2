<?php

declare(strict_types=1);

use Codeception\Exception\ModuleException;
use Codeception\Lib\ModuleContainer;
use Codeception\Module\Doctrine2;
use Codeception\Stub;
use Codeception\Test\Unit;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use MultilevelRelations\A;
use MultilevelRelations\B;
use MultilevelRelations\C;
use QuirkyFieldName\Association;
use QuirkyFieldName\AssociationHost;
use QuirkyFieldName\Embeddable;
use QuirkyFieldName\EmbeddableHost;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\UuidInterface;

final class Doctrine2Test extends Unit
{
    private ?EntityManager $em = null;

    private ?Doctrine2 $module = null;

    protected static function _setUpBeforeClass()
    {
        if (!Type::hasType('uuid')) {
            Type::addType('uuid', UuidType::class);
        }
    }

    /**
     * @throws ORMException
     */
    protected function _setUp()
    {
        if (!class_exists(EntityManager::class)) {
            $this->markTestSkipped('doctrine/orm is not installed');
        }

        $dir = __DIR__ . "/../../../data/doctrine2_entities";

        require_once $dir . "/CompositePrimaryKeyEntity.php";
        require_once $dir . "/PlainEntity.php";
        require_once $dir . "/EntityWithConstructorParameters.php";
        require_once $dir . "/JoinedEntityBase.php";
        require_once $dir . "/JoinedEntity.php";
        require_once $dir . "/EntityWithEmbeddable.php";
        require_once $dir . "/NonTypicalPrimaryKeyEntity.php";
        require_once $dir . "/QuirkyFieldName/Association.php";
        require_once $dir . "/QuirkyFieldName/AssociationHost.php";
        require_once $dir . "/QuirkyFieldName/Embeddable.php";
        require_once $dir . "/QuirkyFieldName/EmbeddableHost.php";
        require_once $dir . "/MultilevelRelations/A.php";
        require_once $dir . "/MultilevelRelations/B.php";
        require_once $dir . "/MultilevelRelations/C.php";
        require_once $dir . "/CircularRelations/A.php";
        require_once $dir . "/CircularRelations/B.php";
        require_once $dir . "/CircularRelations/C.php";
        require_once $dir . '/EntityWithUuid.php';

        $this->em = new EntityManager(
            DriverManager::getConnection(['url' => 'sqlite:///:memory:']),
            ORMSetup::createAttributeMetadataConfiguration([$dir], true)
        );

        (new SchemaTool($this->em))->createSchema([
            $this->em->getClassMetadata(CompositePrimaryKeyEntity::class),
            $this->em->getClassMetadata(PlainEntity::class),
            $this->em->getClassMetadata(EntityWithConstructorParameters::class),
            $this->em->getClassMetadata(JoinedEntityBase::class),
            $this->em->getClassMetadata(JoinedEntity::class),
            $this->em->getClassMetadata(EntityWithEmbeddable::class),
            $this->em->getClassMetadata(NonTypicalPrimaryKeyEntity::class),
            $this->em->getClassMetadata(Association::class),
            $this->em->getClassMetadata(AssociationHost::class),
            $this->em->getClassMetadata(Embeddable::class),
            $this->em->getClassMetadata(EmbeddableHost::class),
            $this->em->getClassMetadata(A::class),
            $this->em->getClassMetadata(B::class),
            $this->em->getClassMetadata(C::class),
            $this->em->getClassMetadata(\CircularRelations\A::class),
            $this->em->getClassMetadata(\CircularRelations\B::class),
            $this->em->getClassMetadata(\CircularRelations\C::class),
            $this->em->getClassMetadata(EntityWithUuid::class),
        ]);

        $container = Stub::make(ModuleContainer::class);
        $this->module = new Doctrine2($container, [
            'connection_callback' => fn(): EntityManager => $this->em,
        ]);

        $this->module->_initialize();
        $this->module->_beforeSuite();
    }

    private function _preloadFixtures()
    {
        if (!class_exists(Loader::class)
            || !class_exists(ORMPurger::class)
            || !class_exists(ORMExecutor::class)) {
            $this->markTestSkipped('doctrine/data-fixtures is not installed');
        }

        $dir = __DIR__ . "/../../../data/doctrine2_fixtures";

        require_once $dir . "/TestFixture1.php";
        require_once $dir . "/TestFixture2.php";
    }

    public function testPlainEntity()
    {
        $this->module->dontSeeInRepository(PlainEntity::class, ['name' => 'Test 1']);
        $this->module->haveInRepository(PlainEntity::class, ['name' => 'Test 1']);
        $this->module->seeInRepository(PlainEntity::class, ['name' => 'Test 1']);
    }

    public function testEntityWithConstructorParameters()
    {
        $this->module->dontSeeInRepository(
            EntityWithConstructorParameters::class,
            ['name' => 'Constructor Test 1', 'foo' => 'test', 'bar' => 'foobar']
        );
        $this->module->haveInRepository(
            EntityWithConstructorParameters::class,
            ['name' => 'Constructor Test 1', 'foo' => 'test']
        );
        $this->module->seeInRepository(
            EntityWithConstructorParameters::class,
            ['name' => 'Constructor Test 1', 'foo' => 'test', 'bar' => 'foobar']
        );
    }

    public function testPreconstructedEntityWithConstructorParameters()
    {
        $this->module->dontSeeInRepository(
            EntityWithConstructorParameters::class,
            ['name' => 'Constructor Test 1', 'foo' => 'test', 'bar' => 'foobar']
        );

        $entity = new EntityWithConstructorParameters('Constructor Test 1', 'test');
        $this->module->haveInRepository($entity);

        $this->module->seeInRepository(
            EntityWithConstructorParameters::class,
            ['name' => 'Constructor Test 1', 'foo' => 'test', 'bar' => 'foobar']
        );
    }

    public function testEntityWithConstructorParametersExceptionOnMissingParameter()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Constructor parameter "name" missing');

        $this->module->haveInRepository(EntityWithConstructorParameters::class);
    }

    public function testJoinedEntityOwnField()
    {
        $this->module->dontSeeInRepository(JoinedEntity::class, ['own' => 'Test 1']);
        $this->module->haveInRepository(JoinedEntity::class, ['own' => 'Test 1']);
        $this->module->seeInRepository(JoinedEntity::class, ['own' => 'Test 1']);
    }

    public function testJoinedEntityInheritedField()
    {
        $this->module->dontSeeInRepository(JoinedEntity::class, ['inherited' => 'Test 1']);
        $this->module->haveInRepository(JoinedEntity::class, ['inherited' => 'Test 1']);
        $this->module->seeInRepository(JoinedEntity::class, ['inherited' => 'Test 1']);
    }

    public function testEmbeddable()
    {
        $this->module->dontSeeInRepository(EntityWithEmbeddable::class, ['embed.val' => 'Test 1']);
        $this->module->haveInRepository(EntityWithEmbeddable::class, ['embed.val' => 'Test 1']);
        $this->module->seeInRepository(EntityWithEmbeddable::class, ['embed.val' => 'Test 1']);
    }

    public function testQuirkyAssociationFieldNames()
    {
        // This test case demonstrates how quirky field names can interfere with parameter
        // names generated within Doctrine2. Specifically, parameter name for entity's own field
        // '_assoc_val' clashes with parameter name for field 'val' of relation 'assoc'.

        $this->module->dontSeeInRepository(AssociationHost::class, [
            'assoc' => [
                'val' => 'a',
            ],
            '_assoc_val' => 'b',
        ]);
        $this->module->haveInRepository(AssociationHost::class, [
            'assoc' => $this->module->grabEntityFromRepository(
                Association::class,
                [
                    'id' => $this->module->haveInRepository(Association::class, [
                        'val' => 'a',
                    ]),
                ]
            ),
            '_assoc_val' => 'b',
        ]);
        $this->module->seeInRepository(AssociationHost::class, [
            'assoc' => [
                'val' => 'a',
            ],
            '_assoc_val' => 'b',
        ]);
    }

    public function testQuirkyEmbeddableFieldNames()
    {
        // Same as testQuirkyAssociationFieldNames(), but for embeddables.

        $this->module->dontSeeInRepository(EmbeddableHost::class, [
            'embed.val' => 'a',
            'embedval'  => 'b',
        ]);
        $this->module->haveInRepository(EmbeddableHost::class, [
            'embed.val' => 'a',
            'embedval'  => 'b',
        ]);
        $this->module->seeInRepository(EmbeddableHost::class, [
            'embed.val' => 'a',
            'embedval'  => 'b',
        ]);
    }

    public function testCriteria()
    {
        $this->module->haveInRepository(PlainEntity::class, ['name' => 'Test 1']);
        $this->module->seeInRepository(PlainEntity::class, [
            Criteria::create()->where(
                Criteria::expr()->eq('name', 'Test 1')
            ),
        ]);
        $this->module->seeInRepository(PlainEntity::class, [
            Criteria::create()->where(
                Criteria::expr()->contains('name', 'est')
            ),
        ]);
        $this->module->seeInRepository(PlainEntity::class, [
            Criteria::create()->where(
                Criteria::expr()->in('name', ['Test 1'])
            ),
        ]);
    }

    public function testExpressions()
    {
        $this->module->haveInRepository(PlainEntity::class, ['name' => 'Test 1']);
        $this->module->seeInRepository(PlainEntity::class, [
            Criteria::expr()->eq('name', 'Test 1'),
        ]);
        $this->module->seeInRepository(PlainEntity::class, [
            Criteria::expr()->contains('name', 'est'),
        ]);
        $this->module->seeInRepository(PlainEntity::class, [
            Criteria::expr()->in('name', ['Test 1']),
        ]);
    }

    public function testOrderBy()
    {
        $this->module->haveInRepository(PlainEntity::class, ['name' => 'a']);
        $this->module->haveInRepository(PlainEntity::class, ['name' => 'b']);
        $this->module->haveInRepository(PlainEntity::class, ['name' => 'c']);

        $getName = fn($entity) => $entity->getName();

        $this->assertSame(
            [
                'a',
                'b',
                'c',
            ],
            array_map($getName, $this->module->grabEntitiesFromRepository(PlainEntity::class, [
                Criteria::create()->orderBy(['name' => 'asc']),
            ]))
        );

        $this->assertSame(
            [
                'c',
                'b',
                'a',
            ],
            array_map($getName, $this->module->grabEntitiesFromRepository(PlainEntity::class, [
                Criteria::create()->orderBy(['name' => 'desc']),
            ]))
        );
    }

    public function testSingleFixture()
    {
        $this->_preloadFixtures();
        $this->module->dontSeeInRepository(PlainEntity::class, ['name' => 'from TestFixture1']);
        $this->module->loadFixtures(TestFixture1::class);
        $this->module->seeInRepository(PlainEntity::class, ['name' => 'from TestFixture1']);
    }

    public function testMultipleFixtures()
    {
        $this->_preloadFixtures();
        $this->module->dontSeeInRepository(PlainEntity::class, ['name' => 'from TestFixture1']);
        $this->module->dontSeeInRepository(PlainEntity::class, ['name' => 'from TestFixture2']);
        $this->module->loadFixtures([TestFixture1::class, TestFixture2::class]);
        $this->module->seeInRepository(PlainEntity::class, ['name' => 'from TestFixture1']);
        $this->module->seeInRepository(PlainEntity::class, ['name' => 'from TestFixture2']);
    }

    public function testAppendFixturesMode()
    {
        $this->_preloadFixtures();
        $this->module->dontSeeInRepository(PlainEntity::class, ['name' => 'from TestFixture1']);
        $this->module->dontSeeInRepository(PlainEntity::class, ['name' => 'from TestFixture2']);
        $this->module->loadFixtures([TestFixture1::class]);
        $this->module->seeInRepository(PlainEntity::class, ['name' => 'from TestFixture1']);
        $this->module->dontSeeInRepository(PlainEntity::class, ['name' => 'from TestFixture2']);
        $this->module->loadFixtures([TestFixture2::class]);
        $this->module->seeInRepository(PlainEntity::class, ['name' => 'from TestFixture1']);
        $this->module->seeInRepository(PlainEntity::class, ['name' => 'from TestFixture2']);
    }

    public function testReplaceFixturesMode()
    {
        $this->_preloadFixtures();
        $this->module->dontSeeInRepository(PlainEntity::class, ['name' => 'from TestFixture1']);
        $this->module->dontSeeInRepository(PlainEntity::class, ['name' => 'from TestFixture2']);
        $this->module->loadFixtures([TestFixture1::class]);
        $this->module->seeInRepository(PlainEntity::class, ['name' => 'from TestFixture1']);
        $this->module->dontSeeInRepository(PlainEntity::class, ['name' => 'from TestFixture2']);
        $this->module->loadFixtures([TestFixture2::class], false);
        $this->module->dontSeeInRepository(PlainEntity::class, ['name' => 'from TestFixture1']);
        $this->module->seeInRepository(PlainEntity::class, ['name' => 'from TestFixture2']);
    }

    public function testUnknownFixtureClassName()
    {
        $this->_preloadFixtures();
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageRegExp('/Fixture class ".*" does not exist/');
        // @phpstan-ignore-next-line
        $this->module->loadFixtures('InvalidFixtureClass');
    }

    public function testUnsuitableFixtureClassName()
    {
        $this->_preloadFixtures();
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageRegExp('/Fixture class ".*" does not inherit from/');
        // Somewhat risky, but it's unlikely unit class will
        // ever inherit from a fixture interface:
        // @phpstan-ignore-next-line
        $this->module->loadFixtures(__CLASS__);
    }

    public function testUnsuitableFixtureInstance()
    {
        $this->_preloadFixtures();
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageRegExp('/Fixture ".*" does not inherit from/');
        // @phpstan-ignore-next-line
        $this->module->loadFixtures(new stdClass);
    }

    public function testUnsuitableFixtureType()
    {
        $this->_preloadFixtures();
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageRegExp('/Fixture is expected to be .* got ".*" instead/');
        // @phpstan-ignore-next-line
        $this->module->loadFixtures(1);
    }

    public function testNonTypicalPrimaryKey()
    {
        $primaryKey = $this->module->haveInRepository(NonTypicalPrimaryKeyEntity::class, [
            'primaryKey' => 'abc',
        ]);
        $this->assertSame('abc', $primaryKey);
    }

    public function testCompositePrimaryKey()
    {
        $res = $this->module->haveInRepository(CompositePrimaryKeyEntity::class, [
            'integerPart' => 123,
            'stringPart' => 'abc',
        ]);
        $this->assertSame([123, 'abc'], $res);
    }

    /**
     * The main purpose of this test is, that the debug call at the end of
     * haveInRepository doesn't fail with "var_export does not handle circular references".
     */
    public function testCompositePrimaryKeyWithEntities()
    {
        $a = new \CircularRelations\A();
        $b = new \CircularRelations\B();
        $c = new \CircularRelations\C($a, $b);

        $pks = $this->module->haveInRepository($c);

        $this->assertSame([$a, $b], $pks);
    }

    /**
     * The purpose of this test is to verify that entites with object @id, that are
     * not entites itself, e.g. Ramsey\Uuid\UuidInterface, don't break the debug message.
     */
    public function testDebugEntityWithNonEntityButObjectId()
    {
        $pk = $this->module->haveInRepository(EntityWithUuid::class);

        self::assertInstanceOf(UuidInterface::class, $pk);
    }

    public function testRefresh()
    {
        // We have an entity:
        $original = new PlainEntity;
        $this->module->haveInRepository($original, ['name' => 'a']);
        $id = $original->getId();

        // If we grab entity, original one should be retrieved:
        $this->assertSame($original, $this->module->grabEntityFromRepository(PlainEntity::class, ['id' => $id]));

        // Here comes external change:
        $this->em->getConnection()->executeUpdate('UPDATE PlainEntity SET name = ? WHERE id = ?', ['b', $id]);

        // Our original entity still has old data:
        $this->assertSame('a', $original->getName());

        // Grabbing it again should not work as EntityManager still does not know about external change:
        $grabbed1 = $this->module->grabEntityFromRepository(PlainEntity::class, ['id' => $id]);
        $this->assertSame($original, $grabbed1);
        $this->assertSame('a', $grabbed1->getName());

        // Now we explicitly ask EntityManager invalidate its cache:
        $this->module->refreshEntities($original);

        // Without grabbing, entity should be updated:
        $this->assertSame('b', $original->getName());

        // Grabbing it again should also work:
        $grabbed2 = $this->module->grabEntityFromRepository(PlainEntity::class, ['id' => $id]);
        $this->assertSame($original, $grabbed2);
        $this->assertSame('b', $grabbed2->getName());
    }

    public function testRefreshingMultipleEntities()
    {
        $a = new PlainEntity;
        $this->module->haveInRepository($a, ['name' => 'a']);

        $b = new PlainEntity;
        $this->module->haveInRepository($b, ['name' => 'b']);

        $this->em->getConnection()->executeUpdate('UPDATE PlainEntity SET name = ?', ['c']);

        $this->assertSame('a', $a->getName());
        $this->assertSame('b', $b->getName());

        $this->module->refreshEntities([$a, $b]);

        $this->assertSame('c', $a->getName());
        $this->assertSame('c', $b->getName());
    }

    public function testClear()
    {
        // We have an entity:
        $original = new PlainEntity;
        $this->module->haveInRepository($original);
        $id = $original->getId();

        // If we grab entity, original one should be retrieved:
        $grabbed1 = $this->module->grabEntityFromRepository(PlainEntity::class, ['id' => $id]);
        $this->assertSame($original, $grabbed1);

        $this->module->clearEntityManager();

        // All entities should be detached, grabbing should result in new object:
        $grabbed2 = $this->module->grabEntityFromRepository(PlainEntity::class, ['id' => $id]);
        $this->assertNotSame($original, $grabbed2);
        $this->assertNotSame($grabbed1, $grabbed2);
    }

    public function testManyToOneRecursiveEntityCreation()
    {
        $this->module->haveInRepository(C::class, [
            'name' => 'ccc',
            'b' => [
                'name' => 'bbb',
                'a' => [
                    'name' => 'aaa',
                ],
            ],
        ]);

        $aaa = $this->module->grabEntityFromRepository(A::class, ['name' => 'aaa']);
        $this->assertNotNull($aaa);

        $bbb = $this->module->grabEntityFromRepository(B::class, ['name' => 'bbb']);
        $this->assertNotNull($bbb);

        $ccc = $this->module->grabEntityFromRepository(C::class, ['name' => 'ccc']);
        $this->assertNotNull($ccc);

        $this->assertSame($ccc->getB(), $bbb);
        $this->assertSame($bbb->getA(), $aaa);
        $this->assertSame($ccc->getB()->getA(), $aaa);
    }

    public function testOneToManyRecursiveEntityCreation()
    {
        $this->module->haveInRepository(A::class, [
            'name' => 'aaa',
            'b' => [
                [
                    'name' => 'bbb1',
                ],
                [
                    'name' => 'bbb2',
                    'c' => [
                        [
                            'name' => 'ccc',
                        ],
                    ],
                ],
            ],
        ]);

        $aaa = $this->module->grabEntityFromRepository(A::class, ['name' => 'aaa']);
        $this->assertNotNull($aaa);

        $bbb1 = $this->module->grabEntityFromRepository(B::class, ['name' => 'bbb1']);
        $this->assertNotNull($bbb1);

        $bbb2 = $this->module->grabEntityFromRepository(B::class, ['name' => 'bbb2']);
        $this->assertNotNull($bbb2);

        $ccc = $this->module->grabEntityFromRepository(C::class, ['name' => 'ccc']);
        $this->assertNotNull($ccc);

        $this->assertContains($bbb1, $aaa->getB()->toArray());
        $this->assertContains($bbb2, $aaa->getB()->toArray());
        $this->assertContains($ccc, $bbb2->getC()->toArray());
        $this->assertSame($bbb1->getA(), $aaa);
        $this->assertSame($bbb2->getA(), $aaa);
        $this->assertSame($ccc->getB(), $bbb2);
    }

    public function testHaveFakeRepository()
    {
        $e1 = new PlainEntity();
        $e2 = new PlainEntity();
        $this->module->haveFakeRepository(PlainEntity::class, [
            'findBy' => [$e1, $e2],
        ]);

        $entities = $this->em->getRepository(PlainEntity::class)->findBy(['name' => 'test']);
        $this->assertCount(2, $entities);
        $this->assertContains($e1, $entities);
        $this->assertContains($e2, $entities);
    }
}
