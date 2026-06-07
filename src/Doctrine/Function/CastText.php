<?php

namespace App\Doctrine\Function;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * DQL function: CAST_TEXT(expr) → CAST(expr AS TEXT)
 *
 * Usage in DQL:  LOWER(CAST_TEXT(a.systemInfos)) LIKE :param
 */
class CastText extends FunctionNode
{
    private Node $expression;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->expression = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return 'CAST(' . $this->expression->dispatch($sqlWalker) . ' AS TEXT)';
    }
}
