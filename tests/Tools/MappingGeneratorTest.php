<?php

declare(strict_types=1);

namespace Tests\Tools;

use Refugis\ODM\Elastica\Tools\MappingGenerator;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\Document\Foo;
use Tests\Fixtures\Document\FooWithEmbedded;
use Tests\Traits\DocumentManagerTestTrait;

class MappingGeneratorTest extends TestCase
{
    use DocumentManagerTestTrait;

    public function testBasicFunctionality(): void
    {
        $dm = self::createDocumentManager();
        $generator = new MappingGenerator($dm->getTypeManager(), $dm->getMetadataFactory());

        self::assertEquals([
            'stringField' => ['type' => 'text'],
        ], $generator->getMapping($dm->getClassMetadata(Foo::class))->getProperties());
    }

    public function testMappingOfEmbeddedDocument(): void
    {
        $dm = self::createDocumentManager();
        $generator = new MappingGenerator($dm->getTypeManager(), $dm->getMetadataFactory());

        self::assertEquals([
            'emb' => [
                'type' => 'nested',
                'dynamic' => 'strict',
                'properties' => [
                    'stringField' => ['type' => 'text'],
                    'nestedEmbeddable' => [
                        'type' => 'nested',
                        'dynamic' => 'strict',
                        'properties' => [
                            'stringFieldRenest' => ['type' => 'text'],
                        ],
                    ],
                ],
            ],
        ], $generator->getMapping($dm->getClassMetadata(FooWithEmbedded::class))->getProperties());
    }
}
