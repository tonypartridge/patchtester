<?php
/**
 * Patch testing component for the Joomla! CMS
 *
 * @copyright  Copyright (C) 2011 - 2012 Ian MacLennan, Copyright (C) 2013 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

namespace PatchTester\GitHub;

use Joomla\Registry\Registry;

/**
 * Helper class for interacting with the GitHub API.
 *
 * @since  3.0.0
 */
class GitHub
{
	/**
	 * Options for the connector.
	 *
	 * @var    Registry
	 * @since  3.0.0
	 */
	protected $options;

	/**
	 * The HTTP client object to use in sending HTTP requests.
	 *
	 * @var    \JHttp
	 * @since  3.0.0
	 */
	protected $client;

	/**
	 * Constructor.
	 *
	 * @param   Registry  $options  Connector options.
	 * @param   \JHttp    $client   The HTTP client object.
	 *
	 * @since   3.0.0
	 */
	public function __construct(Registry $options = null, \JHttp $client = null)
	{
		$this->options = $options ?: new Registry;
		$this->client  = $client ?: \JHttpFactory::getHttp($options);
	}

	/**
	 * Build and return a full request URL.
	 *
	 * This method will add appropriate pagination details and basic authentication credentials if necessary
	 * and also prepend the API url to have a complete URL for the request.
	 *
	 * @param   string   $path   URL to inflect
	 * @param   integer  $page   Page to request
	 * @param   integer  $limit  Number of results to return per page
	 *
	 * @return  string   The request URL.
	 *
	 * @since   3.0.0
	 */
	protected function fetchUrl($path, $page = 0, $limit = 0)
	{
		// Get a new JUri object fousing the api url and given path.
		$uri = new \JUri($this->options->get('api.url') . $path);

		// Only apply basic authentication if an access token is not set
		if ($this->options->get('gh.token', false) === false)
		{
			// Use basic authentication
			if ($this->options->get('api.username', false))
			{
				$username = $this->options->get('api.username');
				$username = str_replace('@', '%40', $username);
				$uri->setUser($username);
			}

			if ($this->options->get('api.password', false))
			{
				$password = $this->options->get('api.password');
				$password = str_replace('@', '%40', $password);
				$uri->setPass($password);
			}
		}

		// If we have a defined page number add it to the JUri object.
		if ($page > 0)
		{
			$uri->setVar('page', (int) $page);
		}

		// If we have a defined items per page add it to the JUri object.
		if ($limit > 0)
		{
			$uri->setVar('per_page', (int) $limit);
		}

		return (string) $uri;
	}

	/**
	 * Get the HTTP client for this connector.
	 *
	 * @return  \JHttp
	 *
	 * @since   3.0.0
	 */
	public function getClient()
	{
		return $this->client;
	}

	/**
	 * Get the diff for a pull request.
	 *
	 * @param   string   $user    The name of the owner of the GitHub repository.
	 * @param   string   $repo    The name of the GitHub repository.
	 * @param   integer  $pullId  The pull request number.
	 *
	 * @return  \JHttpResponse
	 *
	 * @since   3.0.0
	 */
	public function getDiffForPullRequest($user, $repo, $pullId)
	{
		// Build the request path.
		$path = "/repos/$user/$repo/pulls/" . (int) $pullId;

		// Build the request headers.
		$headers = array('Accept' => 'application/vnd.github.diff');

		$prepared = $this->prepareRequest($path, 0, 0, $headers);

		return $this->processResponse($this->client->get($prepared['url'], $prepared['headers']));
	}

	/**
	 * Get a file's contents from a repository.
	 *
	 * @param   string  $user  The name of the owner of the GitHub repository.
	 * @param   string  $repo  The name of the GitHub repository.
	 * @param   string  $path  The content path.
	 * @param   string  $ref   The name of the commit/branch/tag. Default: the repository’s default branch (usually master)
	 *
	 * @return  \JHttpResponse
	 *
	 * @since   3.0.0
	 */
	public function getFileContents($user, $repo, $path, $ref = null)
	{
		$path = "/repos/$user/$repo/contents/$path";

		$prepared = $this->prepareRequest($path);

		if ($ref)
		{
			$url = new \JUri($prepared['url']);
			$url->setVar('ref', $ref);

			$prepared['url'] = (string) $url;
		}

		return $this->processResponse($this->client->get($prepared['url'], $prepared['headers']));
	}

	/**
	 * Get the list of modified files for a pull request.
	 *
	 * @param   string   $user    The name of the owner of the GitHub repository.
	 * @param   string   $repo    The name of the GitHub repository.
	 * @param   integer  $pullId  The pull request number.
	 *
	 * @return  \JHttpResponse
	 *
	 * @since   3.0.0
	 */
	public function getFilesForPullRequest($user, $repo, $pullId)
	{
		// Build the request path.
		$path = "/repos/$user/$repo/pulls/" . (int) $pullId . '/files';

		$prepared = $this->prepareRequest($path);

		return $this->processResponse($this->client->get($prepared['url'], $prepared['headers']));
	}

	/**
	 * Get a list of the open issues for a repository.
	 *
	 * @param   string   $user   The name of the owner of the GitHub repository.
	 * @param   string   $repo   The name of the GitHub repository.
	 * @param   integer  $page   The page number from which to get items.
	 * @param   integer  $limit  The number of items on a page.
	 *
	 * @return  \JHttpResponse
	 *
	 * @since   3.0.0
	 */
	public function getOpenIssues($user, $repo, $page = 0, $limit = 0)
	{
		$prepared = $this->prepareRequest("/repos/$user/$repo/issues", $page, $limit);

		return $this->processResponse($this->client->get($prepared['url'], $prepared['headers']));
	}

	/**
	 * Get an option from the connector.
	 *
	 * @param   string  $key      The name of the option to get.
	 * @param   mixed   $default  The default value if the option is not set.
	 *
	 * @return  mixed  The option value.
	 *
	 * @since   3.0.0
	 */
	public function getOption($key, $default = null)
	{
		return $this->options->get($key, $default);
	}

	/**
	 * Get a single pull request.
	 *
	 * @param   string   $user    The name of the owner of the GitHub repository.
	 * @param   string   $repo    The name of the GitHub repository.
	 * @param   integer  $pullId  The pull request number.
	 *
	 * @return  \JHttpResponse
	 *
	 * @since   3.0.0
	 */
	public function getPullRequest($user, $repo, $pullId)
	{
		// Build the request path.
		$path = "/repos/$user/$repo/pulls/" . (int) $pullId;

		$prepared = $this->prepareRequest($path);

		return $this->processResponse($this->client->get($prepared['url'], $prepared['headers']));
	}

	/**
	 * Get the rate limit for the authenticated user.
	 *
	 * @return  \JHttpResponse
	 *
	 * @since   3.0.0
	 */
	public function getRateLimit()
	{
		$prepared = $this->prepareRequest('/rate_limit');

		return $this->processResponse($this->client->get($prepared['url'], $prepared['headers']));
	}

	/**
	 * Process the response and return it.
	 *
	 * @param   \JHttpResponse  $response      The response.
	 * @param   integer         $expectedCode  The expected response code.
	 *
	 * @return  \JHttpResponse
	 *
	 * @since   3.0.0
	 * @throws  Exception\UnexpectedResponse
	 */
	protected function processResponse(\JHttpResponse $response, $expectedCode = 200)
	{
		// Validate the response code.
		if ($response->code != $expectedCode)
		{
			// Decode the error response and throw an exception.
			$body  = json_decode($response->body);
			$error = isset($body->error) ? $body->error : (isset($body->message) ? $body->message : 'Unknown Error');

			throw new Exception\UnexpectedResponse($response, $error, $response->code);
		}

		return $response;
	}

	/**
	 * Method to build and return a full request URL for the request.
	 *
	 * This method will add appropriate pagination details if necessary and also prepend the API url to have a complete URL for the request.
	 *
	 * @param   string   $path     Path to process
	 * @param   integer  $page     Page to request
	 * @param   integer  $limit    Number of results to return per page
	 * @param   array    $headers  The headers to send with the request
	 *
	 * @return  array  Associative array containing the prepared URL and request headers
	 *
	 * @since   3.0.0
	 */
	protected function prepareRequest($path, $page = 0, $limit = 0, array $headers = array())
	{
		$url = $this->fetchUrl($path, $page, $limit);

		if ($token = $this->options->get('gh.token', false))
		{
			$headers['Authorization'] = "token $token";
		}

		return array('url' => $url, 'headers' => $headers);
	}

	/**
	 * Set an option for the connector.
	 *
	 * @param   string  $key    The name of the option to set.
	 * @param   mixed   $value  The option value to set.
	 *
	 * @return  $this
	 *
	 * @since   3.0.0
	 */
	public function setOption($key, $value)
	{
		$this->options->set($key, $value);

		return $this;
	}
}
