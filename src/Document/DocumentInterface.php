<?php
declare(strict_types=1);

namespace Enm\Elasticsearch\Document;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
interface DocumentInterface
{
    /**
     * @return string
     */
    public function getId(): string;

    /**
     * @param string $id
     * @param array $source
     * @return void
     */
    public function buildFromSource(string $id, array $source): void;

    /**
     * @return array
     */
    public function getStorable(): array;
}
