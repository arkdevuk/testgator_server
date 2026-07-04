<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\ApiResource\ProjectStats;
use App\Repository\ProjectRepository;
use App\State\ProjectStateProcessor;
use App\State\ProjectStatsStateProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ApiResource(
    operations: [
        // testers can read, but only projects where they have at least one
        // assigned test plan (see TesterScopeExtension)
        new GetCollection(security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER')"),
        new Get(security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER')"),
        new Get(
            uriTemplate: '/projects/{id}/stats',
            output: ProjectStats::class,
            normalizationContext: [],
            security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER')",
            provider: ProjectStatsStateProvider::class,
            name: 'project_stats',
        ),
        new Post(security: "is_granted('ROLE_USER')", processor: ProjectStateProcessor::class),
        new Put(security: "is_granted('ROLE_USER')", processor: ProjectStateProcessor::class),
        new Patch(security: "is_granted('ROLE_USER')", processor: ProjectStateProcessor::class),
        new Delete(security: "is_granted('ROLE_USER')"),
    ],
    normalizationContext: ['groups' => ['project:read']],
    denormalizationContext: ['groups' => ['project:write']],
    forceEager: false,
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
    #[ORM\OrderBy(['id' => 'ASC'])]
    #[Groups(['project:read'])]
    private Collection $releases;

    #[ORM\ManyToOne]
    #[Groups(['project:read', 'team:write', 'release:read'])]
    private ?File $picture = null;

    /**
     * URL of the project picture (square PNG/JPEG ≤ 800 px, ≤ 500 KB).
     * Set via POST /api/projects/{id}/project-picture — not writable through
     * the standard PATCH body.
     */
    #[ORM\Column(length: 512, nullable: true)]
    #[Groups(['project:read'])]
    private ?string $projectPictureUrl = null;

    /**
     * URL of the project banner (PNG ≤ 1024 px either side, < 1 MB).
     * Set via POST /api/projects/{id}/project-banner — not writable through
     * the standard PATCH body.
     */
    #[ORM\Column(length: 512, nullable: true)]
    #[Groups(['project:read'])]
    private ?string $projectBannerUrl = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'projects')]
    #[ORM\JoinTable(name: 'project_tester')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'tester_id', referencedColumnName: 'id')]
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

    public function getProjectPictureUrl(): ?string
    {
        return $this->projectPictureUrl;
    }

    public function setProjectPictureUrl(?string $projectPictureUrl): static
    {
        $this->projectPictureUrl = $projectPictureUrl;

        return $this;
    }

    public function getProjectBannerUrl(): ?string
    {
        return $this->projectBannerUrl;
    }

    public function setProjectBannerUrl(?string $projectBannerUrl): static
    {
        $this->projectBannerUrl = $projectBannerUrl;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getAllTesters(): Collection
    {
        return $this->allTesters;
    }

    public function addAllTester(User $allTester): static
    {
        if (!$this->allTesters->contains($allTester)) {
            $this->allTesters->add($allTester);
        }

        return $this;
    }

    public function removeAllTester(User $allTester): static
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
