(function () {
	'use strict';

	document.querySelectorAll('.ifaq-copy-shortcode-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var code = btn.getAttribute('data-shortcode');
			var status = btn.querySelector('.ifaq-copy-status');
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(code).then(function () {
					if (status) {
						status.textContent = 'Copied!';
						status.classList.add('is-copied');
						setTimeout(function () {
							status.textContent = 'Copy';
							status.classList.remove('is-copied');
						}, 2000);
					}
				});
			}
		});
	});

	document.addEventListener('click', function (e) {
		var trigger = e.target.closest('[data-confirm]');
		if (!trigger) {
			return;
		}
		var message = trigger.getAttribute('data-confirm');
		if (message && !window.confirm(message)) {
			e.preventDefault();
		}
	});
})();
