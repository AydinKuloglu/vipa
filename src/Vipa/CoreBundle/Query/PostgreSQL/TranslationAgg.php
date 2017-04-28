<?php

namespace Vipa\CoreBundle\Query\PostgreSQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use \Doctrine\ORM\Query\Parser;
use \Doctrine\ORM\Query\SqlWalker;

/**
 * Class TranslationAgg
 * @package Vipa\CoreBundle\Query\PostgreSQL
 */
class TranslationAgg extends FunctionNode
{
    private $stringField = '';

    /**
     * @param Parser $parser
     */
    public function parse(Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->stringField = $parser->StringPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    /**
     * @param SqlWalker $sqlWalker
     * @return string
     */
    public function getSql(SqlWalker $sqlWalker)
    {
        $driver = $sqlWalker->getConnection()->getDriver()->getName();
        $translationField = $this->stringField->dispatch($sqlWalker);
        $translationsAlias = explode('.', $translationField)[0];

        $query = $translationField;
        if($driver == 'pdo_pgsql'){

            $query = "string_agg(DISTINCT ".
                $translationField.
                "|| ' [' || ".$translationsAlias.".locale || '] ', '<br>')";
        }

        return $query;
    }
}
