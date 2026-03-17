document.addEventListener( 'DOMContentLoaded', () => {
    document.querySelectorAll( '.sp-newsletter-form' ).forEach( ( el ) => {
        const form = el.querySelector( 'form' );
        const msg = el.querySelector( '.sp-newsletter-form__message' );
        const restUrl = el.dataset.restUrl;
        const nonce = el.dataset.nonce;

        form.addEventListener( 'submit', async ( e ) => {
            e.preventDefault();
            const email = form.querySelector( 'input[name="email"]' ).value;

            try {
                const res = await fetch( restUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce,
                    },
                    body: JSON.stringify( { email } ),
                } );
                const data = await res.json();
                msg.textContent = data.message;
                msg.style.display = 'block';
                if ( res.ok ) {
                    form.style.display = 'none';
                }
            } catch {
                msg.textContent = 'Error de conexión.';
                msg.style.display = 'block';
            }
        } );
    } );
} );
