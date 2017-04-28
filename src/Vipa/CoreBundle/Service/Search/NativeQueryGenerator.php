<?php

namespace Vipa\CoreBundle\Service\Search;

/**
 * Class $this
 *
 * @package Vipa\CoreBundle\Service
 */
class NativeQueryGenerator
{
    /**
     * @var int
     */
    private $searchSize = 20;

    /**
     * @var null
     */
    private $query = null;

    /**
     * @var array
     */
    private $requestAggsBag = [];

    /**
     * @var array
     */
    private $nativeQuery = [];

    /**
     * @var int
     */
    private $page = 1;

    /**
     * @var bool
     */
    private $setupAggs = true;

    /**
     * native query builder base router
     *
     * @param $section
     * @param bool $setupAggs if we want to only result data for caculate result count you can pass false
     * @return array|bool|mixed|null
     */
    public function generateNativeQuery($section, $setupAggs = true)
    {
        $this->setupAggs = $setupAggs;
        // decide if query has special query route to own route
        if(preg_match('/journal:/', $this->getQuery())){

            $this->nativeQuery = $this->journalQueryGenerator($section);
        }elseif(preg_match('/advanced:/', $this->getQuery())){

            $this->nativeQuery = $this->advancedQueryGenerator($section);
        }elseif(preg_match('/tag:/', $this->getQuery())){

            $this->nativeQuery = $this->tagQueryGenerator($section);
        }else{

            $this->nativeQuery = $this->basicQueryGenerator($section);
        }

        return $this->nativeQuery;
    }

    /**
     * @return array
     */
    public function getNativeQuery()
    {
        return $this->nativeQuery;
    }

    /**
     * @param array $nativeQuery
     * @return $this
     */
    public function setNativeQuery($nativeQuery)
    {
        $this->nativeQuery = $nativeQuery;

        return $this;
    }

    /**
     * @return null
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param null $query
     * @return $this
     */
    public function setQuery($query)
    {
        $this->query = $query;

        return $this;
    }

    /**
     * @return array
     */
    public function getRequestAggsBag()
    {
        return $this->requestAggsBag;
    }

    /**
     * @param array $requestAggsBag
     * @return $this
     */
    public function setRequestAggsBag($requestAggsBag)
    {
        $this->requestAggsBag = $requestAggsBag;

        return $this;
    }

    /**
     * @return int
     */
    public function getPage()
    {
        return (int)$this->page;
    }

    /**
     * @param int $page
     * @return $this
     */
    public function setPage($page = 1)
    {
        $this->page = $page;

        return $this;
    }

    /**
     * @return int
     */
    public function getSearchSize()
    {
        return $this->searchSize;
    }

    /**
     * @param int $searchSize
     * @return $this
     */
    public function setSearchSize($searchSize)
    {
        $this->searchSize = $searchSize;

        return $this;
    }

    /**
     * finds journalId from text query
     *
     * @return bool|int
     */
    private function getJournalIdFromQuery()
    {
        $explodeQuery = explode(' ', $this->query);
        foreach($explodeQuery as $value){
            if(preg_match('/journal:/', $value)){
                return (int)explode('journal:', $value)[1];
            }
        }

        return false;
    }

    /**
     * holds types based search fields and boost types for some
     *
     * @return array
     */
    public function getSearchParamsBag()
    {
        return [
            'journal' => [
                'fields' => [
                    ['title', 3],
                    ['translations.title', 2],
                    ['description', 1],
                    ['translations.description', 1],
                ],
                'aggs' => [
                    'subjects.subject',
                    'publisher.name',
                    'periods.period',
                    'mandatoryLang',
                    'publisher.publisherType.name',
                ]
            ],
            'articles' => [
                'fields' => [
                    ['title', 3],
                    ['translations.title', 2],
                    ['abstract', 1],
                    ['translations.abstract', 1],
                ],
                'aggs' => [
                    'journal.title',
                    'section.title',
                    'subjects.subject',
                    'articleType',
                ]
            ],
            'author' => [
                'fields' => [
                    'firstName',
                    'lastName',
                    'middleName',
                    'fullName',
                ],
                'aggs' => [
                    'title.title',
                ]
            ],
            'user' => [
                'fields' => [
                    'username',
                    'firstName',
                    'lastName',
                    'email',
                    'fullName',
                ],
                'aggs' => [
                    'title.title',
                    'subjects.subject',
                    'journalUsers.journal.title',
                    'journalUsers.roles'
                ]
            ],
            'publisher' => [
                'fields' => [
                    'name',
                ],
                'aggs' => [
                    'publisherType.name',
                ]
            ],

        ];
    }

    /**
     * holds search in journal types and journal id fields list
     *
     * @return array
     */
    private function getSearchInJournalQueryParams()
    {
        return [
            'user'      => 'user.journalUsers.journal.id',
            'articles'  => 'articles.journal.id',
            'citation'  => 'citation.articles.journal.id',
            'author'    => 'author.articleAuthors.article.journal.id',
        ];
    }

    /**
     * holds tag search types and tag fields list
     *
     * @return array
     */
    private function getTagQueryParams()
    {
        return [
            'user' => [
                'tags',
            ],
            'publisher' => [
                'tags',
            ],
            'journal' => [
                'tags',
            ],
            'subject' => [
                'tags',
            ],
            'journal_page' => [
                'tags',
            ],
            'author' => [
                'tags',
            ],
            'articles' => [
                'keywords',
                'translations.keywords',
            ]
        ];
    }

    /**
     * Advanced query generator
     *
     * @todo this function is not finished yet we must do more tests
     * @param $section
     * @return mixed
     */
    private function advancedQueryGenerator($section)
    {
        $sectionParams = $this->getSearchParamsBag()[$section];
        $from = ($this->getPage()-1)*$this->getSearchSize();
        $size = $this->getSearchSize();
        $queryArray['from'] = $from;
        $queryArray['size'] = $size;

        $advancedQuery = trim(preg_replace('/advanced:/', '', $this->query));

        $queryArray['query']['filtered']['query']['bool']['should'][] = [
            'query_string' => [
                'query' => $advancedQuery
            ]
        ];
        if(!empty($this->requestAggsBag)){
            foreach($this->requestAggsBag as $requestAggKey => $requestAgg){
                if(!in_array($requestAggKey, $sectionParams['aggs'])){
                    continue;
                }
                foreach($requestAgg as $aggValue){
                    $queryArray['query']['filtered']['filter']['bool']['must'][] = [
                        'term' => [ $section.'.'.$requestAggKey => $aggValue ]
                    ];
                }
            }
        }
        if($this->setupAggs){
            foreach($sectionParams['aggs'] as $agg){
                $queryArray['aggs'][$agg] = [
                    'terms' => [
                        'field' => $section.'.'.$agg
                    ]
                ];
            }
        }

        return $queryArray;
    }

    /**
     * Generates native query for tag type search queries
     *
     * @param $section
     * @return bool|null
     */
    private function tagQueryGenerator($section)
    {
        // if section is not have tags field return false
        if(!in_array($section, array_keys($this->getTagQueryParams()))){
            return false;
        }
        $sectionParams = $this->getSearchParamsBag()[$section];
        $sectionTagParams = $this->getTagQueryParams()[$section];
        $from = ($this->getPage()-1)*$this->getSearchSize();
        $size = $this->getSearchSize();
        $queryArray['from'] = $from;
        $queryArray['size'] = $size;

        // get tag
        $tagQuery = trim(preg_replace('/tag:/', '', $this->query));
        /**
         * foreach tag search field add should
         * term query with specified field
         */
        foreach($sectionTagParams as $tagField){
            $queryArray['query']['filtered']['query']['bool']['should'][] = [
                'term' => [ $section.'.'.$tagField => strtolower($tagQuery) ]
            ];
        }
        // look basicQueryGenerator() function
        if(!empty($this->requestAggsBag)){
            foreach($this->requestAggsBag as $requestAggKey => $requestAgg){
                if(!in_array($requestAggKey, $sectionParams['aggs'])){
                    continue;
                }
                foreach($requestAgg as $aggValue){
                    $queryArray['query']['filtered']['filter']['bool']['must'][] = [
                        'term' => [ $section.'.'.$requestAggKey => $aggValue ]
                    ];
                }
            }
        }
        // look basicQueryGenerator() function
        if($this->setupAggs){
            foreach($sectionParams['aggs'] as $agg){
                $queryArray['aggs'][$agg] = [
                    'terms' => [
                        'field' => $section.'.'.$agg
                    ]
                ];
            }
        }

        return $queryArray;
    }

    /**
     * journal based query generator
     *
     * @param $section
     * @return bool|array
     */
    private function journalQueryGenerator($section)
    {
        // if journal field not exists for given section return false
        if(!isset($this->getSearchInJournalQueryParams()[$section])){
            return false;
        }
        $journalId = null;
        $sectionParams = $this->getSearchParamsBag()[$section];
        $from = ($this->getPage()-1)*$this->getSearchSize();
        $size = $this->getSearchSize();
        $queryArray['from'] = $from;
        $queryArray['size'] = $size;

        // get journal id from query
        $journalId = $this->getJournalIdFromQuery();
        // get journal pure query from requested query
        $journalQuery = trim(preg_replace('/journal:'.$journalId.'/', '', $this->query));
        // get journal id field for given section
        $journalIdField = $this->getSearchInJournalQueryParams()[$section];

        // look basicQueryGenerator() function
        foreach($sectionParams['fields'] as $field){
            $searchField = $field;
            $boost = 1;
            if(is_array($field)){
                $searchField = $field[0];
                $boost = $field[1];
            }
            if(empty($journalQuery)){
                $queryArray['query']['filtered']['query']['bool']['should'][] = [
                    'match_all' => []
                ];
            }else{
                $queryArray['query']['filtered']['query']['bool']['should'][] = [
                    'query_string' => [
                        'query' => $section.'.'.$searchField.':'.$journalQuery,
                        'boost' => $boost,
                    ]
                ];
            }
        }
        //add journal id filter
        $queryArray['query']['filtered']['filter']['bool']['must'][] = [
            'term' => [ $journalIdField => $journalId ]
        ];
        // look basicQueryGenerator() function
        if(!empty($this->requestAggsBag)){
            foreach($this->requestAggsBag as $requestAggKey => $requestAgg){
                if(!in_array($requestAggKey, $sectionParams['aggs'])){
                    continue;
                }
                foreach($requestAgg as $aggValue){
                    $queryArray['query']['filtered']['filter']['bool']['must'][] = [
                        'term' => [ $section.'.'.$requestAggKey => $aggValue ]
                    ];
                }
            }
        }
        // look basicQueryGenerator() function
        if($this->setupAggs){
            foreach($sectionParams['aggs'] as $agg){
                $queryArray['aggs'][$agg] = [
                    'terms' => [
                        'field' => $section.'.'.$agg
                    ]
                ];
            }
        }

        return $queryArray;
    }

    /**
     * basic query generator
     *
     * @param $section
     * @return mixed
     */
    private function basicQueryGenerator($section)
    {
        // get section params contains section search fields and aggs
        $sectionParams = $this->getSearchParamsBag()[$section];
        // calculate from field from page
        $from = ($this->getPage()-1)*$this->getSearchSize();
        // get size field
        $size = $this->getSearchSize();
        // set from field to query array
        $queryArray['from'] = $from;
        // set size field to query array
        $queryArray['size'] = $size;
        // foreach section field add query to native query array
        foreach($sectionParams['fields'] as $field){
            $searchField = $field;
            // default boost is 1
            $boost = 1;
            // if $field is array 0 is field and 1 is boost int
            if(is_array($field)){
                $searchField = $field[0];
                $boost = (int)$field[1];
            }
            // if query is empty find all section results
            if(empty($this->query)){
                $queryArray['query']['filtered']['query']['bool']['should'][] = [
                    'match_all' => []
                ];
            }else{
                /**
                 * find query via query_string type query
                 * look for more
                 * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-query-string-query.html
                 */
                $queryArray['query']['filtered']['query']['bool']['should'][] = [
                    'query_string' => [
                        'query' => $section.'.'.$searchField.':'.$this->query,
                        'boost' => $boost,
                    ]
                ];
            }
        }
        // if requested agg bag is not empty
        if(!empty($this->requestAggsBag)){
            // for each bag aggregation
            foreach($this->requestAggsBag as $requestAggKey => $requestAgg){
                // if requested agg. is not our specified agg. continue then
                if(!in_array($requestAggKey, $sectionParams['aggs'])){
                    continue;
                }
                /**
                 * add filter for each spesified agg.
                 * look for more
                 * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-filtered-query.html
                 */
                foreach($requestAgg as $aggValue){
                    $queryArray['query']['filtered']['filter']['bool']['must'][] = [
                        'term' => [ $section.'.'.$requestAggKey => $aggValue ]
                    ];
                }
            }
        }
        // inject all aggs. to native query with specified aggs
        if($this->setupAggs){
            foreach($sectionParams['aggs'] as $agg){
                $queryArray['aggs'][$agg] = [
                    'terms' => [
                        'field' => $section.'.'.$agg
                    ]
                ];
            }
        }

        return $queryArray;
    }
}
