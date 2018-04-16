<?php
declare(strict_types=1);

namespace Enm\Elasticsearch;

use Elasticsearch\Client;
use Enm\Elasticsearch\Document\DocumentInterface;
use Enm\Elasticsearch\Exception\DocumentManagerException;
use Enm\Elasticsearch\Exception\DocumentNotFoundException;
use Enm\Elasticsearch\Search\SearchInterface;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
interface DocumentManagerInterface
{
    /**
     * @return Client
     */
    public function elasticsearch(): Client;

    /**
     * @param string $className
     * @return string
     */
    public function indexName(string $className): string;

    /**
     * @param string $className
     * @return string
     */
    public function type(string $className): string;

    /**
     * Creates the elasticsearch index
     * @param string|null $className
     * @return void
     */
    public function createIndex(string $className = null): void;

    /**
     * Drops the elasticsearch index
     * @param string|null $className
     * @return void
     */
    public function dropIndex(string $className = null): void;

    /**
     * @param DocumentInterface $document
     * @param bool $replace
     * @throws DocumentManagerException
     */
    public function register(DocumentInterface $document, bool $replace = false): void;

    /**
     * @param string|null $className
     * @param string|null $id
     */
    public function detach(string $className = null, string $id = null): void;

    /**
     * @param DocumentInterface $document
     * @param bool $replace
     * @return void
     */
    public function save(DocumentInterface $document, bool $replace = false): void;

    /**
     * Save all registered documents
     */
    public function saveAll(): void;

    /**
     * @param string $className
     * @param string $id
     *
     * @return void
     */
    public function delete(string $className, string $id): void;

    /**
     * @param string $className
     * @param string $id
     * @param int $retriesOnError
     * @return DocumentInterface
     * @throws DocumentNotFoundException
     */
    public function document(string $className, string $id, int $retriesOnError = 3): DocumentInterface;

    /**
     * @param DocumentInterface $document
     * @param int $retriesOnError
     * @throws DocumentNotFoundException
     */
    public function refreshDocument(DocumentInterface $document, int $retriesOnError = 3): void;

    /**
     * @param string $className
     * @param  SearchInterface $search
     *
     * @return DocumentInterface[]
     */
    public function documents(string $className, SearchInterface $search): array;

    /**
     * @param string $className
     * @param SearchInterface $search
     * @return int
     */
    public function count(string $className, SearchInterface $search): int;
}
