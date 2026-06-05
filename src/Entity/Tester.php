<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Filter\TesterTestingPlanFilter;
use App\Repository\TesterRepository;
use App\Traits\Entity\TimeStampable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\UuidV7 as Uuid;

#[ORM\Entity(repositoryClass: TesterRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['testers:read'], 'enable_max_depth' => true],
    denormalizationContext: ['groups' => ['testers:write']],
)]
#[ApiFilter(OrderFilter::class, properties: ['id', 'email', 'active'], arguments: ['orderParameterName' => 'order'])]
#[ApiFilter(TesterTestingPlanFilter::class, properties: ['email'])]
class Tester implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimeStampable;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['testers:read'])]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    public ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['testers:read', 'testers:write', 'team:write'])]
    #[ApiFilter(SearchFilter::class, strategy: 'partial')]
    private ?string $email = null;

    #[ORM\Column]
    #[Groups(['testers:read', 'team:write'])]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    private ?bool $active = null;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'allTesters')]
    #[Groups(['testers:read', 'team:write'])]
    private Collection $projects;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['none:read'])]
    private ?string $otp = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['none:read'])]
    private ?int $otpTry = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['none:read'])]
    private ?\DateTimeInterface $otpDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['testers:read'])]
    private ?\DateTimeInterface $lastActive = null;

    public function __construct(
        string $email,
    )
    {
        $this->email = $email;
        $this->active = true;
        $this->setNow();
        $this->projects = new ArrayCollection();
        $this->otpDate = new \DateTime();
        $this->otpTry = 0;
    }

    #[Groups(['testers:read'])]
    public function getActiveProjects(): int
    {
        return $this->projects->count();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getPassword(): ?string
    {
        return null;
    }

    public function getRoles(): array
    {
        return ['ROLE_TESTER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return (string)$this->email;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): static
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->addAllTester($this);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects->removeElement($project)) {
            $project->removeAllTester($this);
        }

        return $this;
    }

    public function getOtp(): ?string
    {
        return $this->otp;
    }

    public function setOtp(?string $otp): static
    {
        $this->otp = $otp;

        return $this;
    }

    public function getOtpTry(): ?int
    {
        return $this->otpTry;
    }

    public function setOtpTry(int $otpTry): static
    {
        $this->otpTry = $otpTry;

        return $this;
    }

    public function getOtpDate(): ?\DateTimeInterface
    {
        return $this->otpDate;
    }

    public function setOtpDate(?\DateTimeInterface $otpDate): static
    {
        $this->otpDate = $otpDate;

        return $this;
    }

    public function getLastActive(): ?\DateTimeInterface
    {
        return $this->lastActive;
    }

    public function setLastActive(?\DateTimeInterface $lastActive): static
    {
        $this->lastActive = $lastActive;

        return $this;
    }
}
