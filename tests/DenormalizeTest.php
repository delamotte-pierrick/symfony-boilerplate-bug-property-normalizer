<?php
declare(strict_types=1);

namespace Tests;

use App\DefinitiveFixedPropertyNormalizer;
use App\TemporaryFixedPropertyNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ConstructorExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer as SymfonyPropertyNormalizer;
use Symfony\Component\Serializer\Serializer;

class DenormalizeTest extends TestCase
{
    private const array CONTEXT_DENORMALIZE = [
        DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
        AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true,
    ];


    /**
     * @param class-string<DenormalizerInterface> $propertyNormalizer
     * @param array<string, mixed> $data
     * @param class-string $class
     * @param ?class-string<\Throwable> $resultException
     * @param ?object $result
     */
    #[DataProvider('providerDenormalize')]
    public function testDenormalize(
        string $propertyNormalizer,
        array $data,
        string $class,
        ?string $resultException = null,
        ?object $result = null,
    ): void
    {
        $serializer = new Serializer(
            [
                new $propertyNormalizer(
                    new ClassMetadataFactory(new AttributeLoader()),
                    nameConverter: new CamelCaseToSnakeCaseNameConverter(),
                    propertyTypeExtractor: new ConstructorExtractor([new PhpDocExtractor(), new ReflectionExtractor()]),
                ),
            ],
        );

        if (null !== $resultException) {
            $this->expectException($resultException);
        }

        $object = $serializer->denormalize($data, $class, 'csv', self::CONTEXT_DENORMALIZE);

        if (null !== $result) {
            $this->assertEquals($result, $object);
        }
    }

    public static function providerDenormalize(): iterable
    {
        $data = [
            'accounting_firm_id' => 'carotte',
            'plop' => 'fraise',
            'money' => 'orange',
            'obj' => 'myrtille',
        ];

        $class = new readonly class {
            public function __construct(
                public ?bool $plop = null,
                public ?int $accountingFirmId = null,
                public ?float $money = null,
                public ?array $tab = null,
                public ?object $obj = null,
            )
            {
            }
        };

        yield 'Current' => [
            'propertyNormalizer' => SymfonyPropertyNormalizer::class,
            'data' => $data,
            'class' => $class::class,
            'resultException' => \TypeError::class,
        ];

        yield 'Expected - Definitive fix' => [
            'propertyNormalizer' => DefinitiveFixedPropertyNormalizer::class,
            'data' => $data,
            'class' => $class::class,
            'resultException' => PartialDenormalizationException::class,
        ];

        yield 'Expected - Temporary fix' => [
            'propertyNormalizer' => TemporaryFixedPropertyNormalizer::class,
            'data' => $data,
            'class' => $class::class,
            'resultException' => PartialDenormalizationException::class,
        ];
    }
}