<?php

declare(strict_types=1);

namespace AltDTO\Tests\Fixtures;

use AltDTO\Collection;
use AltDTO\DTO;

final class UserDTO extends DTO
{
    public int $id;

    public string $name;

    public ?\DateTimeImmutable $createdAt = null;

    /** @var AddressDTO */
    public AddressDTO $address;

    /** @var list<RoleDTO> */
    public Collection $roles;

    public Status $status;
}
