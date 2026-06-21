<?php

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
use ApiPlatform\Metadata\Put;
use App\Enum\UserType;
use App\Filter\TesterTestingPlanFilter;
use App\Repository\UserRepository;
use App\State\TesterStateProcessor;
use App\Traits\Entity\TimeStampable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\UuidV7 as Uuid;

/**
 * Single account entity. Team members and testers are differentiated
 * by the $type field (UserType::USER | UserType::TESTER).
 *
 * The ApiResource is exposed under the "Tester" short name so the
 * existing /api/testers endpoints keep working for the React client.
 * The TesterTypeExtension automatically restricts API queries to
 * type = TESTER, and TesterStateProcessor forces type = TESTER on write.
 */
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`users`')]
#[ORM\Index(name: 'IDX_USERS_TYPE', columns: ['type'])]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ApiResource(
    shortName: 'Tester',
    operations: [
        // team only: testers cannot list or view other testers
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_USER')"),
        new Put(security: "is_granted('ROLE_USER')"),
        new Patch(security: "is_granted('ROLE_USER')"),
        new Delete(security: "is_granted('ROLE_USER')"),
    ],
    normalizationContext: ['groups' => ['testers:read'], 'enable_max_depth' => true],
    denormalizationContext: ['groups' => ['testers:write']],
    processor: TesterStateProcessor::class,
    forceEager: false,
)]
#[ApiFilter(OrderFilter::class, properties: ['id', 'email', 'active'], arguments: ['orderParameterName' => 'order'])]
#[ApiFilter(TesterTestingPlanFilter::class, properties: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimeStampable;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['testers:read'])]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    public ?Uuid $id = null;

    #[ORM\Column(length: 180)]
    #[Groups(['testers:read', 'testers:write', 'team:write'])]
    #[ApiFilter(SearchFilter::class, strategy: 'partial')]
    private ?string $email = null;

    #[ORM\Column(type: 'string', enumType: UserType::class, length: 20, options: ['default' => 'USER'])]
    #[Groups(['testers:read'])]
    private UserType $type = UserType::USER;

    /**
     * @var list<string> The user roles (only used for type = USER)
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string|null The hashed password (null for testers)
     */
    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    /**
     * @var string : app|ldap
     */
    #[ORM\Column(length: 255, options: ['default' => 'app'])]
    private string $src = 'app';

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['testers:read', 'team:write'])]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    private ?bool $active = true;

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

    public function __construct()
    {
        $this->src = 'app';
        $this->type = UserType::USER;
        $this->active = true;
        $this->projects = new ArrayCollection();
        $this->otpDate = new \DateTime();
        $this->otpTry = 0;
        $this->setNow();
    }

    public static function createTester(string $email): self
    {
        $tester = new self();
        $tester->setEmail($email);
        $tester->setType(UserType::TESTER);

        return $tester;
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

    public function getType(): UserType
    {
        return $this->type;
    }

    public function setType(UserType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isTester(): bool
    {
        return $this->type === UserType::TESTER;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string)$this->email;
    }

    /**
     * @return list<string>
     * @see UserInterface
     *
     */
    public function getRoles(): array
    {
        if ($this->type === UserType::TESTER) {
            return ['ROLE_TESTER'];
        }

        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getSrc(): string
    {
        return $this->src;
    }

    public function setSrc(string $src): static
    {
        $this->src = $src;

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

    #[Groups(['testers:read'])]
    public function getActiveProjects(): int
    {
        return $this->projects->count();
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
