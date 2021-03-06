<?php

namespace sndsgd\http;

/**
 * @coversDefaultClass \sndsgd\http\Response
 */
class ResponseTest extends \PHPUnit\Framework\TestCase
{
    protected $response;

    public function setup()
    {
        $this->response = new Response();
    }

    /**
     * @covers ::__construct
     * @dataProvider providerConstructor
     */
    public function testConstructor($statusCode, $body)
    {
        $response = new Response($statusCode, null, $body);
        $this->assertSame($statusCode, $response->getStatusCode());
        $this->assertSame($body, $response->getBody());
    }

    public function providerConstructor()
    {
        return [
            [\sndsgd\http\Status::CREATED, ""],
            [\sndsgd\http\Status::OK, "test"],
        ];
    }

    /**
     * @covers ::setStatus
     * @covers ::getStatusCode
     * @covers ::getStatusText
     * @dataProvider providerSetStatus
     */
    public function testSetStatus($code, $text, $exception = "")
    {
        if ($exception) {
            $this->expectException($exception);
        }

        $this->response->setStatus($code);
        $this->assertSame($code, $this->response->getStatusCode());
        $this->assertSame($text, $this->response->getStatusText());
    }

    public function providerSetStatus()
    {
        $ret = [
            [1, "", \InvalidArgumentException::class],
        ];

        foreach (Status::MESSAGES as $code => $text) {
            $ret[] = [$code, $text, ""];
        }
        return $ret;
    }

    /**
     * @covers ::setHeader
     * @covers ::getHeader
     * @dataProvider providerSetGetHeader
     */
    public function testSetGetHeader($key, $value, $expect)
    {
        $this->response->setHeader($key, $value);
        $this->assertSame($expect, $this->response->getHeader($key));
    }

    public function providerSetGetHeader()
    {
        return [
            ["string", "abc", "abc"],
            ["number", 100, "100"],
        ];
    }

    /**
     * @covers ::setHeaders
     * @covers ::getHeader
     * @dataProvider providerSetHeaders
     */
    public function testSetHeaders($headers)
    {
        $this->response->setHeaders($headers);
        foreach ($headers as $key => $value) {
            $this->assertSame((string) $value, $this->response->getHeader($key));
        }
    }

    public function providerSetHeaders()
    {
        return [
            [
                [
                    "one" => "one",
                    "some-other" => "value",
                ],
            ],
        ];
    }

    /**
     * @covers ::addHeader
     * @dataProvider providerAddHeader
     */
    public function testAddHeader(array $headers, $expect)
    {
        foreach ($headers as list($key, $value)) {
            $this->response->addHeader($key, $value);
        }

        $this->assertSame($expect, $this->response->getHeader($key));
    }

    public function providerAddHeader()
    {
        return [
            [
                [["test", "one"]],
                "one",
            ],
            [
                [["test", "one"], ["test", "two"]],
                "one, two",
            ],
            [
                [["test", "one"], ["test", "two"], ["test", "💩"]],
                "one, two, 💩",
            ],
        ];
    }

    /**
     * @covers ::setBody
     * @covers ::getBody
     * @dataProvider providerSetGetBody
     */
    public function testSetBody($body, $expectLength)
    {
        $this->response->setBody($body);
        $contentLength = $this->response->getHeader("Content-Length");
        $this->assertSame($expectLength, (int) $contentLength);
        $this->assertSame($body, $this->response->getBody());
    }

    public function providerSetGetBody()
    {
        $length = mt_rand(100, 1000);
        $str = \sndsgd\Str::random($length);
        return [
            [$str, $length],
        ];
    }

    /**
     * @dataProvider providerSend
     * @runInSeparateProcess
     */
    public function testSend($protocol, $code, $headers, $body, $expectHeaders)
    {
        $this->expectOutputString($body);

        $this->response
            ->setStatus($code)
            ->setHeaders($headers)
            ->setBody($body)
            ->send($protocol);

        $this->getAndTestHeaders($expectHeaders);
    }

    public function providerSend()
    {
        return [
            [
                "HTTP 2.0",
                200,
                [
                    "x-one" => "one",
                    "x-two" => "two",
                ],
                "hello, world",
                [
                    "HTTP 2.0 200 OK",
                    "x-one: one",
                    "x-two: two",
                    "Content-Length: 12"
                ],
            ],
        ];
    }

    private function getAndTestHeaders(array $expect)
    {
        if (extension_loaded("xdebug")) {
            $headers = xdebug_get_headers();
            for ($i=0, $len=count($expect); $i<$len; $i++) {
                $expectHeader = strtolower($expect[$i]);
                $realHeader = strtolower($headers[$i]);
                // the content type header can include the charset (;charset=UTF-8)
                if (\sndsgd\Str::beginsWith($expectHeader, "content-type:")) {
                    $realHeader = substr($realHeader, 0, strlen($expectHeader));
                    $this->assertEquals($expectHeader, $realHeader);
                }
                else {
                    $this->assertEquals($expectHeader, $realHeader);
                }
            }
        }
    }
}
