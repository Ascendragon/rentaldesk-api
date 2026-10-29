<?php

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateAssetRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 64, normalizer: 'trim')]
        public string $inventoryCode,

        #[Assert\PositiveOrZero]
        public ?int $purchasePriceKopecks = null,

        #[Assert\Date]
        #[Assert\NotBlank(allowNull: true)]
        public ?string $purchasedAt = null
    )
    {}
}
