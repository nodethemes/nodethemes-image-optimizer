/* NodeThemes Image Optimizer — admin script */
(function ($) {
	'use strict';
	$('#ntio-quality').on('input change', function () {
		$('#ntio-quality-out').text(this.value);
	});
})(jQuery);
