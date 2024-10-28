var atlp = {};

(function($) {
	'use strict';
	
	var data = window.atlp_data ? JSON.parse(atlp_data) : {};
		atlp = $.extend(true, data, atlp );
	//console.log(atlp);

	$(document).ready(function(){
		$('body').on('click', '.atlp-likes', function() {
			var button = $(this);
			var post_id = parseInt( $(this).attr('data-post-id') );

			var single = button.attr( 'data-single' );
			var plural = button.attr( 'data-plural' );

			var count_clone = $('.atlp-count', button).clone();
			//console.log(count_clone);

			if( !button.hasClass('atlp-liked') ) {
				button.addClass('atlp-liking');
				add_like(post_id, function( count ) {
					setTimeout(function() {
						button.removeClass('atlp-liking');
						button.addClass('atlp-liked');

						count_clone.text( shorten_large_number(count) );
						var count_html = $('<div/>').append(count_clone).html();

						//console.log(count_html);

						$('.label', button).html( count === 1 ? single.replace( '%s', count_html ) : plural.replace( '%s', count_html ) );
						button.attr( 'title', comma_format_thousands(count, single, plural) );
					}, 1200);
				});
			} else {
				button.addClass('atlp-unliking');
				remove_like(post_id, function( count ) {
					setTimeout(function() {
						button.removeClass('atlp-unliking');
						button.removeClass('atlp-liked');

						count_clone.text( shorten_large_number(count) );
						var count_html = $('<div/>').append(count_clone).html();

						//console.log(count_clone);

						$('.label', button).html( count === 1 ? single.replace( '%s', count_html ) : plural.replace( '%s', count_html ) );
						button.attr( 'title', comma_format_thousands(count, single, plural) );
					}, 1200);
				});
			}
		});

	});// end document ready




	atlp.remove_like = remove_like;
	atlp.add_like = add_like;

	function remove_like( post_id, callback ) {
		if( post_id === undefined && !atlp.post_id ) return;
		//console.log('removing a like...');

		request({
			post_id: post_id ? post_id : false,
			action: atlp.likes.remove_action,
			nonce: atlp.likes.nonce,
			success: function( response ) {
				//console.log( response );
				if( response && response.message && response.message === 'success' ) {
					if( callback ) {
						callback( response.counter );
					}
				}
			}
		});
	}//remove_like

	function add_like( post_id, callback ) {
		if( post_id === undefined && !atlp.post_id ) return;
		//console.log('adding like...');

		request({
			post_id: post_id ? post_id : false,
			action: atlp.likes.add_action,
			nonce: atlp.likes.nonce,
			success: function( response ) {
				//console.log( response );
				if( response && response.message && response.message === 'success' ) {
					if( callback ) {
						callback( response.counter );
					}
				}
			}// end success
		});
	}//add_like




	function request( settings ) {
		if( !settings.action || !settings.nonce ) {
			if( settings.success && typeof(settings.success) === 'function' ) settings.success( response );
			return false;
		}

		$.ajax({
			type : 'get',
			dataType : 'json',
			url : atlp.ajax_url,
			data : {
				action: settings.action,
				nonce: settings.nonce,
				post_id: settings.post_id !== undefined ? parseInt(settings.post_id) : atlp.post_id
			},
			success: function( response ) {
				console.log(response);
				if( response && response.message && response.message === 'success' ) {
					if( settings.success && typeof(settings.success) === 'function' ) settings.success( response );
				} else {
					return false;
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				//console.log(jqXHR, textStatus, errorThrown);
			}
		});
	}





	/**
	 * Helper functions
	 */
	function shorten_large_number( size ) {
		var mod = 1000;
		var units = ['', 'K', 'M', 'B'];

		for (var i = 0; size > mod; i++) {
			size /= mod;
		}

		var splits = size.toString().split('.');
		if( splits[1] ) {
			splits[1] = splits[1].substring(0, 1);
		}

		return splits.join('.') + units[i];
	}

	function comma_format_thousands( number, single, plural ) {
		var comma = number.toLocaleString('en-US');
		var format = plural;
		if( number === 1 ) {
			format = single;
		}
		var output = format.replace( '%s', comma );
		return output;
	}

})(jQuery);