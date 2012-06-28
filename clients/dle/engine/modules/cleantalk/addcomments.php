<?php

if (!defined('DATALIFEENGINE')) {
    die("Hacking attempt!");
}

require_once ENGINE_DIR . '/modules/cleantalk/cleantalk.php';

if (in_array($member_id['user_group'], $Ct->getConfig('ct_groups')) && !$CN_HALT) {

    $ct_row_news = $db->super_query("SELECT short_story from " . PREFIX . "_post WHERE id='$post_id'");
    $ct_row_comments = $db->super_query("SELECT text from " . PREFIX . "_comments WHERE post_id='$post_id' AND approve=1 ORDER BY id DESC LIMIT 5", true);

    $ct_text = $ct_row_news['short_story'];

    foreach ($ct_row_comments as $ct_comment) {
        $ct_text .= "\n\n" . $ct_comment['text'];
    }

    $ct_text = $db->safesql($ct_text);

    /* print_r($Ct->sendRequest('check_message', array(
      'message' => $comments,
      'base_text' => $ct_text,
      'user_email' => $mail,
      'user_name' => $name,
      'message' => $comments,
      'submit_time' => 60,
      'checkjs' => 1,
      'session_ip' => '',
      'user_info' => '',
      )));
      exit(); */

    $checkjs = (int) substr($_POST['ct_checkjs'], 0, 1);
    if ($checkjs !== 0 && $checkjs !== 1) {
        $checkjs = 0;
    }

    $result = $Ct->checkMessage($comments, $ct_text, $mail, $name, $checkjs);

    if ($result['allow'] == 1) {
        /**
         * Отключение премодерации
         */
        $config['allow_cmod'] = false;

        /**
         * Возможно ли объединение?
         */
        if ($config['allow_combine']) {
            $ct_row = $db->super_query("SELECT id, post_id, user_id, date, text, ip, is_register, approve FROM " . PREFIX . "_comments WHERE post_id = '$post_id' ORDER BY id DESC LIMIT 0,1");
            if ($ct_row['id']) {
                if (preg_match('/\*\*\*.+\*\*\*/', $ct_row['text'])) {
                    $config['allow_combine'] = false;
                }
            }
        }
    } else {
        if (isset($result['blacklisted']) && $result['blacklisted'] == 1) {
            $stop[] = $result['comment'];
            $CN_HALT = TRUE;
        } else {
            $config['allow_cmod'] = $user_group[$member_id['user_group']]['allow_modc'] = true;
            $comments = $Ct->addComment($comments, $result['comment']);

            /**
             * Отключение объединения
             */
            $config['allow_combine'] = false;
        }
    }

    $ct_log_extras = 'Username: ' . $name . ', email: ' . $mail . '. ' . $result['comment'];
    $ct_if_exists_log = $db->super_query('show tables like "' . USERPREFIX . '_admin_logs"');
    if (!empty($ct_if_exists_log)) {
        $db->query("INSERT INTO " . USERPREFIX . "_admin_logs (name, date, ip, action, extras) values ('$name', '{$_TIME}', '{$_IP}', '0', '" . $db->safesql($ct_log_extras) . "')");
    }
}
?>