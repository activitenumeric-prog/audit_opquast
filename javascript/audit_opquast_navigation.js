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
		initDynamicRegions(region);
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

		if (region.dataset.auditOpquastNavigationInit === 'oui') {
			return;
		}
		region.dataset.auditOpquastNavigationInit = 'oui';

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

	function initRuleList() {
		if (document.body.dataset.auditOpquastRuleListInit === 'oui') {
			return;
		}
		document.body.dataset.auditOpquastRuleListInit = 'oui';

		document.addEventListener('click', function (event) {
			var link = event.target.closest('.audit-opquast-rule-item__edit-link');
			var region = document.querySelector('[data-audit-opquast-navigation-region]');

			if (!link || !region || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
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

		if (region.dataset.auditOpquastParamsInit === 'oui') {
			return;
		}
		region.dataset.auditOpquastParamsInit = 'oui';

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

	function buildFormUrl(form, pageName) {
		var url = new URL(form.getAttribute('action') || window.location.href, window.location.origin);
		var formData = new FormData(form);

		formData.forEach(function (value, key) {
			url.searchParams.set(key, value);
		});

		url.searchParams.set('page', pageName);
		return url.toString();
	}

	function initResultsRegion(region) {
		if (!region) {
			return;
		}

		if (region.dataset.auditOpquastResultsInit === 'oui') {
			return;
		}
		region.dataset.auditOpquastResultsInit = 'oui';

		region.addEventListener('click', function (event) {
			var shortcut = event.target.closest('.audit-opquast-shortcut');

			if (!shortcut || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
				return;
			}

			var ajaxUrl = buildAjaxUrl(shortcut.href, region.dataset.resultsPage || 'audit_opquast_resultats');
			var fullUrl = preserveCurrentDebugParams(shortcut.href);

			ajaxUrl = preserveCurrentDebugParams(ajaxUrl);

			event.preventDefault();
			navigate(region, ajaxUrl, fullUrl, 'push');
		});

		region.addEventListener('submit', function (event) {
			var form = event.target.closest('.audit-opquast-filters');

			if (!form) {
				return;
			}

			var ajaxUrl = preserveCurrentDebugParams(buildFormUrl(form, region.dataset.resultsPage || 'audit_opquast_resultats'));
			var fullUrl = preserveCurrentDebugParams(buildFormUrl(form, 'audit_opquast_audit'));

			event.preventDefault();
			navigate(region, ajaxUrl, fullUrl, 'push');
		});
	}

	function initDynamicRegions(root) {
		if (!root) {
			return;
		}

		initNavigation(root.querySelector('[data-audit-opquast-navigation-region]'));
		initParamsRegion(root.querySelector('[data-audit-opquast-params-region]'));
		initResultsRegion(root.querySelector('[data-audit-opquast-results-region]'));
	}

	document.addEventListener('DOMContentLoaded', function () {
		initDynamicRegions(document);
		initRuleList();
	});
}());
