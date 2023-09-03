<?php
/**
 * AutoLink 4 DokuWiki plugin
 *
 * @license    MIT
 * @author     Eli Fenton
 */
if(!defined('DOKU_INC')) die();
require_once(DOKU_PLUGIN.'autolink4/consts.php');

class helper_plugin_autolink4 extends DokuWiki_Plugin {
	use autotooltip4_consts;
	const CONFIG_FILE = DOKU_CONF . 'autolink4.conf';

	static $didInit = false;
	static $subs = [];
	static $regexSubs = []; // flat array of data
	static $simpleSubs = []; // 2d map of [namespace][string match]=>data


	public function getSubs() {
		return self::$subs; //TODO: Remove this later?
	}
	public function getRegexSubs() {
		return self::$regexSubs;
	}

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
	 */
	public function loadConfigFile() {
		if (file_exists(self::CONFIG_FILE)) {
			return io_readFile(self::CONFIG_FILE);
		}
	}


	/**
	 * Load the config file
	 */
	public function loadAndProcessConfigFile() {
		// Only load once, so we don't re-process with things like plugin:include.
		if (self::$didInit) {
			return;
		}
		self::$didInit = true;

		if (file_exists(self::CONFIG_FILE)) {
			$cfg = io_readFile(self::CONFIG_FILE);

			// Convert the config into usable data.
			$lines = preg_split('/[\n\r]+/', $cfg);
			foreach ($lines as $line) {
				$line = trim($line);
				if (strlen($line)) {
					$data = str_getcsv($line);
					if (strlen($data[self::$ORIG]) && strlen($data[self::$TO])) {
						$orig = trim($data[self::$ORIG]);

						// utf-8 codes don't work with addSpecialPattern().
						// https://github.com/splitbrain/dokuwiki/issues/856
						// This fix to hex-escape byte codes does not help:
						//if (strlen($orig) != mb_strlen($orig, 'UTF8')) {
						//	$orig = '\\x' . implode('\\x', str_split(implode('', unpack('H*', $orig)), 2));
						//}
						$s = [];
						$s[self::$ORIG] = $orig;
						$s[self::$TO] = trim($data[self::$TO]);
						$s[self::$IN] = isset($data[self::$IN]) ? trim($data[self::$IN]) : null;
						$s[self::$FLAGS] = isset($data[self::$FLAGS]) ? trim($data[self::$FLAGS]) : null;
						$s[self::$TOOLTIP] = isset($data[self::$FLAGS]) ? strstr($data[self::$FLAGS], 'tt') !== FALSE : false;
						$s[self::$ONCE] = isset($data[self::$FLAGS]) ? strstr($data[self::$FLAGS], 'once') !== FALSE : false;
						$s[self::$INWORD] = isset($data[self::$FLAGS]) ? strstr($data[self::$FLAGS], 'inword') !== FALSE : false;

						// Add word breaks, and collapse one space (allows newlines).
						if ($s[self::$INWORD]) {
							$s[self::$MATCH] = preg_replace('/ /', '\s', $orig);
						}
						else {
							$s[self::$MATCH] = '\b' . preg_replace('/ /', '\s', $orig) . '\b';
						}

						self::$subs[] = $s;

						if (preg_match('/[\\\[?.+*^$]/', $orig)) {
							self::$regexSubs[] = $s;
						}
						else {
							// If the search string is not a regex, cache it right away, so we don't have to loop
							// through regexes later.
							$s = $this->cacheMatch($orig, $s[self::$IN], $s);
						}
					}
				}
			}
		}
	}


        /**
         * Get a simple match
         */
	public function getMatch($match, $ns) {
		foreach (self::$simpleSubs as $key => &$vals) {
			if ($this->inNS($ns, $key) && isset(self::$simpleSubs[$key][$match])) {
				return self::$simpleSubs[$key][$match];
			}
		}
		return null;
	}	


        /**
         * Cache a simple match
         */
	public function cacheMatch($match, $ns, $data) {
		if (!isset(self::$simpleSubs[$ns])) {
			self::$simpleSubs[$ns] = [];
		}

		// Sometimes, we call this with a different text match (when caching regex)
		$data[self::$TEXT] = $match;
		self::$simpleSubs[$ns][$match] = $data;
		return $data;
	}	

	/**
	 * Is one namespace inside another.
	 *
	 * @param string $ns - Search inside this namespace.
	 * @param string $test - Look for this namespace.
	 * @return bool
	 */
	function inNS($ns, $test) {
		$len = strlen($test);
		return !$len || substr($ns, 0, $len) == $test;
	}
}
