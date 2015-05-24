<?php

namespace RPBaseTest\Css2Xpath;

use RPBase\Css2Xpath\Parser;

class ParserTest extends \PHPUnit_Framework_TestCase
{

    public function testExtractAnySelector()
    {
        $this->assertEquals('/*/descendant::*', Parser::parse('*'));
    }

    public function testExtractRulesTagName()
    {
        $this->assertEquals('/*/descendant::*[name() = "span"]', Parser::parse('span'));
    }

    public function testExtractRulesId()
    {
        $this->assertEquals('/*/descendant::*[@id="id"]', Parser::parse('#id'));
    }

    public function testExtractRulesClass()
    {
        $this->assertEquals('/*/descendant::*[contains(concat(" ", @class, " "), " classname ")]', Parser::parse('.classname'));
    }

    public function testExtractRuleHasAttribute()
    {
        $this->assertEquals('/*/descendant::*[@href]', Parser::parse('[href]'));
    }

    public function testExtractRuleAttributeWithValue()
    {
        $this->assertEquals('/*/descendant::*[@href="url"]', Parser::parse('[href="url"]'));
    }

    public function testExtractRuleAttributeWithValueStartingWith()
    {
        $this->assertEquals('/*/descendant::*[starts-with(@href, "url")]', Parser::parse('[href^="url"]'));
    }

    public function testExtractRuleAttributeWithValueEndingWith()
    {
        $this->assertEquals('/*/descendant::*[contains(concat(@href, "___"), "url___")]', Parser::parse('[href$="url"]'));
    }

    public function testExtractRuleAttributeWithValueContains()
    {
        $this->assertEquals('/*/descendant::*[contains(@href, "url")]', Parser::parse('[href*="url"]'));
    }

    public function testExtractRuleAttributeWithValueContainsWords()
    {
        $this->assertEquals('/*/descendant::*[contains(concat(" ", @href, " "), " url ")]', Parser::parse('[href~="url"]'));
    }

    public function testExtractRuleAttributeWithValueContainsPrefix()
    {
        $this->assertEquals('/*/descendant::*[@href="url" or starts-with(@href, "url ") or starts-with(@href, "url-")]', Parser::parse('[href|="url"]'));
    }

    public function testExtractRuleAttributeWithValueNot()
    {
        $this->assertEquals('/*/descendant::*[not(@href="url")]', Parser::parse('[href!="url"]'));
    }

    public function testExtractRuleAttributeWithOtherMatcherWillThrowAnException()
    {
        $this->setExpectedException('RPBase\Css2Xpath\MalformedCssExpressionException', "]' expected at offset 5, was '+'");
        Parser::parse('[href+"url"]');
    }

    public function testCanExtractComplexAttributeValues()
    {
        $this->assertEquals('/*/descendant::*[starts-with(@href, "/url")]', Parser::parse('[href^="/url"]'));
    }

    public function testCanCombineSelectors()
    {
        $this->assertEquals('/*/descendant::*[name() = "a"][contains(concat(" ", @class, " "), " classname ")][starts-with(@href, "url")]', Parser::parse('a.classname[href^="url"]'));
    }

    public function testCanExtractFirstChildPseudoSelector()
    {
        $this->assertEquals('/*/descendant::*[name() = "a"][position() = 1]', Parser::parse('a:first-child'));
    }

    public function testCanExtractLastChildPseudoSelector()
    {
        $this->assertEquals('/*/descendant::*[name() = "a"][position() = last()]', Parser::parse('a:last-child'));
    }

    public function testCanExtractNthChildSelectorWithNumber()
    {
        $this->assertEquals('/*/descendant::*[name() = "a"][position() = 2]', Parser::parse('a:nth-child(2)'));
    }

    public function testCanExtractNthChildSelectorWithN()
    {
        $this->assertEquals('/*/descendant::*[name() = "a"][(position() + 0) mod 3 = 0 and position() >= 0]', Parser::parse('a:nth-child(3n)'));
    }

    public function testCanExtractNthChildSelectorWithNPlus()
    {
        $this->assertEquals('/*/descendant::*[name() = "a"][(position() - 2) mod 3 = 0 and position() >= 2]', Parser::parse('a:nth-child(3n + 2)'));
    }

    public function testCanExtractNthChildSelectorWithNMinus()
    {
        $this->assertEquals('/*/descendant::*[name() = "a"][(position() + 1) mod 3 = 0]', Parser::parse('a:nth-child(3n - 1)'));
    }

    public function testCanExtractNotPseudoSelector()
    {
        $this->assertEquals('/*/descendant::*[not(name() = "a")]', Parser::parse(':not(a)'));
    }

    public function testCanChainSelectors()
    {
        $this->assertEquals('/*/descendant::*[@id="id"]/descendant::*[contains(concat(" ", @class, " "), " classname ")]', Parser::parse('#id .classname'));
    }

    public function testCanSelectDirectChildren()
    {
        $this->assertEquals('/*/descendant::*[@id="id"]/*[contains(concat(" ", @class, " "), " classname ")]', Parser::parse('#id > .classname'));
    }

    public function testCanSelectAnyDirectChildren()
    {
        $this->assertEquals('/*/descendant::*[@id="id"]/*', Parser::parse('#id > *'));
    }

}
