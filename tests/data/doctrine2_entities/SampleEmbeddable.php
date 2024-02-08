<?php

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class SampleEmbeddable
{
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $val = null;
}
