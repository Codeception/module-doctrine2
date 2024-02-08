<?php

namespace QuirkyFieldName;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class EmbeddableHost
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Embedded(class: Embeddable::class)]
    private Embeddable $embed;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $embedval = null;

    public function __construct()
    {
        $this->embed = new Embeddable;
    }
}
