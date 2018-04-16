<?php
declare(strict_types=1);

namespace Enm\Elasticsearch\Search;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
class EmptySearch extends Search
{
    /**
     * @param int $from
     * @param int $size
     */
    public function __construct(int $from = 0, int $size = 10000)
    {
        parent::__construct(
            [],
            [],
            $from,
            $size
        );
    }
}
