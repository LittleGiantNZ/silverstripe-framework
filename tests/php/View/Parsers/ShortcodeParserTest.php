<?php

namespace SilverStripe\View\Tests\Parsers;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\View\Parsers\ShortcodeParser;

class ShortcodeParserTest extends SapphireTest
{

    protected $arguments, $contents, $tagName, $parser;
    protected $extra = [];

    protected function setUp(): void
    {
        ShortcodeParser::get('test')->register('test_shortcode', [$this, 'shortcodeSaver']);
        $this->parser = ShortcodeParser::get('test');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        ShortcodeParser::get('test')->unregister('test_shortcode');

        parent::tearDown();
    }

    /**
     * Tests that valid short codes that have not been registered are not replaced.
     */
    public function testNotRegisteredShortcode()
    {
        ShortcodeParser::$error_behavior = ShortcodeParser::STRIP;

        $this->assertEquals(
            '',
            $this->parser->parse('[not_shortcode]')
        );

        $this->assertEquals(
            '<img class="">',
            $this->parser->parse('<img class="[not_shortcode]">')
        );

        ShortcodeParser::$error_behavior = ShortcodeParser::WARN;

        $this->assertEquals(
            '<strong class="warning">[not_shortcode]</strong>',
            $this->parser->parse('[not_shortcode]')
        );

        ShortcodeParser::$error_behavior = ShortcodeParser::LEAVE;

        $this->assertEquals(
            '[not_shortcode]',
            $this->parser->parse('[not_shortcode]')
        );
        $this->assertEquals(
            '[not_shortcode /]',
            $this->parser->parse('[not_shortcode /]')
        );
        $this->assertEquals(
            '[not_shortcode,foo="bar"]',
            $this->parser->parse('[not_shortcode,foo="bar"]')
        );
        $this->assertEquals(
            '[not_shortcode]a[/not_shortcode]',
            $this->parser->parse('[not_shortcode]a[/not_shortcode]')
        );
        $this->assertEquals(
            '[/not_shortcode]',
            $this->parser->parse('[/not_shortcode]')
        );

        $this->assertEquals(
            '<img class="[not_shortcode]">',
            $this->parser->parse('<img class="[not_shortcode]">')
        );
    }

    public function simpleTagDataProvider()
    {
        return [
            ['[test_shortcode]'],
            ['[test_shortcode ]'],
            ['[test_shortcode,]'],
            ['[test_shortcode, ][test_shortcode/]'],
            ['[test_shortcode /]'],
            ['[test_shortcode,/]'],
            ['[test_shortcode, /]']
        ];
    }

    /**
     * @dataProvider simpleTagDataProvider
     */
    public function testSimpleTag($test)
    {
        $this->parser->parse($test);
        $this->assertEquals([], $this->arguments, $test);
        $this->assertEquals('', $this->contents, $test);
        $this->assertEquals('test_shortcode', $this->tagName, $test);
    }

    public function oneArgumentDataProvider()
    {
        return [
            ['[test_shortcode foo="bar"]'],
            ['[test_shortcode,foo="bar"]'],
            ["[test_shortcode foo='bar']"],
            ["[test_shortcode,foo='bar']"],
            ["[test_shortcode foo=bar]"],
            ["[test_shortcode,foo=bar]"],
            ['[test_shortcode  foo  =  "bar"  /]'],
            ['[test_shortcode,  foo  =  "bar"  /]']
        ];
    }

    /**
     * @dataProvider oneArgumentDataProvider
     */
    public function testOneArgument($test)
    {
        $this->parser->parse($test);

        $this->assertEquals(['foo' => 'bar'], $this->arguments, $test);
        $this->assertEquals('', $this->contents, $test);
        $this->assertEquals('test_shortcode', $this->tagName, $test);
    }

    public function testMultipleArguments()
    {
        $this->parser->parse('[test_shortcode foo = "bar",bar=\'foo\', baz="buz"]');

        $this->assertEquals(['foo' => 'bar', 'bar' => 'foo', 'baz' => 'buz'], $this->arguments);
        $this->assertEquals('', $this->contents);
        $this->assertEquals('test_shortcode', $this->tagName);
    }

    public function emptyArgumentsDataProvider()
    {
        return [
            ['[test_shortcode foo=""]'],
            ['[test_shortcode,foo=\'\']'],
            ['[test_shortcode foo=""][/test_shortcode]'],
        ];
    }

    /**
     * @dataProvider emptyArgumentsDataProvider
     */
    public function testEmptyArguments($test)
    {
        $this->parser->parse($test);
        $this->assertEquals(['foo' => ''], $this->arguments);
        $this->assertEquals('', $this->contents);
        $this->assertEquals('test_shortcode', $this->tagName);
    }

    public function testEnclosing()
    {
        $this->parser->parse('[test_shortcode]foo[/test_shortcode]');

        $this->assertEquals([], $this->arguments);
        $this->assertEquals('foo', $this->contents);
        $this->assertEquals('test_shortcode', $this->tagName);
    }

    public function testEnclosingWithArguments()
    {
        $this->parser->parse('[test_shortcode,foo = "bar",bar=\'foo\',baz="buz"]foo[/test_shortcode]');

        $this->assertEquals(['foo' => 'bar', 'bar' => 'foo', 'baz' => 'buz'], $this->arguments);
        $this->assertEquals('foo', $this->contents);
        $this->assertEquals('test_shortcode', $this->tagName);
    }

    public function testShortcodeEscaping()
    {
        $this->assertEquals(
            '[test_shortcode]',
            $this->parser->parse('[[test_shortcode]]')
        );

        $this->assertEquals(
            '[test_shortcode /]',
            $this->parser->parse('[[test_shortcode /]]')
        );

        $this->assertEquals(
            '[test_shortcode]content[/test_shortcode]',
            $this->parser->parse('[[test_shortcode]content[/test_shortcode]]')
        );

        $this->assertEquals(
            '[test_shortcode]content',
            $this->parser->parse('[[test_shortcode]][test_shortcode]content[/test_shortcode]')
        );

        $this->assertEquals(
            '[test_shortcode]content[/test_shortcode]content2',
            $this->parser->parse('[[test_shortcode]content[/test_shortcode]][test_shortcode]content2[/test_shortcode]')
        );

        $this->assertEquals(
            '[[Doesnt strip double [ character if not a shortcode',
            $this->parser->parse('[[Doesnt strip double [ character if not a [test_shortcode]shortcode[/test_shortcode]')
        );

        $this->assertEquals(
            '[[Doesnt shortcode get confused by double ]] characters',
            $this->parser->parse(
                '[[Doesnt [test_shortcode]shortcode[/test_shortcode] get confused by double ]] characters'
            )
        );
    }

    public function testUnquotedArguments()
    {
        $this->assertEquals('', $this->parser->parse('[test_shortcode,foo=bar!,baz = buz123]'));
        $this->assertEquals(['foo' => 'bar!', 'baz' => 'buz123'], $this->arguments);
    }

    public function testSpacesForDelimiter()
    {
        $this->assertEquals('', $this->parser->parse('[test_shortcode foo=bar! baz = buz123]'));
        $this->assertEquals(['foo' => 'bar!', 'baz' => 'buz123'], $this->arguments);
    }

    public function testSelfClosingTag()
    {
        $this->assertEquals(
            'morecontent',
            $this->parser->parse('[test_shortcode,id="1"/]more[test_shortcode,id="2"]content[/test_shortcode]'),
            'Assert that self-closing tags are respected during parsing.'
        );

        $this->assertEquals(2, $this->arguments['id']);
    }

    public function testConsecutiveTags()
    {
        $this->assertEquals('', $this->parser->parse('[test_shortcode][test_shortcode]'));
    }

    protected function assertEqualsIgnoringWhitespace($a, $b, $message = '')
    {
        $this->assertEquals(preg_replace('/\s+/', '', $a), preg_replace('/\s+/', '', $b), $message);
    }

    public function testExtractBefore()
    {
        // Left extracts to before the current block
        $this->assertEqualsIgnoringWhitespace(
            'Code<div>FooBar</div>',
            $this->parser->parse('<div>Foo[test_shortcode class=left]Code[/test_shortcode]Bar</div>')
        );
        // Even if the immediate parent isn't a the current block
        $this->assertEqualsIgnoringWhitespace(
            'Code<div>Foo<b>BarBaz</b>Qux</div>',
            $this->parser->parse('<div>Foo<b>Bar[test_shortcode class=left]Code[/test_shortcode]Baz</b>Qux</div>')
        );
    }

    public function testExtractSplit()
    {
        $this->markTestSkipped(
            'Feature disabled due to https://github.com/silverstripe/silverstripe-framework/issues/5987'
        );
        // Center splits the current block
        $this->assertEqualsIgnoringWhitespace(
            '<div>Foo</div>Code<div>Bar</div>',
            $this->parser->parse('<div>Foo[test_shortcode class=center]Code[/test_shortcode]Bar</div>')
        );
        // Even if the immediate parent isn't a the current block
        $this->assertEqualsIgnoringWhitespace(
            '<div>Foo<b>Bar</b></div>Code<div><b>Baz</b>Qux</div>',
            $this->parser->parse('<div>Foo<b>Bar[test_shortcode class=center]Code[/test_shortcode]Baz</b>Qux</div>')
        );
    }

    public function testExtractNone()
    {
        // No class means don't extract
        $this->assertEqualsIgnoringWhitespace(
            '<div>FooCodeBar</div>',
            $this->parser->parse('<div>Foo[test_shortcode]Code[/test_shortcode]Bar</div>')
        );
    }

    public function testShortcodesInsideScriptTag()
    {
        $this->assertEqualsIgnoringWhitespace(
            '<script>hello</script>',
            $this->parser->parse('<script>[test_shortcode]hello[/test_shortcode]</script>')
        );
    }

    public function testFalseyArguments()
    {
        $this->parser->parse('<p>[test_shortcode falsey=0]');

        $this->assertEquals(
            [
            'falsey' => '',
            ],
            $this->arguments
        );
    }

    public function testNumericShortcodes()
    {
        $this->assertEqualsIgnoringWhitespace(
            '[2]',
            $this->parser->parse('[2]')
        );
        $this->assertEqualsIgnoringWhitespace(
            '<script>[2]</script>',
            $this->parser->parse('<script>[2]</script>')
        );

        $this->parser->register(
            '2',
            function () {
                return 'this is 2';
            }
        );

        $this->assertEqualsIgnoringWhitespace(
            'this is 2',
            $this->parser->parse('[2]')
        );
        $this->assertEqualsIgnoringWhitespace(
            '<script>this is 2</script>',
            $this->parser->parse('<script>[2]</script>')
        );

        $this->parser->unregister('2');
    }

    public function testExtraContext()
    {
        $this->parser->parse('<a href="[test_shortcode]">Test</a>');

        $this->assertInstanceOf('DOMNode', $this->extra['node']);
        $this->assertInstanceOf('DOMElement', $this->extra['element']);
        $this->assertEquals($this->extra['element']->tagName, 'a');
    }

    public function testShortcodeWithAnchorAndQuerystring()
    {
        $result = $this->parser->parse('<a href="[test_shortcode]?my-string=this&thing=2#my-anchor">Link</a>');

        $this->assertStringContainsString('my-string=this', $result);
        $this->assertStringContainsString('thing=2', $result);
        $this->assertStringContainsString('my-anchor', $result);
    }

    public function testNoParseAttemptIfNoCode()
    {
        $stub = $this->getMockBuilder(ShortcodeParser::class)->setMethods(['replaceElementTagsWithMarkers'])
            ->getMock();
        $stub->register(
            'test',
            function () {
                return '';
            }
        );

        $stub->expects($this->never())
            ->method('replaceElementTagsWithMarkers')->will($this->returnValue(['', '']));

        $stub->parse('<p>test</p>');
    }

    public function testSelfClosingHtmlTags()
    {
        $this->parser->register('img', function () {
            return '<img src="http://example.com/image.jpg">';
        });

        $result = $this->parser->parse('[img]');

        $this->assertStringContainsString('http://example.com/image.jpg', $result);
    }

    // -----------------------------------------------------------------------------------------------------------------

    /**
     * Stores the result of a shortcode parse in object properties for easy testing access.
     */
    public function shortcodeSaver($arguments, $content, $parser, $tagName, $extra)
    {
        $this->arguments = $arguments;
        $this->contents = $content;
        $this->tagName = $tagName;
        $this->extra = $extra;

        return $content;
    }
}
