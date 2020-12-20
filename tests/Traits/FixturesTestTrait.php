<?php declare(strict_types=1);

namespace Tests\Traits;

use Elastica\Cluster\Settings;
use Elastica\Mapping;
use Elastica\Type\Mapping as TypeMapping;
use Elasticsearch\Endpoints;
use Refugis\ODM\Elastica\DocumentManagerInterface;

trait FixturesTestTrait
{
    private static function resetFixtures(DocumentManagerInterface $dm): void
    {
        $database = $dm->getDatabase();
        $connection = $database->getConnection();
        (new Settings($connection))->set([
            'persistent' => [
                'action.auto_create_index' => '-foo_index_no_auto_create,+*',
            ],
        ]);

        $connection->requestEndpoint((new Endpoints\Indices\Delete())->setIndex('*'));
        $connection->requestEndpoint((new Endpoints\Indices\Create())->setIndex('foo_index'));
        $connection->requestEndpoint((new Endpoints\Indices\Create())->setIndex('foo_lazy_index'));
        $connection->requestEndpoint((new Endpoints\Indices\Create())->setIndex('foo_with_aliases_index_foo_alias'));

        $fooIndex = $connection->getIndex('foo_index');

        if (class_exists(TypeMapping::class)) {
            $fooType = $fooIndex->getType('foo_index');
            TypeMapping::create([
                'stringField' => ['type' => 'text'],
            ])
                ->setType($fooType)
                ->send()
            ;

            $index = static fn (array $body) =>
                (new Endpoints\Index())
                    ->setType($fooType->getName())
                    ->setIndex($fooIndex->getName())
                    ->setBody($body);
        } else {
            Mapping::create([
                'stringField' => ['type' => 'text'],
            ])->send($fooIndex);

            $index = static fn (array $body) =>
                (new Endpoints\Index())
                    ->setIndex($fooIndex->getName())
                    ->setBody($body);
        }

        $connection->requestEndpoint($index([ 'stringField' => 'foobar' ]));
        $connection->requestEndpoint($index([ 'stringField' => 'barbaz' ]));
        $connection->requestEndpoint($index([ 'stringField' => 'bazbaz' ])->setId('foo_test_document'));

        $connection->requestEndpoint((new Endpoints\Indices\Refresh())->setIndex($fooIndex->getName()));

        $fooIndex = $connection->getIndex('foo_lazy_index');
        if (class_exists(TypeMapping::class)) {
            $fooType = $fooIndex->getType('foo_lazy_index');
            TypeMapping::create([
                'stringField' => ['type' => 'text'],
            ])
                ->setType($fooType)
                ->send()
            ;

            $index = static fn (array $body) =>
                (new Endpoints\Index())
                    ->setType($fooType->getName())
                    ->setIndex($fooIndex->getName())
                    ->setBody($body);
        } else {
            Mapping::create([
                'stringField' => ['type' => 'text'],
            ])->send($fooIndex);

            $index = static fn (array $body) =>
                (new Endpoints\Index())
                    ->setIndex($fooIndex->getName())
                    ->setBody($body);
        }

        $connection->requestEndpoint($index([
            'stringField' => 'foobar',
            'lazyField' => 'lazyFoo',
        ]));
        $connection->requestEndpoint($index([
            'stringField' => 'barbaz',
            'lazyField' => 'lazyBar',
        ]));
        $connection->requestEndpoint($index([
            'stringField' => 'bazbaz',
            'lazyField' => 'lazyBaz',
        ])->setId('foo_test_document'));

        $connection->requestEndpoint((new Endpoints\Indices\Refresh())->setIndex($fooIndex->getName()));

        $fooIndex = $connection->getIndex('foo_with_aliases_index_foo_alias');
        if (class_exists(TypeMapping::class)) {
            $fooType = $fooIndex->getType('foo_with_aliases_index_foo_alias');
            TypeMapping::create([
                'stringField' => ['type' => 'text'],
            ])
                       ->setType($fooType)
                       ->send()
            ;

            $connection->requestEndpoint((new Endpoints\Indices\Refresh())->setIndex($fooIndex->getName()));
            $connection->requestEndpoint(
                (new Endpoints\Indices\Alias\Put())
                    ->setName('foo_with_aliases_index')
                    ->setIndex('foo_with_aliases_index_foo_alias')
            );
        } else {
            Mapping::create([
                'stringField' => ['type' => 'text'],
            ])->send($fooIndex);

            $connection->requestEndpoint((new Endpoints\Indices\Refresh())->setIndex($fooIndex->getName()));
            $connection->requestEndpoint(
                (new Endpoints\Indices\PutAlias())
                    ->setName('foo_with_aliases_index')
                    ->setIndex('foo_with_aliases_index_foo_alias')
            );
        }
    }
}
