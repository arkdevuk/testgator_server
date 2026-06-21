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
use App\Repository\QuestionRepository;
use App\State\QuestionStateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(processor: QuestionStateProcessor::class),
        new Put(processor: QuestionStateProcessor::class),
        new Patch(processor: QuestionStateProcessor::class),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['question:read'], 'enable_max_depth' => true],
    denormalizationContext: ['groups' => ['question:write'], 'enable_max_depth' => true],
    forceEager: false,
)]
#[ApiFilter(OrderFilter::class, properties: ['displayOrder', 'id', 'plan'], arguments: ['orderParameterName' => 'order'])]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['question:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['question:read', 'team:write'])]
    #[ApiFilter(SearchFilter::class, strategy: 'ipartial')]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['question:read', 'team:write'])]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    private ?TestPlan $plan = null;

    /**
     * @var Collection<int, Answer>
     */
    #[ORM\OneToMany(targetEntity: Answer::class, mappedBy: 'question')]
    #[Groups(['question:read', 'team:write'])]
    private Collection $answers;

    /**
     * @var Collection<int, File>
     */
    #[ORM\ManyToMany(targetEntity: File::class)]
    #[Groups(['question:read', 'team:write'])]
    private Collection $files;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['question:read', 'team:write'])]
    private ?string $content = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['question:read', 'team:write'])]
    private ?int $displayOrder = null;

    public function __construct()
    {
        $this->answers = new ArrayCollection();
        $this->files = new ArrayCollection();
        $this->displayOrder = 0;
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

    public function getPlan(): ?TestPlan
    {
        return $this->plan;
    }

    public function setPlan(?TestPlan $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    /**
     * @return Collection<int, Answer>
     */
    public function getAnswers(): Collection
    {
        return $this->answers;
    }

    public function addAnswer(Answer $answer): static
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
            $answer->setQuestion($this);
        }

        return $this;
    }

    public function removeAnswer(Answer $answer): static
    {
        if ($this->answers->removeElement($answer)) {
            // set the owning side to null (unless already changed)
            if ($answer->getQuestion() === $this) {
                $answer->setQuestion(null);
            }
        }

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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): static
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }
}
