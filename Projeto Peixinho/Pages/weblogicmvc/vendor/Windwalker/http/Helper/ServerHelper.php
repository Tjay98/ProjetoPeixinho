<?php
/**
 * Part of Windwalker project. 
 *
 * @copyright  Copyright (C) 2015 LYRASOFT. All rights reserved.
 * @license    GNU General Public License version 2 or later;
 */

namespace Windwalker\Http\Helper;

use Psr\Http\Message\UploadedFileInterface;

/**
 * The ServerHelper class.
 * 
 * @since  2.1
 */
abstract class ServerHelper
{
	/**
	 * Access a value in an array, returning a default value if not found
	 *
	 * Will also do a case-insensitive search if a case sensitive search fails.
	 *
	 * @param   array   $servers  Server values to search.
	 * @param   string  $name     The name we want to search.
	 * @param   mixed   $default  Default value if not found.
	 *
	 * @return  mixed
	 */
	public static function getValue(array $servers, $name, $default = null)
	{
		if (array_key_exists($name, $servers))
		{
			return $servers[$name];
		}

		return $default;
	}

	/**
	 * Recursively validate the structure in an uploaded files array.
	 *
	 * Every file should be an UploadedFileInterface object.
	 *
	 * @param   array  $files  Files array.
	 *
	 * @return  boolean
	 */
	public static function validateUploadedFiles(array $files)
	{
		foreach ($files as $file)
		{
			if (is_array($file))
			{
				static::validateUploadedFiles($file);

				continue;
			}

			if (! $file instanceof UploadedFileInterface)
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * A proxy to getallheaders().
	 * 
	 * If this function not available, we will use native code to implement this function.
	 *
	 * @return  array|false
	 */
	public static function getAllHeaders()
	{
		if (function_exists('getallheaders'))
		{
			return getallheaders();
		}

		$headers = array();

		foreach ($_SERVER as $name => $value)
		{
			if (substr($name, 0, 5) == 'HTTP_')
			{
				$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
			}
		}
		
		return $headers;
	}

	/**
	 * A proxy to apache_request_headers().
	 *
	 * If this function not available, we will use native code to implement this function.
	 *
	 * @return  array
	 *
	 * @link  http://php.net/manual/en/function.getallheaders.php#99814
	 */
	public static function apacheRequestHeaders()
	{
		if (function_exists('apache_request_headers'))
		{
			return apache_request_headers();
		}
		
		$out = array();

		foreach ($_SERVER as $key => $value)
		{
			if (substr($key, 0, 5) == "HTTP_")
			{
				$key = str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", substr($key, 5)))));

				$out[$key] = $value;
			}
			else
			{
				$out[$key] = $value;
			}
		}

		return $out;
	}

	/**
	 * parseFormData
	 *
	 * @param string $input
	 *
	 * @return array
	 *
	 * @link  http://stackoverflow.com/questions/5483851/manually-parse-raw-http-data-with-php/5488449#5488449
	 */
	public static function parseFormData($input)
	{
		$boundary = substr($input, 0, strpos($input, "\r\n"));

		// Fetch each part
		$parts = array_slice(explode($boundary, $input), 1);
		$data  = array();
		$files = array();

		foreach ($parts as $part)
		{
			// If this is the last part, break
			if (strpos($part, '--') === 0)
			{
				break;
			}

			// Separate content from headers
			$part = ltrim($part, "\r\n");

			list($rawHeaders, $content) = explode("\r\n\r\n", $part, 2);

			$content = substr($content, 0, strlen($content) - 2);

			// Parse the headers list
			$rawHeaders = explode("\r\n", $rawHeaders);
			$headers    = array();

			foreach ($rawHeaders as $header)
			{
				list($name, $value) = explode(':', $header, 2);

				$headers[strtolower($name)] = ltrim($value, ' ');
			}

			// Parse the Content-Disposition to get the field name, etc.
			if (isset($headers['content-disposition']))
			{
				$filename = null;

				preg_match(
					'/^form-data; *name="([^"]+)"(?:; *filename="([^"]+)")?/',
					$headers['content-disposition'],
					$matches
				);

				$field = $matches[1];
				$fileName  = (isset($matches[2]) ? $matches[2] : null);

				$fieldName = str_replace(array('[', '][', ']'), array('.', '.', ''), $field);

				// If we have no filename, save the data. Otherwise, save the file.
				if ($fileName === null)
				{
					static::setByPath($data, $fieldName, $content);
				}
				else
				{
					$tempFile = tempnam(sys_get_temp_dir(), 'sfy');

					file_put_contents($tempFile, $content);
					
					$content = array(
						'name'     => $fileName,
						'type'     => $headers['content-type'],
						'tmp_name' => $tempFile,
						'error'    => 0,
						'size'     => filesize($tempFile)
					);

					static::setByPath($files, $fieldName, $content);

					register_shutdown_function(
						function () use ($tempFile)
						{
							@unlink($tempFile);
						}
					);
				}
			}
		}

		return array(
			'data'  => $data,
			'files' => $files
		);
	}

	/**
	 * setByPath
	 *
	 * @param mixed  &$data
	 * @param string $path
	 * @param mixed  $value
	 * @param string $separator
	 *
	 * @return  boolean
	 *
	 * @since   2.0
	 */
	public static function setByPath(array &$data, $path, $value, $separator = '.')
	{
		$nodes = array_values(explode($separator, $path));

		if (empty($nodes))
		{
			return false;
		}

		$dataTmp = &$data;

		foreach ($nodes as $node)
		{
			if (is_array($dataTmp))
			{
				if ((string) $node === '')
				{
					$tmp = array();
					$dataTmp[] = &$tmp;
					$dataTmp = &$tmp;
				}
				else
				{
					if (empty($dataTmp[$node]))
					{
						$dataTmp[$node] = array();
					}

					$dataTmp = &$dataTmp[$node];
				}
			}
			else
			{
				// If a node is value but path is not go to the end, we replace this value as a new store.
				// Then next node can insert new value to this store.
				$dataTmp = &$value;
			}
		}

		// Now, path go to the end, means we get latest node, set value to this node.
		$dataTmp = $value;

		return true;
	}
}
