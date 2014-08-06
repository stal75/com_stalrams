<?php

//������ �� ������� ��������� � �������
defined('_JEXEC') or die;

abstract class ZabbixApiAbstract
{

	/**
	 * @brief   Boolean if requests/responses should be printed out (JSON).
	 */

	private $printCommunication = FALSE;

	/**
	 * @brief   API URL.
	 */

	private $apiUrl = '';

	/**
	 * @brief   Default params.
	 */

	private $defaultParams = array();

	/**
	 * @brief   Auth string.
	*/

	private $auth = '';

	/**
	 * @brief   Request ID.
	 */

	private $id = 0;

	/**
	 * @brief   Request array.
	 */

	private $request = array();

	/**
	 * @brief   JSON encoded request string.
	*/

	private $requestEncoded = '';

	/**
	 * @brief   JSON decoded response string.
	 */

	private $response = '';

	/**
	 * @brief   Response object.
	 */

	private $responseDecoded = NULL;

	/**
	 * @brief   Class constructor.
	 *
	 * @param   $apiUrl     API url (e.g. http://FQDN/zabbix/api_jsonrpc.php)
	 * @param   $user       Username.
	 * @param   $password   Password.
	 */

	public function __construct($apiUrl='', $user='', $password='')
	{
		if($apiUrl)
			$this->setApiUrl($apiUrl);

		if($user && $password)
			$this->userLogin(array('user' => $user, 'password' => $password));
	}

	/**
	 * @brief   Returns the API url for all requests.
	 *
	 * @retval  string  API url.
	 */

	public function getApiUrl()
	{
		return $this->apiUrl;
	}


	/**
	 * @brief   Sets the API url for all requests.
	 *
	 * @param   $apiUrl     API url.
	 *
	 * @retval  ZabbixApiAbstract
	 */

	public function setApiUrl($apiUrl)
	{
		$this->apiUrl = $apiUrl;
		return $this;
	}

	/**
	 * @brief   Returns the default params.
	 *
	 * @retval  array   Array with default params.
	 */

	public function getDefaultParams()
	{
		return $this->defaultParams;
	}

	/**
	 * @brief   Sets the default params.
	 *
	 * @param   $defaultParams  Array with default params.
	 *
	 * @retval  ZabbixApiAbstract
	 *
	 * @throws  Exception
	 */

	public function setDefaultParams($defaultParams)
	{

		if(is_array($defaultParams))
			$this->defaultParams = $defaultParams;
		else
			throw new Exception('The argument defaultParams on setDefaultParams() has to be an array.');

		return $this;
	}

	/**
	 * @brief   Sets the flag to print communication requests/responses.
	 *
	 * @param   $print  Boolean if requests/responses should be printed out.
	 *
	 * @retval  ZabbixApiAbstract
	 */
	public function printCommunication($print = TRUE)
	{
		$this->printCommunication = (bool) $print;
		return $this;
	}

	/**
	 * @brief   Sends are request to the zabbix API and returns the response
	 *          as object.
	 *
	 * @param   $method     Name of the API method.
	 * @param   $params     Additional parameters.
	 * @param   $auth       Enable auth string (default TRUE).
	 *
	 * @retval  stdClass    API JSON response.
	 */

	public function request($method, $params=NULL, $resultArrayKey='', $auth=TRUE)
	{

		// sanity check and conversion for params array
		if(!$params)                $params = array();
		elseif(!is_array($params))  $params = array($params);

		// generate ID
		$this->id = number_format(microtime(true), 4, '', '');

		// build request array
		$this->request = array(
				'jsonrpc' => '2.0',
				'method'  => $method,
				'params'  => $params,
				'auth'    => ($auth ? $this->auth : ''),
				'id'      => $this->id
		);

		// encode request array
		$this->requestEncoded = json_encode($this->request);

		// debug logging
		if($this->printCommunication)
			echo 'API request: '.$this->requestEncoded;

		// do request
		$streamContext = stream_context_create(array('http' => array(
				'method'  => 'POST',
				'header'  => 'Content-type: application/json-rpc'."\r\n",
				'content' => $this->requestEncoded
		)));

		// get file handler
		$fileHandler = fopen($this->getApiUrl(), 'rb', false, $streamContext);
		if(!$fileHandler)
			throw new Exception('Could not connect to "'.$this->getApiUrl().'"');

		// get response
		$this->response = @stream_get_contents($fileHandler);

		// debug logging
		if($this->printCommunication)
			echo $this->response."\n";

		// response verification
		if($this->response === FALSE)
			throw new Exception('Could not read data from "'.$this->getApiUrl().'"');

		// decode response
		$this->responseDecoded = json_decode($this->response);

		// validate response
		if(array_key_exists('error', $this->responseDecoded))
			throw new Exception('API error '.$this->responseDecoded->error->code.': '.$this->responseDecoded->error->data);

		// return response
		if($resultArrayKey && is_array($this->responseDecoded->result))
			return $this->convertToAssociatveArray($this->responseDecoded->result, $resultArrayKey);
		else
			return $this->responseDecoded->result;
	}

	/**
	 * @brief   Returns the last JSON API request.
	 *
	 * @retval  string  JSON request.
	 */

	public function getRequest()
	{
		return $this->requestEncoded;
	}

	/**
	 * @brief   Returns the last JSON API response.
	 *
	 * @retval  string  JSON response.
	 */

	public function getResponse()
	{
		return $this->response;
	}

	/**
	 * @brief   Convertes an indexed array to an associative array.
	 *
	 * @param   $indexedArray           Indexed array with objects.
	 * @param   $useObjectProperty      Object property to use as array key.
	 *
	 * @retval  associative Array
	 */

	private function convertToAssociatveArray($objectArray, $useObjectProperty)
	{
		// sanity check
		if(count($objectArray) == 0 || !property_exists($objectArray[0], $useObjectProperty))
			return $objectArray;

		// loop through array and replace keys
		foreach($objectArray as $key => $object)
		{
			unset($objectArray[$key]);
			$objectArray[$object->{$useObjectProperty}] = $object;
		}

		// return associative array
		return $objectArray;
	}

	/**
	 * @brief   Returns a params array for the request.
	 *
	 * This method will automatically convert all provided types into a correct
	 * array. Which means:
	 *
	 *      - arrays will not be converted (indexed & associatve)
	 *      - scalar values will be converted into an one-element array (indexed)
	 *      - other values will result in an empty array
	 *
	 * Afterwards the array will be merged with all default params, while the
	 * default params have a lower priority (passed array will overwrite default
	 * params). But there is an exception for merging: If the passed array is an
	 * indexed array, the default params will not be merged. This is because
	 * there are some API methods, which are expecting a simple JSON array (aka
	 * PHP indexed array) instead of an object (aka PHP associative array).
	 * Example for this behaviour are delete operations, which are directly
	 * expecting an array of IDs '[ 1,2,3 ]' instead of '{ ids: [ 1,2,3 ] }'.
	 *
	 * @param   $params     Params array.
	 *
	 * @retval  Array
	 */

	private function getRequestParamsArray($params)
	{
		// if params is a scalar value, turn it into an array
		if(is_scalar($params))
			$params = array($params);

		// if params isn't an array, create an empty one (e.g. for booleans, NULL)
		elseif(!is_array($params))
		$params = array();

		// if array isn't indexed, merge array with default params
		if(count($params) == 0 || array_keys($params) !== range(0, count($params) - 1))
			$params = array_merge($this->getDefaultParams(), $params);

		// return params
		return $params;
	}

	/**
	 * @brief   Login into the API.
	 *
	 * This will also retreive the auth Token, which will be used for any
	 * further requests.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	final public function userLogin($params=array(), $arrayKeyProperty='')
	{
		$params = $this->getRequestParamsArray($params);
		$this->auth = $this->request('user.login', $params, $arrayKeyProperty, FALSE);
		return $this->auth;
	}

	/**
	 * @brief   Logout from the API.
	 *
	 * This will also reset the auth Token.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	final public function userLogout($params=array(), $arrayKeyProperty='')
	{
		$params = $this->getRequestParamsArray($params);
		$this->auth = '';
		return $this->request('user.logout', $params, $arrayKeyProperty);
	}


	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method action.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function actionGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('action.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method action.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function actionExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('action.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method action.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function actionCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('action.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method action.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function actionUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('action.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method action.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function actionDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('action.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method action.validateOperations.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function actionValidateOperations($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('action.validateOperations', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method action.validateConditions.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function actionValidateConditions($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('action.validateConditions', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method action.validateOperationConditions.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function actionValidateOperationConditions($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('action.validateOperationConditions', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method alert.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function alertGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('alert.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method apiinfo.version.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function apiinfoVersion($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('apiinfo.version', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method application.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function applicationGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('application.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method application.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function applicationExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('application.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method application.checkInput.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function applicationCheckInput($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('application.checkInput', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method application.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function applicationCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('application.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method application.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function applicationUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('application.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method application.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function applicationDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('application.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method application.massAdd.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function applicationMassAdd($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('application.massAdd', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method application.syncTemplates.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function applicationSyncTemplates($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('application.syncTemplates', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method configuration.export.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function configurationExport($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('configuration.export', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method configuration.import.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function configurationImport($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('configuration.import', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method dcheck.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function dcheckGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('dcheck.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method dcheck.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function dcheckIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('dcheck.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method dcheck.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function dcheckIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('dcheck.isWritable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method dhost.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function dhostGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('dhost.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method dhost.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function dhostExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('dhost.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method dhost.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function dhostCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('dhost.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method dhost.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function dhostUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('dhost.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method dhost.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function dhostDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('dhost.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method discoveryrule.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function discoveryruleGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('discoveryrule.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method discoveryrule.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function discoveryruleExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('discoveryrule.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method discoveryrule.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function discoveryruleCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('discoveryrule.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method discoveryrule.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function discoveryruleUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('discoveryrule.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method discoveryrule.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function discoveryruleDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('discoveryrule.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method discoveryrule.copy.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function discoveryruleCopy($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('discoveryrule.copy', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method discoveryrule.syncTemplates.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function discoveryruleSyncTemplates($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('discoveryrule.syncTemplates', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method discoveryrule.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function discoveryruleIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('discoveryrule.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method discoveryrule.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function discoveryruleIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('discoveryrule.isWritable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method discoveryrule.findInterfaceForItem.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function discoveryruleFindInterfaceForItem($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('discoveryrule.findInterfaceForItem', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method drule.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function druleGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('drule.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method drule.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function druleExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('drule.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method drule.checkInput.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function druleCheckInput($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('drule.checkInput', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method drule.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function druleCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('drule.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method drule.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function druleUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('drule.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method drule.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function druleDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('drule.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method drule.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function druleIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('drule.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method drule.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function druleIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('drule.isWritable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method dservice.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function dserviceGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('dservice.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method dservice.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function dserviceExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('dservice.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method dservice.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function dserviceCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('dservice.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method dservice.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function dserviceUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('dservice.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method dservice.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function dserviceDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('dservice.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method event.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function eventGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('event.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method event.acknowledge.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function eventAcknowledge($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('event.acknowledge', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method graph.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function graphGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('graph.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method graph.syncTemplates.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function graphSyncTemplates($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('graph.syncTemplates', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method graph.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function graphDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('graph.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method graph.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function graphUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('graph.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method graph.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function graphCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('graph.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method graph.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function graphExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('graph.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method graph.getObjects.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function graphGetObjects($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('graph.getObjects', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method graphitem.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function graphitemGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('graphitem.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method graphitem.getObjects.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function graphitemGetObjects($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('graphitem.getObjects', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method graphprototype.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function graphprototypeGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('graphprototype.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method graphprototype.syncTemplates.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function graphprototypeSyncTemplates($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('graphprototype.syncTemplates', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method graphprototype.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function graphprototypeDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('graphprototype.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method graphprototype.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function graphprototypeUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('graphprototype.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method graphprototype.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function graphprototypeCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('graphprototype.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method graphprototype.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function graphprototypeExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('graphprototype.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method graphprototype.getObjects.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function graphprototypeGetObjects($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('graphprototype.getObjects', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method host.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('host.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method host.getObjects.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostGetObjects($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('host.getObjects', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method host.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('host.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method host.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('host.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method host.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('host.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method host.massAdd.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostMassAdd($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('host.massAdd', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method host.massUpdate.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostMassUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('host.massUpdate', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method host.massRemove.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostMassRemove($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('host.massRemove', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method host.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('host.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method host.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('host.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method host.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('host.isWritable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostgroup.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostgroupGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostgroup.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostgroup.getObjects.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostgroupGetObjects($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostgroup.getObjects', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostgroup.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostgroupExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostgroup.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostgroup.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostgroupCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostgroup.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostgroup.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostgroupUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostgroup.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostgroup.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostgroupDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostgroup.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostgroup.massAdd.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostgroupMassAdd($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostgroup.massAdd', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostgroup.massRemove.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostgroupMassRemove($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostgroup.massRemove', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostgroup.massUpdate.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostgroupMassUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostgroup.massUpdate', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostgroup.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostgroupIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostgroup.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostgroup.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostgroupIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostgroup.isWritable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method history.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function historyGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('history.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method history.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function historyCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('history.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method history.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function historyDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('history.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostinterface.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostinterfaceGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostinterface.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostinterface.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostinterfaceExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostinterface.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostinterface.checkInput.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostinterfaceCheckInput($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostinterface.checkInput', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostinterface.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostinterfaceCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostinterface.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostinterface.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostinterfaceUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostinterface.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostinterface.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostinterfaceDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostinterface.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostinterface.massAdd.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostinterfaceMassAdd($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostinterface.massAdd', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostinterface.massRemove.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostinterfaceMassRemove($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostinterface.massRemove', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method hostinterface.replaceHostInterfaces.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function hostinterfaceReplaceHostInterfaces($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('hostinterface.replaceHostInterfaces', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method image.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function imageGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('image.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method image.getObjects.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function imageGetObjects($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('image.getObjects', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method image.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function imageExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('image.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method image.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function imageCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('image.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method image.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function imageUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('image.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method image.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function imageDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('image.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method iconmap.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function iconmapGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('iconmap.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method iconmap.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function iconmapCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('iconmap.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method iconmap.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function iconmapUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('iconmap.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method iconmap.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function iconmapDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('iconmap.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method iconmap.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function iconmapIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('iconmap.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method iconmap.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function iconmapIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('iconmap.isWritable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method item.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('item.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method item.getObjects.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemGetObjects($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('item.getObjects', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method item.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('item.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method item.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('item.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method item.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('item.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method item.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('item.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method item.syncTemplates.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemSyncTemplates($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('item.syncTemplates', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method item.validateInventoryLinks.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemValidateInventoryLinks($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('item.validateInventoryLinks', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method item.addRelatedObjects.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemAddRelatedObjects($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('item.addRelatedObjects', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method item.findInterfaceForItem.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemFindInterfaceForItem($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('item.findInterfaceForItem', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method item.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('item.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method item.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('item.isWritable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method itemprototype.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemprototypeGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('itemprototype.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method itemprototype.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemprototypeExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('itemprototype.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method itemprototype.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemprototypeCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('itemprototype.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method itemprototype.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemprototypeUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('itemprototype.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method itemprototype.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemprototypeDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('itemprototype.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method itemprototype.syncTemplates.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemprototypeSyncTemplates($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('itemprototype.syncTemplates', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method itemprototype.findInterfaceForItem.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemprototypeFindInterfaceForItem($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('itemprototype.findInterfaceForItem', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method itemprototype.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemprototypeIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('itemprototype.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method itemprototype.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function itemprototypeIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('itemprototype.isWritable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method maintenance.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function maintenanceGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('maintenance.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method maintenance.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function maintenanceExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('maintenance.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method maintenance.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function maintenanceCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('maintenance.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method maintenance.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function maintenanceUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('maintenance.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method maintenance.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function maintenanceDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('maintenance.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method map.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function mapGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('map.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method map.getObjects.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function mapGetObjects($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('map.getObjects', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method map.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function mapExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('map.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method map.checkInput.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function mapCheckInput($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('map.checkInput', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method map.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function mapCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('map.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method map.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function mapUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('map.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method map.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function mapDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('map.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method map.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function mapIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('map.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method map.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function mapIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('map.isWritable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method map.checkCircleSelementsLink.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function mapCheckCircleSelementsLink($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('map.checkCircleSelementsLink', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method mediatype.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function mediatypeGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('mediatype.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method mediatype.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function mediatypeCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('mediatype.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method mediatype.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function mediatypeUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('mediatype.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method mediatype.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function mediatypeDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('mediatype.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method proxy.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function proxyGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('proxy.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method proxy.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function proxyCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('proxy.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method proxy.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function proxyUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('proxy.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method proxy.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function proxyDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('proxy.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method proxy.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function proxyIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('proxy.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method proxy.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function proxyIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('proxy.isWritable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method service.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function serviceGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('service.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method service.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function serviceCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('service.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method service.validateUpdate.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function serviceValidateUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('service.validateUpdate', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method service.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function serviceUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('service.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method service.validateDelete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function serviceValidateDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('service.validateDelete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method service.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function serviceDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('service.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method service.addDependencies.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function serviceAddDependencies($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('service.addDependencies', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method service.deleteDependencies.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function serviceDeleteDependencies($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('service.deleteDependencies', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method service.validateAddTimes.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function serviceValidateAddTimes($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('service.validateAddTimes', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method service.addTimes.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function serviceAddTimes($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('service.addTimes', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method service.getSla.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function serviceGetSla($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('service.getSla', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method service.deleteTimes.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function serviceDeleteTimes($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('service.deleteTimes', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method service.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function serviceIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('service.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method service.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function serviceIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('service.isWritable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method service.expandPeriodicalTimes.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function serviceExpandPeriodicalTimes($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('service.expandPeriodicalTimes', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method screen.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function screenGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('screen.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method screen.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function screenExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('screen.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method screen.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function screenCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('screen.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method screen.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function screenUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('screen.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method screen.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function screenDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('screen.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method screenitem.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function screenitemGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('screenitem.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method screenitem.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function screenitemCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('screenitem.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method screenitem.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function screenitemUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('screenitem.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method screenitem.updateByPosition.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function screenitemUpdateByPosition($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('screenitem.updateByPosition', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method screenitem.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function screenitemDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('screenitem.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method screenitem.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function screenitemIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('screenitem.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method screenitem.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function screenitemIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('screenitem.isWritable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method script.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function scriptGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('script.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method script.getObjects.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function scriptGetObjects($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('script.getObjects', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method script.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function scriptCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('script.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method script.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function scriptUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('script.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method script.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function scriptDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('script.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method script.execute.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function scriptExecute($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('script.execute', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method script.getScriptsByHosts.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function scriptGetScriptsByHosts($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('script.getScriptsByHosts', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method template.pkOption.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templatePkOption($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('template.pkOption', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method template.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templateGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('template.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method template.getObjects.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templateGetObjects($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('template.getObjects', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method template.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templateExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('template.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method template.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templateCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('template.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method template.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templateUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('template.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method template.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templateDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('template.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method template.massAdd.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templateMassAdd($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('template.massAdd', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method template.massUpdate.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templateMassUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('template.massUpdate', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method template.massRemove.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templateMassRemove($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('template.massRemove', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method template.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templateIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('template.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method template.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templateIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('template.isWritable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method templatescreen.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templatescreenGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('templatescreen.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method templatescreen.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templatescreenExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('templatescreen.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method templatescreen.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templatescreenCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('templatescreen.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method templatescreen.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templatescreenUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('templatescreen.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method templatescreen.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templatescreenDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('templatescreen.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method templatescreen.copy.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templatescreenCopy($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('templatescreen.copy', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method templatescreen.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templatescreenIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('templatescreen.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method templatescreen.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templatescreenIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('templatescreen.isWritable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method templatescreenitem.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function templatescreenitemGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('templatescreenitem.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method trigger.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('trigger.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method trigger.getObjects.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerGetObjects($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('trigger.getObjects', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method trigger.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('trigger.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method trigger.checkInput.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerCheckInput($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('trigger.checkInput', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method trigger.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('trigger.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method trigger.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('trigger.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method trigger.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('trigger.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method trigger.addDependencies.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerAddDependencies($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('trigger.addDependencies', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method trigger.deleteDependencies.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerDeleteDependencies($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('trigger.deleteDependencies', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method trigger.syncTemplates.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerSyncTemplates($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('trigger.syncTemplates', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method trigger.syncTemplateDependencies.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerSyncTemplateDependencies($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('trigger.syncTemplateDependencies', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method trigger.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('trigger.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method trigger.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('trigger.isWritable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method triggerprototype.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerprototypeGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('triggerprototype.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method triggerprototype.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerprototypeCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('triggerprototype.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method triggerprototype.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerprototypeUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('triggerprototype.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method triggerprototype.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerprototypeDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('triggerprototype.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method triggerprototype.syncTemplates.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function triggerprototypeSyncTemplates($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('triggerprototype.syncTemplates', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method user.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function userGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('user.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method user.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function userCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('user.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method user.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function userUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('user.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method user.updateProfile.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function userUpdateProfile($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('user.updateProfile', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method user.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function userDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('user.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method user.addMedia.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function userAddMedia($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('user.addMedia', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method user.deleteMedia.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function userDeleteMedia($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('user.deleteMedia', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method user.updateMedia.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function userUpdateMedia($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('user.updateMedia', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method user.checkAuthentication.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function userCheckAuthentication($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('user.checkAuthentication', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method user.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function userIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('user.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method user.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function userIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('user.isWritable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usergroup.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usergroupGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usergroup.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usergroup.getObjects.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usergroupGetObjects($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usergroup.getObjects', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usergroup.exists.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usergroupExists($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usergroup.exists', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usergroup.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usergroupCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usergroup.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usergroup.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usergroupUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usergroup.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usergroup.massAdd.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usergroupMassAdd($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usergroup.massAdd', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usergroup.massUpdate.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usergroupMassUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usergroup.massUpdate', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usergroup.massRemove.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usergroupMassRemove($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usergroup.massRemove', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usergroup.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usergroupDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usergroup.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usergroup.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usergroupIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usergroup.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usergroup.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usergroupIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usergroup.isWritable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usermacro.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usermacroGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usermacro.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usermacro.createGlobal.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usermacroCreateGlobal($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usermacro.createGlobal', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usermacro.updateGlobal.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usermacroUpdateGlobal($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usermacro.updateGlobal', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usermacro.deleteGlobal.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usermacroDeleteGlobal($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usermacro.deleteGlobal', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usermacro.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usermacroCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usermacro.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usermacro.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usermacroUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usermacro.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usermacro.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usermacroDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usermacro.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usermacro.getMacros.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usermacroGetMacros($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usermacro.getMacros', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usermacro.resolveTrigger.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usermacroResolveTrigger($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usermacro.resolveTrigger', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usermacro.resolveItem.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usermacroResolveItem($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usermacro.resolveItem', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usermacro.replaceMacros.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usermacroReplaceMacros($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usermacro.replaceMacros', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method usermedia.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function usermediaGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('usermedia.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method webcheck.get.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function webcheckGet($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('webcheck.get', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method webcheck.create.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function webcheckCreate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('webcheck.create', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method webcheck.update.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function webcheckUpdate($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('webcheck.update', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method webcheck.delete.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function webcheckDelete($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('webcheck.delete', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method webcheck.isReadable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function webcheckIsReadable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('webcheck.isReadable', $params, $arrayKeyProperty);
	}

	/**
	 * @brief   Reqeusts the Zabbix API and returns the response of the API
	 *          method webcheck.isWritable.
	 *
	 * The $params Array can be used, to pass through params to the Zabbix API.
	 * For more informations about this params, check the Zabbix API
	 * Documentation.
	 *
	 * The $arrayKeyProperty is "PHP-internal" and can be used, to get an
	 * associatve instead of an indexed array as response. A valid value for
	 * this $arrayKeyProperty is any property of the returned JSON objects
	 * (e.g. name, host, hostid, graphid, screenitemid).
	 *
	 * @param   $params             Parameters to pass through.
	 * @param   $arrayKeyProperty   Object property for key of array.
	 *
	 * @retval  stdClass
	 *
	 * @throws  Exception
	 */

	public function webcheckIsWritable($params=array(), $arrayKeyProperty='')
	{
		// get params array for request
		$params = $this->getRequestParamsArray($params);

		// request
		return $this->request('webcheck.isWritable', $params, $arrayKeyProperty);
	}


}


class ZabbixApi extends ZabbixApiAbstract
{

}

$document = &JFactory::getDocument();
echo $baseurl = JURI::base().'components/com_stalrams/assets/php/ZabbixApiAbstract.class.php';
//$document->addScript(JURI::base(true).'/components/com_stalrams/assets/js/genpass.js');
//require JURI::base(true).'/components/com_stalrams/assets/php/ZabbixApi.class.php';
//require $baseurl;

class StalramsViewZabbix extends JViewLegacy
{
public $lists = array();

function display ($tpl = null)
	{
	$model = $this->getModel();//��������� ������
		
		$this->lists['numbers'] = JHTML::_('select.integerlist', 1, 20, 1, 'numbers', 'id = "appar"', $selected = null, $format = "%d");
		
		
		try {
		
			// connect to Zabbix API
			$api = new ZabbixApi('http://tl-zabbix.tl.mrsk-cp.net/api_jsonrpc.php', 'tl_stepin_aa', 'Prolog150');
		
		} catch(Exception $e) {
		
			// Exception in ZabbixApi catched
			echo $e->getMessage();
		
		}
		
	try {
		
		// get all graphs named "CPU"
    	$cpuGraphs = $api->graphGet(array('output' => 'extend','search' => array('name' => 'CPU')));

    	// print graph ID with graph name
    	foreach($cpuGraphs as $graph){printf("id:%d name:%s\n", $graph->graphid, $graph->name);?><br><?php }
			
		//$events= $api->eventGet(array('output' => 'extend','search' => array('name' => 'CPU')));
		//printf ($events);
		
	} catch(Exception $e) {
		
			// Exception in ZabbixApi catched
			echo $e->getMessage();
		
		}
		
		parent::display($tpl);;
	}

}

?>