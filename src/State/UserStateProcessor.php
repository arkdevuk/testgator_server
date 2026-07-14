<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Enum\UserType;
use App\Event\DevCreatedAppEvent;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Processor for the /api/users resource (team accounts).
 * - Forces type = USER on every write.
 * - Hashes plainPassword when provided (required on POST, optional on PATCH).
 */
final readonly class UserStateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private UserPasswordHasherInterface $hasher,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof User) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $data->setType(UserType::USER);

        $plain = $data->getPlainPassword();

        if ($operation instanceof Post && ($plain === null || $plain === '')) {
            throw new InvalidArgumentException('plainPassword is required when creating a user.');
        }

        if ($plain !== null && $plain !== '') {
            $data->setPassword($this->hasher->hashPassword($data, $plain));
            // Clear the transient plain password (eraseCredentials() is deprecated since Symfony 7.3).
            $data->setPlainPassword(null);
        }

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($operation instanceof Post && $result instanceof User) {
            $this->dispatcher->dispatch(new DevCreatedAppEvent($result));
        }

        return $result;
    }
}
