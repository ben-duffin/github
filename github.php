<?php

/**
 * GitHub class
 *
 * This source file can be used to communicate with github (http://github.com)
 *
 * The class is documented in the file itself. If you find any bugs help me out and report them. Reporting can be done by creating an issue on sending an email to https://github.com/tijsverkoyen/github/issues
 * If you report a bug, make sure you give me enough information (include your code).
 *
 * License
 * Copyright (c), Tijs Verkoyen. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products derived from this software without specific prior written permission.
 *
 * This software is provided by the author "as is" and any express or implied warranties, including, but not limited to, the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall the author be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption) however caused and on any theory of liability, whether in contract, strict liability, or tort (including negligence or otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.
 *
 * @author			Tijs Verkoyen <php-github@verkoyen.eu>
 * @version			0.0.1
 *
 * @copyright		Copyright (c), Tijs Verkoyen. All rights reserved.
 * @license			BSD License
 */
class GitHub
{
	// internal constant to enable/disable debugging
	const DEBUG = false;

	// url for the bitly-api
	const API_URL = 'https://github.com/api/v2/json';

	// port for the bitly-API
	const API_PORT = 443;

	// current version
	const VERSION = '0.0.1';


	/**
	 * The API-key that will be used for authenticating
	 *
	 * @var	string
	 */
	private $apiKey;


	/**
	 * The login that will be used for authenticating
	 *
	 * @var	string
	 */
	private $login;


	/**
	 * The password that will be used for authentication
	 *
	 * @var	string
	 */
	private $password;


	/**
	 * The timeout
	 *
	 * @var	int
	 */
	private $timeOut = 60;


	/**
	 * The user agent
	 *
	 * @var	string
	 */
	private $userAgent;


// class methods
	/**
	 * Default constructor
	 *
	 * @return	void
	 * @param	string $login					The login (username) that has to be used for authenticating.
	 * @param	string[optional] $apiKey		The API-key that has to be used for authentication (see https://github.com/account).
	 * @param	string[optional] $password		The password that has to be used for authentication.
	 */
	public function __construct($login, $apiKey = null, $password = null)
	{
		$this->setLogin($login);
		$this->setApiKey($apiKey);
		$this->setPassword($password);
	}


	/**
	 * Make the call
	 *
	 * @return	mixed
	 * @param	string $url						The URL to call.
	 * @param	array[optional] $parameters		The parameters to send.
	 * @param	string[optional] $method		The method to use, possible values are: GET, POST.
	 * @param	bool[optional] $expectJSON		Do we expect JSON?
	 */
	private function doCall($url, array $parameters = null, $method = 'GET', $expectJSON = true)
	{
		// redefine
		$url = (string) $url;
		$method = (string) $method;

		// validate
		if(!in_array($method, array('GET', 'POST'))) throw new GitHubException('Invalid method.');

		// prepend
		$url = self::API_URL .'/'. $url;

		// set options
		$options[CURLOPT_URL] = $url;
		$options[CURLOPT_PORT] = self::API_PORT;
		$options[CURLOPT_USERAGENT] = $this->getUserAgent();
		if(ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) $options[CURLOPT_FOLLOWLOCATION] = true;
		$options[CURLOPT_RETURNTRANSFER] = true;
		$options[CURLOPT_TIMEOUT] = (int) $this->getTimeOut();
		$options[CURLOPT_SSL_VERIFYPEER] = false;
		$options[CURLOPT_SSL_VERIFYHOST] = false;

		// authentication
		if($this->getPassword() !== null || $this->getApiKey() !== null)
		{
			// API key in favor of password
			if($this->getApiKey() !== null)
			{
				$options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
				$options[CURLOPT_USERPWD] = $this->getLogin() .'/token:'. $this->getApiKey();
			}

			// authenticate with password
			else
			{
				$options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
				$options[CURLOPT_USERPWD] = $this->getLogin() .':'. $this->getPassword();
			}
		}

		// POST
		if($method == 'POST')
		{
			$options[CURLOPT_POST] = true;
			if(!empty($parameters)) $options[CURLOPT_POSTFIELDS] = $parameters;
		}

		// GET
		else
		{
			$options[CURLOPT_POST] = false;
			if(!empty($parameters))
			{
				if(substr_count($url, '?') > 0) $options[CURLOPT_URL] .= '&'. http_build_query($parameters);
				else $options[CURLOPT_URL] .= '?'. http_build_query($parameters);
			}
		}

		// init
		$curl = curl_init();

		// set options
		curl_setopt_array($curl, $options);

		// execute
		$response = curl_exec($curl);
		$headers = curl_getinfo($curl);

		// fetch errors
		$errorNumber = curl_errno($curl);
		$errorMessage = curl_error($curl);

		// close
		curl_close($curl);

		// error?
		if($errorNumber != '') throw new GitHubException($errorMessage, $errorNumber);

		// we don't expect JSON
		if(!$expectJSON) return $response;

		// we expect JSON so decode it
		$json = @json_decode($response, true);

		// validate json
		if($json === false) throw new GitHubException('Invalid JSON-response');

		// is error?
		if(isset($json['error'])) throw new GitHubException((string) $json['error']);

		// return
		return $json;
	}


	/**
	 * Get the APIkey
	 *
	 * @return	mixed
	 */
	private function getApiKey()
	{
		return $this->apiKey;
	}


	/**
	 * Get the login
	 *
	 * @return	string
	 */
	private function getLogin()
	{
		return (string) $this->login;
	}


	/**
	 * Get the password
	 *
	 * @return	mixed
	 */
	private function getPassword()
	{
		return $this->password;
	}


	/**
	 * Get the timeout that will be used
	 *
	 * @return	int
	 */
	public function getTimeOut()
	{
		return (int) $this->timeOut;
	}


	/**
	 * Get the useragent that will be used. Our version will be prepended to yours.
	 * It will look like: "PHP GitHub/<version> <your-user-agent>"
	 *
	 * @return	string
	 */
	public function getUserAgent()
	{
		return (string) 'PHP GitHub/'. self::VERSION .' '. $this->userAgent;
	}


	/**
	 * Set the API-key that has to be used
	 *
	 * @return	void
	 * @param	string $apiKey
	 */
	private function setApiKey($apiKey)
	{
		$this->apiKey = (string) $apiKey;
	}


	/**
	 * Set the login that has to be used
	 *
	 * @return	void
	 * @param	string $login
	 */
	private function setLogin($login)
	{
		$this->login = (string) $login;
	}


	/**
	 * Set the password that has to be used
	 *
	 * @return	void
	 * @param	string $password
	 */
	private function setPassword($password)
	{
		$this->password = (string) $password;
	}


	/**
	 * Set the timeout
	 * After this time the request will stop. You should handle any errors triggered by this.
	 *
	 * @return	void
	 * @param	int $seconds	The timeout in seconds
	 */
	public function setTimeOut($seconds)
	{
		$this->timeOut = (int) $seconds;
	}


	/**
	 * Set the user-agent for you application
	 * It will be appended to ours, the result will look like: "PHP GitHub/<version> <your-user-agent>"
	 *
	 * @return	void
	 * @param	string $userAgent	Your user-agent, it should look like <app-name>/<app-version>
	 */
	public function setUserAgent($userAgent)
	{
		$this->userAgent = (string) $userAgent;
	}


// users
	/**
	 * Search for a user.
	 *
	 * @return	array
	 * @param	string $q
	 */
	public function usersSearch($q)
	{
		// build URL
		$url = 'user/search/'. (string) $q;

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Search for a users by emailaddress
	 *
	 * @return	array
	 * @param	string $email	The emailaddress to search for.
	 */
	public function usersEmail($email)
	{
		// build URL
		$url = 'user/email/'. (string) $email;

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Get extended information for a user.
	 *
	 * @return	array
	 * @param	string $username	The user to get the information for.
	 */
	public function usersShow($username)
	{
		// build URL
		$url = 'user/show/'. (string) $username;

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Update the authenticated user
	 *
	 * @return	mixed
	 * @param	string[optional] $name		The new name.
	 * @param	string[optional] $email		The new emailaddress.
	 * @param	string[optional] $blog		The new blog.
	 * @param	string[optional] $company	The new company.
	 * @param	string[optional] $location	The new location.
	 */
	public function usersUpdate($name = null, $email = null, $blog = null, $company = null, $location = null)
	{
		// build parameters
		$parameters = null;
		if($name !== null) $parameters['values[name]'] = (string) $name;
		if($email !== null) $parameters['values[email]'] = (string) $email;
		if($blog !== null) $parameters['values[blog]'] = (string) $blog;
		if($company !== null) $parameters['values[company]'] = (string) $company;
		if($location !== null) $parameters['values[location]'] = (string) $location;

		// build URL
		$url = 'user/show/'. $this->getLogin();

		// make the call
		return $this->doCall($url, $parameters, 'POST');
	}


	/**
	 * Get the users that an user is following
	 *
	 * @return	array
	 * @param	string $username
	 */
	public function usersShowFollowing($username)
	{
		// build URL
		$url = 'user/show/'. (string) $username .'/following';

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Get the users that are following an user
	 *
	 * @return	array
	 * @param	string $username
	 */
	public function usersShowFollowers($username)
	{
		// build URL
		$url = 'user/show/'. (string) $username .'/followers';

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Start following a user
	 *
	 * @return	void
	 * @param	string $username	The user to follow
	 */
	public function usersFollow($username)
	{
		throw new GitHubException('Not implemented');

		// build URL
		$url = 'user/follow/'. (string) $username;

		// make the call
		return $this->doCall($url, null, 'POST');
	}


	/**
	 * Stop following a user
	 *
	 * @return	void
	 * @param	string $username
	 */
	public function usersUnfollow($username)
	{
		throw new GitHubException('Not implemented');

		// build URL
		$url = 'user/unfollow/'. (string) $username;

		// make the call
		return $this->doCall($url, null, 'POST');
	}


	/**
	 * Get thet repos a user is watching
	 *
	 * @return	array
	 * @param	string $username
	 */
	public function usersReposWatches($username)
	{
		// build URL
		$url = 'repos/watched/'. (string) $username;

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Get a list of the keys for the authenticating user
	 *
	 * @return	array
	 */
	public function usersKeys()
	{
		// build URL
		$url = 'user/keys';

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Add a key for the authenticating user
	 *
	 * @return	void
	 * @param	string $title
	 * @param	string $key
	 */
	public function usersKeyAdd($title, $key)
	{
		throw new GitHubException('Not implemented');

		// build URL
		$url = 'user/key/add';

		// build parameters
		$parameters['title'] = (string) $title;
		$parameters['key'] = (string) $key;

		// make the call
		return $this->doCall($url, $parameters, 'POST');
	}


	/**
	 * Remove a key for the authenticating user
	 *
	 * @return	void
	 * @param	string $id
	 */
	public function usersKeyRemove($id)
	{
		throw new GitHubException('Not implemented');

		// build URL
		$url = 'user/key/remove';

		// build parameters
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall($url, $parameters, 'POST');
	}


	/**
	 * Get a list of emailaddresses for the authenticating user
	 *
	 * @return	array
	 */
	public function usersEmails()
	{
		// build URL
		$url = 'user/emails';

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Add an emailaddress
	 *
	 * @return	void
	 * @param	string $email
	 */
	public function usersEmailAdd($email)
	{
		throw new GitHubException('Not implemented');

		// build URL
		$url = 'user/email/add';

		// build parameters
		$parameters['email'] = (string) $email;

		// make the call
		return $this->doCall($url, $parameters, 'POST');
	}


	/**
	 * Remove an emailaddress
	 *
	 * @return	void
	 * @param	string $id
	 */
	public function usersEmailRemove($id)
	{
		throw new GitHubException('Not implemented');

		// build URL
		$url = 'user/email/remove';

		// build parameters
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall($url, $parameters, 'POST');
	}


// issues
	public function issuesSearch($username, $repository, $state, $q)
	{

	}


	public function issuesList($username, $repository, $state)
	{

	}


	public function issuesListWithLabel($username, $repository, $label)
	{

	}


	public function issuesShow($username, $repository, $number)
	{

	}


	public function issuesComments($username, $repository, $number)
	{

	}


	public function issuesOpen($username, $repository, $title, $body)
	{

	}


	public function issuesClose($username, $repository, $number)
	{

	}


	public function issuesReOpen($username, $repository, $number)
	{

	}


	public function issuesEdit($username, $repository, $number, $title, $body)
	{

	}


	public function issuesLabels($username, $repository)
	{

	}


	public function issuesLabelAdd($username, $repository, $label, $number)
	{

	}


	public function issuesLabelRemove($username, $repository, $label, $number)
	{

	}


	public function issuesComment($username, $repository, $id, $comments)
	{

	}


// repository
	public function reposSearch($q)
	{

	}


	public function reposShow($username, $repository)
	{

	}


	public function reposUpdate($repository, $description = null, $homepage = null, $hasWiki = null, $hasIssues = null, $hasDownloads = null)
	{

	}


	public function reposList($username)
	{

	}


	public function reposWatch($username, $repository)
	{

	}


	public function reposUnwatch($username, $repository)
	{

	}


	public function reposFork($username, $repository)
	{

	}


	public function reposCreate($name, $description = null, $homepage = null, $public = true)
	{

	}


	public function reposDelete($repository)
	{

	}


	public function reposSetPrivate($repository)
	{

	}


	public function reposSetPublic($repository)
	{

	}


	public function reposKeys($repository)
	{

	}


	public function reposKeyAdd($repository, $title, $key)
	{

	}


	public function reposKeyRemove($repository, $id)
	{

	}


	public function reposCollaborators($username, $repository)
	{

	}


	public function reposCollaboratorAdd($username, $repository, $collaborator)
	{

	}


	public function reposCollaboratorRemove($username, $repository, $collaborator)
	{

	}


	public function reposPushable()
	{

	}


	public function reposContributors($username, $repository)
	{

	}


	public function reposWatchers($username, $repository)
	{

	}


	public function reposNetwork($username, $repository)
	{

	}


	public function reposLanguages($username, $repository)
	{

	}


	public function reposTags($username, $repository)
	{

	}


	public function reposBranches($username, $repository)
	{

	}


// commit
	/**
	 * List the commits on a branch
	 *
	 * @return	array
	 * @param	string $username			The username of the repository owner.
	 * @param	string $repository			The name of the repository.
	 * @param	string[optional] $branch	The name of the branch.
	 */
	public function commitsList($username, $repository, $branch = 'master')
	{
		// build URL
		$url = 'commits/list/'. (string) $username .'/'. (string) $repository .'/'. (string) $branch;

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Lost the commints for a file
	 *
	 * @return	array
	 * @param	string $username			The username of the repository owner.
	 * @param	string $repository			The name of the repository.
	 * @param	string $path				The path to the file.
	 * @param	string[optional] $branch	The name of the branch.
	 */
	public function commitsFileList($username, $repository, $path, $branch = 'master')
	{
		// build URL
		$url = 'commits/list/'. (string) $username .'/'. (string) $repository .'/'. (string) $branch .'/'. (string) $path;

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Shows a specific commit
	 *
	 * @return	array
	 * @param	string $username			The username of the repository owner.
	 * @param	string $repository			The name of the repository.
	 * @param	string $sha					The SHA/id of the commit.
	 */
	public function commitsShow($username, $repository, $sha)
	{
		// build URL
		$url = 'commits/show/'. (string) $username .'/'. (string) $repository .'/'. (string) $sha;

		// make the call
		return $this->doCall($url);
	}


// object
	/**
	 * Get the content of a tree by his SHA.
	 *
	 * @return	array
	 * @param	string $username		The username of the repository owner.
	 * @param	string $repository		The name of the repository.
	 * @param	string $sha				The SHA.
	 */
	public function treeShow($username, $repository, $sha)
	{
		// build URL
		$url = 'tree/show/'. (string) $username .'/'. $repository .'/'. (string) $sha;

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Get all metadata of each tree and blob object
	 *
	 * @return	array
	 * @param	string $username		The username of the repository owner.
	 * @param	string $repository		The name of the repository.
	 * @param	string $sha				The SHA.
	 */
	public function treeFull($username, $repository, $sha)
	{
		// build URL
		$url = 'tree/full/'. (string) $username .'/'. $repository .'/'. (string) $sha;

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Get the data about a blob by a tree SHA.
	 *
	 * @return	array
	 * @param	string $username		The username of the repository owner.
	 * @param	string $repository		The name of the repository.
	 * @param	string $sha				The SHA.
	 * @param	string $path			The path to the blob.
	 * @param	bool[optional] $meta	If true only the metadata will be returned.
	 */
	public function blobShow($username, $repository, $sha, $path, $meta = false)
	{
		// build URL
		$url = 'blob/show/'. (string) $username .'/'. $repository .'/'. (string) $sha .'/'. (string) $path;

		// build parameters
		$parameters = null;
		if((bool) $meta) $parameters['meta'] = '1';

		// make the call
		return $this->doCall($url, $parameters);
	}


	/**
	 * Get a list of all blobs
	 *
	 * @return	array
	 * @param	string $username		The username of the repository owner.
	 * @param	string $repository		The name of the repository.
	 * @param	string $sha				The SHA.
	 */
	public function blobAll($username, $repository, $sha)
	{
		// build URL
		$url = 'blob/all/'. (string) $username .'/'. $repository .'/'. (string) $sha;

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Get a list of all blobs including metadata
	 *
	 * @return	array
	 * @param	string $username		The username of the repository owner.
	 * @param	string $repository		The name of the repository.
	 * @param	string $sha				The SHA.
	 */
	public function blobFull($username, $repository, $sha)
	{
		// build URL
		$url = 'blob/full/'. (string) $username .'/'. $repository .'/'. (string) $sha;

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Get the raw content of a blob.
	 *
	 * @return	void
	 * @param	string $username		The username of the repository owner.
	 * @param	string $repository		The name of the repository.
	 * @param	string $sha				The SHA.
	 */
	public function blobShowRawData($username, $repository, $sha)
	{
		// build URL
		$url = 'blob/show/'. (string) $username .'/'. $repository .'/'. (string) $sha;

		// make the call
		return $this->doCall($url, null, 'GET', false);
	}
}


/**
 * GitHub Exception class
 *
 * @author	Tijs Verkoyen <php-github@verkoyen.eu>
 */
class GitHubException extends Exception
{
}

?>