<?php

/**
 * Cleantalk DLE Module Class
 * @version 1.2.2
 * @package Cleantalk
 * @subpackage Dle
 * @author Сleantalk team (shagimuratov@cleantalk.ru)
 * @copyright (C) 2012 Сleantalk team (http://cleantalk.ru)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 *
 */
error_reporting(0);

class CleantalkModule {

    /**
     * Cleantalk base class
     * @var object
     */
    private $CT;

    /**
     * Main config
     * @var array
     */
    private $config = null;

    /**
     * Client version
     * @var string
     */
    private $engine = 'dle-122';

    /**
     * Cleantalk comment
     * @var string
     */
    public $comment;

    /**
     * Errno status
     * @var type
     */
    public $errno;

    /**
     * User IP
     * @var type
     */
    private $user_ip;

    /**
     * DLE main config
     * @var type
     */
    public $dle_config;

    /**
     * Cleantalk Language
     * @var type
     */
    private $lang;

    /**
     * Db Object
     * @var object
     */
    public $db = null;

    /**
     * Construct
     * @param type $db
     */
    public function __construct($db, $config) {
        $this->dle_config = $config;
        $this->db = $db;
        $this->config = $this->getAllConfig();
        $this->CT = $this->getCleantalk();
        $this->user_ip = isset($_SERVER['REMOTE_ADDR']) && preg_match("/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/", $_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
        $this->lang = $this->getFullLang();
    }

    /**
     * Get all config
     * @return array
     */
    private function getAllConfig() {
        require_once ENGINE_DIR . '/modules/cleantalk/ct_functions.php';
        list($ct_config, $ct_config_serialized) = ct_get_config($this->db);
        return $ct_config;
    }

    /**
     * Get one config parametr
     * @param type $name Config name
     * @return boolean|Config array
     */
    public function getConfig($name) {
        if (isset($this->config[$name])) {
            return $this->config[$name];
        }
        return false;
    }

    /**
     * Set config parametr
     * @param type $key
     * @param type $value
     */
    public function setConfig($key, $value) {
        $this->db->query("UPDATE  `" . PREFIX . "_ct_config` SET `value`='{$value}'  WHERE `key`='{$key}'");
        $this->config = $this->getAllConfig();
    }

    /**
     * Get cleantalk base class
     * @return \Cleantalk
     */
    public function getCleantalk() {
        require_once ENGINE_DIR . '/modules/cleantalk/cleantalk.class.php';
        $ct = new Cleantalk(array(
                    'auth_key' => $this->config['ct_key'],
                    'response_lang' => $this->config['ct_language'],
                    'server_url' => $this->config['ct_server_url']
                ));

        return $ct;
    }

    /**
     * Get Full Module Language
     * @return array
     */
    private function getFullLang() {
        $lang = array();
        $selected_language = $this->dle_config['langs'];
        if (file_exists(ROOT_DIR . '/language/' . $selected_language . '/cleantalk.lng')) {
            require_once (ROOT_DIR . '/language/' . $selected_language . '/cleantalk.lng');
        }
        return $lang;
    }

    /**
     * Get lang template
     * @param type $item
     * @return null
     */
    public function getLang($item) {
        if (isset($this->lang[$item])) {
            return $this->lang[$item];
        }

        return null;
    }

    /**
     * Send Method
     * @param type $method
     * @param type $params
     * @return null|int
     */
    function sendRequest($method, $params) {
        //$params = array_map('charset_from', $params);
        $r = 1;
        switch ($method) {
            case 'check_message':
                $sender_id = $this->CT->getSenderId();
                $params_rpc = array(
                    $params['message'],
                    $params['base_text'],
                    $this->config['ct_key'],
                    $this->engine,
                    $params['user_info'],
                    $this->config['ct_stop_words'],
                    $this->config['ct_language'],
                    $params['session_ip'],
                    $params['user_email'],
                    $params['user_name'],
                    $sender_id,
                    $this->config['ct_links'],
                    $params['submit_time'],
                    $params['checkjs']
                );
                break;
            case 'check_newuser':
                $params_rpc = array(
                    $this->config['ct_key'],
                    $this->engine,
                    $this->config['ct_language'],
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
                foreach ($params['moderate'] as $msgFeedback)
                    $feedback[] = $msgFeedback['msg_hash'] . ':' . intval($msgFeedback['is_allow']);

                $feedback = implode(';', $feedback);

                $params_rpc = array(
                    $CT->config['auth_key'],
                    $feedback
                );
                break;
            default:
                $params_rpc = array();
                return NULL;
        }

        $plugin_url = $this->CT->config['server_url'];
        $result = NULL;

        if ((isset($this->config['ct_work_url']) && $this->config['ct_work_url'] !== '') &&
                ($this->config['ct_server_changed'] + $this->config['ct_server_ttl'] > time())) {
            $result = $this->CT->xmlRequest2(
                    $method, $params_rpc, $this->config['ct_work_url']
            );
        }

        if (!isset($result) || $result->faultCode()) {
            $matches = array();
            preg_match("#^(http://|https://)([a-z\.\-0-9]+):?(\d*)$#i", $plugin_url, $matches);
            $url_prefix = $matches[1];
            $pool = $matches[2];
            $port = $matches[3];
            if (empty($url_prefix))
                $url_prefix = 'http://';
            if (empty($pool)) {
                $result = array(
                    'allow' => 0,
                    'comment' => 'Can\'t connect to cleantalk.ru',
                    'blacklisted' => 0,
                );
            } else {
                foreach ($this->CT->get_servers_ip($pool) as $server) {
                    $server_host = gethostbyaddr($server['ip']);
                    $work_url = $url_prefix . $server_host;
                    if ($server['host'] === 'localhost')
                        $work_url = $url_prefix . $server['host'];

                    $work_url = ($port !== '') ? $work_url . ':' . $port : $work_url;

                    $result = $this->CT->xmlRequest2(
                            $method, $params_rpc, $work_url
                    );

                    if (!$result->faultCode()) {
                        $this->setConfig('ct_work_url', $work_url);
                        $this->setConfig('ct_server_ttl', $server['ttl']);
                        $this->setConfig('ct_server_changed', time());
                        break;
                    }
                }
                if (!$result) {
                    $work_url = $this->config['ct_server_url'];
                    $result = $this->CT->xmlRequest2(
                            $method, $params_rpc, $work_url
                    );
                }
            }
        }

        if (is_object($result) && !$result->faultCode()) {
            $result = $result->value();

            if ($method === 'check_message' && $sender_id == '' && isset($result['sender_id']))
                $this->CT->setSenderId($result['sender_id']);
        }else {
            $result = array(
                'allow' => 0,
                'comment' => 'Can\'t connect to cleantalk.ru',
                'blacklisted' => 0,
            );
        }

        return $result;
    }

    /**
     * Check to publish comment
     * @param type $comment
     * @param type $ct_text
     * @param type $mail
     * @param type $name
     * @return boolean|$result
     */
    public function checkMessage($comment, $ct_text, $mail, $name, $checkjs) {
        $comment = charset_from($comment, $this->dle_config['charset']);
        $ct_text = charset_from($ct_text, $this->dle_config['charset']);
        $mail = charset_from($mail, $this->dle_config['charset']);
        $name = charset_from($name, $this->dle_config['charset']);

        $ct_submit_time = time() - $_SESSION['ct_submit_comment_time'];
        $params = array(
            'message' => $comment,
            'base_text' => $ct_text,
            'user_email' => $mail,
            'user_name' => $name,
            'submit_time' => $ct_submit_time,
            'checkjs' => $checkjs,
            'session_ip' => $this->user_ip,
            'user_info' => '',
        );
        $_SESSION['ct_submit_comment_time'] = time();

        $result = $this->sendRequest('check_message', $params);

        $result = charset($result, $this->dle_config['charset']);
        return $result;
    }

    /**
     * Add to the message comment Cleantalk.ru
     * @param $message
     * @param $comment
     * @return string
     */
    public function addComment($message, $comment) {
        $message = $message . "\r\n\r\n";
        return $this->CT->addCleantalkComment($message, $comment);
    }

    /**
     * Delete the comment Cleantalk.ru
     * @param $message
     * @return mixed
     */
    public function delComment($message) {
        $message = str_replace("\r\n\r\n", '', $message);
        return $this->CT->delCleantalkComment($message);
    }

    /**
     * Sends the results of manual moderation
     * @param type $message
     * @param type $allow
     */
    public function moderate($message, $allow) {
        $hash = $this->CT->getCleantalkCommentHash($message);

        $params = array(
            'moderate' => array(
                array('msg_hash' => $hash, 'is_allow' => $allow),
            ),
        );

        $result = $this->sendRequest('send_feedback', $params);
        return $result;
    }

    /**
     * Checks if a user is blacklisted
     * @param type $ip
     * @param type $email
     * @param type $username
     * @return boolean
     */
    public function isAllowUser($email, $username) {
        $email = charset_from($email, $this->dle_config['charset']);
        $username = charset_from($username, $this->dle_config['charset']);

        $ct_submit_register_time = time() - $_SESSION['ct_submit_register_time'];
        $checkjs = (int) substr($_POST['ct_checkjs'], 0, 1);
        if ($checkjs !== 0 && $checkjs !== 1) {
            $checkjs = 0;
        }

        $params = array(
            'engine' => $this->engine,
            'session_ip' => $this->user_ip,
            'user_email' => $email,
            'user_name' => $username,
            'submit_time' => $ct_submit_register_time,
            'js_on' => $checkjs,
            'tz' => null,
        );
        $response = $this->sendRequest('check_newuser', $params);
        $response = charset($response, $this->dle_config['charset']);

        if (is_array($response)) {
            $this->comment = $response['comment'];

            if (@$response['allow'] == 0) {
                return false;
            }
        }

        if (is_object($response)) {
            if (@$response->errno > 0) {
                $this->comment = "*** Can't connect to cleantalk.ru ***";
                $this->feedbackAdmin($this->getLang('ct_email_cant_connect_subject'), $this->getLang('ct_email_cant_connect_text'));
            }
        }

        return true;
    }

    /**
     * Feedback admins
     * @param type $subject
     * @param type $message
     */
    private function feedbackAdmin($subject, $message) {
        $admins = $this->db->super_query("SELECT email FROM " . USERPREFIX . "_users WHERE user_group='1' AND allow_mail = '1'");

        include_once ENGINE_DIR . '/classes/mail.class.php';
        $mail = new dle_mail($this->dle_config);

        foreach ($admins as $email) {
            $mail->send($email, $subject, $message);
        }
    }

}

$Ct = new CleantalkModule($db, $config);
?>
