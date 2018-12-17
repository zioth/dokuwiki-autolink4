<?php
/**
 * Autolink4 plugin for DokuWiki
 *
 * @license    MIT
 * @author     Eli Fenton
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

/**
 *
 */
class admin_plugin_autolink4 extends DokuWiki_Admin_Plugin {
	/** @type helper_plugin_autolink4 */
	protected $hlp;

	public function __construct() {
		$this->hlp = plugin_load('helper', 'autolink4');
	}

	/**
	 * return sort order for position in admin menu
	 */
	public function getMenuSort() {
		return 140;
	}

	/**
	 * return prompt for admin menu
	 */
	public function getMenuText($language) {
		return $this->getLang('name');
	}

	/**
	 * handle user request
	 */
	public function handle() {
		global $INPUT;
		if($INPUT->post->has('aldata')) {
			if(!$this->hlp->saveConfigFile($INPUT->post->str('aldata'))) {
				msg('Failed to save data', 1);
			}
		}
	}

	/**
	 * output appropriate html
	 */
	public function html() {
		global $lang;
		echo $this->locale_xhtml('admin_help');
		echo '<form action="" method="post" >';
		echo '<input type="hidden" name="do" value="admin" />';
		echo '<input type="hidden" name="page" value="autolink4" />';
		echo '<textarea class="edit" rows="15" cols="80" style="height: 500px" name="aldata">';
		echo formtext($this->hlp->loadConfigFile());
		echo '</textarea><br/><br/>';
		echo '<input type="submit" value="' . $lang['btn_save'] . '" class="button" />';
		echo '</form>';
	}

}
