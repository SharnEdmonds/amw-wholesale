/**
 * Wholesale catalog — client-side quote builder.
 * Vanilla JS only. Talks to POST /amw/v1/quotes.
 *
 * The table is the source of truth for unit prices; JS only multiplies
 * by qty for display. Server recomputes on submit.
 */
(function () {
	'use strict';

	const table = document.querySelector( '.amw-wholesale-catalog-table' );
	if ( !table ) { return; }

	const restUrl = table.dataset.restUrl;
	const wpNonce = table.dataset.wpNonce;

	const lines = new Map(); // product_id -> { name, qty, unit_price }

	function formatMoney( n ) {
		return ( Math.round( n * 100 ) / 100 ).toFixed( 2 );
	}

	function redrawLines() {
		const ul = document.querySelector( '.amw-quote-lines' );
		const subtotalEl = document.querySelector( '.amw-subtotal' );
		if ( !ul || !subtotalEl ) { return; }

		ul.innerHTML = '';
		let subtotal = 0;
		lines.forEach( ( line, productId ) => {
			const lineTotal = line.qty * line.unit_price;
			subtotal += lineTotal;
			const li = document.createElement( 'li' );
			li.textContent = `${line.name} × ${line.qty} = ${formatMoney( lineTotal )}`;
			const remove = document.createElement( 'button' );
			remove.type = 'button';
			remove.textContent = '×';
			remove.setAttribute( 'aria-label', 'Remove' );
			remove.addEventListener( 'click', () => {
				lines.delete( productId );
				redrawLines();
			} );
			li.appendChild( remove );
			ul.appendChild( li );
		} );
		subtotalEl.textContent = formatMoney( subtotal );
	}

	table.querySelectorAll( 'tbody tr' ).forEach( ( row ) => {
		const addBtn = row.querySelector( '.amw-add-btn' );
		const qtyInput = row.querySelector( '.amw-qty-input' );
		const lineTotalEl = row.querySelector( '.amw-line-total' );
		const unitPrice = parseFloat( row.dataset.unitPrice );
		const productId = parseInt( row.dataset.productId, 10 );
		const name = row.children[ 1 ].textContent.trim();

		qtyInput.addEventListener( 'input', () => {
			const qty = parseInt( qtyInput.value, 10 ) || 0;
			lineTotalEl.textContent = qty > 0 ? formatMoney( qty * unitPrice ) : '—';
		} );

		addBtn.addEventListener( 'click', () => {
			const qty = parseInt( qtyInput.value, 10 ) || 0;
			if ( qty <= 0 ) { return; }
			lines.set( productId, { name, qty, unit_price: unitPrice } );
			redrawLines();
		} );
	} );

	const submitBtn = document.querySelector( '.amw-submit-btn' );
	const resultEl = document.querySelector( '.amw-submit-result' );
	submitBtn.addEventListener( 'click', async () => {
		if ( lines.size === 0 ) {
			resultEl.textContent = 'Add at least one item before submitting.';
			return;
		}
		const body = {
			items: Array.from( lines.entries() ).map( ( [ productId, line ] ) => ( {
				product_id: productId,
				quantity: line.qty,
			} ) ),
			customer_notes: document.getElementById( 'amw-customer-notes' ).value || '',
		};

		submitBtn.disabled = true;
		resultEl.textContent = 'Submitting…';
		try {
			const res = await fetch( restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': wpNonce,
				},
				body: JSON.stringify( body ),
			} );
			const payload = await res.json();
			if ( !res.ok ) {
				throw new Error( payload.message || ( 'HTTP ' + res.status ) );
			}
			resultEl.textContent = `Quote #${payload.id} submitted. You'll hear from us shortly.`;
			lines.clear();
			redrawLines();
		} catch ( err ) {
			resultEl.textContent = 'Error: ' + err.message;
		} finally {
			submitBtn.disabled = false;
		}
	} );
})();
