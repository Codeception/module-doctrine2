<?php

namespace CircularRelations;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="circular_a")
 */
class A
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private ?int $id = null;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="C", mappedBy="a")
     */
    private Collection $cs;

    public function __construct()
    {
        $this->cs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCs(): ArrayCollection
    {
        return $this->cs;
    }
}
