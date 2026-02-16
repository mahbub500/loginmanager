/* LoginManager – Login Page Script */
/* globals LoginManagerData, jQuery */

( function ( $ ) {
    'use strict';

    var data = window.LoginManagerData || { remaining: 5, locked: false, lockTime: 0 };

    $( document ).ready( function () {
        injectUI();
        if ( data.locked ) {
            startCountdown( data.lockTime );
        }
    } );

    /**
     * Inject UI elements into the login form.
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
            $( '#wp-submit' ).prop( 'disabled', true ).css( 'opacity', '.5' );
        } else if ( data.remaining > 0 ) {
            var pct   = ( data.remaining / 5 ) * 100;
            var color = pct > 60 ? '#6366f1' : pct > 30 ? '#f59e0b' : '#ef4444';
            var prog  = [
                '<div class="lm-attempt-progress">',
                '  <div class="lm-attempt-progress__bar" style="width:' + pct + '%;background:' + color + '"></div>',
                '</div>'
            ].join( '' );
            $( '#loginform' ).append( prog );
        }
    }

    /**
     * Start a visual countdown timer.
     *
     * @param {number} minutes - Minutes remaining.
     */
    function startCountdown( minutes ) {
        var totalSeconds = minutes * 60;

        function tick() {
            if ( totalSeconds <= 0 ) {
                $( '#lm-timer' ).text( '' );
                $( '#wp-submit' ).prop( 'disabled', false ).css( 'opacity', '1' );
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