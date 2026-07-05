<?php

namespace App\Entity;

use DateTimeInterface;
use ApiPlatform\Doctrine\Common\Filter\DateFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
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
use App\Classes\TestPlanState;
use App\Repository\TestPlanRepository;
use App\ApiResource\TestingProgression;
use App\State\TestPlanStateProcessor;
use App\State\TestingProgressionStateProvider;
use App\Traits\Entity\TimeStampable;
use App\Traits\GuidAware;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

// if you remove "forceEager: false," you get the following error:
// The total number of joined relations has exceeded the specified maximum.
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: TestPlanRepository::class)]
#[ApiResource(
    operations: [
        // testers can read, but only plans they are enrolled in
        // (see TesterScopeExtension)
        new GetCollection(security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER')"),
        new Get(security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER')"),
        new Get(
            uriTemplate: '/test_plans/{id}/testing_progression',
            normalizationContext: [],
            security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER')",
            output: TestingProgression::class,
            name: 'test_plan_testing_progression',
            provider: TestingProgressionStateProvider::class,
        ),
        new Post(security: "is_granted('ROLE_USER')", processor: TestPlanStateProcessor::class),
        new Put(security: "is_granted('ROLE_USER')", processor: TestPlanStateProcessor::class),
        new Patch(security: "is_granted('ROLE_USER')", processor: TestPlanStateProcessor::class),
        new Delete(security: "is_granted('ROLE_USER')"),
    ],
    normalizationContext: ['groups' => ['testPlan:read', 'timestampable:read'], 'enable_max_depth' => true],
    denormalizationContext: ['groups' => ['testPlan:write'], 'enable_max_depth' => true],
    forceEager: false,
)]
#[ApiFilter(SearchFilter::class, properties: [
    'release.project' => 'exact',
    'testersEnrolled' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'release', 'state', 'dueDate'], arguments: ['orderParameterName' => 'order'])]
class TestPlan
{

    use GuidAware;
    use TimeStampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['testPlan:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['testPlan:read', 'team:write'])]
    #[ApiFilter(SearchFilter::class, strategy: 'ipartial')]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['testPlan:read', 'team:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Groups(['none:read'])]
    private ?string $key = null;

    #[ORM\Column(length: 255)]
    #[Groups(['testPlan:read', 'team:write'])]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    private ?string $state = TestPlanState::DRAFT;

    #[ORM\ManyToOne(inversedBy: 'plans')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['testPlan:read', 'team:write'])]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    private ?Release $release = null;

    #[ORM\Column]
    #[Groups(['testPlan:read', 'team:write'])]
    private array $questionsOrder = [];

    /**
     * @var Collection<int, Question>
     */
    #[ORM\OneToMany(targetEntity: Question::class, mappedBy: 'plan')]
    #[Groups(['testPlan:read', 'testPlan:write'])]
    private Collection $questions;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'test_plan_tester')]
    #[ORM\JoinColumn(name: 'test_plan_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'tester_id', referencedColumnName: 'id')]
    #[Groups(['testPlan:read', 'testPlan:write'])]
    private Collection $testersEnrolled;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    #[Groups(['testPlan:read', 'testPlan:write'])]
    #[ApiFilter(DateFilter::class, strategy: DateFilterInterface::EXCLUDE_NULL)]
    private ?DateTimeInterface $dueDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['testPlan:read', 'testPlan:write'])]
    private ?string $content = null;

    public function __construct()
    {
        $this->questions = new ArrayCollection();
        $this->key = $this->generateHumanHash(128);
        $this->setNow();
        $this->testersEnrolled = new ArrayCollection();
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

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getRelease(): ?Release
    {
        return $this->release;
    }

    public function setRelease(?Release $release): static
    {
        $this->release = $release;

        return $this;
    }

    public function getQuestionsOrder(): array
    {
        return $this->questionsOrder;
    }

    public function setQuestionsOrder(array $questionsOrder): static
    {
        $this->questionsOrder = $questionsOrder;

        return $this;
    }

    /**
     * @return Collection<int, Question>
     */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(Question $question): static
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setPlan($this);
        }

        return $this;
    }

    public function removeQuestion(Question $question): static
    {
        // set the owning side to null (unless already changed)
        if ($this->questions->removeElement($question) && $question->getPlan() === $this) {
            $question->setPlan(null);
        }

        return $this;
    }

    #[Groups(['testPlan:read'])]
    public function getTotalQuestions(): int
    {
        return $this->questions->count();
    }

    /**
     * @return Collection<int, User>
     */
    public function getTestersEnrolled(): Collection
    {
        return $this->testersEnrolled;
    }

    public function addTestersEnrolled(User $testersEnrolled): static
    {
        if (!$this->testersEnrolled->contains($testersEnrolled)) {
            $this->testersEnrolled->add($testersEnrolled);
            $this->getRelease()?->getProject()?->addAllTester($testersEnrolled);
        }

        return $this;
    }

    public function removeTestersEnrolled(User $testersEnrolled): static
    {
        $this->testersEnrolled->removeElement($testersEnrolled);

        return $this;
    }

    #[Groups(['testPlan:read'])]
    public function getTotalTestersEnrolled(): int
    {
        return $this->testersEnrolled->count();
    }

    public function getDueDate(): ?DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(DateTimeInterface $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }
}
