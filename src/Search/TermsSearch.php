<?php
declare(strict_types=1);

namespace Enm\Elasticsearch\Search;

/**
 * @author Philipp Marien <marien@eosnewmedia.de>
 */
class TermsSearch extends Search
{
    /**
     * @param string $key
     * @param array $values
     * @param int $from
     * @param int $size
     */
    public function __construct(string $key, array $values, int $from = 0, int $size = 10000)
    {
        parent::__construct(
            [
                'terms' => [$key => $values]
            ],
            [],
            $from,
            $size
        );
    }
}
