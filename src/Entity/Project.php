<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['project:read']],
    denormalizationContext: ['groups' => ['project:write']],
)]
#[ApiFilter(OrderFilter::class, properties: ['id', 'project', 'name'], arguments: ['orderParameterName' => 'order'])]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['project:read', 'release:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['project:read', 'team:write', 'release:read'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['project:read', 'team:write'])]
    private ?string $description = null;

    /**
     * @var Collection<int, Release>
     */
    #[ORM\OneToMany(targetEntity: Release::class, mappedBy: 'project')]
    #[Groups(['project:read'])]
    private Collection $releases;

    #[ORM\ManyToOne]
    #[Groups(['project:read', 'team:write', 'release:read'])]
    private ?File $picture = null;

    /**
     * @var Collection<int, Tester>
     */
    #[ORM\ManyToMany(targetEntity: Tester::class, inversedBy: 'projects')]
    #[Groups(['project:read', 'team:write'])]
    private Collection $allTesters;

    public function __construct()
    {
        $this->releases = new ArrayCollection();
        $this->allTesters = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }


    #[Groups(['project:read'])]
    public function getTotalReleases(): int
    {
        return $this->releases->count();
    }

    /**
     * @return Collection<int, Release>
     */
    public function getReleases(): Collection
    {
        return $this->releases;
    }

    public function addRelease(Release $release): static
    {
        if (!$this->releases->contains($release)) {
            $this->releases->add($release);
            $release->setProject($this);
        }

        return $this;
    }

    public function removeRelease(Release $release): static
    {
        if ($this->releases->removeElement($release)) {
            // set the owning side to null (unless already changed)
            if ($release->getProject() === $this) {
                $release->setProject(null);
            }
        }

        return $this;
    }

    public function getPicture(): ?File
    {
        return $this->picture;
    }

    public function setPicture(?File $picture): static
    {
        $this->picture = $picture;

        return $this;
    }

    /**
     * @return Collection<int, Tester>
     */
    public function getAllTesters(): Collection
    {
        return $this->allTesters;
    }

    public function addAllTester(Tester $allTester): static
    {
        if (!$this->allTesters->contains($allTester)) {
            $this->allTesters->add($allTester);
        }

        return $this;
    }

    public function removeAllTester(Tester $allTester): static
    {
        $this->allTesters->removeElement($allTester);

        return $this;
    }

    #[Groups(['project:read'])]
    public function getTotalTesters(): int
    {
        return $this->allTesters->count();
    }

    #[Groups(['project:read'])]
    public function getLatestRelease(): ?array
    {
        $latest = $this->releases->last();
        if ($latest instanceof Release) {
            return [
                'id' => $latest->getId(),
                'name' => $latest->getName(),
            ];
        }
        return null;
    }
}
