<?php
declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Locker;
use Finite\StateMachine;
use Finite\Transition\TransitionInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

final readonly class LockerTransitionsNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface, NormalizerAwareInterface, DenormalizerAwareInterface
{
    public function __construct(
        #[AutowireDecorated]
        private NormalizerInterface&DenormalizerInterface $decorated,
        private StateMachine                              $stateMachine,
    )
    {
    }

    #[\Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $normalized = $this->decorated->normalize($data, $format, $context);

        if ($data instanceof Locker && is_array($normalized)) {
            $normalized['transitions'] = array_values(array_map(
                static fn (TransitionInterface $transition): string => $transition->getName(),
                $this->stateMachine->getReachablesTransitions($data),
            ));
        }

        return $normalized;
    }

    #[\Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->decorated->supportsNormalization($data, $format, $context);
    }

    #[\Override]
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        return $this->decorated->denormalize($data, $type, $format, $context);
    }

    #[\Override]
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $this->decorated->supportsDenormalization($data, $type, $format, $context);
    }

    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return $this->decorated->getSupportedTypes($format);
    }

    #[\Override]
    public function setSerializer(SerializerInterface $serializer): void
    {
        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }

    #[\Override]
    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        if ($this->decorated instanceof NormalizerAwareInterface) {
            $this->decorated->setNormalizer($normalizer);
        }
    }

    #[\Override]
    public function setDenormalizer(DenormalizerInterface $denormalizer): void
    {
        if ($this->decorated instanceof DenormalizerAwareInterface) {
            $this->decorated->setDenormalizer($denormalizer);
        }
    }
}
