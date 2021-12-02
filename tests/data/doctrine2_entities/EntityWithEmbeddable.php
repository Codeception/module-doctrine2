<?php

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class EntityWithEmbeddable
{
    /**
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private int $id;

    /**
     * @ORM\Embedded(class="SampleEmbeddable")
     */
    private SampleEmbeddable $embed;

    public function __construct()
    {
        $this->embed = new SampleEmbeddable();
    }
}
