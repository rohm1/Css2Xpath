<?php
/**
 * @author rohm1
 * @link https://github.com/rohm1/Css2Xpath
 */

namespace RPBase\Css2Xpath;

class Parser
{

    /**
     * @var array
     */
    public static $ATTRS = [
        '#' => [
            'type'    => 'id',
            'matcher' => 'same'
        ],
        '.' => [
            'type'    => 'class',
            'matcher' => 'contains-word'
        ],
    ];

    /**
     * @var string
     */
    protected $fullSelector;

    /**
     * @var string
     */
    protected $selector;

    /**
     * The selector string length
     *
     * @var int
     */
    protected $length;

    /**
     * Current offset in the selector
     *
     * @var int
     */
    protected $offset;

    /**
     * The XPath expression of the given selector
     *
     * @var string
     */
    protected $xpath;

    /**
     * @param string $selector
     */
    public function __construct($selector)
    {
        $this->fullSelector = $selector;
    }

    /**
     * @param string $selector
     * @return string
     * @throws MalformedCssExpressionException
     */
    public static function parse($selector)
    {
        $instance = new self($selector);
        return $instance->getXpath();
    }

    /**
     * @return string
     * @throws MalformedCssExpressionException
     */
    public function getXpath()
    {
        if ($this->xpath === null) {
            $this->_parse();
        }

        return $this->xpath;
    }

    /**
     * @return \RPBase\XQuery\Css2Xpath
     * @throws MalformedCssExpressionException
     */
    protected function _parse()
    {
        $this->xpath = '';

        $selectors = explode(',', $this->fullSelector);
        $parsed = [];
        foreach ($selectors as $selector) {
            $this->selector = trim($selector);
            $this->length = strlen($this->selector);
            $this->offset = 0;

            $parsed[] = $this->rules2Xpath($this->extractRules());
        }

        $this->xpath = implode('|', $parsed);

        return $this;
    }

    /**
     * Extracts the rules in a CSS selector
     *
     * @return \RPBase\XQuery\Css2Xpath
     * @throws MalformedCssExpressionException
     */
    protected function extractRules()
    {
        $rules = [];

        $crt_rule = $this->getEmptyRule();

        while ($this->offset < $this->length) {

            $crt_char = $this->getChar();

            if (array_key_exists($crt_char, self::$ATTRS)) {
                $attr = self::$ATTRS[$crt_char];
                $value = $this->extractAttributeValue();

                $crt_rule['attributes'][] = [
                    'type'    => $attr['type'],
                    'value'   => $value,
                    'matcher' => $attr['matcher'],
                ];

            } elseif (preg_match('/^[a-zA-Z]$/', $crt_char)) {
                $crt_rule['tag_name'] = $this->extractTagName();
            } elseif ($crt_char == '*') {
                $this->offset++;
            } elseif ($crt_char == ':') {
                $crt_rule['pseudo_selectors'][] = $this->extractPseudoSelector();
            } elseif ($crt_char == '[') {

                $this->offset++;
                $attribute = ['type' => $this->extractTagName()];
                $this->findNextRule();
                $attribute['matcher'] = $this->extractAttributeMatcher();

                if ($this->offset < $this->length && $this->getChar() == '"') {
                    $attribute['value'] = $this->extractAttributeValue();
                    $this->offset++;
                }

                if ($this->getChar() != ']') {
                    throw new MalformedCssExpressionException('\']\' expected at offset ' . $this->offset . ', was \'' . $this->getChar() . '\'');
                }

                $this->offset++;

                $crt_rule['attributes'][] = $attribute;

            } else {
                $rules[] = $crt_rule;
                $crt_rule = $this->getEmptyRule();
            }
        }
        $rules[] = $crt_rule;

        return $rules;
    }

    /**
     * Returns an empty CSS rule
     *
     * @return array
     */
    protected function getEmptyRule()
    {
        return [
            'direct_child'      => $this->findNextRule(),
            'attributes'        => [],
            'pseudo_selectors'  => [],
        ];
    }

    /**
     * Return the character at the current offset
     *
     * @param int $offsetIndex
     * @return string
     */
    protected function getChar($offsetIndex = 0)
    {
        return substr($this->selector, $this->offset + $offsetIndex, 1);
    }

    /**
     * Extracts a attribute value
     *
     * @return string
     */
    protected function extractAttributeValue()
    {
        $this->offset++;
        return $this->extract('/^[a-zA-Z0-9_-]$/');
    }

    /**
     * Extracts the matcher type for an attribute
     *
     * @return string
     */
    protected function extractAttributeMatcher()
    {
        $matcher = $this->extract('/^[\!\=\^\$\*\|\~]$/');

        switch ($matcher) {
            case '='  : return 'same';
            case '|=' : return 'contains-prefix';
            case '~=' : return 'contains-word';
            case '$=' : return 'end';
            case '^=' : return 'start';
            case '!=' : return 'not';
            case ''   : return 'none';
            default   : throw new MalformedCssExpressionException('Unrecognized matcher at offset ' . $this->offset . ': \'' . $matcher . '\'');
        }
    }

    /**
     * Extracts a tag name
     *
     * @return string
     */
    protected function extractTagName()
    {
        return $this->extract('/^[a-zA-Z]$/');
    }

    /**
     * Extracts a parameter based on a regex
     *
     * @param string $regex
     * @return string
     */
    protected function extract($regex)
    {
        $i = 0;
        while ($this->offset + $i < $this->length && preg_match($regex, substr($this->selector, $this->offset + $i, 1))) {
            $i++;
        }

        $value = substr($this->selector, $this->offset, $i);
        $this->offset += $i;

        return $value;
    }

    /**
     * Extracts a pseudo selector
     *
     * @return array
     */
    protected function extractPseudoSelector()
    {
        $pseudo_selector = [];

        $i = 1;
        while ($this->offset + $i < $this->length && preg_match('/^[a-z-]$/', substr($this->selector, $this->offset + $i, 1))) {
            $i++;
        }
        $pseudo_selector['name'] = substr($this->selector, $this->offset + 1, $i - 1);
        $this->offset += $i;

        if ($this->getChar() == '(') {
            $i = 1;
            $openned = 1;
            while ($this->offset + $i < $this->length && $openned != 0) {
                $crt_char = $this->getChar($i);
                if ($crt_char == '(') {
                    $openned++;
                } elseif ($crt_char == ')') {
                    $openned--;
                }
                $i++;
            }
            $pseudo_selector['value'] = substr($this->selector, $this->offset + 1, $i - 2);
            $this->offset += $i;
        }

        return $pseudo_selector;
    }

    /**
     * Strips white spaces to the next CSS rule
     *
     * @return bool is the new selector a direct child?
     */
    protected function findNextRule()
    {
        $is_direct_child = false;
        $i = 0;
        while ($this->offset + $i < $this->length && (substr($this->selector, $this->offset + $i, 1) == ' ' || (!$is_direct_child && substr($this->selector, $this->offset + $i, 1) == '>'))) {
            if (!$is_direct_child && substr($this->selector, $this->offset + $i, 1) == '>') {
                $is_direct_child = true;
            }
            $i++;
        }

        $this->offset += $i;

        return $is_direct_child;
    }

    /**
     * Converts an attribute into an XPath expression
     *
     * @param array $attribute
     * @return string
     */
    protected function mkXpathAttributeSelector($attribute)
    {
        switch ($attribute['matcher']) {
            case 'same'            :
                return '@' . $attribute['type'] . '="' . $attribute['value'] . '"';

            case 'start'           :
                return 'starts-with(@' . $attribute['type'] . ', "' . $attribute['value'] . '")';

            case 'end'             :
                // this is ugly!
                return 'contains(concat(@' . $attribute['type'] . ', "___"), "' . $attribute['value'] . '___")';

            case 'contains-word'   :
                return 'contains(concat(" ", @' . $attribute['type'] . ', " "), " ' . $attribute['value'] . ' ")';

            case 'contains-prefix' :
                return '@' . $attribute['type'] . '="' . $attribute['value'] . '"' .
                    ' or ' .
                    'starts-with(@' . $attribute['type'] . ', "' . $attribute['value'] . ' ")' .
                    ' or ' .
                    'starts-with(@' . $attribute['type'] . ', "' . $attribute['value'] . '-")';

            case 'not'             :
                return 'not(@' . $attribute['type'] . '="' . $attribute['value'] . '")';

            case 'none'            :
                return '@' . $attribute['type'];

        }
    }

    /**
     * Converts a pseudo selector into an XPath expression
     *
     * @param array $pseudo_selector
     * @return string
     */
    protected function mkXpathPseudoSelector($pseudo_selector)
    {
        switch ($pseudo_selector['name']) {
            case 'first-child':
                return 'position() = 1';

            case 'last-child':
                return 'position() = last()';

            case 'nth-child':
                $position = $pseudo_selector['value'];
                if (is_numeric($position)) {
                    return 'position() = ' . $position;
                }

                preg_match_all('/^(\-)?([0-9]+)?(\+|\-)?([0-9])+(n)?(\+|\-)?(\-?[0-9]+)?/', $position, $params);
                $offset = ($params[1][0] == '-' ? -$params[2][0] : $params[2][0]) + ($params[6][0] == '-' ? -$params[7][0] : $params[7][0]);
                $factor = $params[3][0] == '-' ? -$params[4][0] : $params[4][0];

                return '(position() + ' . (-$offset) . ') mod ' . $factor . ' = 0' . ($offset >= 0 ? ' and position() >= ' .$offset : '');

            case 'not':
                // not value is a CSS selector: parse it
                return 'not(' . preg_replace('%^(/\*/(descendant::)?\*\[)(.*)(\])%U', '$3', self::parse($pseudo_selector['value'])) . ')';

            default: throw new MalformedCssExpressionException('Unrecognized pseudo selector \'' . $pseudo_selector['name'] . '\'');
        }
    }

    /**
     * Translates a set of rules into an xpath expression
     *
     * @param array $rules
     * @return string
     */
    protected function rules2Xpath($rules)
    {
        $xpath = '/*';

        foreach ($rules as $rule) {

            $xpath .= $rule['direct_child'] ? '/*' : '/descendant::*';

            if (isset($rule['tag_name'])) {
                $xpath .= '[name() = "' .$rule['tag_name'] . '"]';
            }

            foreach ($rule['attributes'] as $attribute) {
                $xpath .= '[' . $this->mkXpathAttributeSelector($attribute) . ']';
            }

            foreach ($rule['pseudo_selectors'] as $pseudo_selector) {
                $xpath .= '[' . $this->mkXpathPseudoSelector($pseudo_selector) . ']';
            }

        }

        return $xpath;
    }

}
