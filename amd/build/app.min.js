/**
 * @module mod_superflipbook/app
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification', 'core/str'], function(Ajax, Notification, Str) {
    'use strict';

    // Use Moodle's cached language-string service instead of bloating js_call_amd arguments.
    const STRING_KEYS = [
        "canvaserror",
        "doublepage",
        "exitfullscreen",
        "fullscreen",
        "fullscreenenterunsupported",
        "fullscreenerror",
        "fullscreenexitunsupported",
        "openingpdf",
        "operationdenied",
        "page",
        "pagenumber",
        "pagerendererror",
        "pages",
        "pdfcontent",
        "pdfdetailslabel",
        "pdfinvalid",
        "pdfjsloaderror",
        "pdfloaderror",
        "pdfopenfailed",
        "pdfpagealt",
        "pdfpasswordprompt",
        "pdfurlerror",
        "preparingpages",
        "singlepage",
        "thumbcreateerror",
        "thumbuploaderror",
        "unknownerror"
];

    const init = async (cfg) => {
        const root = document.querySelector('[data-region="superflipbook"]');
        if (!root) {
            return;
        }
        let translations;
        try {
            // get_strings works on Moodle 4.1+; getStrings only exists on newer releases.
            // Keep the {$a} placeholder intact so dynamic messages can be formatted locally.
            translations = await Str.get_strings(STRING_KEYS.map((key) => ({
                key, component: 'mod_superflipbook', param: '{$a}'
            })));
        } catch (error) {
            Notification.exception(error);
            return;
        }
        const strings = Object.fromEntries(STRING_KEYS.map((key, index) => [key, translations[index]]));
        const translated = (key, value) => strings[key].replace('{$a}', String(value));
        const overlay = root.querySelector('[data-region="overlay"]');
        const cards = [...root.querySelectorAll('[data-pdf-id]')];
        const firstFileId = cards[0]?.dataset.pdfId;
        let activeCard = null;
        let previousFocus = null;
        let lastHeartbeat = Date.now();
        let lastTrackedPage = 0;
        const isTracked = () => cfg.trackfirst && activeCard?.dataset.pdfId === firstFileId;
        const send = (acknowledge = false, entered = false) => {
            if (!isTracked() || !state.pdf || overlay.hidden) {
                return Promise.resolve(null);
            }
            const now = Date.now();
            const elapsed = Math.round((now - lastHeartbeat) / 1000);
            const seconds = document.visibilityState === 'visible' && document.hasFocus() ?
                Math.min(30, Math.max(0, elapsed)) : 0;
            lastHeartbeat = now;
            return Ajax.call([{
                methodname: 'mod_superflipbook_record_progress',
                args: {cmid: cfg.cmid, page: state.page, pages: state.total,
                    seconds, acknowledge, entered}
            }])[0].catch((error) => {
                Notification.exception(error);
                return null;
            });
        };
        const trackPage = () => {
            if (isTracked() && lastTrackedPage !== state.page) {
                lastTrackedPage = state.page;
                void send(false, true);
            }
        };

    const PDFJS_VERSION = '4.10.38';
    const PDFJS_BASE = `https://cdn.jsdelivr.net/npm/pdfjs-dist@${PDFJS_VERSION}/build/`;
    const RENDERED_PAGE_LIMIT = 10;
    const MODE_STORAGE_KEY = 'superflipbook.folio-pdf.mode';
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const $ = (selector) => root.querySelector(selector);
    const ui = {
        input: $('#pdf-input'),
        title: $('#document-title'),
        details: $('#document-details'),
        stage: $('#book-stage'),
        book: $('#book-spread'),
        viewport: $('#book-viewport'),
        mode: $('#mode-button'),
        fullscreen: $('#fullscreen-button'),
        progress: $('#page-progress'),
        progressLabel: $('#progress-label'),
        number: $('#page-number'),
        count: $('#page-count'),
        zoom: $('#zoom-label'),
        error: $('#reader-error'),
        loading: $('#reader-loading'),
        loadingMessage: $('#loading-message'),
        reader: $('.reader-section')
    };

    function savedSpreadPreference() {
        try {
            const saved = window.localStorage.getItem(MODE_STORAGE_KEY);
            if (saved === 'single') return false;
            if (saved === 'double') return true;
        } catch (_) {
            // Storage can be unavailable in private or embedded browsing contexts.
        }
        return window.innerWidth > 640;
    }

    const state = {
        pdf: null, file: null, page: 1, total: 0, zoom: 1,
        preferredSpread: savedSpreadPreference(), busy: false, epoch: 0,
        cache: new Map(), pending: new Map(), visiblePages: [], layout: null, loadTask: null
    };
    let pdfjsPromise;

    const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
    const isSpread = () => state.preferredSpread && window.innerWidth > 640;
    const spreadStart = (page) => isSpread() ? Math.floor((page - 1) / 2) * 2 + 1 : page;
    const step = () => isSpread() ? 2 : 1;

    function status(message) {
        if (!message) {
            ui.error.hidden = true;
            ui.error.textContent = '';
            return;
        }
        ui.error.textContent = message;
        ui.error.hidden = false;
    }

    function setLoading(show, message = strings.preparingpages) {
        ui.loading.hidden = !show;
        ui.loadingMessage.textContent = message;
    }

    async function pdfjs() {
        if (!pdfjsPromise) {
            pdfjsPromise = import(`${PDFJS_BASE}pdf.mjs`).then((mod) => {
                mod.GlobalWorkerOptions.workerSrc = `${PDFJS_BASE}pdf.worker.mjs`;
                return mod;
            }).catch((error) => {
                pdfjsPromise = null;
                throw new Error(strings.pdfjsloaderror);
            });
        }
        return pdfjsPromise;
    }

    function formatDocumentTitle(filename) {
        return String(filename || '')
            .replace(/\.pdf$/i, '')
            .replace(/[_+.~\u2010-\u2015-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim() || strings.pdfcontent;
    }

    async function loadPDF(source) {
        if (!source || state.busy) return;
        const epoch = ++state.epoch;
        state.busy = true;
        status(null);
        setLoading(true, strings.openingpdf);
        try {
            let options;
            let title;
            if (typeof source === 'string') {
                // Relative URLs resolve beside index.html; absolute HTTPS URLs also work when CORS allows.
                const address = new URL(source, window.location.href);
                if (address.protocol !== 'https:' && address.protocol !== 'http:') {
                    throw new Error(strings.pdfurlerror);
                }
                options = {
                    url: address.href,
                    withCredentials: address.origin === window.location.origin,
                    isEvalSupported: false,
                    useSystemFonts: true
                };
                const filename = address.pathname.split('/').pop() || strings.pdfcontent;
                try {
                    title = decodeURIComponent(filename);
                } catch (_) {
                    title = filename;
                }
                title = title.replace(/\.pdf$/i, '') || strings.pdfcontent;
            } else {
                const header = await source.slice(0, 1024).text();
                if (!header.includes('%PDF-')) {
                    throw new Error(strings.pdfinvalid);
                }
                options = {
                    data: new Uint8Array(await source.arrayBuffer()),
                    isEvalSupported: false,
                    useSystemFonts: true
                };
                title = (source.name || strings.pdfcontent).replace(/\.pdf$/i, '');
            }
            const library = await pdfjs();
            if (epoch !== state.epoch) return;
            const task = library.getDocument(options);
            state.loadTask = task;
            task.onPassword = (submit) => {
                const password = window.prompt(strings.pdfpasswordprompt);
                if (password === null) {
                    void task.destroy();
                    return;
                }
                submit(password);
            };
            const pdf = await task.promise;
            if (epoch !== state.epoch) {
                await pdf.destroy();
                return;
            }
            if (state.pdf) await state.pdf.destroy();
            state.pdf = pdf;
            state.file = typeof source === 'string' ? null : source;
            state.page = 1;
            state.total = pdf.numPages;
            state.cache.clear();
            state.pending.clear();
            state.visiblePages = [];
            state.layout = null;
            ui.title.textContent = formatDocumentTitle(title);
            ui.details.textContent = `${strings.pdfdetailslabel} · ${pdf.numPages} ${pdf.numPages === 1 ? strings.page : strings.pages}`;
            await renderBook(epoch);
            trackPage();
            status(null);
        } catch (error) {
            if (epoch === state.epoch) {
                ui.details.textContent = strings.pdfopenfailed;
                status(translated('pdfloaderror', error instanceof Error ? error.message : strings.unknownerror));
            }
        } finally {
            if (epoch === state.epoch) {
                state.busy = false;
                setLoading(false);
                refreshControls();
            }
        }
    }

    function trimCache() {
        while (state.cache.size > RENDERED_PAGE_LIMIT) state.cache.delete(state.cache.keys().next().value);
    }

    async function renderPdfImage(pageNumber, epoch) {
        if (state.cache.has(pageNumber)) {
            const src = state.cache.get(pageNumber);
            state.cache.delete(pageNumber);
            state.cache.set(pageNumber, src);
            return src;
        }
        if (state.pending.has(pageNumber)) return state.pending.get(pageNumber);
        const currentPdf = state.pdf;
        const promise = (async () => {
            const page = await currentPdf.getPage(pageNumber);
            const initial = page.getViewport({scale: 1});
            const scale = Math.min(3, 1250 / initial.width, 1850 / initial.height);
            const viewport = page.getViewport({scale});
            const canvas = document.createElement('canvas');
            canvas.width = Math.ceil(viewport.width);
            canvas.height = Math.ceil(viewport.height);
            const context = canvas.getContext('2d', {alpha: false});
            if (!context) throw new Error(strings.canvaserror);
            await page.render({canvasContext: context, viewport}).promise;
            const rendered = {
                src: canvas.toDataURL('image/png'),
                width: initial.width,
                height: initial.height
            };
            canvas.width = 0;
            canvas.height = 0;
            page.cleanup();
            if (epoch === state.epoch && state.pdf === currentPdf) {
                state.cache.set(pageNumber, rendered);
                trimCache();
            }
            return rendered;
        })();
        state.pending.set(pageNumber, promise);
        try {
            return await promise;
        } finally {
            if (state.pending.get(pageNumber) === promise) state.pending.delete(pageNumber);
        }
    }

    function blankPage(side) {
        const element = document.createElement('div');
        element.className = `page page-${side}`;
        element.setAttribute('aria-hidden', 'true');
        return element;
    }

    async function pdfPage(number, side, epoch) {
        const element = document.createElement('article');
        element.className = `page page-${side} pdf-page`;
        element.setAttribute('aria-label', translated('pagenumber', number));
        const content = document.createElement('div');
        content.className = 'page-contents';
        const image = document.createElement('img');
        image.alt = translated('pdfpagealt', number);
        image.decoding = 'async';
        const rendered = await renderPdfImage(number, epoch);
        image.src = rendered.src;
        content.append(image);
        element.append(content);
        return {element, width: rendered.width, height: rendered.height};
    }


    function fitBook() {
        const pages = state.visiblePages;
        if (!pages.length) {
            ui.viewport.style.width = '0';
            ui.viewport.style.height = '0';
            state.layout = null;
            return;
        }

        // PDF.js getViewport({scale: 1}) already accounts for page rotation.
        // All pages on the spread share a scale, but keep their individual dimensions.
        const stageStyle = window.getComputedStyle(ui.stage);
        const horizontalPadding = parseFloat(stageStyle.paddingLeft) + parseFloat(stageStyle.paddingRight);
        const verticalPadding = parseFloat(stageStyle.paddingTop) + parseFloat(stageStyle.paddingBottom);
        const availableWidth = Math.max(1, ui.stage.clientWidth - horizontalPadding);
        const availableHeight = Math.max(1, ui.stage.clientHeight - verticalPadding);
        const documentWidth = pages.reduce((sum, page) => sum + page.width, 0);
        const documentHeight = Math.max(...pages.map((page) => page.height));
        const scale = Math.min(availableWidth / documentWidth, availableHeight / documentHeight);
        const width = documentWidth * scale;
        const height = documentHeight * scale;

        ui.viewport.style.width = `${width}px`;
        ui.viewport.style.height = `${height}px`;
        for (const page of pages) {
            const pageWidth = page.width * scale;
            const pageHeight = page.height * scale;
            page.element.style.width = `${pageWidth}px`;
            page.element.style.height = `${pageHeight}px`;
            page.element.style.flex = `0 0 ${pageWidth}px`;
        }

        const leftWidth = pages[0].width * scale;
        const seamHeight = Math.min(...pages.map((page) => page.height)) * scale;
        ui.book.style.setProperty('--spine-offset', `${leftWidth}px`);
        ui.book.style.setProperty('--spine-height', `${seamHeight}px`);
        state.layout = {
            width, height,
            pages: pages.map((page) => ({width: page.width * scale, height: page.height * scale}))
        };
    }

    async function renderBook(epoch = state.epoch) {
        if (!state.pdf || state.total < 1) {
            ui.book.replaceChildren();
            ui.book.classList.toggle('single', !isSpread());
            state.visiblePages = [];
            fitBook();
            refreshControls();
            return;
        }
        const spread = isSpread();
        const start = spreadStart(state.page);
        const numbers = spread ? [start, start + 1] : [state.page];
        const pages = await Promise.all(numbers
            .filter((number) => number <= state.total)
            .map((number, index) => pdfPage(number, spread ? (index ? 'right' : 'left') : 'single', epoch)));
        if (epoch !== state.epoch) return;
        if (spread && pages.length === 1) {
            // Preserve the trailing blank leaf without imposing a fixed paper format.
            const {width, height} = pages[0];
            pages.push({element: blankPage('right'), width, height});
        }
        ui.book.replaceChildren(...pages.map((page) => page.element));
        ui.book.classList.toggle('single', !spread);
        state.visiblePages = pages;
        fitBook();
        refreshControls();
    }

    function refreshControls() {
        const hasDocument = Boolean(state.pdf && state.total > 0);
        ui.number.value = String(hasDocument ? state.page : 1);
        ui.number.max = String(Math.max(1, state.total));
        ui.number.disabled = !hasDocument || state.busy;
        ui.count.textContent = String(state.total);
        ui.progress.min = '1';
        ui.progress.max = String(Math.max(1, state.total));
        ui.progress.value = String(hasDocument ? state.page : 1);
        ui.progress.disabled = !hasDocument || state.busy;
        const percentage = hasDocument ? Math.round(state.page / state.total * 100) : 0;
        ui.progressLabel.textContent = `${percentage}%`;
        ui.progress.style.setProperty('--completion', `${percentage}%`);
        ui.zoom.textContent = `${Math.round(state.zoom * 100)}%`;
        ui.viewport.style.transform = `scale(${state.zoom})`;
        const spread = isSpread();
        ui.mode.setAttribute('aria-pressed', String(spread));
        const modeLabel = spread ? strings.singlepage : strings.doublepage;
        ui.mode.setAttribute('title', modeLabel);
        ui.mode.setAttribute('aria-label', modeLabel);
        ui.mode.querySelector('.view-mode-spread').hidden = !spread;
        ui.mode.querySelector('.view-mode-single').hidden = spread;
        ui.mode.disabled = state.busy || window.innerWidth <= 640;
        const atStart = !hasDocument || spreadStart(state.page) <= 1;
        const atEnd = !hasDocument || spreadStart(state.page) + step() > state.total;
        for (const action of ['first', 'prev']) {
            $('[data-action="' + action + '"]').disabled = atStart || state.busy;
        }
        for (const action of ['last', 'next']) {
            $('[data-action="' + action + '"]').disabled = atEnd || state.busy;
        }
    }

    function makeFlipLeaf(direction) {
        const side = direction === 'next' ? '.page-right' : '.page-left';
        const original = ui.book.querySelector(side) || ui.book.querySelector('.page');
        if (!original) return null;
        const leaf = document.createElement('div');
        leaf.className = `flip-leaf turn-${direction}`;
        const clonedPage = original.cloneNode(true);
        clonedPage.className = 'page';
        clonedPage.setAttribute('aria-hidden', 'true');
        leaf.append(clonedPage);
        leaf.style.left = `${original.offsetLeft}px`;
        leaf.style.top = `${original.offsetTop}px`;
        leaf.style.width = `${original.offsetWidth}px`;
        leaf.style.height = `${original.offsetHeight}px`;
        return leaf;
    }

    async function goToPage(newPage, animate = true) {
        if (state.busy || !state.pdf || state.total < 1) return;
        const target = clamp(Math.round(Number(newPage) || 1), 1, state.total);
        if (spreadStart(target) === spreadStart(state.page)) {
            state.page = target;
            refreshControls();
            return;
        }
        const direction = target > state.page ? 'next' : 'prev';
        const previousLayout = state.layout;
        const leaf = animate && !reduceMotion.matches ? makeFlipLeaf(direction) : null;
        state.busy = true;
        state.page = target;
        refreshControls();
        try {
            await renderBook(state.epoch);
            trackPage();
            // If the next sheet has a different aspect ratio, don't fold a differently
            // sized page over it; render the correctly sized page immediately.
            const compatible = previousLayout && state.layout &&
                Math.abs(previousLayout.width - state.layout.width) < 2 &&
                Math.abs(previousLayout.height - state.layout.height) < 2 &&
                previousLayout.pages.length === state.layout.pages.length &&
                previousLayout.pages.every((page, index) =>
                    Math.abs(page.width - state.layout.pages[index].width) < 2 &&
                    Math.abs(page.height - state.layout.pages[index].height) < 2);
            if (leaf && compatible) {
                ui.book.append(leaf);
                await new Promise((resolve) => {
                    let done = false;
                    const finish = () => {
                        if (done) return;
                        done = true;
                        resolve();
                    };
                    leaf.addEventListener('animationend', finish, {once: true});
                    window.setTimeout(finish, 550);
                });
                leaf.remove();
            }
        } catch (error) {
            status(translated('pagerendererror', error instanceof Error ? error.message : strings.unknownerror));
        } finally {
            state.busy = false;
            refreshControls();
        }
    }

    function changeZoom(delta) {
        state.zoom = clamp(Math.round((state.zoom + delta) * 10) / 10, .7, 1.5);
        refreshControls();
    }

    function refreshFullscreenControls() {
        const active = document.fullscreenElement === ui.reader ||
            document.webkitFullscreenElement === ui.reader;
        ui.fullscreen.querySelector('.fullscreen-enter').hidden = active;
        ui.fullscreen.querySelector('.fullscreen-exit').hidden = !active;
        const label = active ? strings.exitfullscreen : strings.fullscreen;
        ui.fullscreen.setAttribute('aria-label', label);
        ui.fullscreen.setAttribute('title', label);
        ui.fullscreen.setAttribute('aria-pressed', String(active));
    }

    async function toggleFullscreen() {
        try {
            if (document.fullscreenElement || document.webkitFullscreenElement) {
                const exit = document.exitFullscreen || document.webkitExitFullscreen;
                if (!exit) throw new Error(strings.fullscreenexitunsupported);
                await exit.call(document);
            } else {
                // Called synchronously from the click handler, preserving the user gesture.
                const enter = ui.reader.requestFullscreen || ui.reader.webkitRequestFullscreen;
                if (!enter) throw new Error(strings.fullscreenenterunsupported);
                await enter.call(ui.reader);
            }
            status(null);
        } catch (error) {
            status(translated('fullscreenerror', error instanceof Error ? error.message : strings.operationdenied));
        } finally {
            refreshFullscreenControls();
        }
    }

    const closeModal = () => {
        void send(false);
        overlay.hidden = true;
        const consent = root.querySelector('[data-region="consent"]');
        if (consent) {
            consent.checked = false;
        }
        if (document.fullscreenElement || document.webkitFullscreenElement) {
            const exit = document.exitFullscreen || document.webkitExitFullscreen;
            if (exit) {
                void exit.call(document);
            }
        }
        if (previousFocus) {
            previousFocus.focus();
        }
    };

    async function runAction(action) {
        switch (action) {
            case 'open':
                break;
            case 'close':
                closeModal();
                break;
            case 'prev':
                await goToPage(spreadStart(state.page) - step());
                break;
            case 'next':
                await goToPage(spreadStart(state.page) + step());
                break;
            case 'first':
                await goToPage(1);
                break;
            case 'last':
                await goToPage(isSpread() ? spreadStart(state.total) : state.total);
                break;
            case 'zoom-out':
                changeZoom(-.1);
                break;
            case 'zoom-in':
                changeZoom(.1);
                break;
            case 'fullscreen':
                await toggleFullscreen();
                break;
            case 'mode':
                if (window.innerWidth > 640) {
                    state.preferredSpread = !state.preferredSpread;
                    try {
                        window.localStorage.setItem(MODE_STORAGE_KEY, state.preferredSpread ? 'double' : 'single');
                    } catch (_) {
                        // Reading remains available when storage is disabled.
                    }
                    await renderBook();
                }
                break;
            default:
                break;
        }
    }

    root.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-action]');
        if (button && !button.disabled) void runAction(button.dataset.action);
    });
    document.addEventListener('fullscreenchange', refreshFullscreenControls);
    document.addEventListener('webkitfullscreenchange', refreshFullscreenControls);

    ui.number.addEventListener('change', () => {
        void goToPage(ui.number.value, false);
    });
    ui.number.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            ui.number.blur();
            void goToPage(ui.number.value, false);
        }
    });
    ui.progress.addEventListener('change', () => {
        void goToPage(ui.progress.value, false);
    });
    document.addEventListener('keydown', (event) => {
        if (overlay.hidden) {
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            closeModal();
            return;
        }
        if (event.altKey || event.ctrlKey || event.metaKey || /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) return;
        if (event.key === 'ArrowRight' || event.key === 'PageDown') {
            event.preventDefault();
            void runAction('next');
        } else if (event.key === 'ArrowLeft' || event.key === 'PageUp') {
            event.preventDefault();
            void runAction('prev');
        } else if (event.key === 'Home') {
            event.preventDefault();
            void runAction('first');
        } else if (event.key === 'End') {
            event.preventDefault();
            void runAction('last');
        }
    });

    let lastMobile = window.innerWidth <= 640;
    window.addEventListener('resize', () => {
        const mobile = window.innerWidth <= 640;
        if (mobile !== lastMobile) {
            lastMobile = mobile;
            if (!state.busy) void renderBook();
        } else {
            fitBook();
            refreshControls();
        }
    });
    if (typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(() => fitBook()).observe(ui.stage);
    }
    refreshControls();
    refreshFullscreenControls();

    // A gallery thumbnail is rendered once and persisted to Moodle's private file storage.
    const queue = [];
    const queued = new Set();
    let activeThumbs = 0;
    const persistThumbnail = async (card) => {
        const library = await pdfjs();
        const task = library.getDocument({url: card.dataset.pdfUrl,
            withCredentials: true, isEvalSupported: false});
        const doc = await task.promise;
        try {
            const page = await doc.getPage(1);
            const pageSize = page.getViewport({scale: 1});
            const ratio = Math.min(300 / pageSize.width, 400 / pageSize.height);
            const viewport = page.getViewport({scale: ratio});
            const canvas = document.createElement('canvas');
            canvas.width = Math.ceil(viewport.width);
            canvas.height = Math.ceil(viewport.height);
            await page.render({canvasContext: canvas.getContext('2d', {alpha: false}), viewport}).promise;
            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', .77));
            canvas.width = 0;
            canvas.height = 0;
            page.cleanup();
            if (!blob) {
                throw new Error(strings.thumbcreateerror);
            }
            const form = new FormData();
            form.append('id', String(cfg.cmid));
            form.append('fileid', card.dataset.pdfId);
            form.append('sesskey', cfg.sesskey);
            form.append('image', blob, 'cover.jpg');
            const response = await fetch(cfg.thumbendpoint, {method: 'POST',
                credentials: 'same-origin', body: form});
            if (!response.ok) {
                throw new Error(strings.thumbuploaderror);
            }
            const data = await response.json();
            if (data.url) {
                const image = document.createElement('img');
                image.src = data.url;
                image.alt = '';
                image.loading = 'lazy';
                card.querySelector('.sf-gallery-cover').replaceChildren(image);
            }
        } finally {
            await doc.destroy();
        }
    };
    const pump = () => {
        while (activeThumbs < 2 && queue.length) {
            const card = queue.shift();
            activeThumbs++;
            void persistThumbnail(card).catch((error) => {
                // A failure leaves the placeholder in place and does not block other PDFs.
                window.console.warn('Super Flipbook thumbnail:', error);
            }).finally(() => {
                activeThumbs--;
                pump();
            });
        }
    };
    const enqueue = (card) => {
        if (card.querySelector('img') || queued.has(card.dataset.pdfId)) {
            return;
        }
        queued.add(card.dataset.pdfId);
        queue.push(card);
        pump();
    };
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    observer.unobserve(entry.target);
                    enqueue(entry.target);
                }
            }
        }, {rootMargin: '250px'});
        cards.forEach((card) => observer.observe(card));
    } else {
        cards.forEach(enqueue);
    }

    root.querySelector('[data-region="gallery"]').addEventListener('click', (event) => {
        const card = event.target.closest('[data-pdf-id]');
        if (!card) {
            return;
        }
        activeCard = card;
        previousFocus = card;
        lastTrackedPage = 0;
        lastHeartbeat = Date.now();
        overlay.hidden = false;
        const consentPanel = root.querySelector('.sf-acknowledge');
        if (consentPanel) {
            consentPanel.hidden = !isTracked();
        }
        root.querySelector('[data-action="close"]').focus();
        void loadPDF(card.dataset.pdfUrl);
    });
    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            closeModal();
        }
    });
    const acknowledge = root.querySelector('[data-action="acknowledge"]');
    acknowledge?.addEventListener('click', () => {
        const check = root.querySelector('[data-region="consent"]');
        if (check?.checked) {
            void send(true).then((result) => {
                if (result?.acknowledged) {
                    check.disabled = true;
                    acknowledge.disabled = true;
                }
            });
        }
    });
    window.addEventListener('pagehide', () => {
        void send(false);
    });
    document.addEventListener('visibilitychange', () => {
        lastHeartbeat = Date.now();
    });
    window.setInterval(() => {
        if (!overlay.hidden && document.visibilityState === 'visible' && document.hasFocus()) {
            void send(false);
        }
    }, 15000);
    };
    return {init};
});
