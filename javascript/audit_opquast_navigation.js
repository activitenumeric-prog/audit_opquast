(function () {
	'use strict';

	function buildAjaxUrl(fullUrl, navigationPage) {
		var url = new URL(fullUrl, window.location.origin);
		url.searchParams.set('page', navigationPage);
		return url.toString();
	}

	function preserveCurrentDebugParams(urlString) {
		var normalizedUrlString = String(urlString || '')
			.replace(/&amp;/g, '&')
			.replace(/&#38;/g, '&');
		var url = new URL(normalizedUrlString, window.location.origin);
		var currentUrl = new URL(window.location.href);
		var varMode = currentUrl.searchParams.get('var_mode');

		if (varMode && !url.searchParams.get('var_mode')) {
			url.searchParams.set('var_mode', varMode);
		}

		return url.toString();
	}

	function ensureHash(urlString, hash) {
		var url = new URL(urlString, window.location.origin);

		if (hash) {
			url.hash = hash;
		}

		return url.toString();
	}

	function preserveSubmittedFilters(urlString, formData) {
		var url = new URL(urlString, window.location.origin);
		var filterMapping = {
			q: 'q',
			famille: 'famille',
			tri: 'tri',
			statut_verification_filtre: 'statut_verification'
		};

		Object.keys(filterMapping).forEach(function (fieldName) {
			var searchParam = filterMapping[fieldName];
			var value = String(formData.get(fieldName) || '').trim();

			if (value) {
				url.searchParams.set(searchParam, value);
				return;
			}

			url.searchParams.delete(searchParam);
		});

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

	function refreshRegion(region, ajaxUrl) {
		if (!region || !ajaxUrl) {
			return Promise.resolve();
		}

		setLoading(region, true);

		return window.fetch(ajaxUrl, {
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			},
			credentials: 'same-origin'
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Refresh HTTP ' + response.status);
				}

				return response.text();
			})
			.then(function (html) {
				updateRegion(region, html);
			})
			.finally(function () {
				setLoading(region, false);
			});
	}

	function showToast(message, type) {
		var toast;

		if (!message) {
			return;
		}

		toast = document.querySelector('[data-audit-opquast-toast]');

		if (!toast) {
			toast = document.createElement('div');
			toast.className = 'audit-opquast-toast';
			toast.setAttribute('data-audit-opquast-toast', 'oui');
			toast.setAttribute('role', 'status');
			toast.setAttribute('aria-live', 'polite');
			document.body.appendChild(toast);
		}

		toast.textContent = message;
		toast.classList.remove('is-visible', 'audit-opquast-toast--error');

		if (type === 'error') {
			toast.classList.add('audit-opquast-toast--error');
		}

		window.clearTimeout(showToast._timer);
		window.requestAnimationFrame(function () {
			toast.classList.add('is-visible');
		});

		showToast._timer = window.setTimeout(function () {
			toast.classList.remove('is-visible');
		}, 2600);
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

		region.addEventListener('submit', function (event) {
			var form = event.target.closest('.formulaire_editer_audit_opquast_resultat form');
			var ajaxSaveUrl;
			var payload;
			var fullUrl;
			var ajaxUrl;

			if (!form) {
				return;
			}

			ajaxSaveUrl = form.getAttribute('data-ajax-save-url');

			if (!ajaxSaveUrl) {
				return;
			}

			event.preventDefault();
			setLoading(region, true);

			payload = new FormData(form);
			ajaxSaveUrl = preserveCurrentDebugParams(ajaxSaveUrl);

			window.fetch(ajaxSaveUrl, {
				method: 'POST',
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				},
				body: payload,
				credentials: 'same-origin'
			})
				.then(function (response) {
					if (!response.ok) {
						throw new Error('Save HTTP ' + response.status);
					}

					return response.json();
				})
				.then(function (data) {
					if (!data || !data.ok || !data.redirect) {
						throw new Error('Save payload');
					}

					showToast(data.message);

					fullUrl = ensureHash(
						preserveSubmittedFilters(
							preserveCurrentDebugParams(data.redirect),
							payload
						),
						region.id || 'audit-opquast-navigation-region'
					);
					var resultsRegion = document.querySelector('[data-audit-opquast-results-region]');
					var dashboardRegion = document.querySelector('[data-audit-opquast-dashboard-region]');
					var resultsAjaxUrl = preserveCurrentDebugParams(
						buildAjaxUrl(fullUrl, (resultsRegion && resultsRegion.dataset.resultsPage) || 'audit_opquast_resultats')
					);
					var dashboardAjaxUrl = preserveCurrentDebugParams(
						buildAjaxUrl(fullUrl, (dashboardRegion && dashboardRegion.dataset.dashboardPage) || 'audit_opquast_tableau_bord')
					);
					var navigationTargetSelector = '[data-audit-opquast-navigation-region]';

					return Promise.all([
						resultsRegion ? navigate(resultsRegion, resultsAjaxUrl, fullUrl, 'push') : Promise.resolve(),
						dashboardRegion ? refreshRegion(dashboardRegion, dashboardAjaxUrl) : Promise.resolve()
					]).then(function () {
						scrollToRegion(document.querySelector(navigationTargetSelector));
					});
				})
				.catch(function () {
					showToast('Erreur pendant l\'enregistrement.', 'error');
					form.submit();
				})
				.finally(function () {
					setLoading(region, false);
				});
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

	function initFamilyLinks() {
		if (document.body.dataset.auditOpquastFamilyLinksInit === 'oui') {
			return;
		}
		document.body.dataset.auditOpquastFamilyLinksInit = 'oui';

		document.addEventListener('click', function (event) {
			var link = event.target.closest('.audit-opquast-family-kpi__link');
			var region = document.querySelector('[data-audit-opquast-results-region]');

			if (!link || !region || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
				return;
			}

			var ajaxUrl = link.getAttribute('data-ajax-href') || buildAjaxUrl(link.href, region.dataset.resultsPage || 'audit_opquast_resultats');
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
			var dashboardRegion = document.querySelector('[data-audit-opquast-dashboard-region]');

			if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
				return;
			}

			var fullUrl = preserveCurrentDebugParams(link.href);
			var ajaxUrl = preserveCurrentDebugParams(
				buildAjaxUrl(fullUrl, (dashboardRegion && dashboardRegion.dataset.dashboardPage) || 'audit_opquast_tableau_bord')
			);

			event.preventDefault();
			navigate(dashboardRegion || region, ajaxUrl, fullUrl, 'replace');
		});
	}

	function initCreationRegion(region) {
		if (!region) {
			return;
		}

		if (region.dataset.auditOpquastCreationInit === 'oui') {
			return;
		}
		region.dataset.auditOpquastCreationInit = 'oui';

		region.addEventListener('click', function (event) {
			var link = event.target.closest('a.audit-opquast-creation__toggle');

			if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
				return;
			}

			var ajaxUrl = link.getAttribute('data-ajax-href') || buildAjaxUrl(link.href, region.dataset.creationPage || 'audit_opquast_creation');
			var fullUrl = preserveCurrentDebugParams(link.href);

			ajaxUrl = preserveCurrentDebugParams(ajaxUrl);

			event.preventDefault();
			navigate(region, ajaxUrl, fullUrl, 'replace');
		});
	}

	function updateAuditTargetFields(form) {
		if (!form) {
			return;
		}

		var select = form.querySelector('[data-audit-opquast-target-type]');
		var urlField = form.querySelector('[data-audit-opquast-field="url"]');
		var siteField = form.querySelector('[data-audit-opquast-field="site"]');
		var mode = select && select.value === 'site' ? 'site' : 'url';
		var urlInput = urlField ? urlField.querySelector('input, textarea, select') : null;
		var siteInput = siteField ? siteField.querySelector('input, textarea, select') : null;

		if (urlField) {
			urlField.hidden = mode !== 'url';
			urlField.style.display = mode === 'url' ? '' : 'none';
			urlField.setAttribute('aria-hidden', mode === 'url' ? 'false' : 'true');
		}

		if (siteField) {
			siteField.hidden = mode !== 'site';
			siteField.style.display = mode === 'site' ? '' : 'none';
			siteField.setAttribute('aria-hidden', mode === 'site' ? 'false' : 'true');
		}

		if (urlInput) {
			urlInput.disabled = mode !== 'url';
		}

		if (siteInput) {
			siteInput.disabled = mode !== 'site';
		}
	}

	function initAuditForm(root) {
		if (!root) {
			return;
		}

		root.querySelectorAll('.formulaire_editer_audit_opquast form').forEach(function (form) {
			if (form.dataset.auditOpquastTargetInit === 'oui') {
				updateAuditTargetFields(form);
				return;
			}

			form.dataset.auditOpquastTargetInit = 'oui';

			var select = form.querySelector('[data-audit-opquast-target-type]');

			updateAuditTargetFields(form);

			if (!select) {
				return;
			}

			select.addEventListener('change', function () {
				updateAuditTargetFields(form);
			});
		});
	}

	function initExportRegion(region) {
		if (!region) {
			return;
		}

		if (region.dataset.auditOpquastExportInit === 'oui') {
			return;
		}
		region.dataset.auditOpquastExportInit = 'oui';

		region.addEventListener('click', function (event) {
			var link = event.target.closest('a.audit-opquast-export__toggle');
			var dashboardRegion = document.querySelector('[data-audit-opquast-dashboard-region]');

			if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
				return;
			}

			var fullUrl = preserveCurrentDebugParams(link.href);
			var ajaxUrl = preserveCurrentDebugParams(
				buildAjaxUrl(fullUrl, (dashboardRegion && dashboardRegion.dataset.dashboardPage) || 'audit_opquast_tableau_bord')
			);

			event.preventDefault();
			navigate(dashboardRegion || region, ajaxUrl, fullUrl, 'replace');
		});
	}

	function initRestitutionRegion(region) {
		if (!region) {
			return;
		}

		if (region.dataset.auditOpquastRestitutionInit === 'oui') {
			return;
		}
		region.dataset.auditOpquastRestitutionInit = 'oui';

		region.addEventListener('click', function (event) {
			var link = event.target.closest('a.audit-opquast-restitution__toggle');
			var dashboardRegion = document.querySelector('[data-audit-opquast-dashboard-region]');

			if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
				return;
			}

			var fullUrl = preserveCurrentDebugParams(link.href);
			var ajaxUrl = preserveCurrentDebugParams(
				buildAjaxUrl(fullUrl, (dashboardRegion && dashboardRegion.dataset.dashboardPage) || 'audit_opquast_tableau_bord')
			);

			event.preventDefault();
			navigate(dashboardRegion || region, ajaxUrl, fullUrl, 'replace');
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
			var closeLink = event.target.closest('.audit-opquast-rule-nav__close');
			var shortcut = event.target.closest('.audit-opquast-shortcut');

			if (closeLink && !event.defaultPrevented && !event.metaKey && !event.ctrlKey && !event.shiftKey && event.button === 0) {
				var closeAjaxUrl = closeLink.getAttribute('data-ajax-href') || buildAjaxUrl(closeLink.href, region.dataset.resultsPage || 'audit_opquast_resultats');
				var closeFullUrl = preserveCurrentDebugParams(closeLink.href);

				closeAjaxUrl = preserveCurrentDebugParams(closeAjaxUrl);

				event.preventDefault();
				navigate(region, closeAjaxUrl, closeFullUrl, 'push').then(function () {
					scrollToRegion(region);
				});
				return;
			}

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

	function initBackToTop() {
		var link = document.querySelector('[data-audit-opquast-back-to-top]');
		var threshold = 280;

		if (!link || link.dataset.auditOpquastBackToTopInit === 'oui') {
			return;
		}
		link.dataset.auditOpquastBackToTopInit = 'oui';

		function toggleVisibility() {
			var scrollTop = window.pageYOffset || document.documentElement.scrollTop || 0;
			link.classList.toggle('is-visible', scrollTop > threshold);
		}

		window.addEventListener('scroll', toggleVisibility, { passive: true });

		link.addEventListener('click', function (event) {
			event.preventDefault();

			try {
				window.scrollTo({ top: 0, behavior: 'smooth' });
			} catch (error) {
				window.scrollTo(0, 0);
			}
		});

		toggleVisibility();
	}

	function initDynamicRegions(root) {
		if (!root) {
			return;
		}

		initNavigation(root.querySelector('[data-audit-opquast-navigation-region]'));
		initNavigation(root.querySelector('[data-audit-opquast-results-region] [data-audit-opquast-navigation-region]'));
		initParamsRegion(root.querySelector('[data-audit-opquast-params-region]'));
		initCreationRegion(root.querySelector('[data-audit-opquast-creation-region]'));
		initExportRegion(root.querySelector('[data-audit-opquast-export-region]'));
		initRestitutionRegion(root.querySelector('[data-audit-opquast-restitution-region]'));
		initResultsRegion(root.querySelector('[data-audit-opquast-results-region]'));
		initAuditForm(root);
	}

	document.addEventListener('DOMContentLoaded', function () {
		initDynamicRegions(document);
		initRuleList();
		initFamilyLinks();
		initBackToTop();
	});

	document.addEventListener('change', function (event) {
		var select = event.target.closest('[data-audit-opquast-target-type]');

		if (!select) {
			return;
		}

		updateAuditTargetFields(select.form || select.closest('form'));
	});
}());
