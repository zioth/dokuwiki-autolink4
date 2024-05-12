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

	private $ignoreMatches = []; // Ignore these, because they were already found once and they're configured to be unique.

	public function getSubs() {
		return self::$subs; //TODO: Remove this later?
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

		if (!file_exists(self::CONFIG_FILE)) {
			return;
		}

		$cfg = io_readFile(self::CONFIG_FILE);

		global $ID;
		$current_ns = getNS($ID);

		// Convert the config into usable data.
		$lines = preg_split('/[\n\r]+/', $cfg);
		foreach ($lines as $line) {
			$line = trim($line);
			if (strlen($line) == 0) {
				continue;
			}

			$data = array_pad(str_getcsv($line), self::$MAX_VAL, '');
			if (!strlen($data[self::$ORIG]) || !strlen($data[self::$TO])) {
				continue;
			}

			$orig = trim($data[self::$ORIG]);

			$ns = isset($data[self::$IN]) ? trim($data[self::$IN]) : null;
			if (!$this->inNS($current_ns, $ns)) {
				continue;
			}

			$s = [];
			$s[self::$ORIG] = $orig;
			$s[self::$TO] = trim($data[self::$TO]);
			$s[self::$IN] = $ns;
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
				$s = $this->cacheMatch($orig, $s);
			}
		}
	}


        /**
         * Get match data from a string.
         */
	public function getMatch($match) {
                // If there's a matching non-regex pattern, or we cached it after finding the regex patter on the page,
                // we can load it from the cache.
                $found = self::$simpleSubs[$match] ?? null;
		if ($found != null) {
			return $found;
		}

		// There's no way to determine which match sent us here, so we have to loop through the whole list.
		foreach (self::$regexSubs as &$s) {
			if (preg_match('/^' . $s[self::$MATCH] . '$/', $match)) {
				// Cache the matched string, so we don't have to loop more than once for the same match.
				$found = $this->cacheMatch($match, $s);
				break;
			}
		}
		return $found;
	}

	/**
	 * Call this in your xhtml renderer code to decide whether it should be rendered as plain text.
	 *
	 * @param Object $data - The return value of getMatch().
	 */
	public function shouldRenderPlainText($data) {
		if (is_string($data)) {
			return true;
		}
		$text = $data[self::$TEXT];
		$match = $data[self::$MATCH] ?? $text;

		if (array_key_exists($text, $this->ignoreMatches) || array_key_exists($match, $this->ignoreMatches)) {
                       	return true;
               	}

		if ($data[self::$ONCE]) {
                        $this->ignoreMatches[$text] = true;
			$this->ignoreMatches[$match] = true;
                }
		return false;
	}


        /**
         * Cache a simple match
         */
	public function cacheMatch($match, $data) {
		// We usually call this with a different text match, so that two things can link to the same page.
		$data[self::$TEXT] = $match;
		self::$simpleSubs[$match] = $data;
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
