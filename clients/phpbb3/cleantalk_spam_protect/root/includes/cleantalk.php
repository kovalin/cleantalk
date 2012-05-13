<?php

/*

cleantalk_post() process phpBB 3 posts to detect SPAM and offtopic.
Copyright (C) 2011 Denis Shagimuratov shagimuratov@cleantalk.ru

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*/

if (!defined('IN_PHPBB'))
{
   exit;
}

function ct_error_mail($response){
	
	global $config, $user, $phpbb_root_path, $phpEx;

	if (!function_exists('phpbb_mail'))
	{
		include($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);
	}
	
	$headers[] = 'Reply-To: ' . $config['board_email'];
	$headers[] = 'Return-Path: <' . $config['board_email'] . '>';
	$headers[] = 'Sender: <' . $config['board_email'] . '>';
	$headers[] = 'MIME-Version: 1.0';
	$headers[] = 'X-Mailer: phpBB3';
	$headers[] = 'X-MimeOLE: phpBB3';
	$headers[] = 'X-phpBB-Origin: phpbb://' . str_replace(array('http://', 'https://'), array('', ''), generate_board_url());
	$headers[] = 'Content-Type: text/plain; charset=UTF-8'; // format=flowed
	$headers[] = 'Content-Transfer-Encoding: 8bit'; // 7bit

	$err_msg = '';
	$err_str = sprintf($user->lang['CT_ERROR'], $response->faultString(), $config['ct_server_url']);
	$result = @phpbb_mail($config['board_email'], $config['ct_server_url'], $err_str, $headers, "\n", &$err_msg);

	if (!$result)
	{
		$this->error('EMAIL', $err_msg);
		return false;
	}
		
	return 1;
}

function cleantalk_post($message, $forum_id, $topic_id, $username, $ct_forum, $subject){
	
	global $db, $config, $user, $phpEx, $phpbb_root_path;
	
	$response = null;
	$ct_example = ''; // Example text 
	$ct_url = ''; // Post URL
	
	if($topic_id == 0) // First post in topic
	{ 				
		$sql = 'SELECT p.post_text FROM ' . TOPICS_TABLE . ' t, ' . POSTS_TABLE . ' p, ' . FORUMS_TABLE . ' f 
			WHERE t.forum_id = f.forum_id and 
			p.forum_id = f.forum_id and p.topic_id = t.topic_id and p.post_id = t.topic_first_post_id and
			p.post_approved = 1 and f.forum_id = ' . (int) $forum_id . ' ORDER BY p.post_time desc';
		$result = $db->sql_query_limit($sql, $config['ct_post_count']);
		while ($row = $db->sql_fetchrow($result))
		{
			$ct_example .= $row['post_text'] . "<br />";
		}
		$db->sql_freeresult($result);
		
		$message = $subject . "<br />" . $message; 
	}
	else
	{
		$sql = 'SELECT topic_title, topic_first_post_id FROM ' . TOPICS_TABLE . ' WHERE topic_id = ' . (int) $topic_id;
		$result = $db->sql_query($sql);
		$sql_result = $db->sql_fetchrow($result);
		$topic_title = $sql_result['topic_title'];
		$topic_first_post_id = $sql_result['topic_first_post_id'];
		$db->sql_freeresult($result);

		$sql = 'SELECT post_text FROM ' . POSTS_TABLE . ' WHERE post_id = ' . (int) $topic_first_post_id;
		$result = $db->sql_query($sql);
		$sql_result = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		
		$ct_example = $topic_title . "<br />" . $sql_result['post_text'] . "<br />";

		$sql = 'SELECT post_id,post_text FROM ' . POSTS_TABLE . ' 
		WHERE topic_id = ' . (int) $topic_id . ' and post_id <> '. (int) $topic_first_post_id . ' and 
		post_approved = 1 ORDER BY post_time desc';
		$result = $db->sql_query_limit($sql, $config['ct_post_count']);
		while ($row = $db->sql_fetchrow($result))
		{
			$ct_example .= $row['post_text'] . "<br />";
		}
		$db->sql_freeresult($result);
	}

	$params = array(
		'message' => $message,
		'base_text' => $ct_example,
		'engine' => $config['ct_agent'],
		'url' => $ct_url,
		'stoplist_check' => $ct_forum['enable_stoplist_check'],
		'session_ip' => ct_session_ip($user->data['session_ip']), 
		'user_email' => $user->data['user_email'],
		'user_name' => $username,
		'allow_links' => $ct_forum['ct_allow_links']
	);
	
	$response = ct_send_request('check_message', $params);

	if(is_object($response) && $response->faultCode())
	{
		ct_error_mail($response);
		$response = null; // Because is object 
		$response['allow'] = 0; // Send message to moderate queue			
		return $response;
	}

	return $response;
}

/*
	Interface to XML RPC server
		$mehod - method name
		$params - array of XML params
		$ct - object of Cleantalk class
		return XML RPS server response.
*/
function ct_send_request($method, $params, $ct = null){

	global $config, $user, $phpEx, $phpbb_root_path;
	
	$result = null;
	
	if (!isset($ct))
	{
		if (!class_exists('Cleantalk'))
		{
			include($phpbb_root_path . 'includes/cleantalk.class.' . $phpEx);
		}
		$ct = new Cleantalk(array(
			'auth_key' => $config['ct_auth_key'],
			'server_url' => $config['ct_server_url'],
			'response_lang' => $config['ct_response_lang']
		));
	}

	switch($method)
	{
		case 'check_message':
			$sender_id = $ct->getSenderId();
			$params = array(
				$params['message'],
				$params['base_text'],
				$ct->config['auth_key'],
				$params['engine'],
				$params['url'],
				$params['stoplist_check'],
				$ct->config['response_lang'],
				$params['session_ip'],
				$params['user_email'],
				$params['user_name'],
				$sender_id,
				$params['allow_links'],
			);
			break;
		case 'check_newuser':
			$params = array(
				$ct->config['auth_key'],
				$params['engine'],
				$ct->config['response_lang'],
				$params['session_ip'],
				$params['user_email'],
				$params['user_name'],
				$params['tz'],
				$params['submit_time'],
				$params['js_on'],
			);
			break;
		case 'send_feedback':
			$feedback = array();
			foreach($params['moderate'] as $msgFeedback)
				$feedback[] = $msgFeedback['msg_hash'] . ':' . intval($msgFeedback['is_allow']);
			
			$feedback = implode(';', $feedback);
				
			$params = array(
				$ct->config['auth_key'],
				$feedback,
			);
			break;
		default: 
			return $result;
	}
	if ((isset($config['ct_work_url']) && $config['ct_work_url'] !== '') && ($config['ct_server_changed'] + $config['ct_server_ttl'] > time()))
	{
		$result = $ct->xmlRequest2(
			$method,
			$params,
			$config['ct_work_url']
		);
		
		if($result->faultCode())
			add_log('critical', sprintf($user->lang['CT_ERROR'], $result->faultString(), $config['ct_work_url']));
	}

	if (!isset($result) || $result->faultCode())
	{
		$url_prefix = $config['ct_url_prefix'];

		preg_match("#^$url_prefix([a-z\.\-0-9]+):?(\d*)$#i", $config['ct_server_url'], $matches);
		$pool = $matches[1];
		$port = $matches[2];
		foreach ($ct->get_servers_ip($pool) as $server)
		{
			$server_host = gethostbyaddr($server['ip']);
			$work_url = $url_prefix . $server_host;
			if ($server['host'] === 'localhost')
				$work_url = $url_prefix . $server['host'];
			
			$work_url = ($port !== '') ? $work_url . ':' . $port : $work_url;

			$result = $ct->xmlRequest2(
				$method,
				$params,
				$work_url	
			);

			if (!$result->faultCode())
			{
				set_config('ct_work_url', $work_url);
				set_config('ct_server_ttl', $server['ttl']);
				set_config('ct_server_changed', time());
				break;
			}
			else
			{
				if(is_object($result) && $result->faultCode())
					add_log('critical', sprintf($user->lang['CT_ERROR'], $result->faultString(), $work_url));
			}
		}
		if (!$result){
			$work_url = $config['ct_server_url'];	
			$result = $ct->xmlRequest2(
				$method,
				$params,
				$work_url	
			);
			if(is_object($result) && $result->faultCode())
				add_log('critical', sprintf($user->lang['CT_ERROR'], $result->faultString(), $work_url));
		}	
	}
	
	if (is_object($result) && !$result->faultCode())
	{
		$result = $result->value();
		
		if($method === 'check_message' && $sender_id == '' && isset($result['sender_id']))
			$ct->setSenderId($result['sender_id']);
	}

	return $result;
}

/*
	Get user IP
*/
function ct_session_ip($data_ip){
	if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
		$forwarded_for = htmlspecialchars((string) $_SERVER['HTTP_X_FORWARDED_FOR']); 
	
	// 127.0.0.1 usually used at reverse proxy
	$session_ip = ($data_ip == '127.0.0.1' && !empty($forwarded_for)) ? $forwarded_for : $data_ip;
	
	return $session_ip; 
}

?>
