<?php declare(strict_types=1);

namespace Tests\Metadata\Processor;

use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Refugis\ODM\Elastica\Annotation\DocumentId;
use Refugis\ODM\Elastica\Metadata\DocumentMetadata;
use Refugis\ODM\Elastica\Metadata\FieldMetadata;
use Refugis\ODM\Elastica\Metadata\Processor\DocumentIdProcessor;
use Tests\Fixtures\Document\Foo;

class DocumentIdProcessorTest extends TestCase
{
    private DocumentIdProcessor $processor;
    private DocumentMetadata $documentMetadata;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->processor = new DocumentIdProcessor();
        $this->documentMetadata = new DocumentMetadata(new \ReflectionClass(Foo::class));
    }

    public function testProcessStrategyAuto(): void
    {
        $metadata = new FieldMetadata($this->documentMetadata, Foo::class);

        $subject = new DocumentId();
        $subject->strategy = 'auto';

        $this->processor->process($metadata, $subject);

        self::assertTrue($metadata->identifier);
        self::assertEquals(DocumentMetadata::GENERATOR_TYPE_AUTO, $this->documentMetadata->idGeneratorType);
    }

    public function testProcessStrategyNone(): void
    {
        $metadata = new FieldMetadata($this->documentMetadata, Foo::class);

        $subject = new DocumentId();
        $subject->strategy = 'none';

        $this->processor->process($metadata, $subject);

        self::assertTrue($metadata->identifier);
        self::assertEquals(DocumentMetadata::GENERATOR_TYPE_NONE, $this->documentMetadata->idGeneratorType);
    }
}
