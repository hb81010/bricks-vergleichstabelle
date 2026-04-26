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

    /**
     * DOM-basierter Patch fuer eine neu hinzugefuegte Zeile.
     *
     * Bricks setzt nach dem "+ Zeile hinzufuegen" oft seinen eigenen
     * default[0] (= image) auf den Vue-State, NACHDEM unser
     * patchNewRow gelaufen ist. Reine Vue-State-Mutation reicht dann
     * nicht — wir muessen das `<select>`-Field im DOM direkt aendern
     * und ein change-Event dispatchen, damit Bricks seinen eigenen
     * Update-Cycle anstoesst und Vue + DOM wieder synchron sind.
     */
    function patchNewRowDom( rowIdx ){
        var scope = document.querySelector( '[data-controlkey="rows"]' );
        if ( ! scope ) return false;
        var items = scope.querySelectorAll( 'li.repeater-item' );
        var item  = items[ rowIdx ];
        if ( ! item ) return false;
        var typeSelect = item.querySelector( '[data-control-key="type"] select' );
        if ( ! typeSelect ) return false;
        if ( typeSelect.value === 'text' ) return true;
        typeSelect.value = 'text';
        typeSelect.dispatchEvent( new Event( 'change', { bubbles: true } ) );
        typeSelect.dispatchEvent( new Event( 'input',  { bubbles: true } ) );
        log( 'Neue Zeile via DOM-Select auf Text gepatcht', rowIdx );
        return true;
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
                // Sofort patchen + Retry-Ticks: Bricks setzt nach dem
                // Hinzufuegen ggf. seine eigenen Default-Werte (basierend
                // auf default[0] = image-row) NACH unserem ersten Patch.
                // Mit setTimeout-Retries ueberschreiben wir das, sobald
                // Bricks "settled" ist. Wir checken pro Retry, ob unser
                // Patch noch greift — wenn schon text, kein Re-Patch noetig.
                var newRowIdx = currentLen - 1;
                function tryPatch(){
                    // 1) Vue-State patchen (greift wenn Bricks reaktiv ist).
                    var freshActive = getActiveElement();
                    if ( freshActive && freshActive.name === 'vergleich' ) {
                        var freshRows = ( freshActive.settings && freshActive.settings.rows ) || [];
                        var freshRow  = freshRows[ newRowIdx ];
                        if ( freshRow && freshRow.type !== 'text' ) {
                            patchNewRow( freshRow );
                        }
                    }
                    // 2) DOM-Select patchen (Fallback fuer Bricks, das den
                    //    Vue-State nach unserem Patch wieder ueberschreibt).
                    patchNewRowDom( newRowIdx );
                }
                tryPatch();
                setTimeout( tryPatch, 0 );
                setTimeout( tryPatch, 50 );
                setTimeout( tryPatch, 200 );
            }
        }
        lastRowsJson = snapshotKey;
    }

    /**
     * Auto-Enable für `hideIfAllEmpty` bei Coupon-Zeilen.
     *
     * Sobald eine Zeile den Type `coupon` hat UND `hideIfAllEmpty` noch NIE
     * explizit gesetzt wurde (also `undefined`), setzen wir es auf `true`.
     * Das spiegelt das PHP-Render-Verhalten (Auto-Default in
     * compute_hidden_rows): Coupon-Zellen verbergen sich automatisch, wenn
     * kein Produkt einen Code liefert. Im Builder wird die Checkbox damit
     * gleich sichtbar als aktiv angezeigt — kein „warum greift das nicht?"-
     * Moment für den User.
     *
     * Wichtig: Wir setzen nur, wenn der Wert `undefined` ist. Sobald der
     * User den Toggle einmal anfasst (auch zum Ausschalten → `false`),
     * respektieren wir das und überschreiben nicht.
     */
    function autoEnableHideForCoupon() {
        var active = getActiveElement();
        if ( ! active || active.name !== 'vergleich' ) return;
        var rows = ( active.settings && active.settings.rows ) || [];
        if ( ! Array.isArray( rows ) ) return;
        for ( var i = 0; i < rows.length; i++ ) {
            var r = rows[ i ];
            if ( ! r || typeof r !== 'object' ) continue;
            if ( r.type === 'coupon' && r.hideIfAllEmpty === undefined ) {
                r.hideIfAllEmpty = true;
                log( 'Auto-Enable hideIfAllEmpty für coupon-Zeile', i );
            }
        }
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
    function hasMarker( item, markerKey ) {
        var marker = item.querySelector( '[data-control-key="' + markerKey + '"]' );
        return !! ( marker && marker.style.display !== 'none' );
    }
    function isRowCollapsible( item ) { return hasMarker( item, '_markerCollapsible' ); }
    function isRowStickyTop( item )    { return hasMarker( item, '_markerStickyTop' ); }
    function isRowStickyBottom( item ) { return hasMarker( item, '_markerStickyBottom' ); }
    function isRowSchema( item )       { return hasMarker( item, '_markerSchema' ); }

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
        },
        stickyTop: {
            text: 'ST',
            color: '#86efac',       // subtiler Gruen-Ton
            bg:    'rgba(34,197,94,.14)',
            border:'rgba(34,197,94,.3)'
        },
        stickyBottom: {
            text: 'SB',
            color: '#fdba74',       // subtiler Orange-Ton
            bg:    'rgba(249,115,22,.14)',
            border:'rgba(249,115,22,.3)'
        },
        schema: {
            text: 'SEO',
            color: '#fde047',       // subtiler Gold-/Gelb-Ton
            bg:    'rgba(234,179,8,.14)',
            border:'rgba(234,179,8,.3)'
        }
    };

    function ensureBadge( title, kind, opts ) {
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

        // Optional: Badge LINKS vom letzten Nicht-Badge-Child einfuegen
        // (z.B. links vom Aufklapp-Pfeil bei Group-Headers). Bei Repeater-
        // Items (kein Pfeil im Title) wird append wie gewohnt verwendet.
        if ( opts && opts.beforeLast ) {
            var anchor = null;
            for ( var i = title.children.length - 1; i >= 0; i-- ) {
                if ( ! title.children[ i ].classList.contains( 'vergleich-badge' ) ) {
                    anchor = title.children[ i ];
                    break;
                }
            }
            if ( anchor ) {
                title.insertBefore( badge, anchor );
                return;
            }
        }
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

            if ( isRowStickyTop( item ) ) ensureBadge( title, 'stickyTop' );
            else                          removeBadge( item, 'stickyTop' );

            if ( isRowStickyBottom( item ) ) ensureBadge( title, 'stickyBottom' );
            else                             removeBadge( item, 'stickyBottom' );

            if ( isRowSchema( item ) ) ensureBadge( title, 'schema' );
            else                       removeBadge( item, 'schema' );
        } );
    }

    // ─── Group-Header-Badges (Element-Settings-Sidebar, nicht Repeater) ───
    // Beispiel: "Zugänglichkeit & SEO" Group-Header bekommt ein "JSON-LD"-
    // Badge wenn das schemaEnabled-Toggle in der Group aktiv ist. Hilft
    // dem User, ohne die Group aufzuklappen zu sehen ob Schema an ist.
    //
    // DOM-Strategie: Bricks rendert Groups mit Wrapper [data-control-group="<key>"]
    // und klickbarem Header .control-group-title. Den Toggle-Status lesen wir
    // direkt aus dem checkbox-Input innerhalb des [data-controlkey="<toggleKey>"]-
    // Containers. Marker-Strategie wie bei Repeater-Items (info-Control mit
    // required-Bedingung) funktioniert hier NICHT — Bricks rendert info-Controls
    // mit leerem content immer, unabhaengig vom required-Result.
    var GROUP_BADGE_CONFIGS = [
        { toggleKey: 'schemaEnabled', groupKey: 'a11y', kind: 'jsonld' }
    ];

    // Cache: zuletzt bekannter Toggle-Wert pro controlKey. Wird nur
    // genutzt wenn weder Vue-State noch DOM erreichbar sind (extremer
    // Edge-Case: Element gerade neu selektiert + Group eingeklappt).
    var toggleStateCache = {};

    function isControlToggleActive( controlKey ) {
        // Strategy 1: Vue-State des Element-Settings-Objekts. Funktioniert
        // AUCH wenn die Group eingeklappt ist — Bricks rendert Group-Children
        // per v-if, der State des kompletten Element-Settings-Objekts liegt
        // aber im Vue-Tree des umschliessenden Settings-Panels. Wir crawlen
        // vom irgendwo greifbaren Group-Container nach oben durch props/ctx/
        // setupState bis wir ein settings-Object finden, das unseren Key
        // enthaelt.
        var anyGroup = document.querySelector( '[data-control-group]' );
        if ( anyGroup && anyGroup.__vueParentComponent ) {
            var comp = anyGroup.__vueParentComponent;
            for ( var d = 0; d < 12 && comp; d++ ) {
                var probes = [ comp.props, comp.ctx, comp.setupState, comp.data, comp.attrs ];
                for ( var p = 0; p < probes.length; p++ ) {
                    var box = probes[ p ];
                    if ( ! box ) continue;
                    if ( box.settings && typeof box.settings[ controlKey ] !== 'undefined' ) {
                        var v1 = !! box.settings[ controlKey ];
                        toggleStateCache[ controlKey ] = v1;
                        return v1;
                    }
                    if ( box.element && box.element.settings && typeof box.element.settings[ controlKey ] !== 'undefined' ) {
                        var v2 = !! box.element.settings[ controlKey ];
                        toggleStateCache[ controlKey ] = v2;
                        return v2;
                    }
                }
                comp = comp.parent;
            }
        }
        // Strategy 2: DOM-Lookup auf das Toggle-Control selbst (greift nur
        // wenn die Group aufgeklappt ist). Bricks-Inkonsistenz: Sidebar
        // nutzt data-controlkey, Repeater data-control-key — beides probieren.
        var ctrl = document.querySelector( '[data-controlkey="' + controlKey + '"]' )
                || document.querySelector( '[data-control-key="' + controlKey + '"]' );
        if ( ctrl ) {
            var input = ctrl.querySelector( 'input[type="checkbox"]' );
            if ( input ) {
                toggleStateCache[ controlKey ] = !! input.checked;
                return toggleStateCache[ controlKey ];
            }
            var compCtrl = ctrl.__vueParentComponent;
            for ( var d2 = 0; d2 < 5 && compCtrl; d2++ ) {
                var probesC = [ compCtrl.props, compCtrl.ctx, compCtrl.setupState ];
                for ( var pc = 0; pc < probesC.length; pc++ ) {
                    var boxC = probesC[ pc ];
                    if ( ! boxC ) continue;
                    if ( typeof boxC.modelValue === 'boolean' ) { toggleStateCache[ controlKey ] = boxC.modelValue; return boxC.modelValue; }
                    if ( typeof boxC.checked === 'boolean' )    { toggleStateCache[ controlKey ] = boxC.checked;    return boxC.checked; }
                    if ( typeof boxC.value === 'boolean' )      { toggleStateCache[ controlKey ] = boxC.value;      return boxC.value; }
                }
                compCtrl = compCtrl.parent;
            }
        }
        // Strategy 3: Letzter bekannter Wert. Korrekt solange das Element
        // nicht gewechselt wurde — sonst ggf. Stale-Read fuer einen Frame.
        if ( typeof toggleStateCache[ controlKey ] !== 'undefined' ) {
            return toggleStateCache[ controlKey ];
        }
        return false;
    }

    BADGE_STYLES.jsonld = {
        text: 'JSON-LD',
        color: '#fde047',       // Gold/Gelb (gleiche Farbe wie Repeater-SEO-Badge)
        bg:    'rgba(234,179,8,.14)',
        border:'rgba(234,179,8,.3)'
    };

    function annotateGroups() {
        for ( var i = 0; i < GROUP_BADGE_CONFIGS.length; i++ ) {
            var cfg = GROUP_BADGE_CONFIGS[ i ];
            var groupContainer = document.querySelector( '[data-control-group="' + cfg.groupKey + '"]' );
            if ( ! groupContainer ) continue;
            var title = groupContainer.querySelector( '.control-group-title' );
            if ( ! title ) continue;
            title.style.overflow = 'visible';

            var active = isControlToggleActive( cfg.toggleKey );
            if ( active ) ensureBadge( title, cfg.kind, { beforeLast: true } );
            else          removeBadge( groupContainer, cfg.kind );
        }
    }

    // Auto-Probe: Beim ersten Mount jeder Group, deren Children noch nicht
    // im DOM sind (= eingeklappt), einmal kurz aufklappen (mit visibility:
    // hidden, damit der User keinen Flicker sieht), den Toggle-Status lesen,
    // wieder einklappen. Damit ist der Cache gefuellt und das Badge erscheint
    // sofort — auch beim allerersten Page-Load, ohne dass der User die
    // Group aktiv aufklappen muss.
    //
    // Hintergrund: Bricks rendert Group-Children per v-if (komplett aus DOM
    // entfernt wenn eingeklappt). Vue-State des schemaEnabled-Toggle ist im
    // Pinia/Vue-Store eines schwer erreichbaren Komponenten-Pfads — ohne den
    // exakten Pfad zu kennen, ist DOM-Lookup die einzige robuste Option.
    var probedGroups = {};

    function probeGroupOnce( groupKey ) {
        if ( probedGroups[ groupKey ] ) return;
        var group = document.querySelector( '[data-control-group="' + groupKey + '"]' );
        if ( ! group ) return;
        var title = group.querySelector( '.control-group-title' );
        if ( ! title ) return;
        // Schon offen? (= mind. ein Child-Control im DOM)
        if ( group.querySelector( '[data-controlkey], [data-control-key]' ) ) {
            probedGroups[ groupKey ] = true;
            return;
        }
        probedGroups[ groupKey ] = true;
        // Group-Inhalt unsichtbar machen, damit der User waehrend des
        // Probings keinen Layout-Sprung sieht. Visibility statt display,
        // damit das Layout-Resultat (Render der Children) trotzdem auftritt.
        var prevVis = group.style.visibility;
        group.style.visibility = 'hidden';
        // Erster Klick: Group aufklappen → Bricks rendert Children
        title.click();
        // Zwei rAF abwarten (Bricks-Vue-Reactivity braucht ggf. 1-2 Frames),
        // dann State lesen + wieder schliessen.
        requestAnimationFrame( function () {
            requestAnimationFrame( function () {
                annotateGroups(); // jetzt sind die Children im DOM, Cache wird gefuellt
                title.click();    // wieder einklappen
                group.style.visibility = prevVis || '';
                // Nochmal annotate, damit das Badge auch nach dem Schliessen sichtbar bleibt
                requestAnimationFrame( annotateGroups );
            } );
        } );
    }

    function probeAllGroups() {
        for ( var i = 0; i < GROUP_BADGE_CONFIGS.length; i++ ) {
            probeGroupOnce( GROUP_BADGE_CONFIGS[ i ].groupKey );
        }
    }

    function boot() {
        var rafId = 0;
        var lastSidebarSignature = '';
        var obs = new MutationObserver( function () {
            if ( rafId ) return;
            rafId = requestAnimationFrame( function () {
                rafId = 0;
                try {
                    checkRepeaterGrowth();
                    autoEnableHideForCoupon();
                    annotateRows();
                    annotateGroups();
                    // Element-Wechsel-Erkennung: wenn sich der Set der
                    // sichtbaren Group-Container aendert (= anderes Element
                    // selektiert oder Sidebar erstmals befuellt), Cache
                    // zuruecksetzen + probe neu starten. Sonst wuerde das
                    // Badge eines vorigen Elements hartnaeckig haengen.
                    var sig = '';
                    document.querySelectorAll( '[data-control-group]' ).forEach( function ( g ) {
                        sig += g.getAttribute( 'data-control-group' ) + '|';
                    } );
                    if ( sig && sig !== lastSidebarSignature ) {
                        lastSidebarSignature = sig;
                        probedGroups = {};
                        toggleStateCache = {};
                        probeAllGroups();
                    }
                } catch ( e ) { log( 'check-Fehler', e ); }
            } );
        } );
        obs.observe( document.body, { childList: true, subtree: true } );
        checkRepeaterGrowth();
        autoEnableHideForCoupon();
        annotateRows();
        annotateGroups();
        probeAllGroups();
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', boot );
    } else {
        boot();
    }
} )();
