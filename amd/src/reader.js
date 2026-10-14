/**
 * @module mod_superflipbook/reader
 * @copyright 2026 Eduardo Kraus
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str', 'core/notification'], function(Ajax, Str, Notification) {
    'use strict';

    const loadScript = (url) => new Promise((resolve, reject) => {
        const el = document.createElement('script');
        el.src = url;
        el.onload = resolve;
        el.onerror = reject;
        document.head.appendChild(el);
    });

    /** Init full flipbook and persist the current page on navigation and active reading. */
    const init = async (cfg) => {
        const root = document.querySelector('[data-region="reader"]');
        const bookel = root.querySelector('[data-region="book"]');
        const statusel = root.querySelector('[data-region="status"]');
        const pageel = root.querySelector('[data-region="page-number"]');
        const progressel = root.querySelector('[data-region="progress"]');
        let book;
        let pdf;
        let current = 1;
        let visited = new Set();
        let last = Date.now();
        let zoom = 1;
        const cached = new Set();
        const send = (acknowledge = false, entered = false) => {
            if (!pdf) {
                return Promise.resolve();
            }
            const now = Date.now();
            const active = document.visibilityState === 'visible' && document.hasFocus();
            const seconds = active ? Math.min(30, Math.max(0, Math.round((now - last) / 1000))) : 0;
            last = now;
            return Ajax.call([{
                methodname: 'mod_superflipbook_record_progress',
                args: {cmid: cfg.cmid, page: current, pages: pdf.numPages, seconds, acknowledge, entered}
            }])[0].then((result) => {
                visited = new Set(result.visited.split(',').filter(Boolean).map(Number));
                progressel.textContent = String(Math.round(visited.size / pdf.numPages * 100)) + '%';
                return result;
            }).catch(Notification.exception);
        };
        const draw = async (num) => {
            if (cached.has(num) || num < 1 || num > pdf.numPages) {
                return;
            }
            const canvas = bookel.querySelector('[data-page="' + String(num) + '"] canvas');
            if (!canvas) {
                return;
            }
            cached.add(num);
            try {
                const p = await pdf.getPage(num);
                const view = p.getViewport({scale: 1.25 * zoom});
                canvas.width = view.width;
                canvas.height = view.height;
                await p.render({canvasContext: canvas.getContext('2d'), viewport: view}).promise;
            } catch (error) {
                cached.delete(num);
                Notification.exception(error);
            }
        };
        const show = (num) => {
            if (!pdf) {
                return;
            }
            current = Math.min(pdf.numPages, Math.max(1, num));
            visited.add(current);
            pageel.textContent = current + ' / ' + pdf.numPages;
            [current - 1, current, current + 1, current + 2].forEach(draw);
            send(false, true);
        };
        try {
            await loadScript(cfg.pdfjs);
            await loadScript(cfg.pageflip);
            if (!window.pdfjsLib || !window.St || !window.St.PageFlip) {
                throw new Error('Missing bundled PDF.js or StPageFlip');
            }
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = cfg.worker;
            pdf = await window.pdfjsLib.getDocument({url: cfg.pdfurl, withCredentials: true}).promise;
            for (let p = 1; p <= pdf.numPages; p++) {
                const wrap = document.createElement('div');
                wrap.className = 'sf-page';
                wrap.dataset.page = String(p);
                const canvas = document.createElement('canvas');
                wrap.appendChild(canvas);
                if (cfg.watermark) {
                    const watermark = document.createElement('span');
                    watermark.className = 'sf-watermark';
                    watermark.textContent = cfg.watermark;
                    wrap.appendChild(watermark);
                }
                bookel.appendChild(wrap);
            }
            book = new window.St.PageFlip(bookel, {
                width: 540, height: 700, size: 'stretch', minWidth: 250, maxWidth: 1000,
                minHeight: 340, maxHeight: 1200, showCover: false, usePortrait: true,
                maxShadowOpacity: 0.25, mobileScrollSupport: false
            });
            book.loadFromHTML(bookel.querySelectorAll('.sf-page'));
            book.on('flip', (e) => show(e.data + 1));
            const initial = await Ajax.call([{
                methodname: 'mod_superflipbook_get_progress', args: {cmid: cfg.cmid}
            }])[0];
            const resume = Math.min(pdf.numPages, Math.max(1, initial.maxpage || 1));
            book.flip(resume - 1);
            last = Date.now();
            show(resume);
            statusel.textContent = '';
        } catch (error) {
            statusel.textContent = error.message;
            Notification.exception(error);
            return;
        }
        root.querySelector('[data-action="previous"]').addEventListener('click', () => book.flipPrev());
        root.querySelector('[data-action="next"]').addEventListener('click', () => book.flipNext());
        root.querySelector('[data-action="zoomin"]').addEventListener('click', () => {
            zoom = Math.min(3, zoom + 0.25);
            cached.clear();
            draw(current);
        });
        root.querySelector('[data-action="zoomout"]').addEventListener('click', () => {
            zoom = Math.max(0.5, zoom - 0.25);
            cached.clear();
            draw(current);
        });
        root.querySelector('[data-action="fullscreen"]').addEventListener('click', () => {
            if (root.requestFullscreen) {
                root.requestFullscreen();
            }
        });
        root.querySelector('[data-action="bookmark"]').addEventListener('click', () => {
            Str.get_string('bookmarkprompt', 'mod_superflipbook').then((msg) => {
                const note = window.prompt(msg, '');
                if (note !== null) {
                    Ajax.call([{methodname: 'mod_superflipbook_save_bookmark',
                        args: {cmid: cfg.cmid, page: current, note: note.substring(0, 2000)}}])[0]
                        .catch(Notification.exception);
                }
            });
        });
        const printbtn = root.querySelector('[data-action="print"]');
        if (printbtn) {
            printbtn.addEventListener('click', () => window.print());
        }
        const thumbnailsel = root.querySelector('[data-region="thumbnails"]');
        const bookmarksel = root.querySelector('[data-region="bookmarks"]');
        let thumbnailsbuilt = false;
        const renderThumbnail = async (page, canvas) => {
            try {
                const source = await pdf.getPage(page);
                const viewport = source.getViewport({scale: 0.24});
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                await source.render({canvasContext: canvas.getContext('2d'), viewport}).promise;
            } catch (error) {
                Notification.exception(error);
            }
        };
        root.querySelector('[data-action="thumbnails"]').addEventListener('click', () => {
            bookmarksel.hidden = true;
            thumbnailsel.hidden = !thumbnailsel.hidden;
            if (thumbnailsbuilt || thumbnailsel.hidden) {
                return;
            }
            thumbnailsbuilt = true;
            const observer = window.IntersectionObserver ? new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        renderThumbnail(Number(entry.target.dataset.page), entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, {root: thumbnailsel}) : null;
            for (let p = 1; p <= pdf.numPages; p++) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'sf-thumb';
                button.textContent = String(p);
                const canvas = document.createElement('canvas');
                canvas.dataset.page = String(p);
                button.appendChild(canvas);
                button.addEventListener('click', () => book.flip(p - 1));
                thumbnailsel.appendChild(button);
                if (observer) {
                    observer.observe(canvas);
                } else if (p <= 10) {
                    renderThumbnail(p, canvas);
                }
            }
        });
        root.querySelector('[data-action="bookmarklist"]').addEventListener('click', () => {
            thumbnailsel.hidden = true;
            bookmarksel.hidden = !bookmarksel.hidden;
            if (bookmarksel.hidden) {
                return;
            }
            Ajax.call([{methodname: 'mod_superflipbook_get_bookmarks', args: {cmid: cfg.cmid}}])[0]
                .then((items) => {
                    bookmarksel.replaceChildren();
                    items.forEach((item) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'sf-bookmark';
                        button.textContent = String(item.page) + ': ' + item.note;
                        button.addEventListener('click', () => book.flip(item.page - 1));
                        bookmarksel.appendChild(button);
                    });
                }).catch(Notification.exception);
        });
        const searchinput = root.querySelector('[data-region="search"]');
        root.querySelector('[data-action="search"]').addEventListener('click', async () => {
            const query = searchinput.value.trim().toLocaleLowerCase();
            if (!query) {
                return;
            }
            statusel.textContent = '';
            for (let p = 1; p <= pdf.numPages; p++) {
                const page = await pdf.getPage(p);
                const content = await page.getTextContent();
                const joined = content.items.map((item) => item.str).join(' ').toLocaleLowerCase();
                if (joined.includes(query)) {
                    book.flip(p - 1);
                    return;
                }
            }
            statusel.textContent = await Str.get_string('searchnotfound', 'mod_superflipbook');
        });
        const acknowledgebtn = root.querySelector('[data-action="acknowledge"]');
        if (acknowledgebtn) {
            acknowledgebtn.addEventListener('click', () => {
                if (root.querySelector('[data-region="consent"]').checked) {
                    send(true).then((result) => {
                        if (result && result.acknowledged) {
                            acknowledgebtn.disabled = true;
                            root.querySelector('[data-region="consent"]').disabled = true;
                        }
                    });
                }
            });
        }
        window.addEventListener('pagehide', () => send(false));
        document.addEventListener('visibilitychange', () => {
            last = Date.now();
        });
        setInterval(() => {
            if (document.visibilityState === 'visible' && document.hasFocus()) {
                send(false);
            }
        }, 15000);
    };
    return {init};
});
