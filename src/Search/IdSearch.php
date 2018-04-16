<?php
declare(strict_types=1);

namespace Enm\Elasticsearch\Search;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
class IdSearch extends Search
{
    /**
     * @param array $ids
     * @param int $from
     * @param int $size
     */
    public function __construct(array $ids, int $from = 0, int $size = 10000)
    {
        parent::__construct(
            [
                'ids' => ['values' => $ids]
            ],
            [],
            $from,
            $size
        );
    }
}
