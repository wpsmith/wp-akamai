<?php
/**
 *
 * Original Author: Davey Shafik <dshafik@akamai.com>
 *
 * For more information visit https://developer.akamai.com
 *
 * Copyright 2014 Akamai Technologies, Inc. All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace Akamai\Open\EdgeGrid\Tests\Client;

class AuthenticationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider createAuthHeaderDataProvider
     */
    public function testCreateAuthHeader(
        $auth,
        $body,
        $expected,
        $headers,
        $headersToSign,
        $host,
        $maxBody,
        $method,
        $name,
        $nonce,
        $path,
        $query,
        $timestamp
    ) {
        $this->setName($name);

        $mockTimestamp = $this->prophesize('\Akamai\Open\EdgeGrid\Authentication\Timestamp');
        $mockTimestamp->__toString()->willReturn($timestamp);
        $mockTimestamp->isValid()->willReturn(true);
        $mockNonce = $this->prophesize('\Akamai\Open\EdgeGrid\Authentication\Nonce');
        $mockNonce->__toString()->willReturn($nonce);

        $authentication = new \Akamai\Open\EdgeGrid\Authentication();
        $authentication->setAuth($auth['client_token'], $auth['client_secret'], $auth['access_token']);
        $authentication->setHttpMethod($method);
        $authentication->setHeaders($headers);
        $authentication->setHeadersToSign($headersToSign);
        $authentication->setQuery($query);
        $authentication->setPath($path);
        $authentication->setHost($host);
        $authentication->setBody($body);
        $authentication->setMaxBodySize($maxBody);
        $authentication->setTimestamp($mockTimestamp->reveal());
        $authentication->setNonce($mockNonce->reveal());

        $result = $authentication->createAuthHeader();

        $this->assertEquals($expected, $result);
    }

    public function testDefaultTimestamp()
    {
        $authentication = new \Akamai\Open\EdgeGrid\Authentication();
        $authentication->setAuth("test", "test", "test");
        $authentication->setHttpMethod("GET");
        $authentication->setPath("/test");
        $authentication->setHost("https://example.org");
        $authentication->createAuthHeader();

        $this->assertInstanceOf(
            '\Akamai\Open\EdgeGrid\Authentication\Timestamp',
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'timestamp')
        );
    }

    public function testDefaultNonce()
    {
        $authentication = new \Akamai\Open\EdgeGrid\Authentication();
        $authentication->setAuth("test", "test", "test");
        $authentication->setHttpMethod("GET");
        $authentication->setPath("/test");
        $authentication->setHost("https://example.org");
        $authentication->createAuthHeader();
        $authentication->setNonce();

        $this->assertInstanceOf(
            '\Akamai\Open\EdgeGrid\Authentication\Nonce',
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'nonce')
        );
    }

    /**
     * @expectedException \Akamai\Open\EdgeGrid\Authentication\Exception\SignerException\InvalidSignDataException
     * @expectedExceptionMessage Timestamp is invalid. Too old?
     */
    public function testTimestampTimeout()
    {
        $authentication = new \Akamai\Open\EdgeGrid\Authentication();
        $authentication->setAuth("test", "test", "test");
        $authentication->setHttpMethod("GET");
        $authentication->setPath("/test");
        $authentication->setHost("https://example.org");

        $timestamp = new \Akamai\Open\EdgeGrid\Authentication\Timestamp();
        $timestamp->setValidFor('PT0S');
        $authentication->setTimestamp($timestamp);
        sleep(1);
        $authentication->createAuthHeader();
    }

    public function testSignHeadersArray()
    {
        $authentication = new \Akamai\Open\EdgeGrid\Authentication();

        $reflection = new \ReflectionMethod($authentication, 'canonicalizeHeaders');
        $reflection->setAccessible(true);

        $authentication->setAuth("test", "test", "test");
        $authentication->setHttpMethod("GET");
        $authentication->setPath("/test");
        $authentication->setHost("https://example.org");
        $authentication->setHeaders(array(
            'X-Test-1' => array("Value1", "value2")
        ));
        $authentication->setHeadersToSign(array('X-Test-1'));

        $this->assertEquals("x-test-1:Value1", $reflection->invoke($authentication));

        $authentication->setHeaders(array(
            'X-Test-1' => array()
        ));
        $authentication->setHeadersToSign(array('X-Test-1'));
        $this->assertEmpty($reflection->invoke($authentication));
    }

    public function testSetHost()
    {
        $authentication = new \Akamai\Open\EdgeGrid\Authentication();
        $authentication->setHost("example.org");
        $this->assertEquals(
            "example.org",
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'host')
        );

        $this->assertNull(\PHPUnit_Framework_Assert::readAttribute($authentication, 'path'));
        $this->assertArrayNotHasKey('query', \PHPUnit_Framework_Assert::readAttribute($authentication, 'config'));

        $authentication = new \Akamai\Open\EdgeGrid\Authentication();
        $authentication->setHost("http://example.com");
        $this->assertEquals(
            "example.com",
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'host')
        );

        $this->assertNull(\PHPUnit_Framework_Assert::readAttribute($authentication, 'path'));
        $this->assertArrayNotHasKey('query', \PHPUnit_Framework_Assert::readAttribute($authentication, 'config'));
    }

    public function testSetHostWithPath()
    {
        $authentication = new \Akamai\Open\EdgeGrid\Authentication();

        $authentication->setHost("example.net/path");
        $this->assertEquals(
            "example.net",
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'host')
        );
        $this->assertEquals('/path', \PHPUnit_Framework_Assert::readAttribute($authentication, 'path'));
        $this->assertArrayNotHasKey('query', \PHPUnit_Framework_Assert::readAttribute($authentication, 'config'));

        $authentication->setHost("http://example.org/newpath");
        $this->assertEquals(
            "example.org",
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'host')
        );
        $this->assertEquals('/newpath', \PHPUnit_Framework_Assert::readAttribute($authentication, 'path'));
        $this->assertArrayNotHasKey('query', \PHPUnit_Framework_Assert::readAttribute($authentication, 'config'));
    }

    public function testSetHostWithQuery()
    {
        $authentication = new \Akamai\Open\EdgeGrid\Authentication();

        $authentication->setHost("example.net/path?query=string");
        $this->assertEquals(
            "example.net",
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'host')
        );
        $this->assertEquals('/path', \PHPUnit_Framework_Assert::readAttribute($authentication, 'path'));
        $this->assertArrayHasKey('query', \PHPUnit_Framework_Assert::readAttribute($authentication, 'config'));
        $this->assertEquals(
            'query=string',
            $authentication->getQuery()
        );

        $authentication->setHost("http://example.org/newpath?query=newstring");
        $this->assertEquals(
            "example.org",
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'host')
        );
        $this->assertEquals('/newpath', \PHPUnit_Framework_Assert::readAttribute($authentication, 'path'));
        $this->assertArrayHasKey('query', \PHPUnit_Framework_Assert::readAttribute($authentication, 'config'));
        $this->assertEquals(
            'query=newstring',
            $authentication->getQuery()
        );

        $authentication->setHost("http://example.org?query=newstring");
        $this->assertEquals(
            "example.org",
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'host')
        );
        $this->assertEquals('/', \PHPUnit_Framework_Assert::readAttribute($authentication, 'path'));
        $this->assertArrayHasKey('query', \PHPUnit_Framework_Assert::readAttribute($authentication, 'config'));
        $this->assertEquals(
            'query=newstring',
            $authentication->getQuery()
        );

        $authentication->setHost("http://example.net/?query=string");
        $this->assertEquals(
            "example.net",
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'host')
        );
        $this->assertEquals('/', \PHPUnit_Framework_Assert::readAttribute($authentication, 'path'));
        $this->assertArrayHasKey('query', \PHPUnit_Framework_Assert::readAttribute($authentication, 'config'));
        $this->assertEquals(
            'query=string',
            $authentication->getQuery()
        );
    }

    public function testSetPath()
    {
        $authentication = new \Akamai\Open\EdgeGrid\Authentication();

        $authentication->setPath("/path");
        $this->assertEmpty(
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'host')
        );
        $this->assertEquals('/path', \PHPUnit_Framework_Assert::readAttribute($authentication, 'path'));
        $this->assertArrayNotHasKey('query', \PHPUnit_Framework_Assert::readAttribute($authentication, 'config'));
        $this->assertEmpty($authentication->getQuery());

        $authentication = new \Akamai\Open\EdgeGrid\Authentication();
        $authentication->setPath("https://example.net/path");
        $this->assertEquals(
            "example.net",
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'host')
        );
        $this->assertEquals('/path', \PHPUnit_Framework_Assert::readAttribute($authentication, 'path'));
        $this->assertArrayNotHasKey('query', \PHPUnit_Framework_Assert::readAttribute($authentication, 'config'));
        $this->assertEmpty($authentication->getQuery());

        $authentication = new \Akamai\Open\EdgeGrid\Authentication();
        $authentication->setPath("/newpath?query=string");
        $this->assertEmpty(
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'host')
        );
        $this->assertEquals('/newpath', \PHPUnit_Framework_Assert::readAttribute($authentication, 'path'));
        $this->assertArrayHasKey('query', \PHPUnit_Framework_Assert::readAttribute($authentication, 'config'));
        $this->assertEquals(
            'query=string',
            $authentication->getQuery()
        );
        $this->assertEquals('query=string', $authentication->getQuery());

        $authentication = new \Akamai\Open\EdgeGrid\Authentication();
        $authentication->setPath("https://example.net/path?query=newstring");
        $this->assertEquals(
            "example.net",
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'host')
        );
        $this->assertEquals('/path', \PHPUnit_Framework_Assert::readAttribute($authentication, 'path'));
        $this->assertArrayHasKey('query', \PHPUnit_Framework_Assert::readAttribute($authentication, 'config'));
        $this->assertEquals(
            'query=newstring',
            $authentication->getQuery()
        );
        $this->assertEquals('query=newstring', $authentication->getQuery());
    }

    /**
     * @dataProvider createFromEdgeRcProvider
     */
    public function testCreateFromEdgeRcDefault($section, $file)
    {
        $_SERVER['HOME'] = __DIR__ . '/edgerc';
        $authentication = \Akamai\Open\EdgeGrid\Authentication::createFromEdgeRcFile($section, $file);

        $this->assertInstanceOf('\Akamai\Open\EdgeGrid\Authentication', $authentication);
        $this->assertEquals(
            array(
                'client_token' => "akab-client-token-xxx-xxxxxxxxxxxxxxxx",
                'client_secret' => "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=",
                'access_token' => "akab-access-token-xxx-xxxxxxxxxxxxxxxx"
            ),
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'auth')
        );
        $this->assertEquals(
            'akaa-baseurl-xxxxxxxxxxx-xxxxxxxxxxxxx.luna.akamaiapis.net',
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'host')
        );
        $this->assertEquals(2048, \PHPUnit_Framework_Assert::readAttribute($authentication, 'max_body_size'));
    }

    /**
     * @expectedException \Akamai\Open\EdgeGrid\Authentication\Exception\ConfigException
     * @expectedExceptionMessage Section "default" does not exist!
     */
    public function testCreateFromEdgeRcUseCwd()
    {
        $_SERVER['HOME'] = "/non-existant";
        $unlink = false;
        if (!file_exists('./.edgerc')) {
            touch('./.edgerc');
            $unlink = true;
        }

        try {
            $auth = \Akamai\Open\EdgeGrid\Authentication::createFromEdgeRcFile();
            $this->assertInstanceOf('\Akamai\Open\EdgeGrid\Authentication', $auth);
        } catch (\Exception $e) {
            if ($unlink) {
                unlink('./.edgerc');
            }
            throw $e;
        }

        if ($unlink) {
            unlink('./.edgerc');
        }
    }

    /**
     * @expectedException \Akamai\Open\EdgeGrid\Authentication\Exception\ConfigException
     * @expectedExceptionMessage Path to .edgerc file "/non-existant/.edgerc" does not exist!
     */
    public function testCreateFromEdgeRcNonExistant()
    {
        $auth = \Akamai\Open\EdgeGrid\Authentication::createFromEdgeRcFile(null, "/non-existant/.edgerc");
    }

    /**
     * @expectedException \Akamai\Open\EdgeGrid\Authentication\Exception\ConfigException
     * @expectedExceptionMessage Unable to read .edgerc file!
     */
    public function testCreateFromEdgeRcNonReadable()
    {
        $filename = tempnam(sys_get_temp_dir(), '.');
        touch(tempnam(sys_get_temp_dir(), '.'));
        chmod($filename, 0000);

        try {
            $auth = \Akamai\Open\EdgeGrid\Authentication::createFromEdgeRcFile(null, $filename);
        } catch (\Exception $e) {
            chmod($filename, 0777);
            unlink($filename);
            throw $e;
        }

        chmod($filename, 0777);
        unlink($filename);
    }

    public function testCreateFromEdgeRcColons()
    {
        $file = __DIR__ . '/edgerc/.edgerc.invalid';
        $authentication = \Akamai\Open\EdgeGrid\Authentication::createFromEdgeRcFile(null, $file);

        $this->assertInstanceOf('\Akamai\Open\EdgeGrid\Authentication', $authentication);
        $this->assertEquals(
            array(
                'client_token' => "akab-client-token-xxx-xxxxxxxxxxxxxxxx",
                'client_secret' => "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=",
                'access_token' => "akab-access-token-xxx-xxxxxxxxxxxxxxxx"
            ),
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'auth')
        );
        $this->assertEquals(
            'akaa-baseurl-xxxxxxxxxxx-xxxxxxxxxxxxx.luna.akamaiapis.net',
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'host')
        );
        $this->assertEquals(2048, \PHPUnit_Framework_Assert::readAttribute($authentication, 'max_body_size'));
    }

    public function testCreateFromEdgeRcColonsWithSpaces()
    {
        $file = __DIR__ . '/edgerc/.edgerc.invalid-spaces';
        $authentication = \Akamai\Open\EdgeGrid\Authentication::createFromEdgeRcFile(null, $file);

        $this->assertInstanceOf('\Akamai\Open\EdgeGrid\Authentication', $authentication);
        $this->assertEquals(
            array(
                'client_token' => "akab-client-token-xxx-xxxxxxxxxxxxxxxx",
                'client_secret' => "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=",
                'access_token' => "akab-access-token-xxx-xxxxxxxxxxxxxxxx"
            ),
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'auth')
        );
        $this->assertEquals(
            'akaa-baseurl-xxxxxxxxxxx-xxxxxxxxxxxxx.luna.akamaiapis.net',
            \PHPUnit_Framework_Assert::readAttribute($authentication, 'host')
        );
        $this->assertEquals(2048, \PHPUnit_Framework_Assert::readAttribute($authentication, 'max_body_size'));
    }

    public function testSetConfig()
    {
        $authentication = new \Akamai\Open\EdgeGrid\Authentication();

        $config = array('test' => 'value');
        $authentication->setConfig($config);

        $this->assertEquals($config, \PHPUnit_Framework_Assert::readAttribute($authentication, 'config'));

        $authentication = new \Akamai\Open\EdgeGrid\Authentication();
        $authentication->setQuery('query=string');
        $authentication->setConfig($config);

        $config['query'] = 'query=string';
        $this->assertEquals($config, \PHPUnit_Framework_Assert::readAttribute($authentication, 'config'));
    }

    public function testSetQuery()
    {
        $authentication = new \Akamai\Open\EdgeGrid\Authentication();
        $authentication->setQuery('query=string');
        $this->assertEquals('query=string', $authentication->getQuery());

        $authentication->setQuery(array('query' => 'string'));
        $authentication->getQuery('query=string', $authentication->getQuery());
    }

    public function testSetQueryEncoding()
    {
        $authentication = new \Akamai\Open\EdgeGrid\Authentication();
        $authentication->setQuery('query=string%20with%20spaces');
        $this->assertEquals('query=string%20with%20spaces', $authentication->getQuery());

        $authentication->setQuery('query=string+with+spaces');
        $this->assertEquals('query=string%20with%20spaces', $authentication->getQuery());
    }

    public function createFromEdgeRcProvider()
    {
        return array(
            array(
                'section' => null,
                'file' => null,
            ),
            array(
                'section' => 'default',
                'file' => null,
            ),
            array(
                'section' => 'testing',
                'file' => __DIR__ . '/edgerc/.edgerc.testing',
            ),
            array(
                'section' => 'testing',
                'file' => __DIR__ . '/edgerc/.edgerc.default-testing',
            )
        );
    }

    public function createAuthHeaderDataProvider()
    {
        $testdata = json_decode(file_get_contents(__DIR__ . '/testdata.json'), true);

        $defaults = array(
            'auth' => array(
                'client_token' => $testdata['client_token'],
                'client_secret' => $testdata['client_secret'],
                'access_token' => $testdata['access_token'],
            ),
            'host' => parse_url($testdata['base_url'], PHP_URL_HOST),
            'headersToSign' => $testdata['headers_to_sign'],
            'nonce' => $testdata['nonce'],
            'timestamp' => $testdata['timestamp'],
            'maxBody' => $testdata['max_body'],
        );

        foreach ($testdata['tests'] as &$test) {
            $data = array_merge($defaults, array(
                'method' => $test['request']['method'],
                'path' => $test['request']['path'],
                'expected' => $test['expectedAuthorization'],
                'query' => (isset($test['request']['query'])) ? $test['request']['query'] : null,
                'body' => (isset($test['request']['data'])) ? $test['request']['data'] : null,
                'name' => $test['testName'],
            ));

            $data['headers'] = array();
            if (isset($test['request']['headers'])) {
                array_walk_recursive($test['request']['headers'], function ($value, $key) use (&$data) {
                    $data['headers'][$key] = $value;
                });
            }

            ksort($data);

            $test = $data;
        }

        return $testdata['tests'];
    }
}
