'use strict';

/**
 * CropUploader — reusable image-crop-before-upload widget.
 *
 * Requires Cropper.js (loaded before this script).
 *
 * Usage:
 *   new CropUploader({
 *     triggerBtn:       <button>,       // "Add image" button
 *     fileInput:        <input[file]>,  // hidden file picker (outside the form)
 *     queueEl:          <div>,          // thumbnail strip container (inside form)
 *     hiddenContainer:  <div>,          // where hidden base64 inputs go (inside form)
 *     submitBtn:        <button>,       // form submit button (disabled when empty)
 *     countEl:          <span>,         // optional: shows "N images ready"
 *     maxImages:        6,
 *     inputName:        'cropped_images[]',
 *     aspectRatio:      16 / 9,
 *     outputWidth:      1280,
 *     outputHeight:     720,
 *     ratioLabel:       '16 : 9',
 *     sizeLabel:        '1280 × 720 px',
 *   });
 */
class CropUploader {
    constructor(opts = {}) {
        this.o = Object.assign({
            triggerBtn:      null,
            fileInput:       null,
            queueEl:         null,
            hiddenContainer: null,
            submitBtn:       null,
            countEl:         null,
            maxImages:       6,
            inputName:       'cropped_images[]',
            aspectRatio:     16 / 9,
            outputWidth:     1280,
            outputHeight:    720,
            ratioLabel:      '16 : 9',
            sizeLabel:       '1280 × 720 px',
        }, opts);

        this._ids    = [];
        this._crop   = null;
        this._modal  = null;
        this._srcImg = null;

        this._buildModal();
        this._bindEvents();
        this._refresh();
    }

    /* ── Modal DOM ──────────────────────────────────────────────────── */
    _buildModal() {
        const uid = 'cu-' + Math.random().toString(36).slice(2, 8);

        const overlay = document.createElement('div');
        overlay.className = 'cu-overlay';
        overlay.id = uid + '-overlay';
        overlay.innerHTML = `
<div class="cu-modal" role="dialog" aria-modal="true">
  <div class="cu-modal-head">
    <div>
      <p class="cu-modal-title">Crop Image</p>
      <p class="cu-modal-sub">Drag to reposition &nbsp;·&nbsp; Scroll / pinch to zoom</p>
    </div>
    <button type="button" class="cu-close-btn" aria-label="Close">&#10005;</button>
  </div>
  <div class="cu-crop-area">
    <img id="${uid}-img" class="cu-source-img" src="" alt="">
  </div>
  <div class="cu-ratio-info">
    <span class="cu-ratio-badge">&#9881; ${this.o.ratioLabel}</span>
    <span>Output: <strong>${this.o.sizeLabel}</strong></span>
    <span style="margin-left:auto;font-size:.72rem;">Saved as JPEG · high quality</span>
  </div>
  <div class="cu-modal-foot">
    <button type="button" class="cu-btn cu-btn-cancel">Cancel</button>
    <button type="button" class="cu-btn cu-btn-confirm">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      Crop &amp; Add
    </button>
  </div>
</div>`;
        document.body.appendChild(overlay);

        this._modal  = overlay;
        this._srcImg = overlay.querySelector(`#${uid}-img`);

        overlay.querySelector('.cu-btn-confirm').addEventListener('click', () => this._confirm());
        overlay.querySelector('.cu-btn-cancel').addEventListener('click',  () => this._close());
        overlay.querySelector('.cu-close-btn').addEventListener('click',   () => this._close());
        overlay.addEventListener('click', e => { if (e.target === overlay) this._close(); });

        // Keyboard: Escape closes, Enter confirms
        document.addEventListener('keydown', e => {
            if (this._modal.style.display !== 'flex') return;
            if (e.key === 'Escape') this._close();
            if (e.key === 'Enter')  this._confirm();
        });

        // Add empty-state placeholder to queue
        this._emptyMsg = document.createElement('span');
        this._emptyMsg.className = 'cu-queue-empty';
        this._emptyMsg.textContent = 'No images selected yet — click "Add Image" to start.';
        if (this.o.queueEl) this.o.queueEl.appendChild(this._emptyMsg);
    }

    /* ── Event Binding ──────────────────────────────────────────────── */
    _bindEvents() {
        if (this.o.triggerBtn) {
            this.o.triggerBtn.addEventListener('click', () => {
                if (this._ids.length >= this.o.maxImages) {
                    alert(`You can add a maximum of ${this.o.maxImages} image${this.o.maxImages > 1 ? 's' : ''}.`);
                    return;
                }
                this.o.fileInput.click();
            });
        }
        if (this.o.fileInput) {
            this.o.fileInput.addEventListener('change', e => {
                const file = e.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = ev => this._open(ev.target.result);
                reader.readAsDataURL(file);
                e.target.value = '';
            });
        }
    }

    /* ── Open modal ─────────────────────────────────────────────────── */
    _open(src) {
        this._srcImg.src = src;
        this._modal.style.display = 'flex';
        // wait one frame so img has dimensions before Cropper initialises
        requestAnimationFrame(() => {
            if (this._crop) this._crop.destroy();
            this._crop = new Cropper(this._srcImg, {
                aspectRatio:      this.o.aspectRatio,
                viewMode:         1,
                dragMode:         'move',
                autoCropArea:     0.86,
                movable:          true,
                zoomable:         true,
                zoomOnWheel:      true,
                responsive:       true,
                checkOrientation: true,
                background:       false,
            });
        });
    }

    /* ── Confirm crop ───────────────────────────────────────────────── */
    _confirm() {
        if (!this._crop) return;
        const canvas = this._crop.getCroppedCanvas({
            width:                 this.o.outputWidth,
            height:                this.o.outputHeight,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });
        const dataURL = canvas.toDataURL('image/jpeg', 0.92);
        const id = Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 6);

        this._ids.push(id);

        /* thumbnail */
        const isWide = this.o.aspectRatio > 1;
        const tw = isWide ? 148 : 108;
        const th = Math.round(isWide ? tw * (this.o.outputHeight / this.o.outputWidth) : tw / this.o.aspectRatio);

        const thumb = document.createElement('div');
        thumb.className = 'cu-thumb';
        thumb.dataset.cuId = id;
        thumb.innerHTML = `
            <img src="${dataURL}" width="${tw}" height="${th}" alt="Preview" style="width:${tw}px;height:${th}px;">
            <button type="button" class="cu-thumb-del" title="Remove">&#10005;</button>`;
        thumb.querySelector('.cu-thumb-del').addEventListener('click', () => this._remove(id));
        this.o.queueEl.appendChild(thumb);

        /* hidden input inside the form */
        const inp = document.createElement('input');
        inp.type      = 'hidden';
        inp.name      = this.o.inputName;
        inp.value     = dataURL;
        inp.dataset.cuId = id;
        this.o.hiddenContainer.appendChild(inp);

        this._refresh();
        this._close();
    }

    /* ── Remove item ────────────────────────────────────────────────── */
    _remove(id) {
        this._ids = this._ids.filter(i => i !== id);
        this.o.queueEl.querySelector(`[data-cu-id="${id}"]`)?.remove();
        this.o.hiddenContainer.querySelector(`[data-cu-id="${id}"]`)?.remove();
        this._refresh();
    }

    /* ── Update UI state ────────────────────────────────────────────── */
    _refresh() {
        const count     = this._ids.length;
        const remaining = this.o.maxImages - count;

        if (this._emptyMsg) {
            this._emptyMsg.style.display = count === 0 ? '' : 'none';
        }
        if (this.o.countEl) {
            this.o.countEl.textContent = count
                ? `${count} image${count > 1 ? 's' : ''} ready · ${remaining} slot${remaining !== 1 ? 's' : ''} left`
                : '';
        }
        if (this.o.submitBtn) {
            this.o.submitBtn.disabled = count === 0;
        }
        if (this.o.triggerBtn) {
            this.o.triggerBtn.disabled      = remaining <= 0;
            this.o.triggerBtn.style.opacity = remaining <= 0 ? '.4' : '';
        }
    }

    /* ── Close modal ────────────────────────────────────────────────── */
    _close() {
        this._modal.style.display = 'none';
        if (this._crop) { this._crop.destroy(); this._crop = null; }
        this._srcImg.src = '';
    }
}
