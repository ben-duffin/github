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
	/**
	 * Search for repositories
	 *
	 * @return	array
	 * @param	string $q						The string to search for
	 * @param	int[optional] $page				The page to start from.
	 * @param	string[optional] $language		The language to search for, language searching is done with the capitalized format of the name: "Ruby", not "ruby". It takes the same values as the language drop down on http://github.com/search.
	 */
	public function reposSearch($q, $page = null, $language = null)
	{
		// build URL
		$url = 'repos/search/'. (string) $q;

		// build parameters
		$parameters = null;
		if($page !== null) $parameters['page'] = (int) $page;
		if($language !== null) $parameters['language'] = (string) $language;

		// make the call
		return $this->doCall($url, $parameters);
	}


	/**
	 * Show more info about a repo
	 *
	 * @return	array
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 */
	public function reposShow($username, $repository)
	{
		// build URL
		$url = 'repos/show/'. (string) $username .'/'. (string) $repository;

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Update a repo
	 *
	 * @return	void
	 * @param	string $username				The username of the repo owner.
	 * @param	string $repository				The name of the repository.
	 * @param	string[optional] $description	The new description.
	 * @param	string[optional] $homepage		The new homepage.
	 * @param	bool[optional] $hasWiki			Should the wiki be enabled?
	 * @param	bool[optional] $hasIssues		Should issues be enabled?
	 * @param	bool[optional] $hasDownloads	Should the download be enabled?
	 */
	public function reposUpdate($repository, $description = null, $homepage = null, $hasWiki = null, $hasIssues = null, $hasDownloads = null)
	{
		// build URL
		$url = 'repos/show/'. (string) $username .'/'. (string) $respository;

		// build parameters
		$parameters = null;
		if($description !== null) $parameters['values[description]'] = (string) $description;
		if($homepage !== null) $parameters['values[homepage]'] = (string) $homepage;
		if($hasWiki !== null) $parameters['values[has_wiki]'] = ((bool) $hasWiki) ? '1' : '0';
		if($hasIssues !== null) $parameters['values[has_issues]'] = ((bool) $hasIssues) ? '1' : '0';
		if($hasDownloads !== null) $parameters['values[has_downloads]'] = ((bool) $hasDownloads) ? '1' : '0';

		// make the call
		return $this->doCall($url, $parameters, 'POST');
	}


	/**
	 * List all repos for a user
	 *
	 * @return	array
	 * @param	string $username	The username.
	 */
	public function reposList($username)
	{
		// build URL
		$url = 'repos/show/'. (string) $username;

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Start watching a repository
	 *
	 * @return	void
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 */
	public function reposWatch($username, $repository)
	{
		// build URL
		$url = 'repos/watch/'. (string) $username .'/'. (string) $repository;

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Stop watching a repository
	 *
	 * @return	void
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 */
	public function reposUnwatch($username, $repository)
	{
		// build URL
		$url = 'repos/unwatch/'. (string) $username .'/'. (string) $repository;

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Fork a repository
	 *
	 * @return	array
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 */
	public function reposFork($username, $repository)
	{
		// build URL
		$url = 'repos/fork/'. (string) $username .'/'. (string) $repository;

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Create a repository
	 *
	 * @return	void
	 * @param	string $name						The name of the repo
	 * @param	string[optional] $description		The description of the repo
	 * @param	string[optional] $homepage			The homepage of the repo
	 * @param	bool[optional] $public				Should the repo be public?
	 */
	public function reposCreate($name, $description = null, $homepage = null, $public = true)
	{
		// build URL
		$url = 'repos/create';

		// build parameters
		$parameters['name'] = (string) $name;
		if($description !== null) $parameters['description'] = (string) $description;
		if($homepage !== null) $parameters['homepage'] = (string) $homepage;
		$parameters['public'] = ((bool) $public) ? '1' : '0';

		// make the call
		return $this->doCall($url, $parameters, 'POST');
	}


	/**
	 * Delete a repository
	 *
	 * @return	void
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 */
	public function reposDelete($username, $repository)
	{
		throw new GitHubException('Not implemented');

		// build URL
		$url = 'repos/delete/'. $this->getLogin() .'/'. (string) $repository;

		// make the call
		return $this->doCall($url, null, 'POST');
	}


	/**
	 * Set a public repository private
	 *
	 * @return	void
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 */
	public function reposSetPrivate($username, $repository)
	{
		// build URL
		$url = 'repos/set/private/'. (string) $username .'/'. (string) $repository;

		// make the call
		return $this->doCall($url, null, 'POST');
	}


	/**
	 * Set a private repository public
	 *
	 * @return	void
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 */
	public function reposSetPublic($username, $repository)
	{
		// build URL
		$url = 'repos/set/public/'. (string) $username .'/'. (string) $repository;

		// make the call
		return $this->doCall($url, null, 'POST');
	}


	/**
	 * List all deploy keys
	 *
	 * @return	array
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 */
	public function reposKeys($username, $repository)
	{
		// build URL
		$url = 'repos/keys/'. (string) $username .'/'. (string) $repository;

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Add a new deploy key
	 *
	 * @return	array
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 * @param	string $title			The title of the key.
	 * @param	string $key				The data of the key.
	 */
	public function reposKeyAdd($username, $repository, $title, $key)
	{
		throw new GitHubException('Not implemented');

		// build URL
		$url = 'repos/key/'. (string) $username .'/'. (string) $repository .'/add';

		// build parameters
		$parameters['title'] = (string) $title;
		$parameters['key'] = (string) $key;

		// make the call
		return $this->doCall($url, $parameters, 'POST');
	}


	/**
	 * Removes a deploy key
	 *
	 * @return	array
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 * @param	string $id				The id of the key.
	 */
	public function reposKeyRemove($username, $repository, $id)
	{
		throw new GitHubException('Not implemented');

		// build URL
		$url = 'repos/key/'. (string) $username .'/'. (string) $repository .'/remove';

		// build parameters
		$parameters['id'] = (string) $id;

		// make the call
		return $this->doCall($url, $parameters, 'POST');
	}


	/**
	 * Get a list of collaborators
	 *
	 * @return	array
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 */
	public function reposCollaborators($username, $repository)
	{
		// build URL
		$url = 'repos/show/'.  (string) $username .'/'. (string) $repository .'/collaborators';

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Add a collaborator
	 *
	 * @return	array
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 * @param	string $collaborator	The username of the collaborator
	 */
	public function reposCollaboratorAdd($username, $repository, $collaborator)
	{
		// build URL
		$url = 'repos/collaborators/'.  (string) $username .'/'. (string) $repository .'/add/'. (string) $collaborator;

		// make the call
		return $this->doCall($url, null, 'POST');
	}


	/**
	 * Add a collaborator
	 *
	 * @return	array
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 * @param	string $collaborator	The username of the collaborator
	 */
	public function reposCollaboratorRemove($username, $repository, $collaborator)
	{
		// build URL
		$url = 'repos/collaborators/'.  (string) $username .'/'. (string) $repository .'/remove/'. (string) $collaborator;

		// make the call
		return $this->doCall($url, null, 'POST');
	}


	/**
	 * Get all repos you can push to, but are not your own
	 *
	 * @return	array
	 */
	public function reposPushable()
	{
		// build URL
		$url = 'repos/pushable';

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Get a list of contributors
	 *
	 * @return	array
	 * @param	string $username					The username of the repo owner.
	 * @param	string $repository					The name of the repository.
	 * @param	bool[optional] $includeNonUsers		Include non-users?
	 */
	public function reposContributors($username, $repository, $includeNonUsers = false)
	{
		// build URL
		$url = 'repos/show/'.  (string) $username .'/'. (string) $repository .'/contributors';

		if((bool) $includeNonUsers) $url .= '/anon';

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Get a list of watchers
	 *
	 * @return	array
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 * @param	bool[optional] $full	Include full profile?
	 */
	public function reposWatchers($username, $repository, $full = false)
	{
		// build URL
		$url = 'repos/show/'.  (string) $username .'/'. (string) $repository .'/watchers';

		// build parameters
		$parameters = null;
		if((bool) $full) $parameters['full'] = '1';

		// make the call
		return $this->doCall($url, $parameters);
	}


	/**
	 * Get the full network
	 *
	 * @return	array
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 */
	public function reposNetwork($username, $repository)
	{
		// build URL
		$url = 'repos/show/'.  (string) $username .'/'. (string) $repository .'/network';

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Get the languages used in a repository
	 *
	 * @return	array
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 */
	public function reposLanguages($username, $repository)
	{
		// build URL
		$url = 'repos/show/'.  (string) $username .'/'. (string) $repository .'/languages';

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Get a list of tags
	 *
	 * @return	array
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 */
	public function reposTags($username, $repository)
	{
		// build URL
		$url = 'repos/show/'.  (string) $username .'/'. (string) $repository .'/tags';

		// make the call
		return $this->doCall($url);
	}


	/**
	 * Get a list of branches
	 *
	 * @return	array
	 * @param	string $username		The username of the repo owner.
	 * @param	string $repository		The name of the repository.
	 */
	public function reposBranches($username, $repository)
	{
		// build URL
		$url = 'repos/show/'.  (string) $username .'/'. (string) $repository .'/branches';

		// make the call
		return $this->doCall($url);
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