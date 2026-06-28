<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\TesterAnnotation;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Auto-populates createdBy from the authenticated user on POST.
 */
final class TesterAnnotationStateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security           $security,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof TesterAnnotation && $operation instanceof Post) {
            $user = $this->security->getUser();
            if ($user instanceof User) {
                $data->setCreatedBy($user);
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
