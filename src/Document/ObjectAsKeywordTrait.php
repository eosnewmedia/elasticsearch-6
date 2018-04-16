<?php
declare(strict_types=1);

namespace Enm\Elasticsearch\Document;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
trait ObjectAsKeywordTrait
{
    /**
     * @param object $object
     * @return string
     */
    protected function serializeObject(object $object): string
    {
        return serialize($object);
    }

    /**
     * @param string $serializedObject
     * @return object
     */
    protected function unserializeTypedValue(string $serializedObject): object
    {
        return unserialize($serializedObject, ['allowed_classes' => true]);
    }

    /**
     * @param object[] $objects
     * @return string
     */
    protected function serializeObjectCollection(array $objects): string
    {
        return serialize($objects);
    }

    /**
     * @param string $serializedObjects
     * @return object[]
     */
    protected function unserializeObjectCollection(string $serializedObjects): array
    {
        return unserialize($serializedObjects, ['allowed_classes' => true]);
    }
}
