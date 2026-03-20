(function () {
	'use strict';

	function buildAjaxUrl(fullUrl, navigationPage) {
		var url = new URL(fullUrl, window.location.origin);
		url.searchParams.set('page', navigationPage);
		return url.toString();
	}

	function preserveCurrentDebugParams(urlString) {
		var url = new URL(urlString, window.location.origin);
		var currentUrl = new URL(window.location.href);
		var varMode = currentUrl.searchParams.get('var_mode');

		if (varMode && !url.searchParams.get('var_mode')) {
			url.searchParams.set('var_mode', varMode);
		}

		return url.toString();
	}

	function setLoading(region, isLoading) {
		region.classList.toggle('is-loading', isLoading);
		region.setAttribute('aria-busy', isLoading ? 'true' : 'false');
	}

	function updateRegion(region, html) {
		region.innerHTML = html;
	}

	function scrollToRegion(region) {
		if (!region) {
			return;
		}

		try {
			region.scrollIntoView({ behavior: 'smooth', block: 'start' });
		} catch (error) {
			region.scrollIntoView(true);
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
			var fullUrl = preserveCurrentDebugParams(link.href);

			ajaxUrl = preserveCurrentDebugParams(ajaxUrl);

			event.preventDefault();
			navigate(region, ajaxUrl, fullUrl, 'push');
		});

		window.addEventListener('popstate', function () {
			if (!region.isConnected) {
				return;
			}

			var page = new URL(window.location.href).searchParams.get('page');

			if (page && page !== 'audit_opquast_audit') {
				return;
			}

			var currentUrl = preserveCurrentDebugParams(
				buildAjaxUrl(window.location.href, region.dataset.navigationPage || 'audit_opquast_navigation')
			);
			navigate(region, currentUrl, null, 'replace');
		});
	}

	function initRuleList(region) {
		if (!region) {
			return;
		}

		document.addEventListener('click', function (event) {
			var link = event.target.closest('.audit-opquast-rule-item__edit-link');

			if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
				return;
			}

			var ajaxUrl = link.getAttribute('data-ajax-href') || buildAjaxUrl(link.href, region.dataset.navigationPage || 'audit_opquast_navigation');
			var fullUrl = preserveCurrentDebugParams(link.href);

			ajaxUrl = preserveCurrentDebugParams(ajaxUrl);

			event.preventDefault();
			navigate(region, ajaxUrl, fullUrl, 'push').then(function () {
				scrollToRegion(region);
			});
		});
	}

	function initParamsRegion(region) {
		if (!region) {
			return;
		}

		region.addEventListener('click', function (event) {
			var link = event.target.closest('.audit-opquast-params__toggle');

			if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
				return;
			}

			var ajaxUrl = link.getAttribute('data-ajax-href') || buildAjaxUrl(link.href, region.dataset.paramsPage || 'audit_opquast_parametres');
			var fullUrl = preserveCurrentDebugParams(link.href);

			ajaxUrl = preserveCurrentDebugParams(ajaxUrl);

			event.preventDefault();
			navigate(region, ajaxUrl, fullUrl, 'replace');
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var navigationRegion = document.querySelector('[data-audit-opquast-navigation-region]');

		initNavigation(navigationRegion);
		initRuleList(navigationRegion);
		initParamsRegion(document.querySelector('[data-audit-opquast-params-region]'));
	});
}());
