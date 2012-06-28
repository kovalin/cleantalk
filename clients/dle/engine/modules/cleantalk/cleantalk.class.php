<?php
/**
 * Cleantalk base class
 *
 * @version 0.5
 * @package Cleantalk
 * @subpackage Base
 * @author Сleantalk team (shagimuratov@cleantalk.ru)
 * @copyright (C) 2011 - 2012 Сleantalk team (http://cleantalk.ru)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 *
 **/


require_once ENGINE_DIR . '/modules/cleantalk/cleantalk.xmlrpc.php';

class Cleantalk
{
	/**
	 * Main config
	 * @var array
	 */
	public $config = array(
		'auth_key'=>null,
		'server_url'=>null,
		'response_lang'=>null,
		'ttl' => 43200,
	);

	/**
	 * Constructor
	 * @param $config
	 */
	public function Cleantalk($config)
	{
		$this->config = array_merge($this->config, $config);
	}


	/**
	 * Function checks if a user is blacklisted
	 * @param $params
	 * @param $response
	 * @return bool|int
	 */
	public function isAllowUser($params)
	{
		$params = array_merge(array(
				$params['engine'] => '',
				$params['ip'] => '',
				$params['email'] => '',
				$params['username'] => '',
			), $params);

		$response = $this->xmlRequest(
			'check_newuser',
			array(
				$this->config['auth_key'],
				$params['engine'],
				$this->config['response_lang'],
				$params['ip'],
				$params['email'],
				$params['username'],
			)
		);

                return $response;
	}

	/**
	 * Function checks whether it is possible to publish the message
	 * @param $params
	 * @param $response
	 * @return bool|int
	 */
	public function isAllowMessage($params)
	{
		$params = array_merge(array(
				$params['message'] => '',
				$params['base_text'] => '',
				$params['engine'] => '',
				$params['url'] => '',
				$params['stoplist_check'] => '',
				$params['session_ip'] => '',
				$params['user_email'] => '',
				$params['user_name'] => '',
			), $params);

		$sender_id = $this->getSenderId();

		$response = $this->xmlRequest(
			'check_message',
			array(
				$params['message'],
				$params['base_text'],
				$this->config['auth_key'],
				$params['engine'],
				$params['url'],
				$params['stoplist_check'],
				$this->config['response_lang'],
				$params['session_ip'],
				$params['user_email'],
				$params['user_name'],
				$sender_id
			)
		);

		if($sender_id == '' && isset($response['sender_id']))
			$this->setSenderId($response['sender_id']);

		return $response;
	}

	/**
	 * Function sends the results of manual moderation
	 * @param $params
	 * @param $response
	 * @return bool|int
	 */
	public function sendFeedback($params)
	{
		$feedback = array();
		foreach($params['moderate'] as $msgFeedback)
			$feedback[] = $msgFeedback['msg_hash'] . ':' . intval($msgFeedback['is_allow']);
		$feedback = implode(';', $feedback);

		$response = $this->xmlRequest(
			'send_feedback',
			array(
				$this->config['auth_key'],
				$feedback,
			)
		);
                return $response;
	}

	/**
	 * Function adds to the post comment Cleantalk.ru
	 * @param $message
	 * @param $comment
	 * @return string
	 */
	public function addCleantalkComment($message, $comment)
	{
		$comment = preg_match('/\*\*\*(.+)\*\*\*/', $comment, $matches) ? $comment : '*** '.$comment.' ***';
		return $message . "\n\n" . $comment;
	}

	/**
	 * Function deletes the comment Cleantalk.ru
	 * @param $message
	 * @return mixed
	 */
	public function delCleantalkComment($message)
	{
		$message = preg_replace('/\n\n\*\*\*.+\*\*\*$/', '', $message);
		$message = preg_replace('/\<br.*\>[\n]{0,1}\<br.*\>[\n]{0,1}\*\*\*.+\*\*\*$/', '', $message);
		return $message;
	}

	/**
	 * Function to get the message hash from Cleantalk.ru comment
	 * @param $message
	 * @return null
	 */
	public function getCleantalkCommentHash($message)
	{
		$matches = array();
		if(preg_match('/\n\n\*\*\*.+([a-z0-9]{32}).+\*\*\*$/', $message, $matches))
			return $matches[1];
		else if(preg_match('/\<br.*\>[\n]{0,1}\<br.*\>[\n]{0,1}\*\*\*.+([a-z0-9]{32}).+\*\*\*$/', $message, $matches))
			return $matches[1];

		return NULL;
	}

	/**
	 * Function to get the SenderID
	 * @return string
	 */
	public function getSenderId()
	{
		return ( isset($_COOKIE['ct_sender_id']) && !empty($_COOKIE['ct_sender_id']) ) ? $_COOKIE['ct_sender_id'] : '';
	}

	/**
	 * Function to change the SenderID
	 * @param $senderId
	 * @return bool
	 */
	public function setSenderId($senderId)
	{
		return @setcookie('ct_sender_id', $senderId);
	}


	/**
	 * Function XML-request
	 * @param $method
	 * @param $params
	 * @param $response
	 * @return bool|int
	 */
	private function xmlRequest($method, $params)
	{
		$xmlvars = array();
		foreach ($params as $param)
			$xmlvars[] = new xmlrpcval($param);

		$ct_params = new xmlrpcmsg(
			$method,
			array(new xmlrpcval($xmlvars,"array"))
		);

		$ct_request = new xmlrpc_client($this->config['server_url']);
		$ct_request->request_charset_encoding = 'utf-8';
		$ct_request->return_type = 'phpvals';
		$ct_request->setDebug(0);
		$response = $ct_request->send($ct_params);

		if ($response->faultCode())
		{
			return false;
		}

		return $response->value();
	}

	/**
	 * Function XML-request
	 * @param $method
	 * @param $params
	 * @param $server_url
	 * @return object|bool
	 */
	public function xmlRequest2($method, $params, $server_url = '')
	{
		$xmlvars = array();
		$response = null;
		foreach ($params as $param)
			$xmlvars[] = new xmlrpcval($param);

		$ct_params = new xmlrpcmsg(
			$method,
			array(new xmlrpcval($xmlvars,"array"))
		);

		$server_url = isset($server_url) ? $server_url : $this->config['server_url'];

		$ct_request = new xmlrpc_client($server_url);
		$ct_request->request_charset_encoding = 'utf-8';
		$ct_request->return_type = 'phpvals';
		$ct_request->setDebug(0);
		$response = $ct_request->send($ct_params);

		return $response;
	}

	/**
	 * Function DNS request
	 * @param $host
	 * @return array
	 */
	public function get_servers_ip($host)
	{
		$response = null;
		if (!isset($host))
			return $response;

		if (function_exists('dns_get_record')){
			foreach (dns_get_record($host, DNS_A) as $server){
				$response[] = $server;
			}
			if (count($response))
				return $response;
		}
		if (function_exists('gethostbynamel')){
			foreach (gethostbynamel($host) as $server){
				$response[] = array("ip" => $server,
									"host" => $host,
									"ttl" => $this->config['ttl']
									);
			}
		}

		return $response;
	}
}
