<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\SettingsRepository;
use App\Traits\Entity\TimeStampable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SettingsRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        // Any authenticated user can read; non-admins are limited to public=true
        // rows automatically by TesterScopeExtension (applied at query level).
        new GetCollection(security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER') or is_granted('ROLE_ADMIN')"),
        new Get(requirements: ['id' => '.+'], security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER') or is_granted('ROLE_ADMIN')"),
        // Write access: ROLE_ADMIN only
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Patch(requirements: ['id' => '.+'], security: "is_granted('ROLE_ADMIN')"),
        new Delete(requirements: ['id' => '.+'], security: "is_granted('ROLE_ADMIN')"),
    ],
    normalizationContext: ['groups' => ['settings:read', 'timestampable:read']],
    denormalizationContext: ['groups' => ['settings:write']],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'ipartial',
    'section' => 'ipartial',
])]
class Settings
{
    use TimeStampable;

    /**
     * Composite key: "{section}.{name}" — e.g. "general.webhook_url"
     */
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    #[Groups(['settings:read'])]
    private string $id = '';

    #[ORM\Column(length: 100)]
    #[Groups(['settings:read', 'settings:write'])]
    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^[a-z0-9][a-z0-9_-]*$/',
        message: 'Section must be lowercase alphanumeric and may only contain hyphens and underscores.',
    )]
    private string $section = '';

    #[ORM\Column(length: 100)]
    #[Groups(['settings:read', 'settings:write'])]
    #[Assert\NotBlank]
    #[Assert\Regex(
        pattern: '/^[a-z0-9][a-z0-9_-]*$/',
        message: 'Name must be lowercase alphanumeric and may only contain hyphens and underscores.',
    )]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['settings:read', 'settings:write'])]
    private ?string $value = null;

    #[ORM\Column]
    #[Groups(['settings:read', 'settings:write'])]
    private bool $autoload = false;

    #[ORM\Column(name: 'is_public')]
    #[Groups(['settings:read', 'settings:write'])]
    private bool $public = false;

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): string
    {
        return $this->id;
    }

    public function getSection(): string
    {
        return $this->section;
    }

    public function setSection(string $section): static
    {
        $this->section = $section;
        $this->refreshId();

        return $this;
    }

    private function refreshId(): void
    {
        if ($this->section !== '' && $this->name !== '') {
            $this->id = $this->section . '.' . $this->name;
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        $this->refreshId();

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function isAutoload(): bool
    {
        return $this->autoload;
    }

    public function setAutoload(bool $autoload): static
    {
        $this->autoload = $autoload;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): static
    {
        $this->public = $public;

        return $this;
    }
}
