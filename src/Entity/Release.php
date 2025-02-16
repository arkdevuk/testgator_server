<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Repository\ReleaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ReleaseRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['release:read']],
    denormalizationContext: ['groups' => ['release:write']],
)]
#[ApiFilter(OrderFilter::class, properties: ['id', 'name', 'project'], arguments: ['orderParameterName' => 'order'])]
class Release
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['release:read', 'team:write', 'testPlan:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['release:read', 'team:write', 'testPlan:read'])]
    #[ApiFilter(SearchFilter::class, strategy: 'partial')]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'releases')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['release:read', 'team:write', 'testPlan:read'])]
    #[ApiFilter(SearchFilter::class, strategy: 'exact')]
    private ?Project $project = null;

    /**
     * @var Collection<int, TestPlan>
     */
    #[ORM\OneToMany(targetEntity: TestPlan::class, mappedBy: 'release')]
    #[Groups(['release:read', 'team:write'])]
    private Collection $plans;

    public function __construct()
    {
        $this->plans = new ArrayCollection();
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

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    /**
     * @return Collection<int, TestPlan>
     */
    public function getPlans(): Collection
    {
        return $this->plans;
    }

    public function addPlan(TestPlan $plan): static
    {
        if (!$this->plans->contains($plan)) {
            $this->plans->add($plan);
            $plan->setRelease($this);
        }

        return $this;
    }

    public function removePlan(TestPlan $plan): static
    {
        if ($this->plans->removeElement($plan)) {
            // set the owning side to null (unless already changed)
            if ($plan->getRelease() === $this) {
                $plan->setRelease(null);
            }
        }

        return $this;
    }
}
