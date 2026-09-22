<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Language-agnostic syntax highlighter.
 *
 * It runs on the server and emits `<span class="tok-…">` elements only — no
 * client-side library, no scripting. Rather than a grammar per language it
 * recognises the constructs nearly every language shares (comments, strings,
 * numbers, keywords, calls, types, variables, operators), which gives useful
 * colour for anything a member pastes without the board having to ship a parser
 * for each language.
 *
 * The colours themselves are not decided here: each token class maps to a CSS
 * custom property that the active theme defines and an administrator can
 * change under Admin → Themes → Appearance.
 */
final class CodeHighlighter
{
    /** Token classes, in the order the pattern tries them. */
    public const TOKENS = [
        'comment' => 'Comments',
        'string' => 'Strings',
        'number' => 'Numbers',
        'keyword' => 'Keywords',
        'literal' => 'Booleans and null',
        'variable' => 'Variables',
        'function' => 'Function and method names',
        'type' => 'Classes and types',
        'tag' => 'Markup tags',
        'attribute' => 'Attributes and properties',
        'operator' => 'Operators',
        'punctuation' => 'Brackets and punctuation',
    ];

    /**
     * Words treated as keywords. The union of the common control-flow,
     * declaration and modifier words across the languages people actually post:
     * C-family, PHP, Python, Ruby, Go, Rust, SQL, shell and friends.
     *
     * @var array<int,string>
     */
    private const KEYWORDS = [
        'abstract', 'alias', 'and', 'array', 'as', 'async', 'await', 'begin', 'bool', 'boolean',
        'break', 'byte', 'case', 'catch', 'char', 'class', 'clone', 'const', 'constructor',
        'continue', 'declare', 'def', 'default', 'defer', 'del', 'delete', 'do', 'done', 'double',
        'echo', 'elif', 'else', 'elseif', 'elsif', 'end', 'endfor', 'endforeach', 'endif',
        'endswitch', 'endwhile', 'enum', 'esac', 'eval', 'except', 'exit', 'export', 'extends',
        'extern', 'fi', 'final', 'finally', 'float', 'fn', 'for', 'foreach', 'from', 'func',
        'function', 'global', 'go', 'goto', 'if', 'impl', 'implements', 'import', 'in', 'include',
        'include_once', 'instanceof', 'insteadof', 'int', 'integer', 'interface', 'is', 'lambda',
        'let', 'local', 'long', 'loop', 'match', 'mod', 'module', 'mut', 'namespace', 'new',
        'not', 'or', 'package', 'pass', 'print', 'private', 'protected', 'pub', 'public', 'raise',
        'readonly', 'require', 'require_once', 'rescue', 'return', 'select', 'self', 'short',
        'signed', 'sizeof', 'static', 'string', 'struct', 'super', 'switch', 'then', 'this',
        'throw', 'throws', 'trait', 'try', 'type', 'typedef', 'typeof', 'union', 'unless',
        'unsigned', 'until', 'use', 'var', 'void', 'volatile', 'when', 'where', 'while', 'with',
        'yield',
        // SQL reads as prose, so its verbs are included explicitly.
        'alter', 'and', 'asc', 'between', 'by', 'create', 'delete', 'desc', 'distinct', 'drop',
        'exists', 'from', 'group', 'having', 'inner', 'insert', 'into', 'join', 'left', 'like',
        'limit', 'offset', 'on', 'order', 'outer', 'right', 'set', 'table', 'union', 'update',
        'values', 'where',
    ];

    /** @var array<int,string> */
    private const LITERALS = [
        'true', 'false', 'null', 'nil', 'none', 'undefined', 'nan', 'inf',
        'True', 'False', 'None', 'NULL', 'TRUE', 'FALSE',
    ];

    private static ?string $pattern = null;

    /**
     * @return string HTML for the inside of a <code> element.
     */
    public static function highlight(string $code): string
    {
        $pattern = self::pattern();
        $output = '';
        $cursor = 0;

        if (preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === false) {
            return self::escape($code);
        }

        foreach ($matches as $match) {
            [$text, $offset] = $match[0];

            if ($text === '' || $offset < $cursor) {
                continue;
            }

            $output .= self::escape(substr($code, $cursor, $offset - $cursor));
            $output .= '<span class="tok-' . self::classOf($match) . '">' . self::escape($text) . '</span>';
            $cursor = $offset + strlen($text);
        }

        return $output . self::escape(substr($code, $cursor));
    }

    /** @param array<string|int,array{0:string,1:int}> $match */
    private static function classOf(array $match): string
    {
        foreach (array_keys(self::TOKENS) as $name) {
            if (isset($match[$name]) && $match[$name][1] !== -1 && $match[$name][0] !== '') {
                return $name;
            }
        }

        return 'punctuation';
    }

    private static function pattern(): string
    {
        if (self::$pattern !== null) {
            return self::$pattern;
        }

        $keywords = implode('|', array_map('preg_quote', array_unique(self::KEYWORDS)));
        $literals = implode('|', array_map('preg_quote', array_unique(self::LITERALS)));

        // Order is significant: comments and strings must win over everything
        // else, or a keyword inside a string would be coloured as code.
        $alternatives = [
            // Block and line comments. `#` skips CSS-style hex colours and `--`
            // requires a following space, so SQL comments do not eat CSS custom
            // property names.
            'comment' => '\/\*[\s\S]*?(?:\*\/|$)|\/\/[^\n]*|<!--[\s\S]*?-->|#(?![0-9a-fA-F]{3,8}\b)[^\n]*|--[ \t][^\n]*',
            // Single, double, backtick and triple-quoted strings, escapes aware.
            'string' => '"""[\s\S]*?"""|\'\'\'[\s\S]*?\'\'\'|"(?:[^"\\\\\n]|\\\\.)*"|\'(?:[^\'\\\\\n]|\\\\.)*\'|`(?:[^`\\\\]|\\\\.)*`',
            'number' => '\b(?:0[xX][0-9a-fA-F_]+|0[bB][01_]+|0[oO][0-7_]+|\d[\d_]*(?:\.\d[\d_]*)?(?:[eE][+-]?\d+)?)\b|#[0-9a-fA-F]{3,8}\b',
            'variable' => '[$@%][A-Za-z_][A-Za-z0-9_]*|\b[A-Z][A-Z0-9_]{2,}\b(?![\w(])',
            'literal' => '\b(?:' . $literals . ')\b',
            'keyword' => '\b(?:' . $keywords . ')\b',
            'function' => '\b[A-Za-z_][A-Za-z0-9_]*(?=\s*\()',
            'tag' => '<\/?[A-Za-z][A-Za-z0-9:-]*|\/?>',
            'type' => '\b[A-Z][A-Za-z0-9_]*\b',
            'attribute' => '(?<=[.>:])[A-Za-z_][A-Za-z0-9_]*',
            'operator' => '[+\-*\/%=<>!&|^~?:]+',
            'punctuation' => '[{}()\[\];,.]',
        ];

        $parts = [];

        foreach ($alternatives as $name => $expression) {
            $parts[] = '(?<' . $name . '>' . $expression . ')';
        }

        return self::$pattern = '/' . implode('|', $parts) . '/u';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * A short, safe label for the language a member declared with [code=php].
     */
    public static function normaliseLanguage(string $language): string
    {
        $language = strtolower(trim($language));

        return preg_match('/^[a-z0-9+#.-]{1,20}$/', $language) === 1 ? $language : '';
    }
}
