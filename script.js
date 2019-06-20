var plugin_autolink4 = {
	/**
	 * Enable or disable a flag for all links.
	 *
	 * @param {Boolean} on
	 * @param {String} flag - The name of the flag.
	 */
	toggleFlag: function(on, flag) {
		var textElt = document.querySelector('.plugin-autolink4__admintext');
		textElt.value = textElt.value
			.split(/\r?\n/)
			.map(function(line) {
				if (/^\s*$/.test(line)) {
					return line;
				}

				var parts = line.split(/\s*,\s*/);
				var flags = (parts[3] || '')
					.split(/\s*\|\s*/)
					.filter(function(f) {return f && f != flag;});

				if (on) {
					flags.push(flag);
				}

				// Remove all flags
				if (flags.length == 0) {
					parts.length = 3;
				}
				else {
					parts[3] = flags.join('|');
				}

				return parts.join(', ');
			})
			.join('\n');
	}
};
