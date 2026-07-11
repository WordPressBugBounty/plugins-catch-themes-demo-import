(function ($) {
	'use strict';

	$(function () {
		/* CPT switch */
		$('.ctp-switch').on('click', function () {
			var loader = $(this).parent().next();

			loader.show();

			var main_control = $(this);
			var data = {
				action: 'ctp_switch',
				value: this.checked,
				security: $('#ctp_tabs_nonce').val(),
				option_name: main_control.attr('rel'),
			};

			$.post(ajaxurl, data, function (response) {
				response = $.trim(response);

				if ('1' == response) {
					main_control.parent().parent().addClass('active');
					main_control.parent().parent().removeClass('inactive');
				} else if ('0' == response) {
					main_control.parent().parent().addClass('inactive');
					main_control.parent().parent().removeClass('active');
				} else {
					alert(response);
				}
				loader.hide();
			});
		});
		/* CPT switch End */

		// Tabs
		$('.catchp_widget_settings .nav-tab-wrapper a').on(
			'click',
			function (e) {
				e.preventDefault();

				if (!$(this).hasClass('ui-state-active')) {
					$('.nav-tab').removeClass('nav-tab-active');
					$('.wpcatchtab').removeClass('active').fadeOut(0);

					$(this).addClass('nav-tab-active');

					var anchorAttr = $(this).attr('href');

					$(anchorAttr).addClass('active').fadeOut(0).fadeIn(500);
				}
			}
		);
	});

	// jQuery Match Height init for sidebar spots
	$(document).ready(function () {
		$(
			'.catchp-sidebar-spot .sidebar-spot-inner, .col-2 .catchp-lists li, .col-3 .catchp-lists li'
		).matchHeight();
	});

	// The "activate" object is only localized on the plugin's own admin page; guard
	// with typeof so the importer route (admin.php?import=...) doesn't throw a
	// ReferenceError that would break the rest of this script.
	if (typeof activate !== 'undefined' && activate && activate.url) {
		$(function () {
			$('#dialog-confirm').dialog({
				resizable: false,
				height: 'auto',
				width: 400,
				modal: true,
				buttons: [
					{
						text: object.texts.install,
						click: function () {
							$(this).dialog('close');
							window.location.href = activate.url;
						},
					},
					{
						text: object.texts.skip,
						click: function () {
							$(this).dialog('close');
						},
					},
				],
			});
		});
	}

	if (
		typeof object !== 'undefined' &&
		object.url + '&response=activated' === window.location.href
	) {
		$(function () {
			$('#dialog-activated').dialog({
				resizable: false,
				height: 'auto',
				width: 400,
				modal: true,
				buttons: [
					{
						text: object.texts.ok,
						click: function () {
							$(this).dialog('close');
							if (window.history.replaceState) {
								//prevents browser from storing history with each change:
								window.history.replaceState(
									'state',
									'Catch Themes Demo Import',
									object.url
								);
							}
						},
					},
				],
			});
		});
	}
})(jQuery);
