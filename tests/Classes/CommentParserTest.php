<?php

declare(strict_types = 1);

namespace Weiran\Core\Tests\Classes;

use Weiran\Core\Classes\Inspect\CommentParser;
use Weiran\Framework\Application\TestCase;

class CommentParserTest extends TestCase
{
    public function testParser()
    {
        $data = /** @lang text */
            '<?php
	class FooBar
	{
		
		/**
		 * Short description about class
		 * 
		 * Foo Foo Foo
		 *
		 * @param PamAccount $value 描述
		 * @param \DomNode   $node  Node 描述
		 * @param value2
		 * @param Complex Value !@#$%^&*()_+-= Foo!
		 * @throws ApplicationException
		 */
		public function foo() 
		{
			// Bogus
		}		
	}';

        $p = new CommentParser();
        $x = $p->parseContent($data);

        $this->assertArrayHasKey('foo', $x);
        $this->assertEquals('PamAccount', $x['foo']['params'][0]['var_type']);
        $this->assertEquals('描述', $x['foo']['params'][0]['var_desc']);
        $this->assertEquals('$value', $x['foo']['params'][0]['var_name']);
        $this->assertEquals('param', $x['foo']['params'][0]['type']);
        $this->assertEquals('ApplicationException', $x['foo']['throws']);
    }

    public function testMethod()
    {
        $method = '
        /**
		 * Short description about class
		 *
		 * @param PamAccount $value 描述
		 * @param \DomNode   $node  Node 描述
		 * @param value2
		 * @param Complex Value !@#$%^&*()_+-= Foo!
		 * @throws ApplicationException
		 */';

        $p = new CommentParser();
        $x = $p->parseMethod($method);

        $this->assertEquals('Short description about class', $x['description']);
    }
}
