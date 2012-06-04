<?php

if (!defined('DATALIFEENGINE') OR !defined('LOGGED_IN')) {
    die("Hacking attempt!");
}

/* if( ! $user_group[$member_id['user_group']]['admin_newsletter'] ) {
  msg( "error", $lang['index_denied'], $lang['index_denied'] );
  } */


if (file_exists(ROOT_DIR . '/language/' . $selected_language . '/cleantalk.lng')) {
    require_once (ROOT_DIR . '/language/' . $selected_language . '/cleantalk.lng');
}

include_once(ENGINE_DIR . '/api/api.class.php');
$ct_result = $db->super_query("SELECT COUNT(*) AS c FROM " . PREFIX . "_admin_sections WHERE name='cleantalk'");

function ct_echoheader($title, $image) {
    echoheader($image, $title);
    echo '<div style="padding-top:5px;padding-bottom:2px;">
<table width="100%">
    <tr>
        <td width="4"><img src="engine/skins/images/tl_lo.gif" width="4" height="4" border="0"></td>
        <td background="engine/skins/images/tl_oo.gif"><img src="engine/skins/images/tl_oo.gif" width="1" height="4" border="0"></td>
        <td width="6"><img src="engine/skins/images/tl_ro.gif" width="6" height="4" border="0"></td>
    </tr>
    <tr>
        <td background="engine/skins/images/tl_lb.gif"><img src="engine/skins/images/tl_lb.gif" width="4" height="1" border="0"></td>
        <td style="padding:5px;" bgcolor="#FFFFFF">
<table width="100%">
    <tr>
        <td bgcolor="#EFEFEF" height="29" style="padding-left:10px;"><div class="navigation">' . $title . '</div></td>
    </tr>
</table>
<div class="unterline"></div>';
    ;
}

function ct_echofooter() {
    echo '</td>
        <td background="engine/skins/images/tl_rb.gif"><img src="engine/skins/images/tl_rb.gif" width="6" height="1" border="0"></td>
    </tr>
    <tr>
        <td><img src="engine/skins/images/tl_lu.gif" width="4" height="6" border="0"></td>
        <td background="engine/skins/images/tl_ub.gif"><img src="engine/skins/images/tl_ub.gif" width="1" height="6" border="0"></td>
        <td><img src="engine/skins/images/tl_ru.gif" width="6" height="6" border="0"></td>
    </tr>
</table>
</div>';
    echofooter();
}

function ct_options_form($options, $selected) {
    $output = null;
    if (is_array($options)) {
        foreach ($options as $value => $name) {
            $output .= '<option value="' . $value . '"';
            if ($value == $selected) {
                $output .= ' selected';
            }
            $output .= '>' . $name . '</option>';
        }
    }

    return $output;
}


require_once ENGINE_DIR . '/modules/cleantalk/ct_functions.php';

/**
 * Установлен модуль?
 */
if (!$ct_result['c']) {

    /**
     * Установка
     */
    if (isset($_POST['ct_install'])) {

        $dle_api->install_admin_module('cleantalk', $lang['ct_module_name'], $lang['ct_module_about'], 'cleantalk.png', '1');

        $db->query("CREATE TABLE IF NOT EXISTS `" . PREFIX . "_ct_config` (
                    `key` varchar(32) NOT NULL,
                    `value` text NOT NULL,
                    `serialized` tinyint(1) NOT NULL DEFAULT '0',
                    PRIMARY KEY(`key`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

        $db->query("INSERT IGNORE INTO `" . PREFIX . "_ct_config` (`key`, `value`, `serialized`) VALUES
			('ct_groups', 'a:1:{i:0;s:1:\"5\";}', '1'),
			('ct_stop_words', '1', '0'),
			('ct_links', '0', '0'),
			('ct_language', 'Russian', '0'),
			('ct_key', '', '0'),
			('ct_server_url', 'http://moderate.cleantalk.ru', '0')");

        ct_echoheader($lang['ct_module_name'] . ' ' . $lang['ct_module_release'], "cleantalk");
        echo <<<HTML
            <form method="POST" action="admin.php?mod=cleantalk">
                    <center>
            {$lang['ct_installed']}<br><br>
                <input type="submit" value="Продолжить">
                <center></form>
HTML;
        ct_echofooter();
        exit();
    } else {

        /**
         * Кнопка установки
         */
        ct_echoheader($lang['ct_module_name'] . ' ' . $lang['ct_module_release'], "cleantalk");

        echo <<<HTML
<form method="POST" action="admin.php?mod=cleantalk">
        <center>
{$lang['ct_not_installed']}<br><br>
    <input type="hidden" value="1" name="ct_install">
    <input type="submit" value="Установить">
    <center>
</form>
HTML;
        ct_echofooter();
        exit();
    }
}

/**
 * Удаление модуля
 */
if (isset($_POST['ct_uninstall'])) {
    $dle_api->uninstall_admin_module('cleantalk');
    $db->query("DROP TABLE IF EXISTS  `" . PREFIX . "_ct_config`");

    ct_echoheader($lang['ct_module_name'] . ' ' . $lang['ct_module_release'], "cleantalk");
    echo <<<HTML
            <form method="POST" action="admin.php">
                    <center>
            {$lang['ct_uninstalled']}<br><br>
                <input type="submit" value="Продолжить">
                <center></form>
HTML;
    ct_echofooter();
    exit();
}
/**
 * Конфиг
 */
list($ct_config, $ct_config_serialized) = ct_get_config($db);
/**
 * Сохранение изменений
 */
if (isset($_POST['ct_save'])) {
    $ct_request = array();
    $ct_request['ct_stop_words'] = (in_array(intval($_POST['ct_stop_words']), array(0, 1))) ? intval($_POST['ct_stop_words']) : false;
    $ct_request['ct_links'] = (in_array(intval($_POST['ct_links']), array(0, 1))) ? intval($_POST['ct_links']) : false;
    $ct_request['ct_language'] = (in_array($db->safesql($_POST['ct_language']), array('ru', 'en'))) ? $db->safesql($_POST['ct_language']) : false;

    $_POST['ct_key'] = substr($_POST['ct_key'], 0, 30);
    $_POST['ct_server_url'] = substr($_POST['ct_server_url'], 0, 200);
    $ct_request['ct_key'] = $db->safesql($_POST['ct_key']);
    $ct_request['ct_server_url'] = $db->safesql($_POST['ct_server_url']);

    if (is_array($_POST['ct_groups'])) {
        $ct_real_post_ct_groups = array();
        foreach ($_POST['ct_groups'] as $ct_post_groups) {
            $ct_post_groups = intval($ct_post_groups);
            if ($ct_post_groups == 0 || $ct_post_groups > 100)
                continue;
            $ct_real_post_ct_groups[] = $ct_post_groups;
        }
        $ct_request['ct_groups'] = $ct_real_post_ct_groups;
    } else {
        $ct_request['ct_groups'] = array();
    }

    /**
     * Запись в бд
     */
    if (is_array($ct_request)) {
        foreach ($ct_request as $key => $value) {
            if ($value !== false && array_key_exists($key, $ct_config)) {
                if ($ct_config_serialized[$key] == 1)
                    $value = serialize($value);

                $db->query("UPDATE  `" . PREFIX . "_ct_config` SET `value`='{$value}'  WHERE `key`='{$key}'");
            }
        }

        header('Location: admin.php?mod=cleantalk');
    }
}

/**
 * Главное окно
 */
$ct_group_list = get_groups($ct_config['ct_groups']);

$ct_options_stop_words = ct_options_form(
        array(0 => $lang['ct_off'], 1 => $lang['ct_on']), $ct_config['ct_stop_words']
);
$ct_options_links = ct_options_form(
        array(0 => $lang['ct_off'], 1 => $lang['ct_on']), $ct_config['ct_links']
);
$ct_options_language = ct_options_form(
        array('ru' => 'Russian', 'en' => 'English'), $ct_config['ct_language']
);


ct_echoheader($lang['ct_module_name'] . ' ' . $lang['ct_module_release'], "cleantalk");
echo <<<HTML
        <form method="POST" action=""><input type="hidden" name="ct_save" value="1" >
<table width="100%">
    <tr>
        <td width="50%">&nbsp;<b>{$lang['ct_main_setings']}</b></td>
        <td width="50%">&nbsp;<b>{$lang['ct_server_setings']}</b></td>
    </tr>
    <tr>
        <td colspan="2"><div class="unterline"></div></td>
    </tr>
    <tr>
        <td width="50%" valign="top">
            <table width="100%">
                <tr>
                    <td width="220" style="padding:6px;">{$lang['ct_groups']}</td>
                    <td><select name="ct_groups[]" size="6" multiple>
                    {$ct_group_list}
                            </select></td>
                </tr>
                <tr>
                    <td style="padding:6px;">{$lang['ct_stop_words']}</td>
                    <td><select name="ct_stop_words">
                    {$ct_options_stop_words}</select></td>
                </tr>
                <tr>
                    <td style="padding:6px;">{$lang['ct_links']}</td>
                    <td><select name="ct_links">
                    {$ct_options_links}</select></td>
                </tr>
                <tr>
                    <td style="padding:6px;">{$lang['ct_language']}</td>
                    <td><select name="ct_language">
                    {$ct_options_language}</select></td>
                </tr>
            </table>
        </td>
        <td width="50%" valign="top">
        <table width="100%">
                <tr>
                    <td style="padding:6px;">{$lang['ct_key']}</td>
                    <td><input name="ct_key" value="{$ct_config['ct_key']}" ></td>
                </tr>
                <tr>
                    <td style="padding:6px;">{$lang['ct_server_url']}</td>
                    <td><input name="ct_server_url" value="{$ct_config['ct_server_url']}" style="width: 220px;"></td>
                </tr>
            </table>
        </td>
    </tr>
    <tr><td align="center" colspan="2"><br><input type="submit" value="{$lang['ct_save_button']}"</td></tr>
</table>
</form>

<form method="POST" action="" name="form_module_delete">
                    <input type="hidden" value="1" name="ct_uninstall">
<br><br><div style="float: right; color: red; cursor: pointer;" onclick="if (confirm('Вы уверены? Удалить модуль {$lang['ct_module_name']}?')) { document.form_module_delete.submit(); } event.returnValue = false; return false;"><img src="pic/delete.png" border="0" style="vertical-align: middle;"> удалить модуль</div>
</form>
HTML;
ct_echofooter();
?>