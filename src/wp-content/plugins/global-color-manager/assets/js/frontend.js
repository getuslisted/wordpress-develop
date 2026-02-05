(function ($) {
	'use strict';

	function renderRows() {
		var list = $('#gcm-color-list');
		list.empty();

		Object.keys(gcmFrontend.colors || {}).forEach(function (key) {
			var item = gcmFrontend.colors[key];
			var row = $('<label class="gcm-row" />');
			row.append('<input type="checkbox" class="gcm-select" value="' + key + '"/>');
			row.append('<span class="gcm-swatch" style="background:' + item.hex + '"></span>');
			row.append('<span class="gcm-meta"><strong>' + item.hex + '</strong><em>' + item.rgb + ' · ' + item.count + '</em></span>');
			list.append(row);
		});
	}

	function selectedKeys() {
		var keys = [];
		$('.gcm-select:checked').each(function () {
			keys.push($(this).val());
		});
		return keys;
	}

	$(function () {
		renderRows();
		$('#gcm-replace-color').wpColorPicker();

		$('#gcm-toggle').on('click', function () {
			$('#gcm-sidebar').toggleClass('is-open');
		});

		$('#gcm-apply').on('click', function () {
			var selected = selectedKeys();
			var color = $('#gcm-replace-color').val();
			if (!selected.length || !color) {
				return;
			}
			$.post(gcmFrontend.ajaxUrl, {
				action: 'gcm_save_page_replacements',
				nonce: gcmFrontend.nonce,
				postId: gcmFrontend.postId,
				selected: selected,
				color: color
			}).done(function () {
				window.location.reload();
			});
		});

		$('#gcm-reset').on('click', function () {
			$.post(gcmFrontend.ajaxUrl, {
				action: 'gcm_reset_page_replacements',
				nonce: gcmFrontend.nonce,
				postId: gcmFrontend.postId
			}).done(function () {
				window.location.reload();
			});
		});
	});
})(jQuery);
