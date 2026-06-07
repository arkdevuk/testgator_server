<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Enum\AnswerState;
use App\Filter\AnswerQueryFilter;
use App\Repository\AnswerRepository;
use App\Traits\Entity\TimeStampable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Tester;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: AnswerRepository::class)]
#[ApiResource]
#[ApiFilter(DateFilter::class, properties: ['created'])]
#[ApiFilter(AnswerQueryFilter::class)]
#[ApiFilter(OrderFilter::class, properties: ['created', 'state'], arguments: ['orderParameterName' => 'order'])]
class Answer
{
    use TimeStampable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tester::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    private ?Tester $tester = null;

    #[ORM\Column(nullable: true)]
    private ?array $systemInfos = null;

    #[ORM\Column(type: 'string', enumType: AnswerState::class, length: 20)]
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
    #[ORM\JoinColumn(nullable: false)]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    private ?Question $question = null;

    public function __construct()
    {
        $this->files = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTester(): ?Tester
    {
        return $this->tester;
    }

    public function setTester(?Tester $tester): static
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
}
