<?php
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
if(!defined('DOKU_REL')) define('DOKU_REL', '/dokuwiki/');
require_once(DOKU_PLUGIN.'syntax.php');

//TODO: Bugs:
// - lexer combines all plugin search strings. If you have too many links in a namespace (something between 500 and 1000),
//   the regex gets too long. This has to be changed to an action plugin to fix that, and we can combined regexes in chunks.

/**
 * Autolink 4 DokuWiki plugin
 *
 * @license    MIT
 * @author     Eli Fenton
 */
class syntax_plugin_autolink4 extends DokuWiki_Syntax_Plugin {
	private $subs = [];
	private $regexSubs = [];
	private $simpleSubs = [];
	private $didInit = false;
	/** @type helper_plugin_autotooltip $tooltip */
	private $tooltip;

	// Values from the file
	static $ORIG = 0;
	static $TO = 1;
	static $IN = 2;
	static $TOOLTIP = 3;
	// Calculated values
	static $MATCH = 4;
	static $TEXT = 5;

	public function __construct() {
		if (!plugin_isdisabled('autotooltip')) {
			$this->tooltip = plugin_load('helper', 'autotooltip');
		}

		/** @type helper_plugin_autolink4 $helper */
		$helper = plugin_load('helper', 'autolink4');
		$cfg = $helper->loadConfigFile();

		// Convert the config into usable data.
		$lines = preg_split('/[\n\r]+/', $cfg);
		foreach ($lines as $line) {
			$line = trim($line);
			if (strlen($line)) {
				$data = str_getcsv($line);

				if (strlen($data[self::$ORIG]) && strlen($data[self::$TO])) {
					$orig = trim($data[self::$ORIG]);
					$this->subs[] = [
						$orig,
						trim($data[self::$TO]),
						isset($data[self::$IN]) ? trim($data[self::$IN]) : null,
						isset($data[self::$TOOLTIP]) ? strstr($data[self::$TOOLTIP], 'tt') !== FALSE : false,
						'\b' . $orig . '\b'
					];
				}
			}
		}
	}


	/**
	 * @return string
	 */
	function getType() {
		return 'substition';
	}


	/**
	 * @return string
	 */
	function getPType() {
		return 'normal';
	}


	/**
	 * @return int
	 */
	function getSort() {
		// Try not to interfere with any other lexer patterns.
		return 999;
	}


	/**
	 * @param $mode
	 */
	function connectTo($mode) {
		global $ID;
		$ns = getNS($ID);

		foreach ($this->subs as $s) {
			// Check that it's in the right namespace, and skip links to the current page.
			if ($this->_inNS($ns, $s[self::$IN]) && $s[self::$TO] != $ID) {
				$this->Lexer->addSpecialPattern($s[self::$MATCH], $mode, 'plugin_autolink4');
			}
		}
	}


	/**
	 * Handle the found text, and send it off to render().
	 *
	 * @param string $match - The found text, from addSpecialPattern.
	 * @param int $state - The DokuWiki event state.
	 * @param int $pos - The position in the full text.
	 * @param Doku_Handler $handler
	 * @return array|string
	 */
	function handle($match, $state, $pos, Doku_Handler $handler) {
		// Save initialization of regexSubs and simpleSubs until now. No reason to do all that pre-processing
		// if there aren't any substitutions to make.
		if (!$this->didInit) {
			$this->didInit = true;
			foreach ($this->subs as $s) {
				$orig = $s[self::$ORIG];
				// If the search string is not a regex, cache it right away, so we don't have to loop through
				// regexes later.
				if (!preg_match('/[\\\[?.+*^$]/', $orig)) {
					$this->simpleSubs[$orig] = $s;
					$this->simpleSubs[$orig][self::$TEXT] = $orig;
				}
				else {
					$this->regexSubs[] = $s;
				}
			}
		}

		// Load from cache
		if (isset($this->simpleSubs[$match])) {
			return $this->simpleSubs[$match];
		}

		// Annoyingly, there's no way (I know of) to determine which match sent us here, so we have to loop through the
		// whole list.
		foreach ($this->regexSubs as &$s) {
			if (preg_match('/^' . $s[self::$MATCH] . '$/', $match)) {
				// Add all found matches to simpleSubs, so we don't have to loop more than once for the same string.
				$mod = null;
				if (!isset($this->simpleSubs[$match])) {
					$mod = $s;
					$mod[self::$TEXT] = $match;
					$this->simpleSubs[$match] = $mod;
				}

				return $mod;
			}
		}

		return $match;
	}


	/**
	 * Render the replaced links.
	 *
	 * @param string $mode
	 * @param Doku_Renderer $renderer
	 * @param array|string $data - Data from handle()
	 * @return bool
	 */
	function render($mode, Doku_Renderer $renderer, $data) {
		if (is_string($data)) {
			$renderer->doc .= $data;
		}
		else if ($mode == 'xhtml') {
			if ($this->tooltip && $data[self::$TOOLTIP]) {
				$renderer->doc .= $this->tooltip->forWikilink($data[self::$TO], $data[self::$TEXT]);
			}
			else {
				$renderer->internallink($data[self::$TO], $data[self::$TEXT]);
			}
		}
		else {
			$renderer->doc .= $data[self::$ORIG];
		}
		return true;
	}


	/**
	 * Is one namespace inside another.
	 *
	 * @param string $ns - Search inside this namespace.
	 * @param string $test - Look for this namespace.
	 * @return bool
	 */
	function _inNS($ns, $test) {
		$len = strlen($test);
		return !$len || substr($ns, 0, $len) == $test;
	}
}
