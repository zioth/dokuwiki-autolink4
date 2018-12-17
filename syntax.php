<?php
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
if(!defined('DOKU_REL')) define('DOKU_REL', '/dokuwiki/');
require_once(DOKU_PLUGIN.'syntax.php');

//TODO: Future features:
// - Clicking on the link shows the page abstract (fetched with ajax). The popup has a link to the page.

class syntax_plugin_autolink4 extends DokuWiki_Syntax_Plugin {
	private $subs = [];

	public function __construct() {
		$helper = plugin_load('helper', 'autolink4');
		$cfg = $helper->loadConfigFile();

		$lines = preg_split('/[\n\r]+/', $cfg);
		foreach ($lines as $line) {
			$line = trim($line);
			if (strlen($line)) {
				$data = str_getcsv($line);
				if (strlen($line[0]) && strlen($line[1])) {
					$this->subs[] = ['match' => '\b' . trim($data[0]) . '\b', 'to' => trim($data[1]), 'in' => trim($data[2])];
				}
			}
		}
	}

	function getType() {
		return 'substition';
	}

	function getPType() {
		return 'normal';
	}

	function getSort() {
		// Try not to interfere with any other lexer patterns.
		return 999;
	}

	function connectTo($mode) {
		foreach ($this->subs as $s) {
			$this->Lexer->addSpecialPattern($s['match'], $mode, 'plugin_autolink4');
		}
	}

	function handle($match, $state, $pos, Doku_Handler $handler) {
		global $ID;
		$ns = getNS($ID);

		// Annoyingly, there's no way (I know of) to determine which match sent us here, so we have to loop through the
		// whole list.
		foreach ($this->subs as &$s) {
			if (preg_match('/' . $s['match'] . '/i', $match)) {
				// Check that it's in the right namespace
				if (!strlen($s['in']) || substr($ns, 0, strlen($s['in'])) == $s['in']) {
					$mod = $s;
					$mod['text'] = $match;
					return $mod;
				}
			}
		}

		return $match;
	}

	function render($mode, Doku_Renderer $renderer, $data) {
		if (is_string($data)) {
			$renderer->doc .= $data;
		}
		else {
			$renderer->doc .= $renderer->internallink($data['to'], $data['text']);
		}
	}
}
