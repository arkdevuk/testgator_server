<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\FileRepository;
use App\Traits\Entity\TimeStampable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\UuidV7 as Uuid;

#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ApiResource]
class File
{
    use TimeStampable;
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private ?string $key = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $bucket = null;

    #[ORM\Column(length: 255)]
    private ?string $extension = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $s3uuid = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bucketUrl = null;

    public function __construct(string $name)
    {
        $this->setNow();
        $this->bucket = 'none';
        // set key using time and md5 hash of name
        $this->key = time() . md5($name);
    }

    public function getUrl(): string
    {
        return $_ENV['PUBLIC_URL_BUCKET'] . '/' . $this->getKey() . '.' . $this->getExtension();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function getBucket(): ?string
    {
        return $this->bucket;
    }

    public function setBucket(string $bucket): static
    {
        $this->bucket = $bucket;

        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): static
    {
        $this->extension = $extension;

        return $this;
    }

    public function getS3uuid(): ?string
    {
        return $this->s3uuid;
    }

    public function setS3uuid(?string $s3uuid): static
    {
        $this->s3uuid = $s3uuid;

        return $this;
    }

    public function getBucketUrl(): ?string
    {
        return $this->bucketUrl;
    }

    public function setBucketUrl(?string $bucketUrl): static
    {
        $this->bucketUrl = $bucketUrl;

        return $this;
    }
}
