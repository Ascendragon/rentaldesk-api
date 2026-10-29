<?php

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateInventoryItemRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max:128)]
        public string $name,

        #[Assert\PositiveOrZero]
        public int $stock,

    ){}
}
