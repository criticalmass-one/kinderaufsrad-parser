<?php declare(strict_types=1);

namespace App\Model;

use Symfony\Component\Serializer\Annotation\Ignore;

class City
{
    protected ?int $id = null;

    protected ?string $name = null;

    #[Ignore]
    protected ?CitySlug $mainSlug = null;

    protected string $timezone = 'Europe/Berlin';

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getMainSlug(): ?CitySlug
    {
        return $this->mainSlug;
    }

    public function setMainSlug(CitySlug $citySlug): self
    {
        $this->mainSlug = $citySlug;

        return $this;
    }

    public function setTimezone(string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }
}
