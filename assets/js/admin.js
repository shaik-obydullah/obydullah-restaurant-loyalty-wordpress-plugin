/**
 * Obydullah Restaurant Loyalty - admin behaviour
 */
( function ( $ ) {
	'use strict';

	var config = window.orlAdmin || {};

	function text( key, fallback ) {
		return ( config.strings && config.strings[ key ] ) || fallback;
	}

	/* ------------------------------------------------------------------ QR */

	function renderQRCodes() {
		$( '[data-qr-text]' ).each( function () {
			var $el = $( this );
			var value = $el.attr( 'data-qr-text' );

			if ( ! value || 'undefined' === typeof window.qrcode ) {
				return;
			}
			if ( $el.data( 'orlQrDone' ) ) {
				return;
			}

			try {
				var qr = window.qrcode( 0, 'M' );
				qr.addData( value );
				qr.make();
				$el.html( qr.createSvgTag( { cellSize: 4, margin: 0, scalable: true } ) );
				$el.data( 'orlQrDone', true );
			} catch ( e ) {
				// Leave the container empty rather than breaking the screen.
			}
		} );
	}

	/* ------------------------------------------------- Member autocomplete */

	function initMemberLookup() {
		var $input = $( '#obydrelo-member-search' );
		if ( ! $input.length ) {
			return;
		}

		var $results = $( '#obydrelo-member-results' );
		var $selected = $( '#obydrelo-member-selected' );
		var $hidden = $( '#obydrelo-member-id' );
		var timer = null;
		var lastItems = [];

		function clearSelection() {
			$hidden.val( '0' );
			$selected.empty().hide();
		}

		function selectMember( item ) {
			$hidden.val( item.id );
			$selected
				.html(
					'<button type="button" class="obydrelo-selected__clear" aria-label="' + text( 'clear', 'Clear' ) + '">&times;</button>' +
					'<strong>' + item.name + '</strong> &middot; ' + item.code +
					'<br><small>' + item.balance + '</small>'
				)
				.show();
			$input.val( '' );
			$results.empty().hide();
		}

		function describe( item ) {
			return item.balance || ( item.points + ' pts / ' + item.stamps + ' stamps / ' + item.visits + ' visits' );
		}

		$input.on( 'input', function () {
			var term = $.trim( $( this ).val() );

			window.clearTimeout( timer );

			if ( term.length < 2 ) {
				$results.empty().hide();
				return;
			}

			timer = window.setTimeout( function () {
				$.post( config.ajaxUrl, {
					action: 'obydrelo_search_members',
					nonce: config.searchNonce,
					term: term
				} ).done( function ( response ) {
					var items = response && response.success && response.data && response.data.items
						? response.data.items
						: [];

					lastItems = items;
					$results.empty();

					if ( ! items.length ) {
						$results.append(
							'<span class="obydrelo-autocomplete__item">' + text( 'noResults', 'No results.' ) + '</span>'
						);
					} else {
						$.each( items, function ( index, item ) {
							$( '<button type="button" class="obydrelo-autocomplete__item"></button>' )
								.html( '<strong>' + item.name + '</strong><small>' + item.code + ' &middot; ' + describe( item ) + '</small>' )
								.attr( 'data-index', index )
								.appendTo( $results );
						} );
					}

					$results.show();
				} );
			}, 250 );
		} );

		$results.on( 'click', '.obydrelo-autocomplete__item', function () {
			var index = parseInt( $( this ).attr( 'data-index' ), 10 );
			if ( isNaN( index ) || ! lastItems[ index ] ) {
				return;
			}
			var item = lastItems[ index ];
			item.balance = describe( item );
			selectMember( item );
		} );

		$selected.on( 'click', '.obydrelo-selected__clear', function () {
			clearSelection();
		} );

		$( document ).on( 'click', function ( e ) {
			if ( ! $input.is( e.target ) && ! $.contains( $results[ 0 ], e.target ) ) {
				$results.hide();
			}
		} );

		// Preselect when arriving from a scanned card or a "record for member" link.
		var preset = $input.attr( 'data-preset-member' );
		if ( ! preset ) {
			return;
		}

		$.post( config.ajaxUrl, {
			action: 'obydrelo_get_member',
			nonce: config.searchNonce,
			member_id: preset
		} ).done( function ( response ) {
			if ( ! response || ! response.success ) {
				return;
			}
			var member = response.data;
			selectMember( {
				id: member.id,
				name: member.name,
				code: member.code,
				balance: member.points + ' pts / ' + member.stamps + ' stamps / ' + member.visits + ' visits'
			} );
		} );
	}

	/* ------------------------------------------------------- Record/redeem */

	function initTypeToggle() {
		var $type = $( '#obydrelo-type' );
		if ( ! $type.length ) {
			return;
		}

		function sync() {
			var isPurchase = 'purchase' === $type.val();
			$( '#obydrelo-amount-row' ).prop( 'hidden', ! isPurchase );
			$( '#obydrelo-manual-rows' ).prop( 'hidden', isPurchase );
		}

		$type.on( 'change', sync );
		sync();
	}

	/* ------------------------------------------------------ Balance recalc */

	function initRecalculate() {
		$( '#obydrelo-recalc' ).on( 'click', function () {
			var $btn = $( this );

			if ( ! window.confirm( text( 'confirm', 'Are you sure?' ) ) ) {
				return;
			}

			$btn.prop( 'disabled', true );

			$.post( config.ajaxUrl, {
				action: 'obydrelo_recalculate',
				nonce: config.nonce,
				member_id: $btn.attr( 'data-member-id' )
			} ).done( function ( response ) {
				if ( response && response.success ) {
					window.location.reload();
					return;
				}
				window.alert( ( response && response.data && response.data.message ) || 'Failed.' );
			} ).always( function () {
				$btn.prop( 'disabled', false );
			} );
		} );
	}

	/* --------------------------------------------------------------- Init */

	$( function () {
		renderQRCodes();
		initMemberLookup();
		initTypeToggle();
		initRecalculate();
	} );
} )( jQuery );
