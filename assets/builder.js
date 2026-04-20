/**
 * Bricks Vergleich — Builder-UI-Patch
 *
 * Bricks' Repeater-Control kopiert beim "+ Zeile hinzufügen" den ersten Eintrag
 * aus `default` als Template. Das ist für uns eine Bild-Zeile — wir wollen Text.
 *
 * Ansatz: MutationObserver auf Bricks Builder-UI. Wenn sich die Repeater-Items
 * des "Zeilen"-Repeaters vermehren, patchen wir das letzte Item über den Vuex-
 * Store (oder per DOM-Interaktion als Fallback).
 */
( function () {
    'use strict';

    if ( window.__vergleichBuilderPatched ) return;
    window.__vergleichBuilderPatched = true;

    var DEBUG = false;
    var lastRowsJson = '';

    function log() {
        if ( DEBUG && window.console ) console.log.apply( console, [ '[VGL]' ].concat( [].slice.call( arguments ) ) );
    }

    /**
     * Bricks legt seinen Vuex-Store unter verschiedenen Pfaden ab, je nach Version.
     * Wir probieren mehrere durch.
     */
    function getStore() {
        try { if ( window.$$bricksData && window.$$bricksData.$store ) return window.$$bricksData.$store; } catch ( e ) {}
        try { if ( window.bricks && window.bricks.$store )            return window.bricks.$store; } catch ( e ) {}
        try { if ( window.app && window.app.$store )                  return window.app.$store; } catch ( e ) {}
        return null;
    }

    function getActiveElement() {
        var s = getStore();
        if ( s && s.getters ) {
            try {
                var ae = s.getters.activeElement || s.getters[ 'element/active' ];
                if ( ae ) return ae;
            } catch ( e ) {}
            try {
                if ( s.state && s.state.activeElement ) return s.state.activeElement;
                if ( s.state && s.state.element && s.state.element.active ) return s.state.element.active;
            } catch ( e ) {}
        }
        // Fallback: Bricks legt ActiveElement teils global ab
        if ( window.bricksData && window.bricksData.activeElement ) return window.bricksData.activeElement;
        return null;
    }

    function patchNewRow( row ) {
        if ( ! row || typeof row !== 'object' ) return;
        row.type = 'text';
        row.label = 'Zeile';
        row.text = '';
        row.textTag = 'p';
        delete row.image;
        delete row.imageLink;
        delete row.icon;
        delete row.button;
        delete row.rating;
        delete row.html;
        delete row.dynamic;
        log( 'Neue Zeile auf Text gepatcht', row );
    }

    function checkRepeaterGrowth() {
        var active = getActiveElement();
        if ( ! active || active.name !== 'vergleich' ) {
            lastRowsJson = '';
            return;
        }
        var rows = ( active.settings && active.settings.rows ) || [];
        if ( ! Array.isArray( rows ) ) {
            lastRowsJson = '';
            return;
        }
        var currentLen;
        try { currentLen = rows.length; } catch ( e ) { return; }

        // Wir merken uns einen JSON-Snapshot der row-Labels/types — nicht der rows
        // selbst (wegen Vue-Reactivity-Proxies). Bei Wachstum patchen wir das neue.
        var snapshotKey = currentLen + ':' + ( rows[ 0 ] && rows[ 0 ].type ) + ':' + ( rows[ currentLen - 1 ] && rows[ currentLen - 1 ].type );
        if ( snapshotKey === lastRowsJson ) return;

        // Wachstum erkannt: letztes Item ist neu
        if ( lastRowsJson !== '' ) {
            var prevLen = parseInt( lastRowsJson.split( ':' )[ 0 ], 10 );
            if ( currentLen === prevLen + 1 ) {
                patchNewRow( rows[ currentLen - 1 ] );
            }
        }
        lastRowsJson = snapshotKey;
    }

    // ─── Manuell-Badge im Repeater ────────────────────────────────────
    // Nur für Zeilen mit type === 'manual' ein kleines Badge direkt hinter
    // dem Label-Text. Alles andere bleibt unmarkiert.
    /**
     * Lies den Zellentyp eines Repeater-Items. Primär Vue-State (funktioniert
     * auch bei eingeklappten Items), dann DOM-Fallback.
     */
    function getRowType( item ) {
        var data = getRowDataFromVue( item );
        if ( data && typeof data.type === 'string' && data.type ) return data.type;

        var sel = item.querySelector( '[data-control-key="type"] select' );
        if ( sel && sel.value ) return String( sel.value );
        var typeMarkers = {
            'manualColumns': 'manual',
            'image':         'image',
            'icon':          'icon',
            'btnText':       'button',
            'ratingValue':   'rating',
            'boolValue':     'bool',
            'html':          'html',
            'dynamic':       'dynamic'
        };
        for ( var key in typeMarkers ) {
            var el = item.querySelector( '[data-control-key="' + key + '"]' );
            if ( el && el.style.display !== 'none' ) return typeMarkers[ key ];
        }
        return 'text';
    }

    /**
     * Holt das Row-Item-Objekt aus dem Vue-State. Wir scannen alle erreichbaren
     * Container (props, ctx, setupState, data) mehrere Levels tief nach einem
     * Object, das unsere Row-Struktur erkennen lässt (hat entweder 'type' oder
     * 'collapsible' als eigenes Feld).
     */
    function isRowLike( obj ) {
        if ( ! obj || typeof obj !== 'object' || Array.isArray( obj ) ) return false;
        return ( 'type' in obj ) || ( 'collapsible' in obj ) || ( 'label' in obj && 'highlight' in obj );
    }

    function getRowDataFromVue( li ) {
        // Vue 3 – primärer Pfad
        var comp = li.__vueParentComponent;
        for ( var depth = 0; depth < 8 && comp; depth++ ) {
            var containers = [ comp.props, comp.ctx, comp.setupState, comp.data, comp.attrs ];
            for ( var c = 0; c < containers.length; c++ ) {
                var box = containers[ c ];
                if ( ! box ) continue;
                // Direkt: ein Feld, das wie eine Row aussieht
                for ( var k in box ) {
                    var v = box[ k ];
                    if ( isRowLike( v ) ) return v;
                    // Verschachtelt: eine Ebene tiefer
                    if ( v && typeof v === 'object' && ! Array.isArray( v ) ) {
                        for ( var kk in v ) {
                            if ( isRowLike( v[ kk ] ) ) return v[ kk ];
                        }
                    }
                }
            }
            comp = comp.parent;
        }
        // Vue 2 Fallback
        if ( li.__vue__ ) {
            var vm = li.__vue__;
            var probes = [ vm.item, vm.value, vm.row, vm.$attrs && vm.$attrs.item, vm._data && vm._data.item ];
            for ( var p = 0; p < probes.length; p++ ) {
                if ( isRowLike( probes[ p ] ) ) return probes[ p ];
            }
        }
        return null;
    }

    /**
     * Exakt gleiche Strategie wie bei Manuell-Badge: Existenz eines conditional
     * Sub-Felds im DOM prüfen. Das `_markerCollapsible`-Feld in vergleich.php
     * hat `required: [collapsible, =, true]` — Bricks rendert es nur dann mit
     * sichtbarem Style (style.display !== 'none'). Bleibt im DOM auch wenn das
     * Item eingeklappt ist (Bricks behält Sub-Feld-Wrappers mit inline-display-
     * Toggle bei — genau wie bei manualColumns).
     */
    function isRowCollapsible( item ) {
        var marker = item.querySelector( '[data-control-key="_markerCollapsible"]' );
        return !! ( marker && marker.style.display !== 'none' );
    }

    var BADGE_STYLES = {
        manual: {
            text: 'Manuell',
            color: '#f0abfc',       // subtiler Lila-Ton
            bg:    'rgba(168,85,247,.14)',
            border:'rgba(168,85,247,.3)'
        },
        collapsible: {
            text: 'Versteckt',
            color: '#93c5fd',       // subtiler Blau-Ton
            bg:    'rgba(59,130,246,.14)',
            border:'rgba(59,130,246,.3)'
        }
    };

    function ensureBadge( title, kind ) {
        var cls = 'vergleich-badge--' + kind;
        if ( title.querySelector( '.' + cls ) ) return;
        var s = BADGE_STYLES[ kind ];
        var badge = document.createElement( 'span' );
        badge.className = 'vergleich-badge ' + cls;
        badge.textContent = s.text;
        badge.style.cssText =
            'display:inline-block;' +
            'margin-left:6px;' +
            'padding:1px 7px;' +
            'border-radius:4px;' +
            'font-size:10px;' +
            'font-weight:500;' +
            'line-height:1.6;' +
            'vertical-align:middle;' +
            'pointer-events:none;' +
            'color:' + s.color + ';' +
            'background:' + s.bg + ';' +
            'border:1px solid ' + s.border + ';';
        title.appendChild( badge );
    }

    function removeBadge( item, kind ) {
        var el = item.querySelector( '.vergleich-badge--' + kind );
        if ( el ) el.remove();
    }

    function annotateRows() {
        var scope = document.querySelector( '[data-controlkey="rows"]' );
        if ( ! scope ) return;
        var items = scope.querySelectorAll( 'li.repeater-item' );
        items.forEach( function ( item ) {
            var title = item.querySelector( '.sortable-title' );
            if ( ! title ) return;
            title.style.overflow = 'visible';

            if ( getRowType( item ) === 'manual' ) ensureBadge( title, 'manual' );
            else                                   removeBadge( item, 'manual' );

            if ( isRowCollapsible( item ) ) ensureBadge( title, 'collapsible' );
            else                            removeBadge( item, 'collapsible' );
        } );
    }

    function boot() {
        var rafId = 0;
        var obs = new MutationObserver( function () {
            if ( rafId ) return;
            rafId = requestAnimationFrame( function () {
                rafId = 0;
                try {
                    checkRepeaterGrowth();
                    annotateRows();
                } catch ( e ) { log( 'check-Fehler', e ); }
            } );
        } );
        obs.observe( document.body, { childList: true, subtree: true } );
        checkRepeaterGrowth();
        annotateRows();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', boot );
    } else {
        boot();
    }
} )();
