/* JPX Cookies LGPD — painel */
(function ($) {
	'use strict';

	$(function () {
		$('.jpxcc-color').wpColorPicker();

		// Modelos de mensagem: preenche os campos do banner.
		$('.jpxcc-use-preset').on('click', function () {
			var data = $(this).data('preset') || {};
			$.each(data, function (key, val) {
				$('#jpxcc-texts-' + key).val(val).trigger('change');
			});
			$('.jpxcc-preset').removeClass('is-current');
			$(this).closest('.jpxcc-preset').addClass('is-current');
			$('.jpxcc-preset-applied').prop('hidden', false);
			var $first = $('#jpxcc-texts-cm_title');
			$('html, body').animate({ scrollTop: $first.offset().top - 120 }, 250);
		});

		var $tabs = $('.jpxcc-js-tab');
		var $panels = $('.jpxcc-panel');
		if (!$tabs.length) {
			return;
		}

		function show(slug) {
			if (!$panels.filter('[data-panel="' + slug + '"]').length) {
				slug = 'geral';
			}
			$tabs.removeClass('nav-tab-active').filter('[data-tab="' + slug + '"]').addClass('nav-tab-active');
			$panels.hide().filter('[data-panel="' + slug + '"]').show();

			// Mantém a aba depois de salvar.
			var $ref = $('input[name="_wp_http_referer"]');
			if ($ref.length) {
				$ref.val($ref.val().split('#')[0] + '#' + slug);
			}
		}

		$tabs.on('click', function (e) {
			e.preventDefault();
			var slug = $(this).data('tab');
			history.replaceState(null, '', '#' + slug);
			show(slug);
		});

		$(window).on('hashchange', function () {
			show((location.hash || '#geral').slice(1));
		});

		show((location.hash || '#geral').slice(1));
	});
})(jQuery);
