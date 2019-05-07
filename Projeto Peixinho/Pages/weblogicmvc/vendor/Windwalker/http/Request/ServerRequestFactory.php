<?php
/**
 * Part of Windwalker project.
 *
 * @copyright  Copyright (C) 2016 LYRASOFT. All rights reserved.
 * @license    GNU General Public License version 2 or later.
 */

namespace Windwalker\Http\Request;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Windwalker\Http\Helper\HeaderHelper;
use Windwalker\Http\Helper\ServerHelper;
use Windwalker\Http\Stream\PhpInputStream;
use Windwalker\Http\UploadedFile;
use Windwalker\Uri\PsrUri;

/**
 * The ServerRequestFactory class.
 *
 * @since  3.0
 */
class ServerRequestFactory
{
	/**
	 * Function name to get apache request headers. This property is for test use.
	 *
	 * @var callable
	 */
	public static $apacheRequestHeaders = 'apache_request_headers';

	/**
	 * Create a request from the supplied superglobal values.
	 *
	 * If any argument is not supplied, the corresponding superglobal value will
	 * be used.
	 *
	 * The ServerRequest created is then passed to the fromServer() method in
	 * order to marshal the request URI and headers.
	 *
	 * @see fromServer()
	 *
	 * @param  array $server     The $_SERVER superglobal variable.
	 * @param  array $query      The $_GET superglobal variable.
	 * @param  array $parsedBody The $_POST superglobal variable.
	 * @param  array $cookies    The $_COOKIE superglobal variable.
	 * @param  array $files      The $_FILES superglobal variable.
	 *
	 * @return ServerRequestInterface
	 *
	 * @throws \InvalidArgumentException for invalid file values
	 */
	public static function createFromGlobals(array $server = array(), array $query = array(), array $parsedBody = null,
		array $cookies = array(), array $files = array())
	{
		$server  = static::prepareServers($server ? : $_SERVER);
		$headers = static::prepareHeaders($server);

		$body = new PhpInputStream;

		$method = ServerHelper::getValue($server, 'REQUEST_METHOD', 'GET');

		$decodedBody  = $_POST;
		$decodedFiles = $_FILES;

		if (in_array(strtoupper($method), array('PUT', 'PATCH', 'DELETE', 'LINK', 'UNLINK')))
		{
			$type = HeaderHelper::getValue($headers, 'Content-Type');

			if (strpos($type, 'application/x-www-form-urlencoded') !== false)
			{
				parse_str($body->__toString(), $decodedBody);
			}
			elseif (strpos($type, 'multipart/form-data') !== false)
			{
				list($decodedBody, $decodedFiles) = array_values(ServerHelper::parseFormData($body->__toString()));
			}
		}

		$files = static::prepareFiles($files ? : $decodedFiles);

		return new ServerRequest(
			$server,
			$files,
			static::prepareUri($server, $headers),
			$method,
			$body,
			$headers,
			$cookies ? : $_COOKIE,
			$query   ? : $_GET,
			$parsedBody ? : $decodedBody,
			static::getProtocolVersion($server)
		);
	}

	/**
	 * createFromUri
	 *
	 * @param string $uri
	 * @param string $script
	 * @param array  $server
	 * @param array  $query
	 * @param array  $parsedBody
	 * @param array  $cookies
	 * @param array  $files
	 *
	 * @return  ServerRequestInterface
	 */
	public static function createFromUri($uri, $script = null, array $server = array(), array $query = array(), array $parsedBody = null,
		array $cookies = array(), array $files = array())
	{
		$server = $server ? : $_SERVER;

		if ($script)
		{
			$server['SCRIPT_NAME'] = $script;
		}

		$server['SCRIPT_NAME'] = '/' . ltrim($server['SCRIPT_NAME'], '/');

		$request = static::createFromGlobals($server, $query, $parsedBody, $cookies, $files);

		return $request->withUri(new PsrUri($uri));
	}

	/**
	 * Prepare the $_SERVER variables.
	 *
	 * @param   array  $server  The $_SERVER superglobal variable.
	 *
	 * @return  array
	 */
	public static function prepareServers(array $server)
	{
		// Authorization can only get by apache_request_headers()
		$apacheRequestHeaders = static::$apacheRequestHeaders;

		if (isset($server['HTTP_AUTHORIZATION']) || !is_callable($apacheRequestHeaders))
		{
			return $server;
		}

		$apacheRequestHeaders = array_change_key_case(call_user_func($apacheRequestHeaders), CASE_LOWER);

		if (isset($apacheRequestHeaders['authorization']))
		{
			$server['HTTP_AUTHORIZATION'] = $apacheRequestHeaders['authorization'];

			return $server;
		}

		return $server;
	}

	/**
	 * Normalize uploaded files
	 *
	 * Transforms each value into an UploadedFileInterface instance, and ensures
	 * that nested arrays are normalized.
	 *
	 * @param   array  $files  THe $_FILES superglobal variable.
	 *
	 * @return  UploadedFileInterface[]
	 *
	 * @throws  \InvalidArgumentException for unrecognized values
	 */
	public static function prepareFiles(array $files)
	{
		$return = array();

		foreach ($files as $key => $value)
		{
			if ($value instanceof UploadedFileInterface)
			{
				$return[$key] = $value;

				continue;
			}

			// Nested files
			if (is_array($value) && isset($value['tmp_name']))
			{
				$return[$key] = static::createUploadedFile($value);

				continue;
			}

			// get next level files
			if (is_array($value))
			{
				$return[$key] = static::prepareFiles($value);

				continue;
			}

			throw new \InvalidArgumentException('Invalid value in files specification');
		}

		return $return;
	}

	/**
	 * Get headers from $_SERVER.
	 *
	 * @param   array  $server  The $_SERVER superglobal variable.
	 *
	 * @return  array
	 */
	public static function prepareHeaders(array $server)
	{
		$headers = array();
		
		foreach ($server as $key => $value)
		{
			if ($value && strpos($key, 'HTTP_') === 0)
			{
				$name = strtr(substr($key, 5), '_', ' ');
				$name = strtr(ucwords(strtolower($name)), ' ', '-');
				$name = strtolower($name);

				$headers[$name] = $value;
				
				continue;
			}

			if ($value && strpos($key, 'CONTENT_') === 0)
			{
				$name = substr($key, 8);
				$name = 'content-' . strtolower($name);
				
				$headers[$name] = $value;
				
				continue;
			}
		}

		return $headers;
	}

	/**
	 * Marshal the URI from the $_SERVER array and headers
	 *
	 * @param   array  $server   The $_SERVER superglobal.
	 * @param   array  $headers  The headers variable from server.
	 *
	 * @return  PsrUri  Prepared Uri object.
	 */
	public static function prepareUri(array $server, array $headers)
	{
		$uri = new PsrUri('');

		// URI scheme
		$scheme = 'http';
		$https  = ServerHelper::getValue($server, 'HTTPS');

		// Is https or not
		if (($https && $https !== 'off') || HeaderHelper::getValue($headers, 'x-forwarded-proto', false) === 'https')
		{
			$scheme = 'https';
		}

		// URI host
		$host = '';
		$port = null;

		static::getHostAndPortFromHeaders($host, $port, $server, $headers);

		// URI path
		$path = static::getRequestUri($server);
		$path = static::stripQueryString($path);

		// URI query
		$query = '';

		if (isset($server['QUERY_STRING']))
		{
			$query = ltrim($server['QUERY_STRING'], '?');
		}

		// URI fragment
		$fragment = '';

		if (strpos($path, '#') !== false)
		{
			list($path, $fragment) = explode('#', $path, 2);
		}

		return $uri->withScheme($scheme)
			->withHost($host)
			->withPort($port)
			->withPath($path)
			->withFragment($fragment)
			->withQuery($query);
	}

	/**
	 * Marshal the host and port from HTTP headers and/or the PHP environment
	 *
	 * @param   string  $host     The uri host.
	 * @param   string  $port     The request port.
	 * @param   array   $server   The $_SERVER superglobal.
	 * @param   array   $headers  The headers variable from server.
	 */
	public static function getHostAndPortFromHeaders(&$host, &$port, array $server, array $headers)
	{
		if (HeaderHelper::getValue($headers, 'host', false))
		{
			static::getHostAndPortFromHeader($host, $port, HeaderHelper::getValue($headers, 'host'));

			return;
		}

		if (!isset($server['SERVER_NAME']))
		{
			return;
		}

		$host = $server['SERVER_NAME'];

		if (isset($server['SERVER_PORT']))
		{
			$port = (int) $server['SERVER_PORT'];
		}

		if (!isset($server['SERVER_ADDR']) || !preg_match('/^\[[0-9a-fA-F\:]+\]$/', $host))
		{
			return;
		}

		// Handle Ipv6
		$host = '[' . $server['SERVER_ADDR'] . ']';
		$port = $port ? : 80;

		if ($port . ']' === substr($host, strrpos($host, ':') + 1))
		{
			// The last digit of the IPv6-Address has been taken as port
			// Unset the port so the default port can be used
			$port = null;
		}
	}

	/**
	 * Get the base URI for the $_SERVER superglobal.
	 *
	 * Try to auto detect the base URI from different server system including IIS and Apache.
	 *
	 * This method based on ZF2's Zend\Http\PhpEnvironment\Request class
	 *
	 * @see  https://github.com/zendframework/zend-http/blob/master/src/PhpEnvironment/Request.php
	 *
	 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
	 * @license   http://framework.zend.com/license/new-bsd New BSD License
	 *
	 * @param array $server
	 *
	 * @return string
	 */
	public static function getRequestUri(array $server)
	{
		// IIS7 with URL Rewrite: make sure we get the unencoded url
		// (double slash problem).
		$iisUrlRewritten = ServerHelper::getValue($server, 'IIS_WasUrlRewritten');
		$unencodedUrl    = ServerHelper::getValue($server, 'UNENCODED_URL', '');

		if ('1' == $iisUrlRewritten && !empty($unencodedUrl))
		{
			return $unencodedUrl;
		}

		$requestUri = ServerHelper::getValue($server, 'REQUEST_URI');

		// Check this first so IIS will catch.
		$httpXRewriteUrl = ServerHelper::getValue($server, 'HTTP_X_REWRITE_URL');

		if ($httpXRewriteUrl !== null)
		{
			$requestUri = $httpXRewriteUrl;
		}

		// Check for IIS 7.0 or later with ISAPI_Rewrite
		$httpXOriginalUrl = ServerHelper::getValue($server, 'HTTP_X_ORIGINAL_URL');

		if ($httpXOriginalUrl !== null)
		{
			$requestUri = $httpXOriginalUrl;
		}

		if ($requestUri !== null)
		{
			return preg_replace('#^[^/:]+://[^/]+#', '', $requestUri);
		}

		$origPathInfo = ServerHelper::getValue($server, 'ORIG_PATH_INFO');

		if (empty($origPathInfo))
		{
			return '/';
		}

		return $origPathInfo;
	}

	/**
	 * Strip the query string from a path
	 *
	 * @param   string  $path  The uri path.
	 *
	 * @return  string  The path striped.
	 */
	public static function stripQueryString($path)
	{
		$qMark = strpos($path, '?');
			
		if ($qMark !== false)
		{
			return substr($path, 0, $qMark);
		}

		return $path;
	}

	/**
	 * Marshal the host and port from the request header
	 *
	 * @param string $host
	 * @param string $port
	 * @param string $headerHost
	 */
	protected static function getHostAndPortFromHeader(&$host, &$port, $headerHost)
	{
		if (is_array($headerHost))
		{
			$headerHost = implode(', ', $headerHost);
		}

		$host = $headerHost;

		if (preg_match('|\:(\d+)$|', $host, $matches))
		{
			$host = substr($host, 0, -1 * (strlen($matches[1]) + 1));
			$port = (int) $matches[1];
		}
	}

	/**
	 * Create an UploadedFile object for every uploaded file specifications.
	 *
	 * If an element is array, will call getFlattenFileData() to normalize them to
	 * a standard nested file list.
	 *
	 * @param   array  $value  $_FILES  struct.
	 *
	 * @return  UploadedFileInterface|UploadedFileInterface[]
	 */
	private static function createUploadedFile(array $value)
	{
		// Flatten file if is nested.
		if (is_array($value['tmp_name']))
		{
			return static::getFlattenFileData($value);
		}

		return new UploadedFile(
			$value['tmp_name'],
			$value['size'],
			$value['error'],
			$value['name'],
			$value['type']
		);
	}

	/**
	 * Normalize an array of file specifications.
	 *
	 * Loops through all nested files and returns a normalized array of
	 * UploadedFileInterface instances.
	 *
	 * @param   array  $files  The file spec array.
	 *
	 * @return  UploadedFileInterface[]
	 */
	protected static function getFlattenFileData(array $files = array())
	{
		$return = array();

		foreach (array_keys($files['tmp_name']) as $key)
		{
			$file = array(
				'tmp_name' => $files['tmp_name'][$key],
				'size'     => $files['size'][$key],
				'error'    => $files['error'][$key],
				'name'     => $files['name'][$key],
				'type'     => $files['type'][$key],
			);

			$return[$key] = self::createUploadedFile($file);
		}

		return $return;
	}

	/**
	 * Return HTTP protocol version (X.Y)
	 *
	 * @param   array  $server  The $_SERVER supperglobal.
	 *
	 * @return  string  Protocol version.
	 */
	private static function getProtocolVersion(array $server)
	{
		if (!isset($server['SERVER_PROTOCOL']))
		{
			return '1.1';
		}

		if (!preg_match('/^(HTTP\/)?(\d+(?:\.\d+)+)/', $server['SERVER_PROTOCOL'], $matches))
		{
			throw new \UnexpectedValueException(sprintf(
				'Invalid protocol version format (%s)',
				$server['SERVER_PROTOCOL']
			));
		}

		return $matches[2];
	}
}
