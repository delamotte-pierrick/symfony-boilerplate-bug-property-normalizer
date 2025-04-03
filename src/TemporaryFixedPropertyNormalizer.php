<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\PropertyAccess\Exception\InvalidTypeException;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer as SymfonyPropertyNormalizer;

class TemporaryFixedPropertyNormalizer extends AbstractObjectNormalizer
{
    private \ReflectionClass $propertyNormalizerReflectionClass;
    private SymfonyPropertyNormalizer $propertyNormalizer;

    /**
     * @param array<string, mixed> $defaultContext
     */
    public function __construct(
        ?ClassMetadataFactoryInterface $classMetadataFactory = null,
        ?NameConverterInterface $nameConverter = null,
        ?PropertyTypeExtractorInterface $propertyTypeExtractor = null,
        ?ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null,
        ?callable $objectClassResolver = null,
        array $defaultContext = [],
    ) {
        $this->propertyNormalizer = new SymfonyPropertyNormalizer(
            $classMetadataFactory,
            $nameConverter,
            $propertyTypeExtractor,
            $classDiscriminatorResolver,
            $objectClassResolver,
            $defaultContext,
        );
        $this->propertyNormalizerReflectionClass = new \ReflectionClass($this->propertyNormalizer);

        parent::__construct(
            $classMetadataFactory,
            $nameConverter,
            $propertyTypeExtractor,
            $classDiscriminatorResolver,
            $objectClassResolver,
            $defaultContext,
        );
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->propertyNormalizer->getSupportedTypes($format);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<mixed>
     *
     * @throws \ReflectionException
     */
    protected function extractAttributes(object $object, ?string $format = null, array $context = []): array
    {
        $reflectionMethod = $this->propertyNormalizerReflectionClass->getMethod('extractAttributes');

        return $reflectionMethod->invoke($this->propertyNormalizer, $object, $format, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function getAttributeValue(object $object, string $attribute, ?string $format = null, array $context = []): mixed
    {
        $reflectionMethod = $this->propertyNormalizerReflectionClass->getMethod('getAttributeValue');

        return $reflectionMethod->invoke($this->propertyNormalizer, $object, $attribute, $format, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function setAttributeValue(object $object, string $attribute, mixed $value, ?string $format = null, array $context = []): void
    {
        try {
            $objectReflexion = new \ReflectionClass($object);
            $property = $objectReflexion->getProperty($attribute);
        } catch (\ReflectionException) {
            return;
        }

        try {
            /** @throws \TypeError */
            $reflectionMethod = $this->propertyNormalizerReflectionClass->getMethod('setAttributeValue');
            $reflectionMethod->invoke($this->propertyNormalizer, $object, $attribute, $value, $format, $context);
        } catch (\TypeError $e) {
            throw new InvalidTypeException(
                (string) $property->getType(),
                \gettype($value),
                $attribute,
                $e,
            );
        }
    }
}
