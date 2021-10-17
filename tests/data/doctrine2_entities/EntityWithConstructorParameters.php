<?php

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class EntityWithConstructorParameters
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private int $id;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $foo = null;

    /**
     * @ORM\Column(type="string")
     */
    private string $bar = '';

    public function __construct($name, $foo = null, $bar = 'foobar')
    {
        $this->name = $name;
        $this->foo = $foo;
        $this->bar = $bar;
    }
}
