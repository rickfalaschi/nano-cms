// Nano CMS — admin JS
// TipTap richtext editors + repeater + small interactions.

import { Editor } from 'https://esm.sh/@tiptap/core@2.10.3';
import StarterKit from 'https://esm.sh/@tiptap/starter-kit@2.10.3';
import Link from 'https://esm.sh/@tiptap/extension-link@2.10.3';

// Admin URL base (e.g. "/fulber/admin", "/admin").
// Server-rendered into <body data-admin-base="..."> by the layout. Without
// this, fetch() calls would hardcode "/admin/..." and break whenever the
// project lives under a subpath like /fulber or /expmark. Trailing slash
// is stripped server-side, so we always concat with a leading "/".
const ADMIN_BASE = (document.body && document.body.dataset.adminBase) || '';

const initRichText = (root) => {
  const editorEl = root.querySelector('[data-richtext-editor]');
  const sourceEl = root.querySelector('.richtext__source');
  const toolbarEl = root.querySelector('[data-richtext-toolbar]');
  if (!editorEl || !sourceEl) return;

  const editor = new Editor({
    element: editorEl,
    extensions: [
      StarterKit,
      Link.configure({ openOnClick: false, HTMLAttributes: { rel: 'noopener noreferrer' } }),
    ],
    content: sourceEl.value || '',
    onUpdate: ({ editor }) => {
      sourceEl.value = editor.getHTML();
    },
    onSelectionUpdate: () => updateToolbarState(),
  });

  // Click anywhere inside the wrapper that ISN'T the contenteditable surface
  // (e.g. the small gap between the toolbar bottom border and the .ProseMirror
  // top, or any future padding) should still focus the editor at end. This
  // makes the whole control feel like a single text input.
  root.addEventListener('mousedown', (e) => {
    const target = e.target;
    if (target.closest('[data-richtext-toolbar], button, a, input, textarea, select')) {
      return;
    }
    if (target.closest('.ProseMirror')) {
      return; // ProseMirror handles its own focus correctly
    }
    e.preventDefault();
    editor.commands.focus('end');
  });

  const updateToolbarState = () => {
    if (!toolbarEl) return;
    toolbarEl.querySelectorAll('button[data-cmd]').forEach((btn) => {
      const cmd = btn.dataset.cmd;
      let active = false;
      switch (cmd) {
        case 'bold': active = editor.isActive('bold'); break;
        case 'italic': active = editor.isActive('italic'); break;
        case 'strike': active = editor.isActive('strike'); break;
        case 'paragraph': active = editor.isActive('paragraph'); break;
        case 'h2': active = editor.isActive('heading', { level: 2 }); break;
        case 'h3': active = editor.isActive('heading', { level: 3 }); break;
        case 'bulletList': active = editor.isActive('bulletList'); break;
        case 'orderedList': active = editor.isActive('orderedList'); break;
      }
      btn.classList.toggle('is-active', active);
    });
  };

  if (toolbarEl) {
    toolbarEl.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-cmd]');
      if (!btn) return;
      e.preventDefault();
      const cmd = btn.dataset.cmd;
      const chain = editor.chain().focus();
      switch (cmd) {
        case 'bold': chain.toggleBold().run(); break;
        case 'italic': chain.toggleItalic().run(); break;
        case 'strike': chain.toggleStrike().run(); break;
        case 'paragraph': chain.setParagraph().run(); break;
        case 'h2': chain.toggleHeading({ level: 2 }).run(); break;
        case 'h3': chain.toggleHeading({ level: 3 }).run(); break;
        case 'bulletList': chain.toggleBulletList().run(); break;
        case 'orderedList': chain.toggleOrderedList().run(); break;
        case 'link': {
          const url = window.prompt('URL do link:');
          if (url === null) return;
          if (url === '') {
            chain.unsetLink().run();
          } else {
            chain.setLink({ href: url }).run();
          }
          break;
        }
        case 'undo': chain.undo().run(); break;
        case 'redo': chain.redo().run(); break;
      }
      updateToolbarState();
    });
  }

  // Keep textarea in sync on form submit (defensive)
  const form = root.closest('form');
  if (form) {
    form.addEventListener('submit', () => {
      sourceEl.value = editor.getHTML();
    });
  }

  updateToolbarState();
};

const initRepeater = (root) => {
  const rowsEl = root.querySelector('[data-repeater-rows]');
  const tplEl = root.querySelector('[data-repeater-template]');
  const addBtn = root.querySelector('[data-repeater-add]');
  if (!rowsEl || !tplEl || !addBtn) return;

  // Template is base64-encoded server-side so nested HTML (inputs, even
  // nested repeaters) survives intact and __INDEX__ stays unescaped.
  // atob() returns a Latin-1 byte string — we need to re-decode as UTF-8
  // or multi-byte chars like × and Portuguese accents get mangled.
  let templateHtml = '';
  try {
    const binary = atob((tplEl.textContent || '').trim());
    const bytes = Uint8Array.from(binary, (c) => c.charCodeAt(0));
    templateHtml = new TextDecoder('utf-8').decode(bytes);
  } catch (e) {
    console.warn('Repeater template decode failed', e);
    return;
  }

  const reindex = () => {
    rowsEl.querySelectorAll(':scope > [data-repeater-row]').forEach((row, idx) => {
      row.querySelectorAll('[name]').forEach((el) => {
        // Only rewrite the OUTER index of this repeater — leave nested ones alone.
        const prefix = root.dataset.repeaterName;
        const re = new RegExp('^(' + prefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')\\[(\\d+|__INDEX__)\\]');
        el.name = el.name.replace(re, `$1[${idx}]`);
      });
    });
  };

  const bindRow = (row) => {
    const removeBtn = row.querySelector(':scope > [data-repeater-remove]');
    if (removeBtn) {
      removeBtn.addEventListener('click', () => {
        row.remove();
        reindex();
      });
    }
  };

  addBtn.addEventListener('click', () => {
    const idx = rowsEl.querySelectorAll(':scope > [data-repeater-row]').length;
    const html = templateHtml.replace(/__INDEX__/g, idx.toString());
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html;
    const newRow = wrapper.firstElementChild;
    if (newRow) {
      rowsEl.appendChild(newRow);
      bindRow(newRow);
      // Init any nested widgets inside the freshly inserted row
      newRow.querySelectorAll('[data-richtext]').forEach(initRichText);
      newRow.querySelectorAll('[data-repeater]').forEach(initRepeater);
      newRow.querySelectorAll('[data-image-field]').forEach(initImageField);
    }
  });

  rowsEl.querySelectorAll(':scope > [data-repeater-row]').forEach(bindRow);
};

const initSlugify = () => {
  const titleInput = document.querySelector('[data-slug-source]');
  const slugInput = document.querySelector('[data-slug-target]');
  if (!titleInput || !slugInput) return;

  let manuallyEdited = slugInput.value.trim() !== '';
  slugInput.addEventListener('input', () => { manuallyEdited = true; });

  titleInput.addEventListener('input', () => {
    if (manuallyEdited) return;
    slugInput.value = slugify(titleInput.value);
  });
};

const slugify = (str) => {
  return str
    .toString()
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'item';
};

const initConfirms = () => {
  document.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      const message = form.dataset.confirm || 'Tem certeza?';
      if (!window.confirm(message)) {
        e.preventDefault();
      }
    });
  });
};

// Auto-submit a select's parent <form> on change. Used for the table
// toolbar filters (status, etc.) so changing a dropdown reloads the list
// without a separate "Apply" button.
const initAutosubmit = () => {
  document.querySelectorAll('[data-autosubmit]').forEach((el) => {
    el.addEventListener('change', () => {
      const form = el.closest('form');
      if (form) form.submit();
    });
  });
};

// ── Modal helper ────────────────────────────────────────────────────────────
const openModal = ({ title = '', src = '' } = {}) => {
  const modal = document.createElement('div');
  modal.className = 'modal';
  modal.innerHTML = `
    <div class="modal__overlay" data-modal-close></div>
    <div class="modal__body">
      <header class="modal__header">
        <h2 class="modal__title">${escapeHtml(title)}</h2>
        <button type="button" class="modal__close" data-modal-close aria-label="Fechar">×</button>
      </header>
      <div class="modal__content">
        <iframe class="modal__iframe" src="${escapeHtml(src)}"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
  document.body.style.overflow = 'hidden';

  const close = () => {
    modal.remove();
    document.body.style.overflow = '';
  };
  modal.querySelectorAll('[data-modal-close]').forEach((el) => el.addEventListener('click', close));
  document.addEventListener('keydown', function onKey(e) {
    if (e.key === 'Escape') {
      close();
      document.removeEventListener('keydown', onKey);
    }
  });

  return { close, iframe: modal.querySelector('iframe') };
};

const escapeHtml = (str) =>
  String(str ?? '').replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);

// ── Image field ────────────────────────────────────────────────────────────
const initImageField = (root) => {
  const input = root.querySelector('[data-image-field-input]');
  const preview = root.querySelector('[data-image-field-preview]');
  const pickBtn = root.querySelector('[data-image-field-pick]');
  const clearBtn = root.querySelector('[data-image-field-clear]');
  if (!input || !preview || !pickBtn) return;

  const renderPreview = (data) => {
    if (!data) {
      preview.innerHTML = '';
      input.value = '';
      pickBtn.textContent = 'Selecionar imagem';
      if (clearBtn) clearBtn.hidden = true;
      return;
    }
    const isImage = data.is_image !== false && data.thumb_url;
    const visual = isImage
      ? `<img class="image-field__current-img" src="${escapeHtml(data.thumb_url)}" alt="">`
      : `<div class="image-field__current-doc">${escapeHtml((data.filename || '').split('.').pop().toUpperCase())}</div>`;
    const dims = data.width && data.height ? ` · ${data.width}×${data.height}` : '';
    preview.innerHTML = `
      <div class="image-field__current">
        ${visual}
        <div class="image-field__meta">
          <strong>${escapeHtml(data.original_name || data.filename || '')}</strong>
          <span class="muted">${escapeHtml(data.human_size || '')} · ${escapeHtml(data.mime || '')}${dims}</span>
        </div>
      </div>
    `;
    input.value = String(data.id);
    pickBtn.textContent = 'Trocar imagem';
    if (clearBtn) clearBtn.hidden = false;
  };

  pickBtn.addEventListener('click', () => {
    window.__nanoPickerCallback = (data) => {
      renderPreview(data);
      modal.close();
      delete window.__nanoPickerCallback;
    };
    const adminBase = document.querySelector('link[href*="/__static/css/admin.css"]')?.getAttribute('href') || '';
    const adminUrl = adminBase.replace('/__static/css/admin.css', '');
    const modal = openModal({
      title: 'Biblioteca de mídia',
      src: `${adminUrl}/media?picker=1`,
    });
  });

  if (clearBtn) {
    clearBtn.addEventListener('click', () => renderPreview(null));
  }
};

// ── Media uploader (drop + queue) ──────────────────────────────────────────
const initMediaUploader = (root) => {
  const drop = root.querySelector('[data-media-uploader-drop]');
  const fileInput = root.querySelector('[data-media-uploader-input]');
  const trigger = root.querySelector('[data-media-uploader-trigger]');
  const queue = root.querySelector('[data-media-uploader-queue]');
  const url = root.dataset.uploadUrl;
  const csrf = root.dataset.csrf;
  if (!drop || !fileInput || !queue || !url) return;

  trigger?.addEventListener('click', () => fileInput.click());
  drop.addEventListener('click', (e) => {
    if (e.target === drop) fileInput.click();
  });

  ['dragenter', 'dragover'].forEach((evt) => {
    drop.addEventListener(evt, (e) => {
      e.preventDefault();
      drop.classList.add('is-dragover');
    });
  });
  ['dragleave', 'drop'].forEach((evt) => {
    drop.addEventListener(evt, (e) => {
      e.preventDefault();
      drop.classList.remove('is-dragover');
    });
  });
  drop.addEventListener('drop', (e) => {
    if (e.dataTransfer?.files?.length) handleFiles(e.dataTransfer.files);
  });

  fileInput.addEventListener('change', () => {
    if (fileInput.files?.length) handleFiles(fileInput.files);
    fileInput.value = '';
  });

  const handleFiles = async (files) => {
    for (const file of files) {
      const li = document.createElement('li');
      li.className = 'media-uploader__queue-item';
      li.innerHTML = `<span>${escapeHtml(file.name)}</span><span data-status>Enviando…</span>`;
      queue.appendChild(li);

      try {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('_csrf', csrf);
        const res = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Erro no upload');
        li.classList.add('is-success');
        li.querySelector('[data-status]').textContent = 'Enviado';
        prependToGrid(data);
        // Auto-select if we're inside the picker iframe
        if (window.parent && typeof window.parent.__nanoPickerCallback === 'function') {
          window.parent.__nanoPickerCallback(data);
        }
        setTimeout(() => li.remove(), 2500);
      } catch (err) {
        li.classList.add('is-error');
        li.querySelector('[data-status]').textContent = err.message || 'Falha no upload';
      }
    }
  };
};

const prependToGrid = (data) => {
  const grid = document.querySelector('[data-media-grid]');
  if (!grid) return;
  document.querySelector('[data-media-empty]')?.remove();

  const li = document.createElement('li');
  li.className = 'media-grid__item';
  li.dataset.mediaId = String(data.id);
  li.dataset.mediaUrl = data.url;
  li.dataset.mediaThumb = data.thumb_url || '';
  li.dataset.mediaName = data.original_name || data.filename;
  li.dataset.mediaMime = data.mime;
  li.dataset.mediaSize = data.human_size;
  li.dataset.mediaDimensions = data.width && data.height ? `${data.width}×${data.height}` : '';
  li.dataset.mediaAlt = data.alt || '';
  li.dataset.mediaIsImage = data.is_image ? '1' : '0';
  li.dataset.mediaCreatedAt = data.created_at || '';

  const ext = (data.filename || '').split('.').pop().toUpperCase();
  const tile = data.is_image && data.thumb_url
    ? `<img src="${escapeHtml(data.thumb_url)}" alt="" loading="lazy">`
    : `<div class="media-grid__doc"><span>${escapeHtml(ext)}</span></div>`;

  li.innerHTML = `
    <button type="button" class="media-grid__tile" data-media-open>${tile}</button>
    <span class="media-grid__name" title="${escapeHtml(data.original_name)}">${escapeHtml(data.original_name)}</span>
  `;
  grid.prepend(li);
  bindGridItem(li);
};

// ── Media grid (open detail panel or picker select) ─────────────────────────
const bindGridItem = (item) => {
  const btn = item.querySelector('[data-media-open]');
  if (!btn) return;
  btn.addEventListener('click', () => {
    const grid = item.closest('[data-media-grid]');
    const isPicker = grid?.dataset.picker === '1';
    if (isPicker) {
      // Send selection to parent window
      if (window.parent && typeof window.parent.__nanoPickerCallback === 'function') {
        window.parent.__nanoPickerCallback({
          id: parseInt(item.dataset.mediaId, 10),
          url: item.dataset.mediaUrl,
          thumb_url: item.dataset.mediaThumb,
          filename: item.dataset.mediaName,
          original_name: item.dataset.mediaName,
          mime: item.dataset.mediaMime,
          human_size: item.dataset.mediaSize,
          width: parseInt((item.dataset.mediaDimensions || '').split('×')[0], 10) || null,
          height: parseInt((item.dataset.mediaDimensions || '').split('×')[1], 10) || null,
          alt: item.dataset.mediaAlt,
          is_image: item.dataset.mediaIsImage === '1',
        });
      }
    } else {
      openMediaPanel(item);
    }
  });
};

// ── Media detail panel ─────────────────────────────────────────────────────
const openMediaPanel = (item) => {
  const panel = document.querySelector('[data-media-panel]');
  if (!panel) return;
  const id = item.dataset.mediaId;

  panel.querySelector('[data-media-panel-name]').textContent = item.dataset.mediaName;
  panel.querySelector('[data-media-panel-mime]').textContent = item.dataset.mediaMime;
  panel.querySelector('[data-media-panel-size]').textContent = item.dataset.mediaSize;
  panel.querySelector('[data-media-panel-dimensions]').textContent = item.dataset.mediaDimensions || '—';
  panel.querySelector('[data-media-panel-created]').textContent = item.dataset.mediaCreatedAt;
  panel.querySelector('[data-media-panel-url]').value = item.dataset.mediaUrl;
  panel.querySelector('[data-media-panel-alt]').value = item.dataset.mediaAlt;

  const previewEl = panel.querySelector('[data-media-panel-preview]');
  if (item.dataset.mediaIsImage === '1') {
    previewEl.innerHTML = `<img src="${escapeHtml(item.dataset.mediaUrl)}" alt="">`;
  } else {
    const ext = (item.dataset.mediaName || '').split('.').pop().toUpperCase();
    previewEl.innerHTML = `<div class="media-grid__doc" style="height:200px;width:200px"><span>${escapeHtml(ext)}</span></div>`;
  }

  // Wire alt-text save
  const altForm = panel.querySelector('[data-media-panel-alt-form]');
  altForm.onsubmit = async (e) => {
    e.preventDefault();
    const fd = new FormData(altForm);
    fd.append('alt', panel.querySelector('[data-media-panel-alt]').value);
    const res = await fetch(`${ADMIN_BASE}/media/${id}`, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    const status = panel.querySelector('[data-media-panel-alt-status]');
    if (res.ok) {
      status.textContent = 'Salvo.';
      item.dataset.mediaAlt = panel.querySelector('[data-media-panel-alt]').value;
      setTimeout(() => { status.textContent = ''; }, 2000);
    } else {
      status.textContent = 'Erro ao salvar.';
    }
  };

  // Wire delete
  const deleteForm = panel.querySelector('[data-media-panel-delete-form]');
  deleteForm.onsubmit = async (e) => {
    e.preventDefault();
    const message = deleteForm.dataset.confirm || 'Excluir?';
    if (!window.confirm(message)) return;
    const fd = new FormData(deleteForm);
    const res = await fetch(`${ADMIN_BASE}/media/${id}/delete`, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (res.ok) {
      item.remove();
      closeMediaPanel();
    } else {
      window.alert('Erro ao excluir.');
    }
  };

  panel.hidden = false;
  document.body.style.overflow = 'hidden';
};

const closeMediaPanel = () => {
  const panel = document.querySelector('[data-media-panel]');
  if (!panel) return;
  panel.hidden = true;
  document.body.style.overflow = '';
};

document.querySelectorAll('[data-media-panel-close]').forEach((el) =>
  el.addEventListener('click', closeMediaPanel)
);

document.querySelectorAll('[data-richtext]').forEach(initRichText);
document.querySelectorAll('[data-repeater]').forEach(initRepeater);
document.querySelectorAll('[data-image-field]').forEach(initImageField);
document.querySelectorAll('[data-media-uploader]').forEach(initMediaUploader);
document.querySelectorAll('[data-media-grid] .media-grid__item').forEach(bindGridItem);
initSlugify();
initConfirms();
initAutosubmit();
