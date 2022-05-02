<?php declare(strict_types=1);

namespace App\Model;

use App\Model\City;
use Carbon\Carbon;
use JMS\Serializer\Annotation as JMS;

/**
 * @JMS\ExclusionPolicy("all")
 */
class Ride
{
    /**
     * @JMS\Expose
     */
    protected ?int $id = null;

    protected ?string $cityName = null;

    /**
     * @JMS\Expose
     */
    protected ?City $city = null;

    /**
     * @JMS\Expose
     */
    protected ?string $slug = null;

    /**
     * @JMS\Expose
     */
    protected ?string $title = null;

    /**
     * @JMS\Expose
     */
    protected ?string $description = null;

    /**
     * @JMS\Expose
     * @JMS\Type("Carbon<'U'>")
     */
    protected ?Carbon $dateTime = null;

    /**
     * @JMS\Expose
     */
    protected ?string $location = null;

    /**
     * @JMS\Expose
     */
    protected ?float $latitude = null;

    /**
     * @JMS\Expose
     */
    protected ?float $longitude = null;

    /**
     * @JMS\Expose
     * @JMS\Type("Carbon<'U'>")
     */
    protected ?Carbon $createdAt = null;

    /**
     * @JMS\Expose
     * @JMS\Type("Carbon<'U'>")
     */
    protected ?Carbon $updatedAt = null;

    /**
     * @JMS\Expose
     */
    protected ?string $rideType = null;

    public function __construct()
    {
        $this->dateTime = new Carbon();
        $this->createdAt = new Carbon();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setCityName(string $cityName = null): self
    {
        $this->cityName = $cityName;

        return $this;
    }

    public function getCityName(): ?string
    {
        return $this->cityName;

    }

    public function setCity(City $city = null): self
    {
        $this->city = $city;

        return $this;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function setDateTime(Carbon $dateTime = null): self
    {
        $this->dateTime = $dateTime;

        return $this;
    }

    public function getDateTime(): ?Carbon
    {
        return $this->dateTime;
    }

    public function hasDateTime(): bool
    {
        return $this->dateTime !== null;
    }

    public function setLocation(string $location = null): self
    {
        $this->location = $location;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLatitude(float $latitude = null): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLongitude(float $longitude = null): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setSlug(string $slug = null): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function hasSlug(): bool
    {
        return $this->slug !== null;
    }

    public function setTitle(string $title = null): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getRideType(): ?string
    {
        return $this->rideType;
    }

    public function setRideType(string $rideType): self
    {
        $this->rideType = $rideType;

        return $this;
    }
}
