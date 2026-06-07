<?php

namespace App\Traits\Entity;

use ApiPlatform\Metadata\ApiProperty;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

trait TimeStampable
{
    #[ApiProperty(readable: true, writable: false, iris: 'https://schema.org/DateTime')]
    #[ORM\Column(name: 'created', type: 'datetime')]
    #[Groups(['timestampable:read'])]
    protected ?DateTimeInterface $created = null;

    #[ApiProperty(readable: true, writable: false, iris: 'https://schema.org/DateTime')]
    #[ORM\Column(name: 'updated', type: 'datetime')]
    #[Groups(['timestampable:read'])]
    protected ?DateTimeInterface $updated = null;

    /**
     * Returns created.
     *
     * @return DateTimeInterface
     */
    public function getCreated(): ?DateTimeInterface
    {
        return $this->created;
    }

    /**
     * Sets created.
     *
     * @return $this
     */
    public function setCreated(DateTimeInterface $created): self
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Returns updated.
     *
     * @return DateTimeInterface
     */
    public function getUpdated(): ?DateTimeInterface
    {
        return $this->updated;
    }

    /**
     * Sets updated.
     *
     * @return $this
     */
    public function setUpdated(DateTimeInterface $updated): self
    {
        $this->updated = $updated;

        return $this;
    }

    public function autoUpdate(): self
    {
        $this->updated = new \DateTime();

        return $this;
    }

    public function setNow(): self
    {
        $now = new \DateTime();
        $this->setCreated($now);
        $this->setUpdated($now);

        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTime();
        if ($this->created === null) {
            $this->created = $now;
        }
        $this->updated = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updated = new \DateTime();
    }
}
