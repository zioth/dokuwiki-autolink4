<?php
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
if(!defined('DOKU_REL')) define('DOKU_REL', '/dokuwiki/');
require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_PLUGIN.'autolink4/consts.php');

//TODO: Bugs:
// - lexer combines all plugin search strings. If you have too many links in a namespace (something between 500 and 1000),
//   the regex gets too long. This has to be changed to an action plugin to fix that, and we can combined regexes in chunks.
// - How does this play with ORPHANSWANTED?
//TODO: Document:
// - Regexes always match the thing that started first, or the longest string if there are two matches.
//	'Mother in law' - [in law][Mother] => Mother
//	'The Mother in law' - [Mother in law][Mother] => Mother in law

/**
 * Autolink 4 DokuWiki plugin
 *
 * @license    MIT
 * @author     Eli Fenton
 */
class syntax_plugin_autolink4_regex extends DokuWiki_Syntax_Plugin {
	use autotooltip4_consts;

	/** @type helper_plugin_autotooltip $tooltip */
	private $tooltip;
	/** @type helper_plugin_autolink4 $tooltip */
	private $helper;

	private $foundMatches;

	public function __construct() {
		if (!plugin_isdisabled('autotooltip')) {
			$this->tooltip = plugin_load('helper', 'autotooltip');
		}

		$this->helper = plugin_load('helper', 'autolink4');
		$this->helper->loadAndProcessConfigFile();
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

		foreach ($this->helper->getSubs() as $s) {
			// Check that it's in the right namespace, and skip links to the current page.
			if ($this->_inNS($ns, $s[self::$IN]) && !$this->_isSamePage($s[self::$TO], $ID)) {
				$this->Lexer->addSpecialPattern($s[self::$MATCH], $mode, 'plugin_autolink4_regex');
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
		if ($this->foundMatches[$match]) {
			return $match;
		}

		// Load from cache
		if (isset($this->helper->getSimpleSubs()[$match])) {
			$s = $this->helper->getSimpleSubs()[$match];
			if ($s[self::$ONCE]) {
				$this->foundMatches[$match] = true;
			}

			return $s;
		}

		// Annoyingly, there's no way (I know of) to determine which match sent us here, so we have to loop through the
		// whole list.
		foreach ($this->helper->getRegexSubs() as &$s) {
			if ($s[self::$ONCE]) {
				$this->foundMatches[$match] = true;
			}

			if (preg_match('/^' . $s[self::$MATCH] . '$/', $match)) {
				// Add all found matches to simpleSubs, so we don't have to loop more than once for the same string.
				$mod = null;
				if (!isset($this->helper->getSimpleSubs()[$match])) {
					$mod = $s;
					$mod[self::$TEXT] = $match;
					//TODO: Do this differently (cache locally).
					$this->helper->getSimpleSubs()[$match] = $mod;
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
	 * @param Doku_Renderer|Doku_Renderer_metadata $renderer
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
			if (!$renderer->capture) {
				return false;
			}
			$renderer->doc .= $data[self::$TEXT];
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


	/**
	 * Are these two the same page>
	 *
	 * @param string $p1 - One page.
	 * @param string $p2 - Another page.
	 * @return bool
	 */
	function _isSamePage($p1, $p2) {
		return $p1 == $p2;
	}
}
