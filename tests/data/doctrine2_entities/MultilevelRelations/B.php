<?php

namespace MultilevelRelations;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class B
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
     * @ORM\ManyToOne(targetEntity="A")
     */
    private ?A $a = null;

    /**
     * @var Collection|C[]
     *
     * @ORM\OneToMany(targetEntity="C", mappedBy="b")
     */
    private Collection $c;

    public function __construct()
    {
        $this->c = new ArrayCollection();
    }

    public function getA(): ?A
    {
        return $this->a;
    }

    /**
     * @return Collection|C[]
     */
    public function getC(): Collection
    {
        return $this->c;
    }
}
