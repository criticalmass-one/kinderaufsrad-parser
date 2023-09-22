<?php declare(strict_types=1);

namespace App\Model;

class CitySlug
{
    protected int $id;

    protected string $slug;

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug = null): self
    {
        $this->slug = $slug;

        return $this;
    }
}
