/**
 * Bricks Vergleich — Frontend-Runtime
 *
 * Läuft sowohl auf dem echten Frontend als auch in der Bricks-Canvas-iframe.
 * Zuvor war dieses Script inline in der Render-HTML (print_sync_script) — das
 * hat im Canvas nicht funktioniert, weil Bricks re-renders per innerHTML
 * injiziert und innerHTML <script>-Tags nicht ausführt. Als enqueue'tes
 * externes Script läuft es einmal beim iframe-Load; der MutationObserver
 * unten fängt dann alle nachfolgenden Canvas-Re-Renders ab.
 */
(function(){
    function syncRows(wrapper){
        if (!wrapper || !wrapper.isConnected) return;
        var labels = wrapper.querySelectorAll(".vergleich-labels .vergleich-label");
        var cards  = wrapper.querySelectorAll(".vergleich-card");
        if (!labels.length || !cards.length) return;

        labels.forEach(function(el){ el.style.minHeight = ""; });
        var cardCells = [];
        cards.forEach(function(card){
            var cells = [];
            card.querySelectorAll(".vergleich-zelle").forEach(function(el){
                if (el.closest(".vergleich-card") === card) cells.push(el);
            });
            cardCells.push(cells);
            cells.forEach(function(el){ el.style.minHeight = ""; });
        });

        for (var i = 0; i < labels.length; i++) {
            var max = labels[i].offsetHeight;
            for (var c = 0; c < cardCells.length; c++) {
                var cell = cardCells[c][i];
                if (cell) max = Math.max(max, cell.offsetHeight);
            }
            labels[i].style.minHeight = max + "px";
            for (var c2 = 0; c2 < cardCells.length; c2++) {
                var cell2 = cardCells[c2][i];
                if (cell2) cell2.style.minHeight = max + "px";
            }
        }
    }

    function bindExpand(wrapper){
        if (!wrapper || wrapper._vglBound) return;
        wrapper._vglBound = true;
        var root = wrapper.closest(".vergleich-root") || wrapper.parentNode;
        var btn = root ? root.querySelector(".vergleich-expand-btn") : null;
        if (!btn) return;
        btn.addEventListener("click", function(){
            wrapper.classList.toggle("is-collapsed");
            var isCollapsed = wrapper.classList.contains("is-collapsed");
            btn.setAttribute("aria-expanded", isCollapsed ? "false" : "true");
            var txt = btn.querySelector(".vergleich-expand-text");
            if (txt) {
                txt.textContent = isCollapsed
                    ? btn.getAttribute("data-label-expand")
                    : btn.getAttribute("data-label-collapse");
            }
            var iconWrap = btn.querySelector(".vergleich-expand-icon");
            if (iconWrap) iconWrap.style.transform = isCollapsed ? "rotate(0deg)" : "rotate(180deg)";
            requestAnimationFrame(function(){
                syncRows(wrapper);
                updateNav(wrapper);
            });
        });
    }

    function findCounter(wrapper){
        var id = wrapper.getAttribute("data-counter");
        if (id) {
            var byId = document.getElementById(id);
            if (byId) return byId;
        }
        var root = wrapper.closest(".vergleich-root") || wrapper.parentNode;
        return root ? root.querySelector("[data-vgl-counter]") : null;
    }

    function updateNavPosition(wrapper){
        var rootEl = wrapper.closest(".vergleich-root") || wrapper.parentNode;
        if (!rootEl) return;
        var prev = rootEl.querySelector(".vergleich-nav--prev");
        var next = rootEl.querySelector(".vergleich-nav--next");
        if (!prev && !next) return;

        var rect = wrapper.getBoundingClientRect();
        var vh   = window.innerHeight || document.documentElement.clientHeight || 0;
        if (vh <= 0) return;
        if (rect.bottom <= 0 || rect.top >= vh) return;

        var rootRect = rootEl.getBoundingClientRect();
        var cs = window.getComputedStyle(wrapper);

        // --- Y: Anker zweite Zelle (Produkt-Name), sticky-Clamp + User-Nudge ---
        var anchor = wrapper.querySelector(".vergleich-card > .vergleich-zelle:nth-child(2)")
                  || wrapper.querySelector(".vergleich-card > .vergleich-zelle")
                  || wrapper;
        var aRect = anchor.getBoundingClientRect();
        // parseFloat + isNaN-Check, weil (x || default) bei 0 fälschlich auf
        // default fällt — User-Eingabe 0 soll aber exakt 0 bedeuten.
        var nudgeYRaw = parseFloat(cs.getPropertyValue("--vgl-nav-offset-y"));
        var nudgeY = isNaN(nudgeYRaw) ? 0 : nudgeYRaw;
        var defaultScreenY = aRect.top + aRect.height / 2 + nudgeY;

        var stickyScreenY = 40;
        var screenY = Math.max(defaultScreenY, stickyScreenY);

        var maxScreenY = rect.bottom - 30;
        if (screenY > maxScreenY) screenY = maxScreenY;

        var topInRoot = screenY - rootRect.top;
        var topVal = topInRoot + "px";
        if (prev) prev.style.top = topVal;
        if (next) next.style.top = topVal;

        // --- X: an den tatsächlichen Scroll-Viewport ankern ---
        // clientWidth liefert die VISUELLE Viewport-Breite des Scroll-Containers
        // (also der Bereich, in dem die Cards sichtbar sind), unabhängig davon,
        // wie das umliegende Grid den Container box-technisch sized. Das ist der
        // einzige verlässliche Wert in beiden Modi:
        //   - Frontend: Scroll-Box clippt Overflow → clientWidth = sichtbare Card-Breite
        //   - Canvas:   Scroll-Box breiter, aber clientWidth reflektiert dennoch
        //               die visuelle Viewport-Größe (nicht die Grid-Zelle).
        // Der Viewport bleibt beim Scrollen der Cards FIX (nur der Inhalt
        // bewegt sich), deshalb zappeln die Pfeile nicht mehr hinterher.
        // parseFloat + isNaN-Check wie bei nudgeY: User-Eingabe 0 muss echt 0
        // bleiben, nicht in den Default fallen (0 ist falsy in JS).
        var navOffsetRaw = parseFloat(cs.getPropertyValue("--vgl-nav-offset"));
        var navOffset    = isNaN(navOffsetRaw) ? 12 : navOffsetRaw;
        var nudgeXRaw    = parseFloat(cs.getPropertyValue("--vgl-nav-offset-x"));
        var nudgeX       = isNaN(nudgeXRaw) ? 0 : nudgeXRaw;

        var scroll = wrapper.querySelector(".vergleich-scroll");

        if (scroll) {
            var sRect = scroll.getBoundingClientRect();
            var viewportLeft  = sRect.left;
            var viewportRight = sRect.left + scroll.clientWidth;

            if (prev) {
                prev.style.left  = (viewportLeft - rootRect.left + navOffset + nudgeX) + "px";
                prev.style.right = "auto";
            }
            if (next) {
                next.style.right = (rootRect.right - viewportRight + navOffset - nudgeX) + "px";
                next.style.left  = "auto";
            }
        }
    }

    function updateNav(wrapper){
        var scroll = wrapper.querySelector(".vergleich-scroll");
        if (!scroll) return;

        var counter = findCounter(wrapper);
        var rootEl  = wrapper.closest(".vergleich-root") || wrapper.parentNode;
        var prev    = rootEl ? rootEl.querySelector(".vergleich-nav--prev") : null;
        var next    = rootEl ? rootEl.querySelector(".vergleich-nav--next") : null;

        var overflows = scroll.scrollWidth - scroll.clientWidth > 1;
        var atStart   = scroll.scrollLeft <= 1;
        var atEnd     = scroll.scrollLeft >= scroll.scrollWidth - scroll.clientWidth - 1;

        // Zuerst positionieren, dann hidden toggeln — vermeidet einen Flash,
        // bei dem der Pfeil für einen Frame am (falschen) CSS-Default-Ort
        // sichtbar wäre, bevor JS die korrekte Position gesetzt hat.
        updateNavPosition(wrapper);

        if (prev && next) {
            prev.hidden = !overflows || atStart;
            next.hidden = !overflows || atEnd;
        }

        if (counter) {
            var cards = scroll.querySelectorAll(".vergleich-card");
            var total = cards.length;
            if (total === 0) {
                counter.textContent = "";
            } else {
                var firstCard = cards[0];
                var cardW = firstCard ? firstCard.getBoundingClientRect().width : 0;
                if (!cardW || cardW < 1) cardW = 1;
                var cw = scroll.clientWidth || cardW;
                var visible = Math.max(1, Math.round(cw / cardW));
                var start = Math.floor(scroll.scrollLeft / cardW) + 1;
                if (start < 1) start = 1;
                if (start > total) start = total;
                var end = Math.min(start + visible - 1, total);
                var fmt = counter.getAttribute("data-format") || "{start}–{end} von {total}";
                counter.textContent = fmt
                    .replace("{start}", start)
                    .replace("{end}",   end)
                    .replace("{total}", total);
            }
        }
    }

    function bindNav(wrapper){
        if (wrapper._vglNavBound) { updateNav(wrapper); return; }
        wrapper._vglNavBound = true;

        var rootForClicks = wrapper.closest(".vergleich-root") || wrapper.parentNode;
        (rootForClicks || wrapper).addEventListener("click", function(e){
            var btn = e.target && e.target.closest ? e.target.closest("[data-vgl-nav]") : null;
            if (!btn) return;
            var clickRoot = btn.closest(".vergleich-root");
            if (clickRoot && clickRoot !== rootForClicks) return;
            var scroll = wrapper.querySelector(".vergleich-scroll");
            if (!scroll) return;
            var dir = btn.getAttribute("data-vgl-nav");
            var stepMode = wrapper.classList.contains("vgl-nav-step-view") ? "view" : "card";
            var cards = scroll.querySelectorAll(".vergleich-card");

            // scrollBy (nicht scrollTo mit queued target). Grund: jeder Klick
            // bewegt visuell um „eine Einheit", auch wenn eine vorherige
            // smooth-Animation noch läuft — das ist die UX-Erwartung an
            // Pfeil-Klicks. Ein Target-Queue, das alle Klicks zu einem langen
            // Smooth-Scroll akkumuliert, fühlt sich bei schnellen Klicks wie
            // Gummiband an.
            //
            // Drift-Prophylaxe: Stride als exakte Ganzzahl aus
            // cards[1].offsetLeft − cards[0].offsetLeft (Card-Breite + Gap),
            // NICHT aus getBoundingClientRect().width — letzteres ist
            // fraktional und summiert sich über mehrere Klicks zum sichtbaren
            // Versatz an der Label-Spalte auf.
            var step;
            if (stepMode === "view") {
                step = Math.max(scroll.clientWidth - 20, 100);
            } else if (cards.length >= 2) {
                step = cards[1].offsetLeft - cards[0].offsetLeft;
            } else if (cards.length === 1) {
                step = cards[0].offsetWidth;
            } else {
                step = 200;
            }
            scroll.scrollBy({ left: (dir === "prev" ? -step : step), behavior: "smooth" });
        });

        var handler = function(){ updateNav(wrapper); };
        var posHandler = function(){ updateNavPosition(wrapper); };

        var scroll = wrapper.querySelector(".vergleich-scroll");
        if (scroll) scroll.addEventListener("scroll", handler, { passive: true });
        window.addEventListener("resize", handler);
        document.addEventListener("scroll", posHandler, { passive: true, capture: true });
        window.addEventListener("scroll", posHandler, { passive: true });

        // Kein Dauer-RAF-Loop für die Pfeil-Position: die Pfeile müssen nur
        // neu positioniert werden, wenn entweder die Seite scrollt, das Fenster
        // resized wird oder die Tabelle ihre Größe ändert. Die entsprechenden
        // Listener/Observer sind schon oben gebunden. Ein 60fps-RAF-Loop lief
        // permanent, solange die Tabelle im Viewport war, rief pro Frame
        // getBoundingClientRect() + getComputedStyle() auf und war auf Mobile
        // die Hauptquelle für Ruckler an den Navigationspfeilen.

        if (typeof ResizeObserver !== "undefined") {
            var ro = new ResizeObserver(handler);
            if (scroll) ro.observe(scroll);
            ro.observe(wrapper);
        }
        requestAnimationFrame(handler);
        setTimeout(handler, 250);
        setTimeout(handler, 800);
    }

    function bindLabelRowSync(wrapper){
        if (!wrapper || wrapper._vglLabelRowBound) return;
        var root = wrapper.closest(".vergleich-root") || wrapper.parentNode;
        if (!root) return;
        var track = root.querySelector(".vergleich-product-label-row__track");
        if (!track) return;
        var scroll = wrapper.querySelector(".vergleich-scroll");
        if (!scroll) return;
        wrapper._vglLabelRowBound = true;

        var ticking = false;
        function apply(){
            track.style.transform = "translateX(" + (-scroll.scrollLeft) + "px)";
        }
        scroll.addEventListener("scroll", function(){
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(function(){
                apply();
                ticking = false;
            });
        }, { passive: true });
        apply();
    }

    function bindRowHover(wrapper){
        if (wrapper._vglRowHoverBound) return;
        wrapper._vglRowHoverBound = true;

        function rowEls(idx){
            return wrapper.querySelectorAll(
                '[data-row-index="' + idx + '"]'
            );
        }
        function clearActive(){
            var active = wrapper.querySelectorAll(".is-row-hover");
            for (var i = 0; i < active.length; i++) active[i].classList.remove("is-row-hover");
        }
        wrapper.addEventListener("mouseover", function(e){
            if (!wrapper.classList.contains("has-row-hover")) return;
            var el = e.target && e.target.closest ? e.target.closest("[data-row-index]") : null;
            if (!el || !wrapper.contains(el)) return;
            var idx = el.getAttribute("data-row-index");
            if (idx === null) return;
            clearActive();
            var els = rowEls(idx);
            for (var i = 0; i < els.length; i++) els[i].classList.add("is-row-hover");
        });
        wrapper.addEventListener("mouseleave", clearActive);
    }

    // Sticky-Zeilen per transform:translate3d. Hintergrund: position:sticky auf
    // Grid-Items innerhalb von Subgrid funktioniert in Chrome nicht zuverlässig
    // (Containing-Block-Berechnung + Subgrid-Interaktion). Statt das Element aus
    // dem Grid-Flow zu reißen, lassen wir die Zellen in ihren Slots und
    // verschieben sie beim Scrollen rein visuell per Transform nach unten.
    //
    // Mobile-Performance: Die frühere Implementierung las pro Scroll-Frame
    // getBoundingClientRect() jeder Sticky-Zelle UND setzte vorher
    // `style.transform = ""` zurück — das erzwingt einen synchronen Layout-
    // Reflow pro Zelle pro Frame, was iOS Safari im Momentum-Scroll sichtbar
    // ruckeln ließ. Jetzt:
    //   - Natürliche Position + Zellhöhe einmal messen (auf Init/Resize/RO),
    //     gecached als Offset relativ zum Wrapper.
    //   - Im Scroll-Frame nur EIN getBoundingClientRect() (Wrapper) + Writes
    //     von translate3d (Compositor-Layer, kein Repaint).
    //   - will-change: transform in CSS sorgt für GPU-Layer — auf Mobile
    //     läuft das Scrollen dann auf dem Compositor-Thread, nicht Main.
    function bindStickyRows(wrapper){
        if (wrapper._vglStickyBound) return;
        var root = wrapper.closest(".vergleich-root") || wrapper.parentNode;
        if (!root) return;

        var rowMap = {};
        root.querySelectorAll(".is-sticky-row").forEach(function(cell){
            var idx = cell.getAttribute("data-row-index");
            if (idx === null) return;
            (rowMap[idx] = rowMap[idx] || []).push(cell);
        });
        if (!Object.keys(rowMap).length) return;
        wrapper._vglStickyBound = true;

        var sortedIdxs = Object.keys(rowMap).sort(function(a, b){
            return parseInt(a, 10) - parseInt(b, 10);
        });

        // Cache: wird auf Init/Resize neu befüllt, NICHT pro Scroll-Frame.
        // rows[i] = { cells, offsetInWrap, height }
        var baseOffset = 0;
        var rows = [];
        // Letzte gesetzte Translations pro Row — verhindert redundante DOM-Writes.
        var lastTy = [];

        function measure(){
            var cs = window.getComputedStyle(wrapper);
            var offsetRaw = parseFloat(cs.getPropertyValue("--vgl-sticky-row-top"));
            baseOffset = isNaN(offsetRaw) ? 0 : offsetRaw;

            rows = [];
            lastTy = [];
            // Transforms für die Messung temporär zurücksetzen, damit die
            // gemessenen Positionen die NATÜRLICHEN Positionen sind, nicht
            // die aktuell translatierten.
            for (var k = 0; k < sortedIdxs.length; k++) {
                var cellsK = rowMap[sortedIdxs[k]];
                for (var i = 0; i < cellsK.length; i++) cellsK[i].style.transform = "";
            }
            var wrapRect = wrapper.getBoundingClientRect();
            var wrapTop = wrapRect.top;
            for (var m = 0; m < sortedIdxs.length; m++) {
                var cellsM = rowMap[sortedIdxs[m]];
                if (!cellsM.length) continue;
                var r = cellsM[0].getBoundingClientRect();
                rows.push({
                    cells: cellsM,
                    offsetInWrap: r.top - wrapTop,
                    height: r.height
                });
                lastTy.push(-1);
            }
        }

        function update(){
            if (!rows.length) return;
            var wrapRect = wrapper.getBoundingClientRect();
            var wrapTop = wrapRect.top;
            var wrapBottom = wrapRect.bottom;
            var topAccum = baseOffset;

            for (var i = 0; i < rows.length; i++) {
                var row = rows[i];
                var naturalTop = wrapTop + row.offsetInWrap;
                var translateY = 0;
                if (naturalTop < topAccum) {
                    translateY = topAccum - naturalTop;
                    // Clamp: Zeile bleibt am Tabellenboden stehen, wenn der
                    // Wrapper rausscrollt.
                    var maxTranslate = wrapBottom - naturalTop - row.height;
                    if (maxTranslate < 0) maxTranslate = 0;
                    if (translateY > maxTranslate) translateY = maxTranslate;
                }
                // Nur schreiben wenn sich der Wert (auf ganze Pixel gerundet)
                // geändert hat — spart DOM-Writes im Momentum-Scroll.
                var tyRounded = Math.round(translateY);
                if (tyRounded !== lastTy[i]) {
                    var t = tyRounded > 0 ? "translate3d(0," + tyRounded + "px,0)" : "";
                    for (var c = 0; c < row.cells.length; c++) {
                        row.cells[c].style.transform = t;
                    }
                    lastTy[i] = tyRounded;
                }
                if (translateY > 0) topAccum += row.height;
            }
        }

        var pending = false;
        function schedule(){
            if (pending) return;
            pending = true;
            requestAnimationFrame(function(){ update(); pending = false; });
        }
        function remeasure(){
            measure();
            schedule();
        }

        measure();
        update();

        // Nur window.scroll binden — document+capture feuerte zuvor
        // zusätzlich bei jedem Scroll, ohne neue Information zu liefern
        // (doppelte Kosten auf Mobile). Page-Scrolls bubblen an window.
        window.addEventListener("scroll", schedule, { passive: true });
        window.addEventListener("resize", remeasure);
        if (typeof ResizeObserver !== "undefined") {
            var ro = new ResizeObserver(remeasure);
            ro.observe(wrapper);
        }
        // Nachzügler für Layout-Settling (Fonts, Bilder).
        setTimeout(remeasure, 100);
        setTimeout(remeasure, 500);
    }

    function init(wrapper){
        syncRows(wrapper);
        bindExpand(wrapper);
        bindNav(wrapper);
        bindRowHover(wrapper);
        bindLabelRowSync(wrapper);
        bindStickyRows(wrapper);
        var burst = 0;
        (function tick(){
            if (!wrapper.isConnected) return;
            syncRows(wrapper);
            if (++burst < 20) requestAnimationFrame(tick);
        })();

        if (typeof ResizeObserver !== "undefined") {
            var ro = new ResizeObserver(function(){ syncRows(wrapper); });
            ro.observe(wrapper);
            wrapper.querySelectorAll("img").forEach(function(img){
                if (!img.complete) img.addEventListener("load", function(){ syncRows(wrapper); }, { once: true });
            });
        }
    }

    function boot(){
        document.querySelectorAll(".vergleich-wrapper").forEach(init);
    }

    // Lightbox-Zellen: ein globaler delegierter Click-Handler reicht. Der
    // Dialog wird via showModal() geöffnet — damit landet er im Browser-
    // Top-Layer, ignoriert overflow/transform/filter der Vorfahren und
    // bekommt ESC + Backdrop-Click for free. Backdrop-Click schließen wir
    // manuell, weil die Browser das nur über ::backdrop-Klicks auf dem
    // <dialog>-Element zulassen, nicht automatisch.
    if (!document._vglLightboxBound) {
        document._vglLightboxBound = true;
        // Hilfsfunktion: ARIA-State am Trigger synchron zum Dialog-State.
        // Screenreader-Nutzer hoeren "expanded"/"collapsed", wenn sie zum
        // Trigger zurueckfokussieren (z.B. nach ESC).
        function setTriggerExpanded(dlgId, expanded) {
            if (!dlgId) return;
            var triggers = document.querySelectorAll('[data-vgl-lightbox-open="' + dlgId + '"]');
            for (var i = 0; i < triggers.length; i++) {
                triggers[i].setAttribute("aria-expanded", expanded ? "true" : "false");
            }
        }

        document.addEventListener("click", function(e){
            var t = e.target;
            if (!t || !t.closest) return;
            // Trigger
            var trigger = t.closest("[data-vgl-lightbox-open]");
            if (trigger) {
                var id = trigger.getAttribute("data-vgl-lightbox-open");
                var dlg = id ? document.getElementById(id) : null;
                if (dlg && typeof dlg.showModal === "function") {
                    try { dlg.showModal(); setTriggerExpanded(id, true); } catch (err) { /* already open */ }
                } else if (dlg && typeof dlg.show === "function") {
                    // Alter Fallback ohne Top-Layer — funktioniert visuell,
                    // aber ohne Modal-Verhalten.
                    try { dlg.show(); setTriggerExpanded(id, true); } catch (err) {}
                }
                return;
            }
            // Close-Button
            var closer = t.closest("[data-vgl-lightbox-close]");
            if (closer) {
                var dlg2 = closer.closest("dialog.vergleich-lightbox-dialog");
                if (dlg2 && typeof dlg2.close === "function") dlg2.close();
                return;
            }
            // Backdrop-Click: Browser feuern click auf das <dialog>-Element
            // selbst, wenn auf den Backdrop geklickt wird — nicht auf den
            // inneren Content. Also: wenn target === dialog, schließen.
            if (t.tagName === "DIALOG" && t.classList.contains("vergleich-lightbox-dialog")) {
                if (typeof t.close === "function") t.close();
            }
        });

        // 'close'-Event feuert bei ESC, Close-Button und Backdrop-Click —
        // zentral dort den Trigger-State zuruecksetzen, statt an drei Stellen.
        document.addEventListener("close", function(e){
            var dlg = e.target;
            if (!dlg || dlg.tagName !== "DIALOG") return;
            if (!dlg.classList || !dlg.classList.contains("vergleich-lightbox-dialog")) return;
            setTriggerExpanded(dlg.id, false);
        }, true);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }

    // Canvas-Re-Renders: wenn Bricks neue .vergleich-wrapper ins DOM schiebt
    // (AJAX-Replace), initialisieren wir die neuen Wrappers. Nur auf neu
    // hinzugefügte Knoten reagieren — Attribut-Änderungen innerhalb bestehender
    // Wrapper würden sonst eine Endlosschleife via Counter-Text-Update triggern.
    if (typeof MutationObserver !== "undefined") {
        var mo = new MutationObserver(function(mutations){
            for (var i = 0; i < mutations.length; i++) {
                var added = mutations[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    var n = added[j];
                    if (!n || n.nodeType !== 1) continue;
                    if ((n.classList && n.classList.contains("vergleich-wrapper")) ||
                        (n.querySelector && n.querySelector(".vergleich-wrapper"))) {
                        boot();
                        return;
                    }
                }
            }
        });
        mo.observe(document.body || document.documentElement, { childList: true, subtree: true });
    }
})();
