/* Fólio — static, framework-independent PDF flipbook. MIT licensed. */
(() => {
    'use strict';

    const PDFJS_VERSION = '4.10.38';
    const PDFJS_BASE = `https://cdn.jsdelivr.net/npm/pdfjs-dist@${PDFJS_VERSION}/build/`;
    const RENDERED_PAGE_LIMIT = 10;
    const MODE_STORAGE_KEY = 'superflipbook.folio-pdf.mode';
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const $ = (selector) => document.querySelector(selector);
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

    function setLoading(show, message = 'Preparando as páginas...') {
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
                throw new Error('Não foi possível carregar o PDF.js. Verifique sua conexão com a internet.');
            });
        }
        return pdfjsPromise;
    }

    function formatDocumentTitle(filename) {
        return String(filename || '')
            .replace(/\.pdf$/i, '')
            .replace(/[_+.~\u2010-\u2015-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim() || 'Documento PDF';
    }

    async function loadPDF(source) {
        if (!source || state.busy) return;
        const epoch = ++state.epoch;
        state.busy = true;
        status(null);
        setLoading(true, 'Abrindo arquivo PDF...');
        try {
            let options;
            let title;
            if (typeof source === 'string') {
                // Relative URLs resolve beside index.html; absolute HTTPS URLs also work when CORS allows.
                const address = new URL(source, window.location.href);
                if (address.protocol !== 'https:' && address.protocol !== 'http:') {
                    throw new Error('Use uma URL HTTP ou HTTPS para o arquivo PDF.');
                }
                options = {
                    url: address.href,
                    withCredentials: address.origin === window.location.origin,
                    isEvalSupported: false,
                    useSystemFonts: true
                };
                const filename = address.pathname.split('/').pop() || 'Documento PDF';
                try {
                    title = decodeURIComponent(filename);
                } catch (_) {
                    title = filename;
                }
                title = title.replace(/\.pdf$/i, '') || 'Documento PDF';
            } else {
                const header = await source.slice(0, 1024).text();
                if (!header.includes('%PDF-')) {
                    throw new Error('Este arquivo não parece ser um PDF válido.');
                }
                options = {
                    data: new Uint8Array(await source.arrayBuffer()),
                    isEvalSupported: false,
                    useSystemFonts: true
                };
                title = (source.name || 'Documento PDF').replace(/\.pdf$/i, '');
            }
            const library = await pdfjs();
            if (epoch !== state.epoch) return;
            const task = library.getDocument(options);
            state.loadTask = task;
            task.onPassword = (submit) => {
                const password = window.prompt('Este PDF é protegido. Digite a senha para abri-lo:');
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
            ui.details.textContent = `ARQUIVO PDF · ${pdf.numPages} ${pdf.numPages === 1 ? 'PÁGINA' : 'PÁGINAS'}`;
            await renderBook(epoch);
            status(null);
        } catch (error) {
            if (epoch === state.epoch) {
                ui.details.textContent = 'NÃO FOI POSSÍVEL ABRIR O DOCUMENTO';
                status('Erro ao carregar PDF: ' + (error instanceof Error ? error.message : 'Erro desconhecido.') +
                    ' Verifique o endereço, as permissões de acesso e o CORS, se o PDF estiver em outro domínio.');
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
            if (!context) throw new Error('O navegador não conseguiu criar uma área de desenho.');
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
        element.setAttribute('aria-label', `Página ${number}`);
        const content = document.createElement('div');
        content.className = 'page-contents';
        const image = document.createElement('img');
        image.alt = `Página ${number} do documento`;
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
        const modeLabel = spread ? 'Exibir uma página' : 'Exibir duas páginas';
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
            status('Erro ao renderizar a página. ' + (error instanceof Error ? error.message : ''));
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
        const label = active ? 'Sair da tela cheia' : 'Tela cheia';
        ui.fullscreen.setAttribute('aria-label', label);
        ui.fullscreen.setAttribute('title', label);
        ui.fullscreen.setAttribute('aria-pressed', String(active));
    }

    async function toggleFullscreen() {
        try {
            if (document.fullscreenElement || document.webkitFullscreenElement) {
                const exit = document.exitFullscreen || document.webkitExitFullscreen;
                if (!exit) throw new Error('O navegador não suporta sair da tela cheia.');
                await exit.call(document);
            } else {
                // Called synchronously from the click handler, preserving the user gesture.
                const enter = ui.reader.requestFullscreen || ui.reader.webkitRequestFullscreen;
                if (!enter) throw new Error('O navegador não suporta a API de tela cheia.');
                await enter.call(ui.reader);
            }
            status(null);
        } catch (error) {
            status('Falha ao alternar tela cheia: ' +
                (error instanceof Error ? error.message : 'Operação não permitida.') +
                ' Se estiver em iframe, habilite allowfullscreen no elemento iframe.');
        } finally {
            refreshFullscreenControls();
        }
    }

    async function runAction(action) {
        switch (action) {
            case 'open':
                ui.input.click();
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

    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-action]');
        if (button && !button.disabled) void runAction(button.dataset.action);
    });
    document.addEventListener('fullscreenchange', refreshFullscreenControls);
    document.addEventListener('webkitfullscreenchange', refreshFullscreenControls);
    ui.input.addEventListener('change', async () => {
        const [file] = ui.input.files || [];
        ui.input.value = '';
        await loadPDF(file);
    });
    ui.number.addEventListener('change', () => {
        void goToPage(ui.number.value, false);
    });
    ui.number.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            ui.number.blur();
            void goToPage(ui.number.value, false);
        }
    });
    ui.progress.addEventListener('input', () => {
        void goToPage(ui.progress.value, false);
    });
    document.addEventListener('keydown', (event) => {
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

    // Keep file drag-and-drop support without rendering an overlay.
    document.addEventListener('dragover', (event) => {
        if (!event.dataTransfer?.types.includes('Files')) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
    });
    document.addEventListener('drop', (event) => {
        if (!event.dataTransfer?.types.includes('Files')) return;
        event.preventDefault();
        const file = [...event.dataTransfer.files].find((candidate) =>
            /\.pdf$/i.test(candidate.name) || candidate.type === 'application/pdf');
        if (file) void loadPDF(file);
        else status('Arraste um arquivo com extensão .pdf.');
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
    const pdfURL = new URLSearchParams(window.location.search).get('file');
    if (pdfURL && pdfURL.trim()) {
        void loadPDF(pdfURL.trim());
    } else {
        ui.details.textContent = 'NENHUM PDF INFORMADO';
        status('Informe o documento na URL: index.html?file=arquivo.pdf');
    }
})();
