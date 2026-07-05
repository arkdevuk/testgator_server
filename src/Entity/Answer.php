<?php

declare(strict_types=1);

namespace App\Entity;

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
use App\Enum\AnswerState;
use App\Filter\AnswerQueryFilter;
use App\Repository\AnswerRepository;
use App\State\AnswerStateProcessor;
use App\Traits\Entity\TimeStampable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: AnswerRepository::class)]
#[ApiResource(
    operations: [
        // testers only ever see their own answers (see TesterScopeExtension);
        // a tester PATCHing someone else's answer 404s through the same scoping
        new GetCollection(security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER')"),
        new Get(security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER')"),
        // answer.tester is forced to the current user for testers
        new Post(
            security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER')",
            securityPostDenormalize: "object.getQuestion() != null and object.getQuestion().getPlan() != null and object.getQuestion().getPlan().getState() != 'archived'",
            processor: AnswerStateProcessor::class,
        ),
        // testers cannot reassign the answer or move it to another question
        new Put(
            security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER')",
            securityPostDenormalize: "(is_granted('ROLE_USER') or (object.getTester()?.getId() == previous_object.getTester()?.getId() and object.getQuestion()?.getId() == previous_object.getQuestion()?.getId())) and object.getQuestion() != null and object.getQuestion().getPlan() != null and object.getQuestion().getPlan().getState() != 'archived'",
            processor: AnswerStateProcessor::class,
        ),
        new Patch(
            security: "is_granted('ROLE_USER') or is_granted('ROLE_TESTER')",
            securityPostDenormalize: "(is_granted('ROLE_USER') or (object.getTester()?.getId() == previous_object.getTester()?.getId() and object.getQuestion()?.getId() == previous_object.getQuestion()?.getId())) and object.getQuestion() != null and object.getQuestion().getPlan() != null and object.getQuestion().getPlan().getState() != 'archived'",
            processor: AnswerStateProcessor::class,
        ),
        new Delete(security: "is_granted('ROLE_USER')"),
    ],
    forceEager: false,
)]
#[ApiFilter(DateFilter::class, properties: ['created'])]
#[ApiFilter(AnswerQueryFilter::class)]
#[ApiFilter(SearchFilter::class, properties: ['question.plan' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['created', 'state'], arguments: ['orderParameterName' => 'order'])]
class Answer
{
    use TimeStampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'tester_id', nullable: true, onDelete: 'SET NULL')]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    private ?User $tester = null;

    #[ORM\Column(nullable: true)]
    private ?array $systemInfos = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: AnswerState::class)]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    private AnswerState $state = AnswerState::PENDING;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $comment = null;

    /**
     * @var Collection<int, File>
     */
    #[ORM\ManyToMany(targetEntity: File::class)]
    private Collection $files;

    #[ORM\ManyToOne(inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    private ?Question $question = null;

    #[ORM\Column(options: ['default' => false])]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    private bool $important = false;

    /**
     * When true, this answer is excluded from statistics and reporting.
     * Can only be set on an existing answer (not on POST) and only by ROLE_USER (dev team).
     * Testers always have this reset to its previous value via AnswerStateProcessor.
     */
    #[ORM\Column(options: ['default' => false])]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    private bool $ignored = false;

    public function __construct()
    {
        $this->files = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTester(): ?User
    {
        return $this->tester;
    }

    public function setTester(?User $tester): static
    {
        $this->tester = $tester;

        return $this;
    }

    public function getSystemInfos(): ?array
    {
        return $this->systemInfos;
    }

    public function setSystemInfos(?array $systemInfos): static
    {
        $this->systemInfos = $systemInfos;

        return $this;
    }

    public function getState(): AnswerState
    {
        return $this->state;
    }

    public function setState(AnswerState $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @return Collection<int, File>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(File $file): static
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
        }

        return $this;
    }

    public function removeFile(File $file): static
    {
        $this->files->removeElement($file);

        return $this;
    }

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function setQuestion(?Question $question): static
    {
        $this->question = $question;

        return $this;
    }

    public function isImportant(): bool
    {
        return $this->important;
    }

    public function setImportant(bool $important): static
    {
        $this->important = $important;

        return $this;
    }

    public function isIgnored(): bool
    {
        return $this->ignored;
    }

    public function setIgnored(bool $ignored): static
    {
        $this->ignored = $ignored;

        return $this;
    }
}
