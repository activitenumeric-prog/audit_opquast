(function () {
	'use strict';

	function buildAjaxUrl(fullUrl, navigationPage) {
		var url = new URL(fullUrl, window.location.origin);
		url.searchParams.set('page', navigationPage);
		return url.toString();
	}

	function setLoading(region, isLoading) {
		region.classList.toggle('is-loading', isLoading);
		region.setAttribute('aria-busy', isLoading ? 'true' : 'false');
	}

	function updateRegion(region, html) {
		region.innerHTML = html;
		var title = region.querySelector('.audit-opquast-rule-nav__current-title');

		if (title) {
			title.setAttribute('tabindex', '-1');
			try {
				title.focus({ preventScroll: true });
			} catch (error) {
				title.focus();
			}
		}
	}

	function navigate(region, ajaxUrl, fullUrl, mode) {
		setLoading(region, true);

		return window.fetch(ajaxUrl, {
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			},
			credentials: 'same-origin'
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Navigation HTTP ' + response.status);
				}

				return response.text();
			})
			.then(function (html) {
				updateRegion(region, html);

				if (fullUrl) {
					if (mode === 'replace') {
						window.history.replaceState({ auditOpquastNavigation: true }, '', fullUrl);
					} else {
						window.history.pushState({ auditOpquastNavigation: true }, '', fullUrl);
					}
				}
			})
			.catch(function () {
				if (fullUrl) {
					window.location.assign(fullUrl);
				}
			})
			.finally(function () {
				setLoading(region, false);
			});
	}

	function initNavigation(region) {
		if (!region) {
			return;
		}

		region.addEventListener('click', function (event) {
			var link = event.target.closest('.audit-opquast-rule-nav__link');

			if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
				return;
			}

			var ajaxUrl = link.getAttribute('data-ajax-href') || buildAjaxUrl(link.href, region.dataset.navigationPage || 'audit_opquast_navigation');

			event.preventDefault();
			navigate(region, ajaxUrl, link.href, 'push');
		});

		window.addEventListener('popstate', function () {
			if (!region.isConnected) {
				return;
			}

			var page = new URL(window.location.href).searchParams.get('page');

			if (page && page !== 'audit_opquast_audit') {
				return;
			}

			var currentUrl = buildAjaxUrl(window.location.href, region.dataset.navigationPage || 'audit_opquast_navigation');
			navigate(region, currentUrl, null, 'replace');
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		initNavigation(document.querySelector('[data-audit-opquast-navigation-region]'));
	});
}());
