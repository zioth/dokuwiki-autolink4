<?php
/**
 * AutoLink 4 DokuWiki plugin
 *
 * @license    MIT
 * @author     Eli Fenton
 */
if(!defined('DOKU_INC')) die();

class helper_plugin_autolink4 extends DokuWiki_Admin_Plugin {
	const CONFIG_FILE = DOKU_CONF . '/autolink4.conf';

	/**
	 * Saves the config file
	 *
	 * @param string $config the raw text for the config
	 * @return bool
	 */
	public function saveConfigFile($config) {
		return io_saveFile(self::CONFIG_FILE, cleanText($config));
	}

	/**
	 * Load the config file
	 *
	 * @return string the raw text of the config
	 */
	public function loadConfigFile() {
		return file_exists(self::CONFIG_FILE) ? io_readFile(self::CONFIG_FILE) : '';
	}
}
