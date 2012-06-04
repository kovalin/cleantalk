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

    if ($Ct->checkMessage($comments, $ct_text, $mail, $name) === false) {
        if ($Ct->errno == 0) {
            if ($Ct->blacklisted == 0) {
                $config['allow_cmod'] = $user_group[$member_id['user_group']]['allow_modc'] = true;
                $comments = $Ct->addComment($comments, $Ct->comment);
                /**
                 * Отключение объединения
                 */
                $config['allow_combine'] = false;
            } else {
                $stop[] = $Ct->comment;
                $CN_HALT = TRUE;
            }
        } else {
            // Клинтолк не доступен
            $config['allow_cmod'] = $user_group[$member_id['user_group']]['allow_modc'] = true;
            $comments = $Ct->addComment($comments, $Ct->comment);
            $config['allow_combine'] = false;
        }
    } else {
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
    }


    $ct_log_extras = 'Username: ' . $name . ', email: ' . $mail . '. ' . $Ct->comment;
    $ct_if_exists_log = $db->super_query('show tables like "' . USERPREFIX . '_admin_logs"');
    if (!empty($ct_if_exists_log)) {
        $db->query("INSERT INTO " . USERPREFIX . "_admin_logs (name, date, ip, action, extras) values ('$name', '{$_TIME}', '{$_IP}', '0', '" . $db->safesql($ct_log_extras) . "')");
    }
}
?>