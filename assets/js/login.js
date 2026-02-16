/* LoginManager – Login Page Script */
/* globals LoginManagerData, jQuery */

( function ( $ ) {
    'use strict';

    var data = window.LoginManagerData || {
        remaining: 5,
        max: 5,
        locked: false,
        lockTime: 0,
        showCaptcha: false
    };

    $( document ).ready( function () {
        injectUI();

        if ( data.locked ) {
            startCountdown( data.lockTime );
            $( '#wp-submit' ).prop( 'disabled', true ).css( 'opacity', '.5' );
        }

        // Shake captcha on wrong answer (WP error contains our key).
        if ( $( '.lm-captcha-wrap' ).length && $( '#login_error' ).length ) {
            $( '.lm-captcha-wrap' ).addClass( 'is-wrong' );
            // Focus the captcha input.
            setTimeout( function () {
                $( '#lm_captcha_answer' ).val( '' ).focus();
            }, 100 );
        }

        // Auto-focus captcha input if visible.
        if ( data.showCaptcha && ! data.locked ) {
            setTimeout( function () {
                $( '#lm_captcha_answer' ).focus();
            }, 200 );
        }

        // Prevent form submit if captcha shown but empty.
        $( '#loginform' ).on( 'submit', function ( e ) {
            if ( ! $( '#lm_captcha_answer' ).length ) return;

            var answer = $.trim( $( '#lm_captcha_answer' ).val() );
            if ( '' === answer ) {
                e.preventDefault();
                $( '.lm-captcha-wrap' ).addClass( 'is-wrong' );
                $( '#lm_captcha_answer' ).focus();
            }
        } );
    } );

    /**
     * Inject attempt progress UI.
     */
    function injectUI() {
        if ( data.locked ) {
            var bar = [
                '<div class="loginmanager-lockout-bar">',
                '  <span class="loginmanager-lockout-bar__icon">&#128274;</span>',
                '  <span class="loginmanager-lockout-bar__title">Too many failed attempts</span>',
                '  <span class="loginmanager-lockout-bar__msg">Please wait before trying again</span>',
                '  <span class="loginmanager-timer" id="lm-timer"></span>',
                '</div>'
            ].join( '' );
            $( '#loginform' ).prepend( bar );
            return;
        }

        if ( data.remaining > 0 && data.remaining < data.max ) {
            $( '#loginform' ).append( renderAttemptBlocks( data.remaining, data.max ) );
        }
    }

    /**
     * Render segmented attempt blocks.
     */
    function renderAttemptBlocks( remaining, max ) {
        var used     = max - remaining;
        var segments = '';

        for ( var i = 0; i < max; i++ ) {
            var cls = 'lm-attempt-blocks__segment';
            if ( i < used ) {
                if ( remaining <= 1 )      cls += ' is-danger';
                else if ( remaining <= 2 ) cls += ' is-warn';
                else                       cls += ' is-used';
            }
            segments += '<div class="' + cls + '"></div>';
        }

        var labelColor = remaining <= 1 ? '#ef4444' : ( remaining <= 2 ? '#f59e0b' : '#6b7280' );
        var labelText  = remaining === 1
            ? '⚠ Last attempt before lockout!'
            : remaining + ' of ' + max + ' attempts remaining';

        return '<div class="lm-attempt-blocks">' + segments + '</div>' +
               '<span class="lm-attempt-blocks__label" style="color:' + labelColor + '">' + labelText + '</span>';
    }

    /**
     * Countdown timer.
     */
    function startCountdown( minutes ) {
        var totalSeconds = minutes * 60;

        function tick() {
            if ( totalSeconds <= 0 ) {
                $( '#lm-timer' ).text( 'Refreshing...' );
                window.location.reload();
                return;
            }
            var m = Math.floor( totalSeconds / 60 );
            var s = totalSeconds % 60;
            $( '#lm-timer' ).text(
                ( m < 10 ? '0' : '' ) + m + ':' + ( s < 10 ? '0' : '' ) + s
            );
            totalSeconds--;
            setTimeout( tick, 1000 );
        }

        tick();
    }

} )( jQuery );

// In login.js — replace the old progress injection

function renderAttemptBlocks( remaining, max ) {
    var used    = max - remaining;
    var segments = '';

    for ( var i = 0; i < max; i++ ) {
        var cls = 'lm-attempt-blocks__segment';
        if ( i < used ) {
            if ( remaining <= 1 )      cls += ' is-danger';
            else if ( remaining <= 2 ) cls += ' is-warn';
            else                       cls += ' is-used';
        }
        segments += '<div class="' + cls + '"></div>';
    }

    var label = remaining === 1
        ? '<span class="lm-attempt-blocks__label" style="color:#ef4444;font-weight:700">⚠ Last attempt!</span>'
        : '<span class="lm-attempt-blocks__label">' + remaining + ' of ' + max + ' attempts remaining</span>';

    return '<div class="lm-attempt-blocks">' + segments + '</div>' + label;
}

