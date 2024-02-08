<?php

namespace MultilevelRelations;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class A
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $name = null;

    /**
     * @var Collection|B[]
     */
    #[ORM\OneToMany(targetEntity: B::class, mappedBy: 'a')]
    private Collection $b;

    public function __construct()
    {
        $this->b = new ArrayCollection();
    }

    /**
     * @return Collection|B[]
     */
    public function getB(): Collection
    {
        return $this->b;
    }
}
