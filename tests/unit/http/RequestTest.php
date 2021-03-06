<?php

namespace sndsgd\http;

/**
 * @coversDefaultClass \sndsgd\http\Request
 */
class RequestTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @covers ::__construct
     * @covers ::getEnvironment
     * @covers ::getDecoderOptions
     * @dataProvider providerConstructor
     */
    public function testConstructor(array $server)
    {
        $environment = createTestEnvironment($server);
        $request = new Request($environment);
        $this->assertSame($environment, $request->getEnvironment());
        $this->assertInstanceOf(
            \sndsgd\http\data\decoder\DecoderOptions::class,
            $request->getDecoderOptions()
        );
    }

    public function providerConstructor()
    {
        return [
            [
                [
                    "key" => \sndsgd\Str::random(100),
                ],
            ],
            [
                [
                    "REQUEST_METHOD" => "GET",
                    "REQUEST_URI" => "/some/path?query=value"
                ],
            ],
            [
                [
                    "REQUEST_METHOD" => "POST",
                    "REQUEST_URI" => "/1/a/2/b/3/c/?query=value"
                ],
            ],
        ];
    }

    /**
     * @covers ::getHost
     */
    public function testGetHost()
    {
        $domain = "example.com";
        $request = new Request(createTestEnvironment(["HTTP_HOST" => "example.com"]));
        $host = $request->getHost();
        $this->assertSame($domain, $host->getDnsName());
    }

    /**
     * @covers ::getClient
     */
    public function testGetClient()
    {
        $ip = "1.1.1.1";
        $request = new Request(createTestEnvironment(["REMOTE_ADDR" => $ip]));
        $client = $request->getClient();
        $this->assertSame($ip, $client->getIp());
    }

    /**
     * @covers ::getMethod
     * @dataProvider providerGetMethod
     */
    public function testGetMethod(array $server, $expect)
    {
        $req = new Request(createTestEnvironment($server));
        $this->assertSame($expect, $req->getMethod());
        $this->assertSame($expect, $req->getMethod());
    }

    public function providerGetMethod()
    {
        $methods = [
            "GET",
            "POST",
            "PATCH",
            "PUT",
            "DELETE",
            "HEAD",
            "OPTIONS",
            \sndsgd\Str::random(32),
            \sndsgd\Str::random(32),
        ];

        $ret = [[[], "GET"]];
        foreach ($methods as $method) {
            $ret[] = [["REQUEST_METHOD" => $method], $method];
        }
        return $ret;
    }

    /**
     * @covers ::getPath
     * @dataProvider providerGetPath
     */
    public function testGetPath(array $server, $expect)
    {
        $req = new Request(createTestEnvironment($server));
        $this->assertSame($expect, $req->getPath());
        $this->assertSame($expect, $req->getPath());
    }

    public function providerGetPath()
    {
        return [
            [[], "/"],
            [["REQUEST_URI" => "/"], "/"],
            [["REQUEST_URI" => "/a/b/c?a=1&b=2&c=3"], "/a/b/c"],
            [["REQUEST_URI" => "/a/b/c/?a=1&b=2&c=3"], "/a/b/c/"],
            [["REQUEST_URI" => "/test/@/:?a=1&b=2&c=3"], "/test/@/:"],
        ];
    }

    /**
     * @covers ::getProtocol
     * @dataProvider providerGetProtocol
     */
    public function testGetProtocol($server, $expect)
    {
        $req = new Request(createTestEnvironment($server));
        $this->assertSame($expect, $req->getProtocol());
    }

    public function providerGetProtocol()
    {
        return [
            [[], "HTTP/1.1"],
            [["SERVER_PROTOCOL" => "asd"], "asd"],
        ];
    }

    /**
     * @covers ::getScheme
     * @dataProvider providerGetScheme
     */
    public function testGetScheme($server, $expect)
    {
        $req = new Request(createTestEnvironment($server));
        $this->assertSame($expect, $req->getScheme());
    }

    public function providerGetScheme()
    {
        return [
            [["HTTP_X_FORWARDED_PROTO" => "https"], Scheme::HTTPS],
            [["HTTP_X_FORWARDED_PROTO" => "http"], Scheme::HTTP],
            [["HTTP_X_FORWARDED_PROTO" => "invalid"], Scheme::HTTP],
            [["HTTP_X_FORWARDED_PROTO" => "invalid", "HTTPS" => "on"], Scheme::HTTPS],
            [["HTTPS" => "on"], Scheme::HTTPS],
            [["HTTPS" => "whatever"], Scheme::HTTPS],
            [["SERVER_PORT" => 443], Scheme::HTTPS],
            [["SERVER_PORT" => "443"], Scheme::HTTPS],
            [["SERVER_PORT" => 80], Scheme::HTTP],
            [["SERVER_PORT" => 42], Scheme::HTTP],
            [[], Scheme::HTTP],
        ];
    }

    /**
     * @covers ::isHttps
     * @dataProvider providerGetScheme
     */
    public function testIsHttps(array $server, string $scheme)
    {
        $req = new Request(createTestEnvironment($server));
        $this->assertSame($scheme === Scheme::HTTPS, $req->isHttps());
    }

    /**
     * @covers ::getAcceptContentTypes
     * @dataProvider acceptContentTypeProviders
     */
    public function testGetAcceptContentTypes($header, $expect)
    {
        $environment = createTestEnvironment(["HTTP_ACCEPT" => $header]);
        $req = new Request($environment);
        $this->assertEquals($expect, $req->getAcceptContentTypes());
    }

    public function acceptContentTypeProviders()
    {
        return [
            [
                "application/json,image/webp,*/*;q=0.8",
                [
                    "application/json" => "application/json",
                    "image/webp" => "image/webp",
                    "*/*" => "*/*",
                ],
            ],
            [
                "application/json,image/webp,*/*;q=0.8",
                [
                    "application/json" => "application/json",
                    "image/webp" => "image/webp",
                    "*/*" => "*/*",
                ],
            ],
            [
                "text/html,application/xml;q=0.9,image/webp,*/*;q=0.8",
                [
                    "text/html" => "text/html",
                    "application/xml" => "application/xml",
                    "image/webp" => "image/webp",
                    "*/*" => "*/*",
                ],
            ],
            [
                "application/xml,*/*;asd=1.0",
                [
                    "application/xml" => "application/xml",
                    "*/*" => "*/*",
                ],
            ],
            [
                "TEXT/html",
                [
                    "text/html" => "text/html",
                ],
            ],
            [
                "",
                [],
            ],
        ];
    }

    /**
     * @dataProvider providerGetHeader
     */
    public function testGetHeader($server, $header, $default, $expect)
    {
        $request = new Request(createTestEnvironment($server));
        $this->assertSame($expect, $request->getHeader($header, $default));
    }

    public function providerGetHeader()
    {
        return [
            [
                ["HTTP_SOME_VALUE" => "test"],
                "some-value",
                "",
                "test",
            ],
            [
                ["HTTP_SOME_VALUE" => "test"],
                "SOME-VALUE",
                "",
                "test",
            ],
            [
                ["HTTP_SOME_VALUE" => "test"],
                "a-value",
                "default",
                "default",
            ],
        ];
    }

    /**
     * @dataProvider providerGetHeaders
     */
    public function testGetHeaders($server, $expect)
    {
        $request = new Request(createTestEnvironment($server));
        $this->assertSame($expect, $request->getHeaders());
    }

    public function providerGetHeaders()
    {
        return [
            [
                ["HTTP_ONE" => "1", "HTTP_OTHER_VALUE" => "asd"],
                ["one" => "1", "other-value" => "asd"],
            ],
        ];
    }

    /**
     * @dataProvider providerGetContentType
     */
    public function testGetContentType($server, $expect)
    {
        $request = new Request(createTestEnvironment($server));
        $this->assertSame($expect, $request->getContentType());
    }

    public function providerGetContentType()
    {
        return [
            [
                ["HTTP_CONTENT_TYPE" => "application/json"],
                "application/json",
            ],
            [
                ["HTTP_CONTENT_TYPE" => "application/json; charset=UTF-8"],
                "application/json",
            ],
            [
                ["HTTP_CONTENT_TYPE" => "TEXT/html"],
                "text/html",
            ],
        ];
    }

    /**
     * @dataProvider providerGetContentLength
     */
    public function testGetContentLength($server, $expect)
    {
        $request = new Request(createTestEnvironment($server));
        $this->assertSame($expect, $request->getContentLength());
    }

    public function providerGetContentLength()
    {
        return [
            [[], 0],
            [["HTTP_CONTENT_LENGTH" => "42"], 42],
        ];
    }

    /**
     * @dataProvider providerGetBasicAuth
     */
    public function testGetBasicAuth($server, $expect)
    {
        $request = new Request(createTestEnvironment($server));
        $this->assertSame($expect, $request->getBasicAuth());
    }

    public function providerGetBasicAuth()
    {
        return [
            [
                [],
                ["", ""],
            ],
            [
                ["PHP_AUTH_USER" => "user", "PHP_AUTH_PW" => "pass"],
                ["user", "pass"],
            ]
        ];
    }

    /**
     * @covers ::getCookies
     * @covers ::parseCookies
     * @dataProvider providerGetCookies
     */
    public function testGetCookies(array $environment, array $expect)
    {
        $request = new Request(createTestEnvironment($environment));
        $this->assertSame($expect, $request->getCookies());
    }

    public function providerGetCookies(): array
    {
        $encoded = "!@#$%^&´∑®¥¨øçß´;";
        return [
            [
                [],
                [],
            ],
            [
                ["HTTP_COOKIE" => "a=1; b=abc; c=123"],
                ["a" => "1", "b" => "abc", "c" => "123"]
            ],
            # indexed array
            [
                ["HTTP_COOKIE" => "a=1; b[]=2; b[]=3"],
                ["a" => "1", "b" => ["2", "3"]],
            ],
            # associative array
            [
                ["HTTP_COOKIE" => "a=1; b[two]=2; b[three]=3"],
                ["a" => "1", "b" => ["two" => "2", "three" => "3"]],
            ],
            # encoded content
            [
                ["HTTP_COOKIE" => sprintf("a=%s", urlencode($encoded))],
                ["a" => $encoded],
            ],

            # trailing semicolon
            [
                ["HTTP_COOKIE" => "a=1; b=2;"],
                ["a" => "1", "b" => "2"],
            ],
            # missing value
            [
                ["HTTP_COOKIE" => "a=1; b="],
                ["a" => "1"],
            ],
            # invalid cookie
            [
                ["HTTP_COOKIE" => "a=1; b"],
                ["a" => "1"],
            ],
        ];
    }

    /**
     * @covers ::getCookie
     * @covers ::parseCookies
     * @dataProvider providerGetCookie
     */
    public function testGetCookie(
        array $environment,
        string $name,
        $default,
        $expect
    )
    {
        $request = new Request(createTestEnvironment($environment));
        $this->assertSame($expect, $request->getCookie($name, $default));
    }

    public function providerGetCookie()
    {
        return [
            [["HTTP_COOKIE" => "a=1; b=2"], "a", 0, "1"],
            [["HTTP_COOKIE" => "a=1; b=2"], "b", 0, "2"],
            [["HTTP_COOKIE" => "a=1; b=2"], "c", 0, 0],
        ];
    }

    /**
     * @covers ::getQueryParameters
     * @dataProvider providerGetQueryParameters
     */
    public function testGetQueryParameters($server, $expect)
    {
        $request = new Request(createTestEnvironment($server));
        $this->assertSame($expect, $request->getQueryParameters());
    }

    public function providerGetQueryParameters()
    {
        return [
            [
                [],
                [],
            ],
            [
                ["QUERY_STRING" => "one=1&two=two"],
                ["one" => "1", "two" => "two"],
            ],
        ];
    }

    /**
     * @covers ::getBodyDecoder
     */
    public function testGetBodyDecoder()
    {
        $environment = createTestEnvironment([]);
        $request = new Request($environment);
        $rc = new \ReflectionClass($request);
        $method = $rc->getMethod('getBodyDecoder');
        $method->setAccessible(true);
        $bodyDecoder = $method->invoke($request);
        $this->assertInstanceOf(request\BodyDecoder::class, $bodyDecoder);
    }

    /**
     * @covers ::getBodyParameters
     * @dataProvider providerGetBodyParameters
     */
    public function testGetBodyParameters($server, $expect)
    {
        $bodyDecoder = $this->getMockBuilder(request\BodyDecoder::class)
            ->setMethods(['decode'])
            ->getMock();

        $bodyDecoder->method('decode')->willReturn($expect);

        $environment = createTestEnvironment($server);
        $request = $this->getMockBuilder(Request::class)
            ->setConstructorArgs([$environment])
            ->setMethods(['getBodyDecoder'])
            ->getMock();

        $request->method('getBodyDecoder')->willReturn($bodyDecoder);

        $this->assertSame($expect, $request->getBodyParameters());
    }

    public function providerGetBodyParameters()
    {
        return [
            [
                [],
                []
            ],
            [
                ["HTTP_CONTENT_TYPE" => "application/json"],
                []
            ],
        ];
    }
}
