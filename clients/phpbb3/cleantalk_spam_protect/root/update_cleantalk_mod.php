<?php
/**
*
* @author Denis Shagimuratov shagimuratov@cleantalk.ru 
* @package umil
* @copyright (c) 2008 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('mods/info_acp_cleantalk');
$user->setup('acp/common');

if (!file_exists($phpbb_root_path . 'umil/umil.' . $phpEx))
{
	trigger_error('Please download the latest UMIL (Unified MOD Install Library) from: <a href="http://www.phpbb.com/mods/umil/">phpBB.com/mods/umil</a>', E_USER_ERROR);
}

// We only allow a founder install this MOD
if ($user->data['user_type'] != USER_FOUNDER)
{
    if ($user->data['user_id'] == ANONYMOUS)
    {
        login_box('', 'LOGIN');
    }

    trigger_error('NOT_AUTHORISED');
}

if (!class_exists('umil'))
{
	include($phpbb_root_path . 'umil/umil.' . $phpEx);
}

// If you want a completely stand alone version (able to use UMIL without messing with any of the language stuff) send true, otherwise send false
$umil = new umil(true);

if ($config['ct_agent'] == 'ct-phpbb3-294')
	$umil->config_update('ct_agent', 'ct-phpbb3-304');

if ($config['ct_version'] == '2.9.4')
	$umil->config_remove('ct_version');

// We are done
trigger_error('<br />' . $user->lang['CT_SUCCESSFULLY_INSTALLED'] . '<br /><br />' . sprintf($user->lang['PROCEED_TO_ACP'], '<a href="' . append_sid("{$phpbb_root_path}adm/index.$phpEx", '', true, $user->session_id) . '">', '</a>'));

?>
