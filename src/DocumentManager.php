<?php
declare(strict_types=1);

namespace Enm\Elasticsearch;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Enm\Elasticsearch\Document\DocumentInterface;
use Enm\Elasticsearch\Exception\DocumentManagerException;
use Enm\Elasticsearch\Exception\DocumentNotFoundException;
use Enm\Elasticsearch\Exception\ElasticsearchException;
use Enm\Elasticsearch\Exception\UnavailableException;
use Enm\Elasticsearch\Search\SearchInterface;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
class DocumentManager implements DocumentManagerInterface
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $baseIndex;

    /**
     * @var string[]
     */
    private $types = [];

    /**
     * @var array
     */
    private $settings = [];

    /**
     * @var array
     */
    private $mappings = [];

    /**
     * @var array
     */
    private $pipelines = [];

    /**
     * @var DocumentInterface[][]
     */
    private $documents = [];

    /**
     * @param string $index
     * @param string $host
     */
    public function __construct(string $index, string $host)
    {
        $this->baseIndex = $index;
        $this->client = ClientBuilder::create()->setHosts([$host])->build();
    }

    /**
     * @param string $className
     * @param string $type
     */
    public function registerType(string $className, string $type): void
    {
        $this->types[$className] = $type;
    }


    /**
     * @param string $className
     * @param array $mapping
     */
    public function registerMapping(string $className, array $mapping): void
    {
        $this->mappings[$className] = $mapping;
    }

    /**
     * @param string $className
     * @param array $settings
     */
    public function registerSettings(string $className, array $settings): void
    {
        $this->settings[$className] = $settings;
    }

    /**
     * @param string $className
     * @param array $pipeline
     */
    public function registerPipeline(string $className, array $pipeline): void
    {
        $this->pipelines[$className] = $pipeline;
    }

    /**
     * @return Client
     */
    public function elasticsearch(): Client
    {
        return $this->client;
    }

    /**
     * @param string $className
     * @return string
     */
    public function indexName(string $className): string
    {
        return $this->baseIndex . '__' . strtolower($this->type($className));
    }

    /**
     * @param string $className
     * @return string
     */
    public function type(string $className): string
    {
        if (!array_key_exists($className, $this->types)) {
            $classParts = explode('\\', $className);
            $this->types[$className] = lcfirst((string)array_pop($classParts));
        }

        return $this->types[$className];
    }

    /**
     * @param DocumentInterface $document
     * @param bool $replace
     * @throws DocumentManagerException
     */
    public function register(DocumentInterface $document, bool $replace = false): void
    {
        $className = \get_class($document);

        if ($replace) {
            $this->detach($className, $document->getId());
        }

        if (!\array_key_exists($className, $this->documents)) {
            $this->documents[$className] = [];
        }

        $documentExists = \array_key_exists($document->getId(), $this->documents[$className]);
        if ($documentExists && $this->documents[$className][$document->getId()] !== $document) {
            throw new DocumentManagerException('Document with same id already registered.');
        }

        $this->documents[$className][$document->getId()] = $document;
    }

    /**
     * @param string|null $className
     * @param string|null $id
     */
    public function detach(string $className = null, string $id = null): void
    {
        if (!$className) {
            $this->documents = [];

            return;
        }

        if (!$id) {
            if (\array_key_exists($className, $this->documents)) {
                unset($this->documents[$className]);
            }
            return;
        }

        if (!\array_key_exists($className, $this->documents)) {
            return;
        }

        if (!\array_key_exists($id, $this->documents[$className])) {
            return;
        }

        unset($this->documents[$className][$id]);
    }

    /**
     * @param string $className
     * @param string $id
     * @return DocumentInterface
     * @throws DocumentNotFoundException
     */
    protected function retrieve(string $className, string $id): DocumentInterface
    {
        if (!\array_key_exists($className, $this->documents)) {
            throw new DocumentNotFoundException($className . ' ' . $id . ' not found.');
        }
        if (!\array_key_exists($id, $this->documents[$className])) {
            throw new DocumentNotFoundException($className . ' ' . $id . ' not found.');
        }

        return $this->documents[$className][$id];
    }

    /**
     * @param string|null $className
     */
    public function createIndex(string $className = null): void
    {
        foreach ($this->mappings as $mappedClassName => $mapping) {
            if ($className !== null && $mappedClassName !== $className) {
                continue;
            }

            $body = [
                'mappings' => [
                    $this->type($mappedClassName) => (array)$mapping
                ]
            ];

            if (array_key_exists($mappedClassName, $this->settings)) {
                $body['settings'] = (array)$this->settings[$mappedClassName];
            }

            $index = [
                'index' => $this->indexName($mappedClassName),
                'body' => $body
            ];

            if (array_key_exists($mappedClassName, $this->pipelines)) {
                $this->elasticsearch()->ingest()->putPipeline(
                    [
                        'id' => $this->type($mappedClassName),
                        'body' => [
                            'description' => $this->pipelines[$mappedClassName]['description'],
                            'processors' => array_values($this->pipelines[$mappedClassName]['processors']),
                        ]
                    ]
                );
            }

            $this->elasticsearch()->indices()->create($index);
        }
    }

    /**
     * @param string|null $className
     */
    public function dropIndex(string $className = null): void
    {
        foreach ($this->mappings as $mappedClassName => $mapping) {
            if ($className !== null && $mappedClassName !== $className) {
                continue;
            }

            if (array_key_exists($mappedClassName, $this->pipelines)) {
                try {
                    $this->elasticsearch()->ingest()->deletePipeline(['id' => $this->type($mappedClassName)]);
                } catch (\Exception $e) {

                }
            }

            $this->elasticsearch()->indices()->delete(['index' => $this->indexName($mappedClassName)]);
        }
    }

    /**
     * @param DocumentInterface $document
     * @param bool $replace
     * @throws ElasticsearchException
     */
    public function save(DocumentInterface $document, bool $replace = false): void
    {
        $className = \get_class($document);

        $this->register($document, $replace);

        $elasticDocument = [
            'index' => $this->indexName($className),
            'type' => $this->type($className),
            'id' => $document->getId(),
            'body' => $document->getStorable(),
        ];
        if (array_key_exists($className, $this->pipelines)) {
            $elasticDocument['pipeline'] = $this->type($className);
        }

        $this->elasticsearch()->index($elasticDocument);
    }

    /**
     * Save all registered documents
     * @throws ElasticsearchException
     */
    public function saveAll(): void
    {
        foreach ($this->documents as $documents) {
            foreach ($documents as $document) {
                $this->save($document);
            }
        }
    }

    /**
     * @param string $className
     * @param string $id
     */
    public function delete(string $className, string $id): void
    {
        try {
            $this->elasticsearch()->delete(
                [
                    'index' => $this->indexName($className),
                    'type' => $this->type($className),
                    'id' => $id
                ]
            );
        } catch (\Exception $e) {

        }

        $this->detach($className, $id);
    }

    /**
     * @param string $className
     * @param string $id
     * @param int $retriesOnError
     * @return DocumentInterface
     * @throws DocumentNotFoundException
     */
    public function document(string $className, string $id, int $retriesOnError = 3): DocumentInterface
    {
        try {
            return $this->retrieve($className, $id);
        } catch (DocumentNotFoundException $e) {
            try {
                $response = $this->fetchDocument($className, $id, $retriesOnError);

                return $this->buildDocument($className, $id, $response);
            } catch (\Exception $e) {
                throw new DocumentNotFoundException($className . ' ' . $id . ' not found.');
            }
        }
    }

    /**
     * @param DocumentInterface $document
     * @param int $retriesOnError
     * @throws DocumentNotFoundException|DocumentManagerException
     */
    public function refreshDocument(DocumentInterface $document, int $retriesOnError = 3): void
    {
        try {
            $retrieved = $this->retrieve(\get_class($document), $document->getId());
            if ($retrieved !== $document) {
                throw new DocumentManagerException('Document is not managed by this document manager!');
            }

            $response = $this->fetchDocument(\get_class($document), $document->getId(), $retriesOnError);

            $this->buildDocument(\get_class($document), $document->getId(), $response);
        } catch (\Exception $e) {
            throw new DocumentNotFoundException(\get_class($document) . ' ' . $document->getId() . ' not found.');
        }
    }

    /**
     * @param string $className
     * @param SearchInterface $search
     * @return array
     */
    public function documents(string $className, SearchInterface $search): array
    {
        $body = [];
        $body['from'] = $search->getFrom();
        $body['size'] = $search->getSize();
        if (\count($search->getQuery()) > 0) {
            $body['query'] = $search->getQuery();
        }
        if (\count($search->getSorting()) > 0) {
            $body['sort'] = $search->getSorting();
        }

        $response = $this->elasticsearch()->search([
                'index' => $this->indexName($className),
                'type' => $this->type($className),
                'body' => $body
            ]
        );

        if ($response['hits']['total'] === 0) {
            return [];
        }

        $documents = [];
        foreach ((array)$response['hits']['hits'] as $hit) {
            try {
                $documents[] = $this->buildDocument($className, $hit['_id'], $hit);
            } catch (\Exception $e) {

            }
        }

        return $documents;
    }

    /**
     * @param string $className
     * @param SearchInterface $search
     * @return int
     */
    public function count(string $className, SearchInterface $search): int
    {
        $body = [];
        if (\count($search->getQuery()) > 0) {
            $body['query'] = $search->getQuery();
        }

        $count = (int)$this->elasticsearch()->count(
            [
                'index' => $this->indexName($className),
                'type' => $this->type($className),
                'body' => $body
            ]
        )['count'];

        return $count;
    }

    /**
     * @param string $className
     * @param string $id
     * @param $data
     *
     * @return DocumentInterface
     * @throws DocumentManagerException|DocumentNotFoundException
     */
    protected function buildDocument(string $className, string $id, $data): DocumentInterface
    {
        if (!array_key_exists('_source', $data)) {
            throw new DocumentNotFoundException('Invalid data: "_source" not available!');
        }
        try {
            $document = $this->retrieve($className, $id);
            $document->buildFromSource($id, $data['_source']);
        } catch (\Exception $e) {
            try {
                $reflection = new \ReflectionClass($className);
                /** @var DocumentInterface $document */
                $document = $reflection->newInstanceWithoutConstructor();
                $document->buildFromSource($id, $data['_source']);
                $this->register($document);
            } catch (\Exception $e) {
                throw new DocumentManagerException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $document;
    }

    /**
     * @param string $className
     * @param string $id
     * @param int $retriesOnError
     * @return array
     * @throws UnavailableException
     */
    protected function fetchDocument(string $className, string $id, int $retriesOnError): array
    {
        // try multiple times, because elasticsearch may be down for short times...
        for ($i = 0; $i <= $retriesOnError; $i++) {
            try {
                $response = $this->elasticsearch()->get(
                    [
                        'index' => $this->indexName($className),
                        'type' => $this->type($className),
                        'id' => $id
                    ]
                );

                return $response;
            } catch (\Exception $e) {
                sleep(($i > 0 ? $i : 1) * $i);
            }
        }

        throw new UnavailableException();
    }
}
