<?php
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
if(!defined('DOKU_REL')) define('DOKU_REL', '/dokuwiki/');
require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_PLUGIN.'autolink4/consts.php');

/********
 * This is a work in progress. See regex.php for active code.
 ********/

/**
 * Autolink 4 DokuWiki plugin
 *
 * @license    MIT
 * @author     Eli Fenton
 */
class syntax_plugin_autolink4_allwords extends DokuWiki_Syntax_Plugin {
	use autotooltip4_consts;

	/** @type helper_plugin_autotooltip $tooltip */
	private $tooltip;
	/** @type helper_plugin_autolink4 $tooltip */
	private $helper;

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
		return 1000;
	}


	//TODO: Doesn't seem to do anything.
	function getAllowedTypes() {
		global $PARSER_MODES;
		return array_keys($PARSER_MODES);
	}


	/**
	 * @param $mode
	 */
	function connectTo($mode) {
		global $ID;
		$ns = getNS($ID);

		/*
		TODO: Optimization
		Match any string of words (hoping that the high getSort avoids other plugins).
		Make my own word-by-word parser, after sorting match strings and caching. (only for non-regexes)
		Looking for earliest starting, longest match.
		- See a word. Make array of partial matches with start pos.
		- Next word - Eliminate in-progress matches, and add new ones.
		- If a match is found and there are no in-progress matches of its start pos or earlier, do the
		  replacement.
		Autolink all titles:
		- Every time a page is indexed, add the title to the cache. Remove deleted.
		*/
		//TODO: Because this always starts at the first word, it wins over the regex version except when the regex version matches the first word.
		//		If I make it non-greedy, it competes with itself, and always matches exactly one word.
		//		I could get rid of the regex plugin and only use this one, but it could interfere with other plugins.
		//$this->Lexer->addSpecialPattern('(?:[\w\'\-]+ *)+?', $mode, 'plugin_autolink4_allwords');
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
		msg($match);
		return $match;
		/*
		$this->helper->init();

		// Load from cache
		if (isset($this->helper->getSimpleSubs()[$match])) {
			return $this->helper->getSimpleSubs()[$match];
		}

		// Annoyingly, there's no way (I know of) to determine which match sent us here, so we have to loop through the
		// whole list.
		foreach ($this->helper->getRegexSubs() as &$s) {
			if (preg_match('/^' . $s[self::$MATCH] . '$/', $match)) {
				// Add all found matches to simpleSubs, so we don't have to loop more than once for the same string.
				$mod = null;
				if (!isset($this->helper->getSimpleSubs()[$match])) {
					$mod = $s;
					$mod[self::$TEXT] = $match;
					$this->helper->getSimpleSubs()[$match] = $mod;
				}

				return $mod;
			}
		}

		return $match;
		*/
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
		$renderer->doc .= $data;
		return true;


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
		return preg_replace('/#.*/', '', $p1) == preg_replace('/#.*/', '', $p2);
	}
}
