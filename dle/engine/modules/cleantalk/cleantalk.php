<?php

/**
 * Cleantalk DLE Module Class
 * @version 1.1.0
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
    private $engine = 'dle-110';

    /**
     * Cleantalk comment
     * @var string
     */
    public $comment;

    /**
     * Is black list message
     * @var int 0|1
     */
    public $blacklisted = 0;

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
        $this->ip = $_SERVER['REMOTE_ADDR'];
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
     * Check to publish comment
     * @param type $comment
     * @param type $ct_text
     * @param type $mail
     * @param type $name
     * @return boolean|$result
     */
    public function checkMessage($comment, $ct_text, $mail, $name) {
        $params = array(
            $comment,
            $ct_text,
            $this->config['ct_key'],
            $this->engine,
            '',
            $this->config['ct_stop_words'],
            $this->config['ct_language'],
            $this->user_ip,
            $mail,
            $name,
            $this->CT->getSenderId(),
            $this->config['ct_links']
        );

        $result = $this->CT->xmlRequest2(
                'check_message', $params, $this->config['ct_server_url']
        );

        if ($result) {
            $this->errno = $result->errno;

            if (is_array($result->val)) {
                $this->comment = $result->val['comment'];

                if ($result->val['blacklisted'] == 1) {
                    $this->blacklisted = $result->val['blacklisted'];
                    return false;
                }

                if ($result->val['allow'] == 0) {
                    return false;
                }
                return true;
            } elseif ($result->errno > 0) {
                $this->comment = "*** Can't connect to cleantalk.ru***";
                $this->feedbackAdmin($this->getLang('ct_email_cant_connect_subject'), $this->getLang('ct_email_cant_connect_text'));
                return false;
            } else {
                $this->comment = $result->val['comment'];
                return true;
            }
        }

        return true;
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

        $this->CT->sendFeedback($params, $response);
    }

    /**
     * Checks if a user is blacklisted
     * @param type $ip
     * @param type $email
     * @param type $username
     * @return boolean
     */
    public function isAllowUser($email, $username) {
        $params = array(
            'engine' => $this->engine,
            'ip' => $this->user_ip,
            'email' => $email,
            'username' => $username
        );
        $result = $this->CT->isAllowUser($params, $response);

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