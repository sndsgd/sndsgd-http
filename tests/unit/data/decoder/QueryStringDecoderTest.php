<?php

namespace sndsgd\http\data\decoder;

/**
 * @coversDefaultClass \sndsgd\http\data\decoder\QueryStringDecoder
 */
class QueryStringDecoderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers ::decodePair
     * @dataProvider provideDecodePair
     */
    public function testDecodePair($pair, $expectKey, $expectValue)
    {
        $decoder = new QueryStringDecoder(1);
        $result = $decoder->decodePair($pair);
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertSame($result[0], $expectKey);
        $this->assertSame($result[1], $expectValue);
    }

    public function provideDecodePair()
    {
        return [
            [
                "test=value",
                "test",
                "value",
            ],
            [
                "test=this+has+spaces",
                "test",
                "this has spaces",
            ],
            [
                'test=~%60%21%40%23%24%25%5E%26%2A%28%29_-%3D%2B',
                'test', 
                '~`!@#$%^&*()_-=+',
            ],
            [
                "one%5Btwo%5D%5B%5D%5Ba%5D=%C2%A1%E2%84%A2%C2%A3%C2%A2%E2%88%9E%C2%A7%C2%B6%E2%80%A2%C2%AA%C2%BA",
                "one[two][][a]",
                "¡™£¢∞§¶•ªº",
            ],
            [
                "emoji=💩",
                "emoji",
                "💩",
            ],
            [
                "%F0%9F%92%A9%5B%F0%9F%92%A9%5D=emoji",
                "💩[💩]",
                "emoji",
            ],
        ];
    }
}
