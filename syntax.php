<?php
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
if(!defined('DOKU_REL')) define('DOKU_REL', '/dokuwiki/');
require_once(DOKU_PLUGIN.'syntax.php');

//TODO: Future features:
// - Clicking on the link shows the page abstract (fetched with ajax). The popup has a link to the page.

class syntax_plugin_autolink4 extends DokuWiki_Syntax_Plugin {
	private $subs = [];
	private $regexSubs = [];
	private $simpleSubs = [];

	public function __construct() {
		$helper = plugin_load('helper', 'autolink4');
		$cfg = $helper->loadConfigFile();

		$lines = preg_split('/[\n\r]+/', $cfg);
		foreach ($lines as $line) {
			$line = trim($line);
			if (strlen($line)) {
				$data = str_getcsv($line);
				if (strlen($line[0]) && strlen($line[1])) {
					$match = trim($data[0]);
					$s = ['match' => '\b' . $match . '\b', 'to' => trim($data[1]), 'in' => trim($data[2])];
					$this->subs[] = $s;

					// If the search string is not a regex, cache it right away, so we don't have to loop through
					// regexes later.
					if (!preg_match('/[\\\[?.+*^$]/', $match)) {
						$this->simpleSubs[$match] = $s;
						$this->simpleSubs[$match]['text'] = $match;
					}
					else {
						$this->regexSubs[] = $s;
					}
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
		foreach ($this->subs as $s) {
			$this->Lexer->addSpecialPattern($s['match'], $mode, 'plugin_autolink4');
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
		global $ID;
		$ns = getNS($ID);

		// Load from cache
		if (isset($this->simpleSubs[$match])) {
			$s = $this->simpleSubs[$match];
			if ($this->_checkNs($ns, $s['in'])) {
				return $s;
			}
		}

		// Annoyingly, there's no way (I know of) to determine which match sent us here, so we have to loop through the
		// whole list.
		foreach ($this->regexSubs as &$s) {
			if (preg_match('/^' . $s['match'] . '$/', $match)) {
				// Cache found strings
				$mod = null;
				if (!isset($this->simpleSubs[$match])) {
					$mod = $s;
					$mod['text'] = $match;
					$this->simpleSubs[$match] = $mod;
				}

				// Check that it's in the right namespace
				if ($this->_checkNs($ns, $s['in'])) {
					return $mod;
				}
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
	 * @return bool|void
	 */
	function render($mode, Doku_Renderer $renderer, $data) {
		if (is_string($data)) {
			$renderer->doc .= $data;
		}
		else {
			$renderer->doc .= $renderer->internallink($data['to'], $data['text']);
		}
	}


	/**
	 * Is one namespace inside another.
	 *
	 * @param string $ns - Search inside this namespace.
	 * @param string $test - Look for this namespace.
	 * @return bool
	 */
	function _checkNs($ns, $test) {
		$len = strlen($test);
		return !$len || substr($ns, 0, $len) == $test;
	}
}
