<?php

namespace CircularRelations;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="circular_c")
 */
class C
{
    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="A", inversedBy="cs", cascade={"persist"})
     */
    private ?A $a;

    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="B", inversedBy="cs", cascade={"persist"})
     */
    private ?B $b;

    public function __construct(A $a, B $b)
    {
        $this->a = $a;
        $this->b = $b;
    }
}
