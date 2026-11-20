/* Engine Empire — vanilla JS, no build step */
(() => {
  'use strict';
  const E = window.EE = window.EE || {};
  const $ = (s, r = document) => r.querySelector(s);
  const csrf = $('meta[name="csrf"]')?.content || '';
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // Light / dark theme
  document.addEventListener('click', e => {
    if (!e.target.closest('[data-theme-toggle]')) return;
    const root = document.documentElement;
    const dark = root.dataset.theme ? root.dataset.theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
    root.dataset.theme = dark ? 'light' : 'dark';
    try { localStorage.setItem('ee-theme', root.dataset.theme); } catch (_) {}
  });

  // Mobile menu
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-menu]');
    if (!b) return;
    const open = $('#nav')?.classList.toggle('open');
    b.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  // Product gallery (thumbnails swap the main photo or video)
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-gi]'); if (!b) return;
    const gal = b.closest('.pd-gal'), items = JSON.parse(gal.dataset.gal), it = items[+b.dataset.gi];
    gal.querySelector('.pd-main').innerHTML = it.t === 'img'
      ? `<img src="${esc(it.src)}" alt="${esc(gal.dataset.title)}">`
      : `<video src="${esc(it.src)}" poster="${esc(it.th)}" controls playsinline autoplay></video>`;
    gal.querySelectorAll('[data-gi]').forEach(x => x.classList.toggle('on', x === b));
  });

  // Quote / enquiry forms
  document.querySelectorAll('form[data-enquiry]').forEach(form => {
    const err = $('[data-error]', form), done = $('[data-done]', form), btn = $('button[type=submit]', form);
    form.addEventListener('submit', async ev => {
      ev.preventDefault();
      err.hidden = true;
      const d = new FormData(form);
      if (!String(d.get('name')).trim() || String(d.get('phone')).replace(/\D/g, '').length < 9) { err.textContent = 'Please give your name and a valid cell number.'; err.hidden = false; return; }
      if (!d.get('consent')) { err.textContent = 'Please tick the box so we may contact you.'; err.hidden = false; return; }
      btn.disabled = true;
      try {
        const r = await fetch((E.base || '') + '/ajax/enquiry.php', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }, body: new URLSearchParams(d) });
        const j = await r.json().catch(() => null);
        if (!j) throw new Error('The connection dropped. Please try again.');
        if (!j.ok) throw new Error(j.error || 'Something went wrong.');
        form.querySelectorAll('.field,.row2,.check,.sub,button[type=submit]').forEach(x => x.hidden = true);
        done.innerHTML = `Thank you! Your request <b>${esc(j.ref)}</b> is in. We will send your quote shortly.` +
          (j.whatsapp ? ` <a class="btn btn-wa btn-sm" style="margin-top:10px" href="${esc(j.whatsapp)}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> Message us on WhatsApp</a>` : '');
        done.hidden = false;
      } catch (x) {
        err.textContent = x.message; err.hidden = false; btn.disabled = false;
      }
    });
  });
})();
