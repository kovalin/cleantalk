<?php

/**
 * Параметры модуля
 * @return array Config array
 */
function ct_get_config($db) {
    $ct_config = $db->super_query("SELECT * FROM " . PREFIX . "_ct_config", true);

    $config = array();
    $config_serialized = array();
    foreach ($ct_config as $_config) {
        if ($_config['serialized'] == 0) {
            $config[$_config['key']] = $_config['value'];
            $config_serialized[$_config['key']] = 0;
        } else {
            $config[$_config['key']] = unserialize($_config['value']);
            $config_serialized[$_config['key']] = 1;
        }
    }

    return array($config, $config_serialized);
}

/*
 * ct_server_changed
ct_server_ttl
ct_work_url
 */

/**
 * Список доступных языков
 * @return array Массив языков
 */
function ct_get_languages() {
    $language_list = array();
    $dirs = opendir(ROOT_DIR . '/language/');
    while ($dir = readdir($dirs)) {
        if (!is_file($dir) && $dir != '.' && $dir != '..') {
            if (file_exists(ROOT_DIR . '/language/' . $dir . '/cleantalk.lng')) {
                $language_list[$dir] = $dir;
            }
        }
    }

    return $language_list;
}

function debug($var, $stop = 0) {
    echo '<pre>';
    print_r($var);
    echo '</pre>';

    if (!empty($stop)) {
        exit();
    }
}

function charset($items, $charset){
    if ($charset == 'utf-8') {
        return $items;
    }

    if (is_array($items)) {
        foreach ($items as $key=>&$item) {
            if(is_array($item)) {
                charset($item, $charset);
            } else {
                $item = iconv("UTF-8", $charset, $item);

            }
        }

        return $items;
    } else {
        $items = iconv("UTF-8", $charset, $items);
        return $items;
    }
}

function charset_from($items, $charset){
    if ($charset == 'utf-8') {
        return $items;
    }

    if (is_array($items)) {
        foreach ($items as $key=>&$item) {
            if(is_array($item)) {
                charset($item, $charset);
            } else {
                $item = iconv($charset, "UTF-8", $item);

            }
        }

        return $items;
    } else {
        $items = iconv($charset, "UTF-8", $items);
        return $items;
    }
}

?>
