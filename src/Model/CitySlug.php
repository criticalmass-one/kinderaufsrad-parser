<?php declare(strict_types=1);

namespace App\Model;

use JMS\Serializer\Annotation as JMS;

/**
 * @JMS\ExclusionPolicy("all")
 */
class CitySlug
{
    /**
     * @JMS\Expose
     */
    protected int $id;

    /**
     * @JMS\Expose
     */
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
