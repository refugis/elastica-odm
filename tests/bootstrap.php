<?php declare(strict_types=1);

use Doctrine\Common\Annotations\AnnotationRegistry;

require __DIR__.'/../vendor/autoload.php';

if (method_exists(AnnotationRegistry::class, 'registerLoader')) {
    AnnotationRegistry::registerLoader('class_exists');
}
