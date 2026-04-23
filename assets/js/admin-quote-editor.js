/**
 * Live line-total recompute as admin tweaks per-line prices.
 */
(function () {
	'use strict';

	const table = document.querySelector( '.amw-lines' );
	if ( !table ) { return; }

	function formatNumber( n ) {
		return ( Math.round( n * 100 ) / 100 ).toFixed( 2 );
	}

	function recomputeRow( row ) {
		const qtyCell = row.querySelector( 'td.num:nth-child(3)' );
		const input   = row.querySelector( 'input[type="number"]' );
		const totalCell = row.querySelectorAll( 'td.num' );
		if ( !qtyCell || !input || totalCell.length < 3 ) { return; }
		const qty = parseInt( qtyCell.textContent.trim(), 10 ) || 0;
		const unit = parseFloat( input.value ) || 0;
		totalCell[ totalCell.length - 1 ].textContent = formatNumber( qty * unit );
	}

	table.querySelectorAll( 'tbody tr' ).forEach( ( row ) => {
		const input = row.querySelector( 'input[type="number"]' );
		if ( input ) {
			input.addEventListener( 'input', () => recomputeRow( row ) );
		}
	} );
})();
