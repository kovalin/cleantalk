<?php
/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
    $lang = array();
}
// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine

$lang = array_merge($lang, array(
    'CT_ENABLE'                       => 'Enable MOD',
    'CT_SERVER_URL'                       	=> 'Cleantalk server URL',
    'CT_SERVER_URL_EXPLAIN'               	=> 'Example: http://moderate.cleantalk.ru', 
    'CT_AUTH_KEY'                       	=> 'Authorization key',
    'CT_AUTH_KEY_EXPLAIN'                   => 'Key to enable SPAM protect and automoderation. To get a new key please register at site <a href="http://cleantalk.ru/install/phpbb3?step=2" target="_blank">cleantalk.ru</a>',
	'ENABLE_STOPLIST_CHECK' 		=> 'Enable stoplist check',
	'ENABLE_STOPLIST_CHECK_EXPLAIN' 		=> 'Messages with offensive language will be sent to premoderation.',
	'CT_ALLOW_LINKS' 		=> 'Allow links',
	'CT_ALLOW_LINKS_EXPLAIN' 		=> 'Allow offtop posts with HTML links, emails, phone and ICQ numbers.',
	'CT_SETTINGS' 		=> 'Cleantalk. Spam protect',
	'CT_RESPONSE_LANGUAGE' 		=> 'Language of service messages',
	'CT_MODERATE_GUESTS' 		=> 'Moderate Guests',
	'CT_MODERATE_NEWLY_REGISTERED' 		=> 'Moderate Newly Registered Users',
	'CT_MODERATE_REGISTERED' 		=> 'Moderate Registered Users',
	'CT_NEWUSER' 		=> 'Test registration',
	'CT_NEWUSER_EXPLAIN' 		=> 'Failed the test will be given a refusal to register with a statement of reasons.',
	'CT_SUCCESSFULLY_INSTALLED' 		=> 'Cleantalk spam protect successfully installed!',
));

?>
