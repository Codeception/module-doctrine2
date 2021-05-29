<?php

declare(strict_types=1);

namespace Codeception\Module;

use Codeception\Exception\ModuleConfigException;
use Codeception\Exception\ModuleException;
use Codeception\Exception\ModuleRequireException;
use Codeception\Lib\Interfaces\DataMapper;
use Codeception\Lib\Interfaces\DependsOnModule;
use Codeception\Lib\Interfaces\DoctrineProvider;
use Codeception\Module as CodeceptionModule;
use Codeception\Stub;
use Codeception\TestInterface;
use Codeception\Util\ReflectionPropertyAccessor;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Expression;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Exception;
use InvalidArgumentException;
use PDOException;
use ReflectionClass;
use ReflectionException;
use function array_merge;
use function get_class;
use function gettype;
use function is_array;
use function is_object;
use function is_string;
use function sprintf;
use function var_export;

/**
 * Access the database using [Doctrine2 ORM](http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/).
 *
 * When used with Symfony or Zend Framework 2, Doctrine's Entity Manager is automatically retrieved from Service Locator.
 * Set up your `functional.suite.yml` like this:
 *
 * ```yaml
 * modules:
 *     enabled:
 *         - Symfony # 'ZF2' or 'Symfony'
 *         - Doctrine2:
 *             depends: Symfony # Tells Doctrine to fetch the Entity Manager through Symfony
 *             cleanup: true # All doctrine queries will be wrapped in a transaction, which will be rolled back at the end of each test
 * ```
 *
 * If you don't provide a `depends` key, you need to specify a callback function to retrieve the Entity Manager:
 *
 * ```yaml
 * modules:
 *     enabled:
 *         - Doctrine2:
 *             connection_callback: ['MyDb', 'createEntityManager'] # Call the static method `MyDb::createEntityManager()` to get the Entity Manager
 * ```
 *
 * By default, the module will wrap everything into a transaction for each test and roll it back afterwards
 * (this is controlled by the `cleanup` setting).
 * By doing this, tests will run much faster and will be isolated from each other.
 *
 * To use the Doctrine2 Module in acceptance tests, set up your `acceptance.suite.yml` like this:
 *
 * ```yaml
 * modules:
 *     enabled:
 *         - Symfony:
 *             part: SERVICES
 *         - Doctrine2:
 *             depends: Symfony
 * ```
 *
 * You cannot use `cleanup: true` in an acceptance test, since Codeception and your app (i.e. browser) are using two
 * different connections to the database, so Codeception can't wrap changes made by the app into a transaction.
 *
 * Set the SQL statement that Doctrine fixtures ([`doctrine/data-fixtures`](https://github.com/doctrine/data-fixtures))
 * are using to purge the database tables:
 * ```yaml
 * modules:
 *     enabled:
 *         - Doctrine2:
 *             purge_mode: 1 # 1: DELETE (=default), 2: TRUNCATE
 * ```
 *
 * ## Status
 *
 * * Maintainer: **davert**
 * * Stability: **stable**
 * * Contact: codecept@davert.mail.ua
 *
 * ## Config
 *
 * ## Public Properties
 *
 * * `em` - Entity Manager
 *
 * ## Note on parameters
 *
 * Every method that expects some parameters to be checked against values in the database (`see...()`,
 * `dontSee...()`, `grab...()`) can accept instance of
 * [\Doctrine\Common\Collections\Criteria](https://www.doctrine-project.org/api/collections/latest/Doctrine/Common/Collections/Criteria.html)
 * for more flexibility, e.g.:
 *
 * ```php
 * $I->seeInRepository(User::class, [
 *     'name' => 'John',
 *     Criteria::create()->where(
 *         Criteria::expr()->endsWith('email', '@domain.com')
 *     ),
 * ]);
 * ```
 *
 * If criteria is just a `->where(...)` construct, you can pass just expression without criteria wrapper:
 *
 * ```php
 * $I->seeInRepository(User::class, [
 *     'name' => 'John',
 *     Criteria::expr()->endsWith('email', '@domain.com'),
 * ]);
 * ```
 *
 * Criteria can be used not only to filter data, but also to change the order of results:
 *
 * ```php
 * $I->grabEntitiesFromRepository('User', [
 *     'status' => 'active',
 *     Criteria::create()->orderBy(['name' => 'asc']),
 * ]);
 * ```
 *
 * Note that key is ignored, because actual field name is part of criteria and/or expression.
 */

class Doctrine2 extends CodeceptionModule implements DependsOnModule, DataMapper
{

    protected $config = [
        'cleanup' => true,
        'connection_callback' => false,
        'depends' => null,
        'purge_mode' => 1, // ORMPurger::PURGE_MODE_DELETE
    ];

    protected $dependencyMessage = <<<EOF
Provide connection_callback function to establish database connection and get Entity Manager:

modules:
    enabled:
        - Doctrine2:
            connection_callback: [My\ConnectionClass, getEntityManager]

Or set a dependent module, which can be either Symfony or ZF2 to get EM from service locator:

modules:
    enabled:
        - Doctrine2:
            depends: Symfony
EOF;

    /**
     * @var EntityManagerInterface
     */
    public $em = null;

    /**
     * @var DoctrineProvider
     */
    private $dependentModule;

    public function _depends(): array
    {
        if ($this->config['connection_callback']) {
            return [];
        }
        return ['Codeception\Lib\Interfaces\DoctrineProvider' => $this->dependencyMessage];
    }

    public function _inject(DoctrineProvider $dependentModule = null)
    {
        $this->dependentModule = $dependentModule;
    }

    public function _beforeSuite($settings = [])
    {
        $this->retrieveEntityManager();
    }

    public function _before(TestInterface $test)
    {
        $this->retrieveEntityManager();

        if ($this->config['cleanup']) {
            if ($this->em->getConnection()->isTransactionActive()) {
                try {
                    while ($this->em->getConnection()->getTransactionNestingLevel() > 0) {
                        $this->em->getConnection()->rollback();
                    }
                    $this->debugSection('Database', 'Transaction cancelled; all changes reverted.');
                } catch (PDOException $e) {
                }
            }

            $this->em->getConnection()->beginTransaction();
            $this->debugSection('Database', 'Transaction started');
        }
    }

    public function onReconfigure()
    {
        if (!$this->em instanceof EntityManagerInterface) {
            return;
        }
        if ($this->config['cleanup'] && $this->em->getConnection()->isTransactionActive()) {
            try {
                $this->em->getConnection()->rollback();
                $this->debugSection('Database', 'Transaction cancelled; all changes reverted.');
            } catch (PDOException $e) {
            }
        }
        $this->clean();
        $this->em->getConnection()->close();

        $this->retrieveEntityManager();
        if ($this->config['cleanup']) {
            if ($this->em->getConnection()->isTransactionActive()) {
                try {
                    while ($this->em->getConnection()->getTransactionNestingLevel() > 0) {
                        $this->em->getConnection()->rollback();
                    }
                    $this->debugSection('Database', 'Transaction cancelled; all changes reverted.');
                } catch (PDOException $e) {
                }
            }

            $this->em->getConnection()->beginTransaction();
            $this->debugSection('Database', 'Transaction started');
        }
    }

    protected function retrieveEntityManager(): void
    {
        if ($this->dependentModule) {
            $this->em = $this->dependentModule->_getEntityManager();
        } else {
            if (is_callable($this->config['connection_callback'])) {
                $this->em = call_user_func($this->config['connection_callback']);
            }
        }

        if (!$this->em) {
            throw new ModuleConfigException(
                __CLASS__,
                "EntityManager can't be obtained.\n \n"
                . "Please specify either `connection_callback` config option\n"
                . "with callable which will return instance of EntityManager or\n"
                . "pass a dependent module which are Symfony or ZF2\n"
                . "to connect to Doctrine using Dependency Injection Container"
            );
        }


        if (!($this->em instanceof EntityManagerInterface)) {
            throw new ModuleConfigException(
                __CLASS__,
                "Connection object is not an instance of \\Doctrine\\ORM\\EntityManagerInterface.\n"
                . "Use `connection_callback` or dependent framework modules to specify one"
            );
        }

        $this->em->getConnection()->connect();
    }

    public function _after(TestInterface $test)
    {
        if (!$this->em instanceof EntityManagerInterface) {
            return;
        }
        if ($this->config['cleanup'] && $this->em->getConnection()->isTransactionActive()) {
            try {
                while ($this->em->getConnection()->getTransactionNestingLevel() > 0) {
                    $this->em->getConnection()->rollback();
                }
                $this->debugSection('Database', 'Transaction cancelled; all changes reverted.');
            } catch (PDOException $e) {
            }
        }
        $this->clean();
        $this->em->getConnection()->close();
    }

    protected function clean(): void
    {
        $em = $this->em;

        $reflectedEm = new ReflectionClass($em);
        if ($reflectedEm->hasProperty('repositories')) {
            $property = $reflectedEm->getProperty('repositories');
            $property->setAccessible(true);
            $property->setValue($em, []);
        }
        $this->em->clear();
    }

    /**
     * Performs $em->flush();
     */
    public function flushToDatabase(): void
    {
        $this->em->flush();
    }

    /**
     * Performs $em->refresh() on every passed entity:
     *
     * ``` php
     * $I->refreshEntities($user);
     * $I->refreshEntities([$post1, $post2, $post3]]);
     * ```
     *
     * This can useful in acceptance tests where entity can become invalid due to
     * external (relative to entity manager used in tests) changes.
     *
     * @param object|object[] $entities
     */
    public function refreshEntities($entities): void
    {
        if (!is_array($entities)) {
            $entities = [$entities];
        }

        foreach ($entities as $entity) {
            $this->em->refresh($entity);
        }
    }

    /**
     * Performs $em->clear():
     *
     * ``` php
     * $I->clearEntityManager();
     * ```
     */
    public function clearEntityManager(): void
    {
        $this->em->clear();
    }

    /**
     * Mocks the repository.
     *
     * With this action you can redefine any method of any repository.
     * Please, note: this fake repositories will be accessible through entity manager till the end of test.
     *
     * Example:
     *
     * ``` php
     * <?php
     *
     * $I->haveFakeRepository(User::class, ['findByUsername' => function($username) { return null; }]);
     *
     * ```
     *
     * This creates a stub class for Entity\User repository with redefined method findByUsername,
     * which will always return the NULL value.
     *
     */
    public function haveFakeRepository(string $className, array $methods = []): void
    {
        $em = $this->em;

        $metadata = $em->getMetadataFactory()->getMetadataFor($className);
        $customRepositoryClassName = $metadata->customRepositoryClassName;

        if (!$customRepositoryClassName) {
            $customRepositoryClassName = '\Doctrine\ORM\EntityRepository';
        }

        $mock = Stub::make(
            $customRepositoryClassName,
            array_merge(
                [
                    '_entityName' => $metadata->name,
                    '_em' => $em,
                    '_class' => $metadata
                ],
                $methods
            )
        );

        $em->clear();
        $reflectedEm = new ReflectionClass($em);


        if ($reflectedEm->hasProperty('repositories')) {
            //Support doctrine versions before 2.4.0

            $property = $reflectedEm->getProperty('repositories');
            $property->setAccessible(true);
            $property->setValue($em, array_merge($property->getValue($em), [$className => $mock]));
        } elseif ($reflectedEm->hasProperty('repositoryFactory')) {
            //For doctrine 2.4.0+ versions

            $repositoryFactoryProperty = $reflectedEm->getProperty('repositoryFactory');
            $repositoryFactoryProperty->setAccessible(true);
            $repositoryFactory = $repositoryFactoryProperty->getValue($em);

            $reflectedRepositoryFactory = new ReflectionClass($repositoryFactory);

            if ($reflectedRepositoryFactory->hasProperty('repositoryList')) {
                $repositoryListProperty = $reflectedRepositoryFactory->getProperty('repositoryList');
                $repositoryListProperty->setAccessible(true);

                $repositoryListProperty->setValue(
                    $repositoryFactory,
                    [$className => $mock]
                );

                $repositoryFactoryProperty->setValue($em, $repositoryFactory);
            } else {
                $this->debugSection(
                    'Warning',
                    'Repository can\'t be mocked, the EventManager\'s repositoryFactory doesn\'t have "repositoryList" property'
                );
            }
        } else {
            $this->debugSection(
                'Warning',
                'Repository can\'t be mocked, the EventManager class doesn\'t have "repositoryFactory" or "repositories" property'
            );
        }
    }

    /**
     * Persists a record into the repository.
     * This method creates an entity, and sets its properties directly (via reflection).
     * Setters of the entity won't be executed, but you can create almost any entity and save it to the database.
     * If the entity has a constructor, for optional parameters the default value will be used and for non-optional parameters the given fields (with a matching name) will be passed when calling the constructor before the properties get set directly (via reflection).
     *
     * Returns the primary key of the newly created entity. The primary key value is extracted using Reflection API.
     * If the primary key is composite, an array of values is returned.
     *
     * ```php
     * $I->haveInRepository(User::class, ['name' => 'davert']);
     * ```
     *
     * This method also accepts instances as first argument, which is useful when the entity constructor
     * has some arguments:
     *
     * ```php
     * $I->haveInRepository(new User($arg), ['name' => 'davert']);
     * ```
     *
     * Alternatively, constructor arguments can be passed by name. Given User constructor signature is `__constructor($arg)`, the example above could be rewritten like this:
     *
     * ```php
     * $I->haveInRepository(User::class, ['arg' => $arg, 'name' => 'davert']);
     * ```
     *
     * If the entity has relations, they can be populated too. In case of
     * [OneToMany](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/association-mapping.html#one-to-many-bidirectional)
     * the following format is expected:
     *
     * ```php
     * $I->haveInRepository(User::class, [
     *     'name' => 'davert',
     *     'posts' => [
     *         ['title' => 'Post 1'],
     *         ['title' => 'Post 2'],
     *     ],
     * ]);
     * ```
     *
     * For [ManyToOne](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/association-mapping.html#many-to-one-unidirectional)
     * the format is slightly different:
     *
     * ```php
     * $I->haveInRepository(User::class, [
     *     'name' => 'davert',
     *     'post' => [
     *         'title' => 'Post 1',
     *     ],
     * ]);
     * ```
     *
     * This works recursively, so you can create deep structures in a single call.
     *
     * Note that `$em->persist()`, `$em->refresh()`, and `$em->flush()` are called every time.
     *
     * @param string|object $classNameOrInstance
     * @param array $data
     * @return mixed
     */
    public function haveInRepository($classNameOrInstance, array $data = [])
    {
        // Here we'll have array of all instances (including any relations) created:
        $instances = [];

        // Create and/or populate main instance and gather all created relations:
        if (is_object($classNameOrInstance)) {
            $instance = $this->populateEntity($classNameOrInstance, $data, $instances);
        } elseif (is_string($classNameOrInstance)) {
            $instance = $this->instantiateAndPopulateEntity($classNameOrInstance, $data, $instances);
        } else {
            throw new InvalidArgumentException(sprintf('Doctrine2::haveInRepository expects a class name or instance as first argument, got "%s" instead', gettype($classNameOrInstance)));
        }

        // Flush all changes to database and then refresh all entities. We need this because
        // currently all assignments are done via Reflection API without using setters, which means
        // all OneToMany relations won't get set properly as real setter method would use some
        // Collection operation.
        $this->em->flush();
        $this->refreshEntities($instances);

        $pk = $this->extractPrimaryKey($instance);

        $this->debugEntityCreation($instance, $pk);

        return $pk;
    }

    private function instantiateAndPopulateEntity(string $className, array $data, array &$instances): object
    {
        $rpa = new ReflectionPropertyAccessor();
        list($scalars,$relations) = $this->splitScalarsAndRelations($className, $data);
        // Pass relations that are already objects to the constructor, too
        $properties = array_merge(
            $scalars,
            array_filter($relations, function ($relation) {
                return is_object($relation);
            })
        );
        $instance = $rpa->createWithProperties($className, $properties);
        $this->populateEntity($instance, $data, $instances);
        return $instance;
    }

    private function populateEntity(object $instance, array $data, array &$instances): object
    {
        $rpa = new ReflectionPropertyAccessor();
        $className = get_class($instance);
        $instances[] = $instance;
        list($scalars, $relations) = $this->splitScalarsAndRelations($className, $data);
        $rpa->setProperties(
            $instance,
            array_merge(
                $scalars,
                $this->instantiateRelations($className, $instance, $relations, $instances)
            )
        );
        $this->populateEmbeddables($instance, $data);
        $this->em->persist($instance);
        return $instance;
    }

    private function splitScalarsAndRelations(string $className, array $data): array
    {
        $scalars = [];
        $relations = [];

        $metadata = $this->em->getClassMetadata($className);

        foreach ($data as $field => $value) {
            if ($metadata->hasAssociation($field)) {
                $relations[$field] = $value;
            } else {
                $scalars[$field] = $value;
            }
        }

        return [$scalars, $relations];
    }

    private function instantiateRelations(string $className, object $master, array $data, array &$instances): array
    {
        $metadata = $this->em->getClassMetadata($className);

        foreach ($data as $field => $value) {
            if (is_array($value) && $metadata->hasAssociation($field)) {
                unset($data[$field]);
                if ($metadata->isCollectionValuedAssociation($field)) {
                    foreach ($value as $subvalue) {
                        if (!is_array($subvalue)) {
                            throw new InvalidArgumentException('Association "' . $field . '" of entity "' . $className . '" requires array as input, got "' . gettype($subvalue) . '" instead"');
                        }
                        $instance = $this->instantiateAndPopulateEntity(
                            $metadata->getAssociationTargetClass($field),
                            array_merge($subvalue, [
                                $metadata->getAssociationMappedByTargetField($field) => $master,
                            ]),
                            $instances
                        );
                        $instances[] = $instance;
                    }
                } else {
                    $instance = $this->instantiateAndPopulateEntity(
                        $metadata->getAssociationTargetClass($field),
                        $value,
                        $instances
                    );
                    $instances[] = $instance;
                    $data[$field] = $instance;
                }
            }
        }

        return $data;
    }

    /**
     * @param object $instance
     * @return array|mixed
     * @throws ReflectionException
     */
    private function extractPrimaryKey(object $instance)
    {
        $className = get_class($instance);
        $metadata = $this->em->getClassMetadata($className);
        $rpa = new ReflectionPropertyAccessor();
        if ($metadata->isIdentifierComposite) {
            $pk = [];
            foreach ($metadata->identifier as $field) {
                $pk[] = $rpa->getProperty($instance, $field);
            }
        } else {
            $pk = $rpa->getProperty($instance, $metadata->identifier[0]);
        }
        return $pk;
    }

    /**
     * Loads fixtures. Fixture can be specified as a fully qualified class name,
     * an instance, or an array of class names/instances.
     *
     * ```php
     * <?php
     * $I->loadFixtures(AppFixtures::class);
     * $I->loadFixtures([AppFixtures1::class, AppFixtures2::class]);
     * $I->loadFixtures(new AppFixtures);
     * ```
     *
     * By default fixtures are loaded in 'append' mode. To replace all
     * data in database, use `false` as second parameter:
     *
     * ```php
     * <?php
     * $I->loadFixtures(AppFixtures::class, false);
     * ```
     *
     * This method requires [`doctrine/data-fixtures`](https://github.com/doctrine/data-fixtures) to be installed.
     *
     * @param string|string[]|object[] $fixtures
     * @param bool $append
     * @throws ModuleException
     * @throws ModuleRequireException
     */
    public function loadFixtures($fixtures, bool $append = true): void
    {
        if (!class_exists(Loader::class)
            || !class_exists(ORMPurger::class)
            || !class_exists(ORMExecutor::class)) {
            throw new ModuleRequireException(
                __CLASS__,
                'Doctrine fixtures support in unavailable.\n'
                . 'Please, install doctrine/data-fixtures.'
            );
        }

        if (!is_array($fixtures)) {
            $fixtures = [$fixtures];
        }

        $loader = new Loader();

        foreach ($fixtures as $fixture) {
            if (is_string($fixture)) {
                if (!class_exists($fixture)) {
                    throw new ModuleException(
                        __CLASS__,
                        sprintf(
                            'Fixture class "%s" does not exist',
                            $fixture
                        )
                    );
                }

                if (!is_a($fixture, FixtureInterface::class, true)) {
                    throw new ModuleException(
                        __CLASS__,
                        sprintf(
                            'Fixture class "%s" does not inherit from "%s"',
                            $fixture,
                            FixtureInterface::class
                        )
                    );
                }

                try {
                    $fixtureInstance = new $fixture;
                } catch (Exception $e) {
                    throw new ModuleException(
                        __CLASS__,
                        sprintf(
                            'Fixture class "%s" could not be loaded, got %s%s',
                            $fixture,
                            get_class($e),
                            empty($e->getMessage()) ? '' : ': ' . $e->getMessage()
                        )
                    );
                }
            } elseif (is_object($fixture)) {
                if (!$fixture instanceof FixtureInterface) {
                    throw new ModuleException(
                        __CLASS__,
                        sprintf(
                            'Fixture "%s" does not inherit from "%s"',
                            get_class($fixture),
                            FixtureInterface::class
                        )
                    );
                }

                $fixtureInstance = $fixture;
            } else {
                throw new ModuleException(
                    __CLASS__,
                    sprintf(
                        'Fixture is expected to be an instance or class name, inherited from "%s"; got "%s" instead',
                        FixtureInterface::class,
                        is_object($fixture) ? get_class($fixture) ? is_string($fixture) : $fixture : gettype($fixture)
                    )
                );
            }

            try {
                $loader->addFixture($fixtureInstance);
            } catch (Exception $e) {
                throw new ModuleException(
                    __CLASS__,
                    sprintf(
                        'Fixture class "%s" could not be loaded, got %s%s',
                        get_class($fixtureInstance),
                        get_class($e),
                        empty($e->getMessage()) ? '' : ': ' . $e->getMessage()
                    )
                );
            }
        }

        try {
            $purger = new ORMPurger($this->em);
            $purger->setPurgeMode($this->config['purge_mode']);
            $executor = new ORMExecutor($this->em, $purger);
            $executor->execute($loader->getFixtures(), $append);
        } catch (Exception $e) {
            throw new ModuleException(
                __CLASS__,
                sprintf(
                    'Fixtures could not be loaded, got %s%s',
                    get_class($e),
                    empty($e->getMessage()) ? '' : ': ' . $e->getMessage()
                )
            );
        }
    }

    /**
     * Entity can have embeddable as a field, in which case $data argument of persistEntity() and haveInRepository()
     * could contain keys like {field}.{subField}, where {field} is name of entity's embeddable field, and {subField}
     * is embeddable's field.
     *
     * This method checks if entity has embeddables, and if data have keys as described above, and then uses
     * Reflection API to set values.
     *
     * See https://www.doctrine-project.org/projects/doctrine-orm/en/current/tutorials/embeddables.html for
     * details about this Doctrine feature.
     *
     * @throws ReflectionException
     */
    private function populateEmbeddables(object $entityObject, array $data): void
    {
        $rpa = new ReflectionPropertyAccessor();
        $metadata = $this->em->getClassMetadata(get_class($entityObject));
        foreach (array_keys($metadata->embeddedClasses) as $embeddedField) {
            $embeddedData = [];
            foreach ($data as $entityField => $value) {
                $parts = explode('.', $entityField, 2);
                if (count($parts) === 2 && $parts[0] === $embeddedField) {
                    $embeddedData[$parts[1]] = $value;
                }
            }
            if ($embeddedData) {
                $rpa->setProperties($rpa->getProperty($entityObject, $embeddedField), $embeddedData);
            }
        }
    }

    /**
     * Flushes changes to database, and executes a query with parameters defined in an array.
     * You can use entity associations to build complex queries.
     *
     * Example:
     *
     * ``` php
     * <?php
     * $I->seeInRepository(User::class, ['name' => 'davert']);
     * $I->seeInRepository(User::class, ['name' => 'davert', 'Company' => ['name' => 'Codegyre']]);
     * $I->seeInRepository(Client::class, ['User' => ['Company' => ['name' => 'Codegyre']]]);
     * ```
     *
     * Fails if record for given criteria can\'t be found,
     *
     * @param class-string $entity
     * @param array $params
     */
    public function seeInRepository(string $entity, array $params = []): void
    {
        $res = $this->proceedSeeInRepository($entity, $params);
        $this->assert($res);
    }

    /**
     * Flushes changes to database and performs `findOneBy()` call for current repository.
     *
     * @param class-string $entity
     * @param array $params
     */
    public function dontSeeInRepository(string $entity, array $params = []): void
    {
        $res = $this->proceedSeeInRepository($entity, $params);
        $this->assertNot($res);
    }

    protected function proceedSeeInRepository(string $entity, array $params = []): array
    {
        // we need to store to database...
        $this->em->flush();
        $qb = $this->em->getRepository($entity)->createQueryBuilder('s');
        $this->buildAssociationQuery($qb, $entity, 's', $params);
        $this->debug($qb->getDQL());
        $res = $qb->getQuery()->getArrayResult();

        return ['True', (count($res) > 0), "$entity with " . json_encode($params, JSON_THROW_ON_ERROR)];
    }

    /**
     * Selects field value from repository.
     * It builds query based on array of parameters.
     * You can use entity associations to build complex queries.
     *
     * Example:
     *
     * ``` php
     * <?php
     * $email = $I->grabFromRepository(User::class, 'email', ['name' => 'davert']);
     * ```
     *
     * @return mixed
     * @version 1.1
     */
    public function grabFromRepository(string $entity, string $field, array $params = [])
    {
        // we need to store to database...
        $this->em->flush();
        $qb = $this->em->getRepository($entity)->createQueryBuilder('s');
        $qb->select('s.' . $field);
        $this->buildAssociationQuery($qb, $entity, 's', $params);
        $this->debug($qb->getDQL());
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Selects entities from repository.
     * It builds query based on array of parameters.
     * You can use entity associations to build complex queries.
     *
     * Example:
     *
     * ``` php
     * <?php
     * $users = $I->grabEntitiesFromRepository(User::class, ['name' => 'davert']);
     * ```
     *
     * @param string $entity
     * @param array $params. For `IS NULL`, use `['field' => null]`
     * @return array
     * @version 1.1
     */
    public function grabEntitiesFromRepository(string $entity, array $params = []): array
    {
        // we need to store to database...
        $this->em->flush();
        $qb = $this->em->getRepository($entity)->createQueryBuilder('s');
        $qb->select('s');
        $this->buildAssociationQuery($qb, $entity, 's', $params);
        $this->debug($qb->getDQL());

        return $qb->getQuery()->getResult();
    }

    /**
     * Selects a single entity from repository.
     * It builds query based on array of parameters.
     * You can use entity associations to build complex queries.
     *
     * Example:
     *
     * ``` php
     * <?php
     * $user = $I->grabEntityFromRepository(User::class, ['id' => '1234']);
     * ```
     *
     * @param class-string $entity
     * @param array $params. For `IS NULL`, use `['field' => null]`
     * @return object
     * @version 1.1
     */
    public function grabEntityFromRepository(string $entity, array $params = []): object
    {
        // we need to store to database...
        $this->em->flush();
        $qb = $this->em->getRepository($entity)->createQueryBuilder('s');
        $qb->select('s');
        $this->buildAssociationQuery($qb, $entity, 's', $params);
        $this->debug($qb->getDQL());

        return $qb->getQuery()->getSingleResult();
    }

    protected function buildAssociationQuery(QueryBuilder $qb, string $assoc, string $alias, array $params): void
    {
        $paramIndex = 0;
        $this->_buildAssociationQuery($qb, $assoc, $alias, $params, $paramIndex);
    }

    protected function _buildAssociationQuery(QueryBuilder $qb, string $assoc, string $alias, array $params, int &$paramIndex): void
    {
        $data = $this->em->getClassMetadata($assoc);
        foreach ($params as $key => $val) {
            if (isset($data->associationMappings)) {
                if (array_key_exists($key, $data->associationMappings)) {
                    $map = $data->associationMappings[$key];
                    if (is_array($val)) {
                        $qb->innerJoin("$alias.$key", "${alias}__$key");
                        $this->_buildAssociationQuery($qb, $map['targetEntity'], "${alias}__$key", $val, $paramIndex);
                        continue;
                    }
                }
            }
            if ($val === null) {
                $qb->andWhere("$alias.$key IS NULL");
            } elseif ($val instanceof Criteria) {
                $qb->addCriteria($val);
            } elseif ($val instanceof Expression) {
                $qb->addCriteria(Criteria::create()->where($val));
            } else {
                $qb->andWhere("$alias.$key = ?$paramIndex");
                $qb->setParameter($paramIndex, $val);
                $paramIndex++;
            }
        }
    }

    public function _getEntityManager(): EntityManagerInterface
    {
        if (is_null($this->em)) {
            $this->retrieveEntityManager();
        }
        return $this->em;
    }

    private function debugEntityCreation($instance, $pks)
    {
        $message = get_class($instance).' entity created with ';

        if (!is_array($pks)) {
            $pks     = [$pks];
            $message .= 'primary key ';
        } else {
            $message .= 'composite primary key of ';
        }

        foreach ($pks as $pk) {
            if ($this->isDoctrineEntity($pk)) {
                $message .= get_class($pk).': '.var_export($this->extractPrimaryKey($pk), true).', ';
            } else {
                $message .= var_export($pk, true).', ';
            }
        }

        $this->debug(trim($message, ' ,'));
    }

    private function isDoctrineEntity($pk): bool
    {
        $isEntity = is_object($pk);

        if ($isEntity) {
            try {
                $this->em->getClassMetadata(get_class($pk));
            } catch (\Doctrine\ORM\Mapping\MappingException $ex) {
                $isEntity = false;
            } catch (\Doctrine\Common\Persistence\Mapping\MappingException $ex) {
                $isEntity = false;
            } catch (\Doctrine\Persistence\Mapping\MappingException $ex) {
                $isEntity = false;
            }
        }

        return $isEntity;
    }
}
