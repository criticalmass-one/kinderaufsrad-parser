<?php declare(strict_types=1);

namespace App\Model;

class City
{
    protected ?int $id = null;
    protected ?string $name = null;
    protected ?CitySlug $mainSlug = null;
    protected string $timezone = 'Europe/Berlin';
    protected float $latitude;
    protected float $longitude;

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

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

    public function setLatitude(float $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLongitude(float $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }
}
