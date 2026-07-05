<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\TesterTagRepository;
use App\State\TesterTagStateProcessor;
use App\Traits\Entity\TimeStampable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A named tag that the dev team can attach to tester accounts.
 *
 * The `id` is the slug derived from `label` (lowercase, hyphens).
 * Soft-delete: DELETE sets `deleted = true` rather than removing the row.
 */
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: TesterTagRepository::class)]
#[ORM\Table(name: 'tester_tag')]
#[ApiResource(
    shortName: 'TesterTag',
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER')"),
        new Get(
            requirements: ['id' => '[a-z0-9_-]+'],
            security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER')",
        ),
        new Post(
            security: "is_granted('ROLE_USER')",
            processor: TesterTagStateProcessor::class,
        ),
        new Delete(
            requirements: ['id' => '[a-z0-9_-]+'],
            security: "is_granted('ROLE_USER')",
            processor: TesterTagStateProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['tester_tag:read', 'timestampable:read']],
    denormalizationContext: ['groups' => ['tester_tag:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['label' => 'partial'])]
class TesterTag
{
    use TimeStampable;

    /**
     * Slug derived from `label` — e.g. "My Tag" → "my-tag".
     * Set automatically in setLabel(); can also be supplied directly.
     */
    #[ORM\Id]
    #[ORM\Column(length: 128)]
    #[Groups(['tester_tag:read', 'tester_tag:write'])]
    private string $id = '';

    #[ORM\Column(length: 128)]
    #[Groups(['tester_tag:read', 'tester_tag:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    private string $label = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['tester_tag:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['tester_tag:read'])]
    private bool $deleted = false;

    public function __construct()
    {
        $this->setNow();
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Allows the slug to be set explicitly (e.g. from a state processor).
     */
    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        // Auto-derive the slug if not already set.
        if ($this->id === '') {
            $this->id = self::slugify($label);
        }

        return $this;
    }

    public static function slugify(string $text): string
    {
        // Transliterate non-ASCII, lowercase, replace non-alphanumeric with hyphens.
        $slug = mb_strtolower(trim($text));
        $slug = (string)preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
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

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function setDeleted(bool $deleted): static
    {
        $this->deleted = $deleted;

        return $this;
    }
}
