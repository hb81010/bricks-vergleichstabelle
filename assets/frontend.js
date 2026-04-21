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
            var card = scroll.querySelector(".vergleich-card");
            var cardW = card ? card.getBoundingClientRect().width : 200;
            var stepMode = wrapper.classList.contains("vgl-nav-step-view") ? "view" : "card";
            var step = stepMode === "view" ? Math.max(scroll.clientWidth - 20, 100) : cardW;
            scroll.scrollBy({ left: (dir === "prev" ? -step : step), behavior: "smooth" });
        });

        var handler = function(){ updateNav(wrapper); };
        var posHandler = function(){ updateNavPosition(wrapper); };

        var scroll = wrapper.querySelector(".vergleich-scroll");
        if (scroll) scroll.addEventListener("scroll", handler, { passive: true });
        window.addEventListener("resize", handler);
        document.addEventListener("scroll", posHandler, { passive: true, capture: true });
        window.addEventListener("scroll", posHandler, { passive: true });

        var rafId = 0;
        var rafActive = false;
        function rafLoop(){
            if (!rafActive || !wrapper.isConnected) { rafId = 0; return; }
            updateNavPosition(wrapper);
            rafId = requestAnimationFrame(rafLoop);
        }
        function startRaf(){
            if (rafActive) return;
            rafActive = true;
            if (!rafId) rafId = requestAnimationFrame(rafLoop);
        }
        function stopRaf(){
            rafActive = false;
            if (rafId) { cancelAnimationFrame(rafId); rafId = 0; }
        }
        if (typeof IntersectionObserver !== "undefined") {
            var io = new IntersectionObserver(function(entries){
                for (var i = 0; i < entries.length; i++) {
                    if (entries[i].isIntersecting) startRaf();
                    else stopRaf();
                }
            }, { threshold: 0 });
            io.observe(wrapper);
        } else {
            startRaf();
        }

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

    function init(wrapper){
        syncRows(wrapper);
        bindExpand(wrapper);
        bindNav(wrapper);
        bindRowHover(wrapper);
        bindLabelRowSync(wrapper);
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
