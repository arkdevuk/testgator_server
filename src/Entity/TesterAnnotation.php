<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\TesterAnnotationRepository;
use App\State\TesterAnnotationStateProcessor;
use App\Traits\Entity\TimeStampable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\UuidV7 as Uuid;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: TesterAnnotationRepository::class)]
#[ORM\Table(name: 'tester_annotation')]
#[ApiResource(
    shortName: 'TesterAnnotation',
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_USER')", processor: TesterAnnotationStateProcessor::class),
        new Patch(security: "is_granted('ROLE_USER') and (is_granted('ROLE_ADMIN') or object.getCreatedBy() == user)"),
        new Delete(security: "is_granted('ROLE_USER') and (is_granted('ROLE_ADMIN') or object.getCreatedBy() == user)"),
    ],
    normalizationContext: ['groups' => ['tester_annotation:read', 'timestampable:read']],
    denormalizationContext: ['groups' => ['tester_annotation:write']],
    forceEager: false,
)]
#[ApiFilter(SearchFilter::class, properties: ['relateTo' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['created', 'updated'], arguments: ['orderParameterName' => 'order'])]
class TesterAnnotation
{
    use TimeStampable;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['tester_annotation:read'])]
    public ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'relate_to_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['tester_annotation:read', 'tester_annotation:write'])]
    private ?User $relateTo = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['tester_annotation:read', 'tester_annotation:write'])]
    private string $content = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['tester_annotation:read'])]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->setNow();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getRelateTo(): ?User
    {
        return $this->relateTo;
    }

    public function setRelateTo(User $relateTo): static
    {
        $this->relateTo = $relateTo;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }
}
