/* LoginManager – Wizard Script */
/* globals jQuery */

( function ( $ ) {
    'use strict';

    $( document ).ready( function () {
        // Toggle captcha-after field visibility.
        var $captchaToggle = $( '#lm_wz_captcha' );
        var $captchaField  = $( '#lm-captcha-after-field' );

        if ( $captchaToggle.length ) {
            toggleCaptchaField();
            $captchaToggle.on( 'change', toggleCaptchaField );
        }

        function toggleCaptchaField() {
            if ( $captchaToggle.is( ':checked' ) ) {
                $captchaField.show();
            } else {
                $captchaField.hide();
            }
        }

        // Animate number inputs with slider-feel.
        $( '.lm-wizard__card input[type="number"]' ).on( 'input', function () {
            var $this = $( this );
            var val   = parseInt( $this.val(), 10 );
            var min   = parseInt( $this.attr( 'min' ), 10 ) || 1;
            var max   = parseInt( $this.attr( 'max' ), 10 ) || 100;

            if ( val < min ) $this.val( min );
            if ( val > max ) $this.val( max );
        } );
    } );

} )( jQuery );

// Animate circular ring — call after DOM ready
function animateLmRing( el, pct ) {
    var circumference = 251.2;
    var offset = circumference - ( pct / 100 ) * circumference;
    var fill   = el.querySelector( '.lm-ring__fill' );
    if ( ! fill ) return;

    // Add colour class
    fill.classList.remove( 'is-warn', 'is-danger' );
    if ( pct <= 20 )      fill.classList.add( 'is-danger' );
    else if ( pct <= 50 ) fill.classList.add( 'is-warn' );

    // Animate
    setTimeout( function () {
        fill.style.strokeDashoffset = offset;
    }, 100 );
}

document.addEventListener( 'DOMContentLoaded', function () {
    document.querySelectorAll( '[data-lm-ring]' ).forEach( function ( el ) {
        animateLmRing( el, parseFloat( el.dataset.lmRing ) || 0 );
    } );
} );