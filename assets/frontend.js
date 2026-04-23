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

            // Wenn eine Spalte gepinnt ist, soll der Prev-Pfeil NICHT auf der
            // gepinnten Card liegen — dort waere er sinnlos, weil er nur die
            // nicht-gepinnten Spalten nach links zieht. Wir schieben ihn auf
            // die rechte Kante der gepinnten Spalte, sodass er visuell ueber
            // dem tatsaechlich scrollenden Bereich sitzt (wie bei Finanzfluss).
            var pinnedCard = scroll.querySelector(".vergleich-card.is-pinned");
            if (pinnedCard) {
                var pRect = pinnedCard.getBoundingClientRect();
                if (pRect.right > viewportLeft) {
                    viewportLeft = pRect.right;
                }
            }

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

        // Toleranz 4px statt 1: Card-Breiten sind haeufig fraktional (z.B.
        // durch CSS calc / flex), scrollBy landet damit nicht exakt auf
        // scrollWidth-clientWidth. Mit 1px Toleranz blieb der Next-Pfeil
        // am letzten Element sichtbar und brauchte noch einen Klick, der
        // nur ein paar Pixel scrollte, bevor er verschwand.
        var overflows = scroll.scrollWidth - scroll.clientWidth > 1;
        var atStart   = scroll.scrollLeft <= 4;
        var atEnd     = scroll.scrollLeft >= scroll.scrollWidth - scroll.clientWidth - 4;

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
            // Direkte scrollLeft-Zuweisung statt scrollBy({behavior:smooth}):
            // smooth-scroll kann mit dem Scroll-Event-Handler, der die
            // --vgl-scroll-left-Variable updated, racen. Bei Direkt-Setzung
            // landet alles in einem Frame und das Produkt-Label der
            // gepinnten Card folgt ohne Versatz.
            var delta  = (dir === "prev" ? -step : step);
            var target = scroll.scrollLeft + delta;
            var max    = scroll.scrollWidth - scroll.clientWidth;
            if (target < 0)   target = 0;
            if (target > max) target = max;
            scroll.scrollLeft = target;

            // CSS-Variable UND Label-Transform explizit setzen — falls der
            // Scroll-Event verzoegert / nicht feuert, wuerde das Label sonst
            // beim alten Wert haengen und optisch von der scrollenden Spalte
            // ueberholt werden.
            wrapper.style.setProperty("--vgl-scroll-left", target + "px");
            var navRoot = wrapper.closest(".vergleich-root") || wrapper.parentNode;
            if (navRoot) {
                var pinnedLabel = navRoot.querySelector(".vergleich-product-label-item.is-pinned-label");
                if (pinnedLabel) pinnedLabel.style.transform = t;
            }
            updateNav(wrapper);
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

    // ========================================================================
    // PIN: eine Spalte anpinnen, damit sie beim horizontalen Scroll links
    // sichtbar bleibt. Pin-Button wird nur angezeigt, wenn mindestens zwei
    // Cards gleichzeitig in den Viewport passen — sonst hat Pinning keinen
    // Nutzen (nichts zum Vergleichen daneben).
    //
    // Technik: KEIN DOM-Move. Die gepinnte Card bekommt nur die Klasse
    // .is-pinned, und CSS uebernimmt zwei Dinge gleichzeitig:
    //   - order: -1  → die Card rutscht im Grid-auto-flow:column visuell
    //                  an Position 1, ohne dass wir DOM anfassen.
    //   - position: sticky; left: 0 → sie klebt beim horizontalen Scrollen
    //                                 am linken Rand des Scroll-Containers.
    // Ein frueherer Versuch mit insertBefore + smooth scrollTo hat im
    // Zusammenspiel mit .vergleich-scroll/Grid dazu gefuehrt, dass der
    // Scroll nach einem Klick „hing" — CSS-only ist hier die sauberere
    // Loesung und braucht auch keine Restore-Logik mehr. Keine Persistenz:
    // bei Reload ist die Auswahl bewusst weg.
    // ========================================================================
    function bindPin(wrapper){
        if (wrapper._vglPinBound) return;
        if (!wrapper.classList.contains("has-pin")) return;
        wrapper._vglPinBound = true;

        var scroll = wrapper.querySelector(".vergleich-scroll");
        var track  = wrapper.querySelector(".vergleich-track");
        if (!scroll || !track) return;

        function updateAvailability(){
            var cards = track.querySelectorAll(".vergleich-card");
            if (cards.length < 2) {
                wrapper.classList.remove("can-pin");
                return;
            }
            // Card-Breite: offsetWidth der ERSTEN nicht gepinnten Card. Die
            // gepinnte Card haette zwar dieselbe Breite, aber beim Messen
            // auf jedem Stand konsistent an einer un-sticky Card zu messen
            // vermeidet Randfaelle.
            var ref = null;
            for (var i = 0; i < cards.length; i++) {
                if (!cards[i].classList.contains("is-pinned")) { ref = cards[i]; break; }
            }
            if (!ref) ref = cards[0];
            var cardW = ref.offsetWidth;
            if (!cardW || cardW < 1) {
                wrapper.classList.remove("can-pin");
                return;
            }
            var visible = Math.floor(scroll.clientWidth / cardW);
            // >= 2 sichtbare Spalten: Pinnen macht Sinn (1 gepinnt + >=1
            // Vergleichsspalte).
            if (visible >= 2) {
                wrapper.classList.add("can-pin");
            } else {
                wrapper.classList.remove("can-pin");
                // Falls gerade gepinnt war und der Viewport zu schmal wird:
                // automatisch entpinnen — sonst sieht der User nur die
                // gepinnte Spalte und kommt nicht mehr an die anderen.
                var pinned = track.querySelector(".vergleich-card.is-pinned");
                if (pinned) unpin(pinned);
            }
        }

        var rootEl = wrapper.closest(".vergleich-root") || wrapper.parentNode;

        // Label-Item suchen, das zur Card gehoert. Die Produkt-Label-Zeile
        // rendert 1:1 pro Card in gleicher Reihenfolge — also Index-basiert.
        function labelItemForCard(card){
            if (!rootEl) return null;
            var cards = Array.prototype.slice.call(track.querySelectorAll(".vergleich-card"));
            var idx = cards.indexOf(card);
            if (idx < 0) return null;
            var items = rootEl.querySelectorAll(".vergleich-product-label-item");
            return items[idx] || null;
        }

        function clearPinnedLabels(){
            if (!rootEl) return;
            rootEl.querySelectorAll(".vergleich-product-label-item.is-pinned-label")
                .forEach(function(el){ el.classList.remove("is-pinned-label"); });
        }

        function pin(card){
            // Erst evtl. vorherige Pinnung aufheben (nur eine Spalte pinnbar).
            var prev = track.querySelector(".vergleich-card.is-pinned");
            if (prev && prev !== card) {
                prev.classList.remove("is-pinned");
                prev.style.transform = "";
                var prevBtn = prev.querySelector(".vergleich-pin");
                if (prevBtn) prevBtn.setAttribute("aria-pressed", "false");
            }
            clearPinnedLabels();
            card.classList.add("is-pinned");
            var btn = card.querySelector(".vergleich-pin");
            if (btn) btn.setAttribute("aria-pressed", "true");
            var label = labelItemForCard(card);
            if (label) label.classList.add("is-pinned-label");
            refreshPinRefs();
            applySyncNow();
            // Scroll-Position NICHT anfassen: der Transform-basierte Sticky-
            // Effekt haftet die Card am linken Rand, unabhaengig vom aktuellen
            // scrollLeft. Ein scrollTo hier wuerde mit nachfolgenden Nav-Klicks
            // kollidieren (Smooth-Animation friert dann ein).
            requestAnimationFrame(function(){
                syncRows(wrapper);
                updateNav(wrapper);
            });
        }

        function unpin(card){
            card.classList.remove("is-pinned");
            card.style.transform = "";
            clearPinnedLabels();
            if (rootEl) {
                rootEl.querySelectorAll(".vergleich-product-label-item")
                    .forEach(function(el){ el.style.transform = ""; });
            }
            var btn = card.querySelector(".vergleich-pin");
            if (btn) btn.setAttribute("aria-pressed", "false");
            refreshPinRefs();
            requestAnimationFrame(function(){
                syncRows(wrapper);
                updateNav(wrapper);
            });
        }

        // Die gepinnte Card selbst regelt ihren horizontalen Sticky-Effekt
        // ueber CSS (position:sticky, siehe assets/vergleich.css) — der
        // Compositor haelt sie beim Scroll ohne Main-Thread-Umweg, deshalb
        // kein Flackern.
        //
        // Die CSS-Variable --vgl-scroll-left setzen wir weiterhin pro Scroll,
        // weil das PRODUKT-LABEL der gepinnten Card sie braucht: die
        // Label-Row-Track bekommt in bindLabelRowSync ein translateX(-sl);
        // das gepinnte Label kontert mit translateX(+sl) (aus der CSS-Var),
        // damit es optisch ueber der gepinnten Card stehen bleibt statt
        // mitzuscrollen. rAF-gebatcht, damit der Scroll-Handler nichts
        // synchron schreibt.
        var pendingRAF = false;
        function applySyncNow(){
            wrapper.style.setProperty("--vgl-scroll-left", scroll.scrollLeft + "px");
        }
        function syncScrollVar(){
            if (pendingRAF) return;
            pendingRAF = true;
            requestAnimationFrame(function(){
                pendingRAF = false;
                applySyncNow();
            });
        }
        function refreshPinRefs(){ /* no-op: Card ist CSS-sticky, Label ist CSS-var-getrieben */ }
        scroll.addEventListener("scroll", syncScrollVar, { passive: true });
        applySyncNow();

        track.addEventListener("click", function(e){
            var btn = e.target && e.target.closest ? e.target.closest("[data-vgl-pin]") : null;
            if (!btn) return;
            var card = btn.closest(".vergleich-card");
            if (!card) return;
            e.preventDefault();
            // Unpin MUSS immer klappen — auch wenn can-pin zwischenzeitlich
            // weg ist (z.B. wegen Viewport-Resize). Sonst koennte der User
            // seine Pinnung nicht mehr loesen. Nur das NEU-Pinnen gaten wir.
            if (card.classList.contains("is-pinned")) {
                unpin(card);
            } else {
                if (!wrapper.classList.contains("can-pin")) return;
                pin(card);
            }
        });

        updateAvailability();
        window.addEventListener("resize", updateAvailability);
        if (typeof ResizeObserver !== "undefined") {
            var ro = new ResizeObserver(updateAvailability);
            ro.observe(scroll);
        }
        // Nachzuegler fuer Layout-Settling (Fonts, Bilder aendern Kartenbreite).
        setTimeout(updateAvailability, 250);
        setTimeout(updateAvailability, 800);
    }

    function init(wrapper){
        syncRows(wrapper);
        bindExpand(wrapper);
        bindNav(wrapper);
        bindRowHover(wrapper);
        bindLabelRowSync(wrapper);
        bindStickyRows(wrapper);
        bindPin(wrapper);
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

    // ========================================================================
    // Gutscheincode: Kopieren + Toast
    // ========================================================================
    // Singleton-Toast: wird beim ersten Bedarf erzeugt und wiederverwendet,
    // damit nicht bei jedem Click ein neues DOM-Element entsteht.
    var toastEl = null;
    var toastTimer = null;
    var toastHideTimer = null;
    function ensureToast(){
        if (toastEl) return toastEl;
        toastEl = document.createElement("div");
        toastEl.className = "vergleich-toast";
        toastEl.setAttribute("role", "status");
        toastEl.setAttribute("aria-live", "polite");
        toastEl.innerHTML =
            '<span class="vergleich-toast__icon" aria-hidden="true">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' +
            '</span>' +
            '<span class="vergleich-toast__text"></span>';
        document.body.appendChild(toastEl);
        return toastEl;
    }
    function showToast(text){
        var el = ensureToast();
        var textEl = el.querySelector(".vergleich-toast__text");
        if (textEl) textEl.textContent = text || "";
        // Hart resetten, damit schnelle Folge-Clicks die Animation neu starten.
        if (toastTimer) clearTimeout(toastTimer);
        if (toastHideTimer) clearTimeout(toastHideTimer);
        el.classList.remove("is-visible");
        // Force reflow, damit das Einblenden triggert, auch wenn der Toast
        // gerade noch visible war.
        void el.offsetWidth;
        el.classList.add("is-visible");
        toastTimer = setTimeout(function(){
            el.classList.remove("is-visible");
        }, 2200);
    }

    function copyToClipboard(text){
        // Modernen Weg bevorzugen (Promise-basiert). Fallback fuer ältere
        // Browser oder nicht-HTTPS-Kontext via temporäres <textarea>.
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function(resolve, reject){
            try {
                var ta = document.createElement("textarea");
                ta.value = text;
                ta.setAttribute("readonly", "");
                ta.style.position = "fixed";
                ta.style.opacity = "0";
                ta.style.pointerEvents = "none";
                document.body.appendChild(ta);
                ta.select();
                ta.setSelectionRange(0, text.length);
                var ok = document.execCommand("copy");
                document.body.removeChild(ta);
                ok ? resolve() : reject(new Error("execCommand copy failed"));
            } catch (e) {
                reject(e);
            }
        });
    }

    if (!document._vglCouponBound) {
        document._vglCouponBound = true;
        document.addEventListener("click", function(e){
            var t = e.target;
            if (!t || !t.closest) return;
            var btn = t.closest("[data-vgl-copy-code]");
            if (!btn) return;
            var code = btn.getAttribute("data-vgl-copy-code") || "";
            var toast = btn.getAttribute("data-vgl-copy-toast") || "";
            copyToClipboard(code).then(function(){
                // Button-Success-State fuer 1.5s.
                btn.classList.add("is-copied");
                if (btn._vglCopyTimer) clearTimeout(btn._vglCopyTimer);
                btn._vglCopyTimer = setTimeout(function(){
                    btn.classList.remove("is-copied");
                }, 1600);
                showToast(toast);
            }).catch(function(){
                // Fallback-Meldung, falls Clipboard-API geblockt wurde
                // (Iframes ohne allow="clipboard-write" o.ae.).
                showToast(toast);
            });
        });
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
