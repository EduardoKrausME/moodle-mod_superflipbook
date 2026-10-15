/* Fólio — static, framework-independent PDF flipbook. MIT licensed. */
(() => {
    'use strict';

    const PDFJS_VERSION = '4.10.38';
    const PDFJS_BASE = `https://cdn.jsdelivr.net/npm/pdfjs-dist@${PDFJS_VERSION}/build/`;
    const RENDERED_PAGE_LIMIT = 10;
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
        progress: $('#page-progress'),
        progressLabel: $('#progress-label'),
        number: $('#page-number'),
        count: $('#page-count'),
        zoom: $('#zoom-label'),
        error: $('#reader-error'),
        loading: $('#reader-loading'),
        loadingMessage: $('#loading-message'),
        drop: $('#drop-zone'),
        reader: $('.reader-section')
    };

    const state = {
        pdf: null, file: null, kind: 'demo', page: 1, total: 8, zoom: 1,
        preferredSpread: window.innerWidth > 640, busy: false, epoch: 0,
        cache: new Map(), pending: new Map(), loadTask: null
    };
    let pdfjsPromise;
    const demoPages = [
        {
            kind: 'cover',
            html: `<div class="page-contents"><div class="page-small">FÓLIO JOURNAL / VOL. 01</div><div><h2 class="cover-heading">A arte de<br>desacelerar.</h2><div class="cover-caption">Um pequeno guia para encontrar mais presença no cotidiano.</div></div><div class="cover-art"><span class="cover-ring"></span></div><div class="cover-bottom"><span>TEMPO PARA O QUE IMPORTA</span><span>2026</span></div></div>`
        },
        {
            kind: 'inside',
            html: `<div class="page-contents demo-inside"><div class="overline">01 / UM OUTRO OLHAR</div><h2>Um convite para estar presente.</h2><p>Em um mundo que pede velocidade, escolher a calma é uma forma de reencontrar o essencial.</p><p>Observe os detalhes. Respire fundo. Nem tudo precisa acontecer agora: algumas descobertas só aparecem quando damos tempo ao tempo.</p><blockquote>O tempo também precisa de espaço.</blockquote><div class="page-footer"><span>FÓLIO JOURNAL</span><span>02</span></div></div>`
        },
        {
            kind: 'inside',
            html: `<div class="page-contents demo-inside"><div class="overline">02 / PEQUENOS RITUAIS</div><h2>Comece onde você está.</h2><p>Não é necessário mudar tudo para perceber uma diferença. Às vezes, basta reservar alguns minutos para olhar pela janela ou terminar uma xícara de café sem pressa.</p><p>Quando o dia parece curto demais, a atenção transforma o que já existe.</p><blockquote>Estar presente é também saber parar.</blockquote><div class="page-footer"><span>FÓLIO JOURNAL</span><span>03</span></div></div>`
        },
        {
            kind: 'inside',
            html: `<div class="page-contents demo-inside"><div class="overline">03 / PAUSAS NECESSÁRIAS</div><h2>O valor do intervalo.</h2><p>Uma pausa não é uma interrupção da vida. Ela faz parte do movimento, assim como o silêncio faz parte da música.</p><ul><li>Respire por alguns instantes.</li><li>Repare na luz que muda.</li><li>Deixe uma tarefa de cada vez.</li></ul><p>Faça do intervalo uma possibilidade, não uma cobrança.</p><div class="page-footer"><span>FÓLIO JOURNAL</span><span>04</span></div></div>`
        },
        {
            kind: 'inside',
            html: `<div class="page-contents demo-inside"><div class="overline">04 / OLHAR ATENTO</div><h2>Redescobrir o cotidiano.</h2><p>Há beleza nos gestos comuns: abrir um livro, caminhar sem destino, escutar uma conversa até o fim.</p><blockquote>A vida acontece no detalhe.</blockquote><p>A desaceleração não exige distância do mundo, mas outra maneira de habitá-lo.</p><div class="page-footer"><span>FÓLIO JOURNAL</span><span>05</span></div></div>`
        },
        {
            kind: 'inside',
            html: `<div class="page-contents demo-inside"><div class="overline">05 / NOVOS HÁBITOS</div><h2>Leveza é prática.</h2><p>Experimente escolher um pequeno momento do dia para fazer apenas uma coisa, sem alternar entre telas, tarefas e notificações.</p><p>O hábito nasce da repetição possível, não da mudança perfeita. Com o tempo, a tranquilidade encontra lugar na rotina.</p><div class="page-footer"><span>FÓLIO JOURNAL</span><span>06</span></div></div>`
        },
        {
            kind: 'inside',
            html: `<div class="page-contents demo-inside"><div class="overline">06 / CONTINUAR</div><h2>Uma página de cada vez.</h2><p>O melhor ritmo é aquele que permite perceber o caminho. Há coisas que só se revelam quando você decide não apressá-las.</p><blockquote>Leia devagar. Viva com atenção.</blockquote><p>E quando for preciso, volte ao começo: sempre há algo novo a descobrir.</p><div class="page-footer"><span>FÓLIO JOURNAL</span><span>07</span></div></div>`
        },
        {
            kind: 'cover',
            html: `<div class="page-contents"><div class="page-small">FÓLIO JOURNAL / FIM DA EDIÇÃO</div><div><h2 class="cover-heading">Obrigado<br>pela leitura.</h2><div class="cover-caption">A próxima boa história começa quando você abre um novo livro.</div></div><div class="cover-art"><span class="cover-ring"></span></div><div class="cover-bottom"><span>UMA PÁGINA DE CADA VEZ</span><span>08</span></div></div>`
        }
    ];

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

    async function loadPDF(file) {
        if (!file) return;
        if (state.busy) return;
        const epoch = ++state.epoch;
        state.busy = true;
        status(null);
        setLoading(true, 'Abrindo arquivo PDF...');
        try {
            const header = await file.slice(0, 1024).text();
            if (!header.includes('%PDF-')) throw new Error('Este arquivo não parece ser um PDF válido.');
            const [library, bytes] = await Promise.all([pdfjs(), file.arrayBuffer()]);
            if (epoch !== state.epoch) return;
            const task = library.getDocument({
                data: new Uint8Array(bytes),
                isEvalSupported: false,
                useSystemFonts: true
            });
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
            state.file = file;
            state.kind = 'pdf';
            state.page = 1;
            state.total = pdf.numPages;
            state.cache.clear();
            state.pending.clear();
            ui.title.textContent = file.name.replace(/\.pdf$/i, '') || 'Documento PDF';
            ui.details.textContent = `ARQUIVO LOCAL · ${pdf.numPages} ${pdf.numPages === 1 ? 'PÁGINA' : 'PÁGINAS'}`;
            await renderBook(epoch);
            status(null);
        } catch (error) {
            if (epoch === state.epoch) status(error instanceof Error ? error.message : 'Não foi possível abrir o PDF.');
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
            const src = canvas.toDataURL('image/png');
            canvas.width = 0;
            canvas.height = 0;
            page.cleanup();
            if (epoch === state.epoch && state.pdf === currentPdf) {
                state.cache.set(pageNumber, src);
                trimCache();
            }
            return src;
        })();
        state.pending.set(pageNumber, promise);
        try {
            return await promise;
        } finally {
            if (state.pending.get(pageNumber) === promise) state.pending.delete(pageNumber);
        }
    }

    function demoPage(number, side) {
        const model = demoPages[number - 1];
        if (!model) return blankPage(side);
        const element = document.createElement('article');
        element.className = `page page-${side} demo-${model.kind}`;
        element.setAttribute('aria-label', `Página ${number}`);
        element.innerHTML = model.html; // Static, trusted demo markup authored in this file.
        return element;
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
        image.src = await renderPdfImage(number, epoch);
        content.append(image);
        element.append(content);
        return element;
    }

    async function renderBook(epoch = state.epoch) {
        const start = spreadStart(state.page);
        const numbers = isSpread() ? [start, start + 1] : [state.page];
        const pages = await Promise.all(numbers.map(async (number, index) => {
            const side = isSpread() ? (index ? 'right' : 'left') : 'single';
            if (number > state.total) return blankPage(side);
            return state.kind === 'pdf' ? pdfPage(number, side, epoch) : demoPage(number, side);
        }));
        if (epoch !== state.epoch) return;
        ui.book.replaceChildren(...pages);
        ui.book.classList.toggle('single', !isSpread());
        refreshControls();
    }

    function refreshControls() {
        ui.number.value = String(state.page);
        ui.number.max = String(state.total);
        ui.count.textContent = String(state.total);
        ui.progress.min = '1';
        ui.progress.max = String(Math.max(1, state.total));
        ui.progress.value = String(state.page);
        const percentage = Math.round(state.page / Math.max(1, state.total) * 100);
        ui.progressLabel.textContent = `${percentage}%`;
        ui.progress.style.setProperty('--completion', `${percentage}%`);
        ui.zoom.textContent = `${Math.round(state.zoom * 100)}%`;
        ui.viewport.style.transform = `scale(${state.zoom})`;
        ui.mode.setAttribute('aria-pressed', String(isSpread()));
        ui.mode.setAttribute('title', isSpread() ? 'Exibir uma página' : 'Exibir duas páginas');
        ui.mode.disabled = window.innerWidth <= 640;
        const atStart = spreadStart(state.page) <= 1;
        const atEnd = spreadStart(state.page) + step() > state.total;
        for (const action of ['first', 'prev']) {
            $(`[data-action="${action}"]`).disabled = atStart || state.busy;
        }
        for (const action of ['last', 'next']) {
            $(`[data-action="${action}"]`).disabled = atEnd || state.busy;
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
        return leaf;
    }

    async function goToPage(newPage, animate = true) {
        if (state.busy) return;
        const target = clamp(Math.round(Number(newPage) || 1), 1, state.total);
        if (spreadStart(target) === spreadStart(state.page)) {
            state.page = target;
            refreshControls();
            return;
        }
        const direction = target > state.page ? 'next' : 'prev';
        const leaf = animate && !reduceMotion.matches ? makeFlipLeaf(direction) : null;
        state.busy = true;
        state.page = target;
        refreshControls();
        try {
            await renderBook(state.epoch);
            if (leaf) {
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

    async function startDemo() {
        if (state.busy) return;
        state.epoch++;
        state.busy = true;
        setLoading(false);
        status(null);
        try {
            if (state.loadTask && !state.pdf) await state.loadTask.destroy();
            if (state.pdf) await state.pdf.destroy();
        } catch (_) { /* A partially loaded document may already be closed. */
        }
        state.pdf = null;
        state.file = null;
        state.loadTask = null;
        state.kind = 'demo';
        state.page = 1;
        state.total = demoPages.length;
        state.cache.clear();
        state.pending.clear();
        state.zoom = 1;
        ui.title.textContent = 'A arte de desacelerar';
        ui.details.textContent = 'EDIÇÃO DEMONSTRATIVA · 8 PÁGINAS';
        await renderBook();
        state.busy = false;
        refreshControls();
    }

    function changeZoom(delta) {
        state.zoom = clamp(Math.round((state.zoom + delta) * 10) / 10, .7, 1.5);
        refreshControls();
    }

    async function toggleFullscreen() {
        try {
            if (document.fullscreenElement === ui.reader) await document.exitFullscreen();
            else await ui.reader.requestFullscreen();
        } catch (_) {
            status('O navegador não permitiu abrir em tela cheia.');
        }
    }

    async function runAction(action) {
        switch (action) {
            case 'open':
                ui.input.click();
                break;
            case 'demo':
                await startDemo();
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
                    await renderBook();
                }
                break;
            default:
                break;
        }
    }

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action]');
        if (button && !button.disabled) void runAction(button.dataset.action);
    });
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

    let dragDepth = 0;
    document.addEventListener('dragenter', (event) => {
        if (!event.dataTransfer?.types.includes('Files')) return;
        event.preventDefault();
        dragDepth++;
        ui.drop.classList.add('visible');
    });
    document.addEventListener('dragover', (event) => {
        if (!event.dataTransfer?.types.includes('Files')) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
    });
    document.addEventListener('dragleave', (event) => {
        event.preventDefault();
        dragDepth = Math.max(0, dragDepth - 1);
        if (!dragDepth) ui.drop.classList.remove('visible');
    });
    document.addEventListener('drop', (event) => {
        event.preventDefault();
        dragDepth = 0;
        ui.drop.classList.remove('visible');
        const file = [...(event.dataTransfer?.files || [])].find((candidate) => /\.pdf$/i.test(candidate.name) || candidate.type === 'application/pdf');
        if (file) void loadPDF(file);
        else status('Arraste um arquivo com extensão .pdf.');
    });

    let lastMobile = window.innerWidth <= 640;
    window.addEventListener('resize', () => {
        const mobile = window.innerWidth <= 640;
        if (mobile !== lastMobile) {
            lastMobile = mobile;
            if (!state.busy) void renderBook();
        } else refreshControls();
    });
    refreshControls();
    void renderBook();
})();
