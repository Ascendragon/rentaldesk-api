<?php
declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateInventoryItemRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max:128)]
        public string $name,

        #[Assert\PositiveOrZero]
        #[Assert\Range(min: 0, max: 2_147_483_647)]
        public int $stock,

        #[Assert\PositiveOrZero]
        #[Assert\Range(min: 0, max: 2_147_483_647)]
        public int $dailyRateKopecks = 0
    ){}
}
