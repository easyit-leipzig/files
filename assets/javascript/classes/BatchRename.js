/**
 * FilesBatchRename – eigenständige, noJquery-kompatible Erweiterung.
 * Keine jQuery-Abhängigkeit; verwendet nur native Browser-Funktionen.
 * panel: 'left' / 'right'; getSelectedPaths(): string[] (relative Pfade in Anzeigereihenfolge)
 */
(function (global) {
  'use strict';
  class FilesBatchRename {
    constructor(options) {
      if (!options || !options.mount || typeof options.getSelectedPaths !== 'function') {
        throw new Error('mount und getSelectedPaths sind erforderlich.');
      }
      this.opt = Object.assign({
        endpoint: 'assets/php/batch-rename-api.php', panel: 'left', csrfToken: '',
        onRenamed: () => {}, getContext: () => ({drive:'',folder:'',recursive:false})
      }, options);
      this.mount = this.opt.mount;
      this.token = null;
      this.previewData = null;
      this.requestVersion = 0;
      this.render();
    }

    render() {
      const root = document.createElement('section');
      root.className = 'files-batch-rename';
      root.hidden = true;
      root.innerHTML = `
        <div class="fbr-head"><strong>Reihenumbenennung mit Muster</strong>
          <button type="button" data-fbr="close" aria-label="Schließen">×</button></div>
        <label>Muster (ohne Endung)<input data-fbr="pattern" type="text" value="{name}_{n:3}" maxlength="200" spellcheck="false"></label>
        <div class="fbr-settings">
          <label>Startnummer<input data-fbr="start" type="number" min="0" max="1000000000" value="1"></label>
          <label>Schrittweite<input data-fbr="step" type="number" min="1" max="1000000" value="1"></label>
        </div>
        <p class="fbr-help">Platzhalter: <code>{name}</code> ursprünglicher Name, <code>{n}</code> Nummer,
          <code>{n:3}</code> 001, 002 …, <code>{date}</code> aktuelles Datum.
          Die Dateiendung bleibt erhalten. Die Reihenfolge der ausgewählten Dateien bestimmt die Nummerierung.</p>
        <div class="fbr-actions">
          <button type="button" data-fbr="preview">Vorschau erstellen</button>
          <button type="button" data-fbr="apply" disabled>Umbenennung ausführen</button>
        </div>
        <div class="fbr-message" data-fbr="message" aria-live="polite"></div>
        <div class="fbr-table-wrap"><table><thead><tr><th>Bisher</th><th>Neu</th></tr></thead>
          <tbody data-fbr="rows"></tbody></table></div>
      `;
      this.mount.appendChild(root);
      this.root = root;
      this.node = (key) => this.root.querySelector('[data-fbr="' + key + '"]');
      this.node('close').addEventListener('click', () => this.close());
      this.node('preview').addEventListener('click', () => this.preview());
      this.node('apply').addEventListener('click', () => this.apply());
      for (const key of ['pattern', 'start', 'step']) {
        this.node(key).addEventListener('input', () => this.invalidate());
      }
    }

    open(side) {
      if (!['left','right'].includes(side)) throw new Error('Unbekanntes Panel');
      this.opt.panel = side;
      this.root.querySelector('.fbr-head strong').textContent =
        'Reihenumbenennung mit Muster – Panel ' + (side==='left'?'links':'rechts');
      this.root.hidden = false;
      if (this.mount.tagName==='DIALOG' && !this.mount.open) this.mount.showModal();
      this.invalidate();
      this.preview();
    }
    close() {
      this.root.hidden = true;
      if (this.mount.tagName==='DIALOG' && this.mount.open) this.mount.close();
      this.invalidate();
    }
    invalidate() {
      ++this.requestVersion;
      this.previewData = null;
      this.token = null;
      this.node('apply').disabled = true;
      this.node('message').textContent = '';
    }
    selected() {
      return this.opt.getSelectedPaths().slice();
    }
    payload(action, paths) {
      return {
        action, panel: this.opt.panel, ...this.opt.getContext(), paths,
        pattern: this.node('pattern').value,
        start: Number(this.node('start').value),
        step: Number(this.node('step').value)
      };
    }
    async post(payload) {
      const resp = await fetch(this.opt.endpoint, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.opt.csrfToken },
        body: JSON.stringify(payload)
      });
      const data = await resp.json();
      if (!resp.ok || !data.ok) throw new Error(data.error || 'Serverfehler (' + resp.status + ')');
      return data.data;
    }
    message(text, error) {
      const n = this.node('message');
      n.textContent = text;
      n.classList.toggle('fbr-error', !!error);
    }
    busy(value) {
      this.node('preview').disabled = value;
      this.node('apply').disabled = value || !this.previewData;
    }
    async preview() {
      const paths = this.selected();
      this.invalidate();
      this.node('rows').replaceChildren();
      if (!paths.length) return this.message('Bitte mindestens eine Datei im Panel markieren.', true);
      this.busy(true);
      const version = this.requestVersion;
      try {
        const request = this.payload('batchRenamePreview', paths);
        if (!Number.isInteger(request.start) || !Number.isInteger(request.step)) throw new Error('Startnummer und Schrittweite müssen ganze Zahlen sein.');
        const data = await this.post(request);
        if (version !== this.requestVersion || JSON.stringify(this.payload('batchRenamePreview', this.selected())) !== JSON.stringify(request)) {
          throw new Error('Die Auswahl wurde während der Vorschau geändert. Bitte erneut prüfen.');
        }
        this.previewData = data;
        this.token = JSON.stringify(request); // Bearbeitung nur für exakt die vorgezeigte Auswahl.
        const body = this.node('rows');
        for (const item of data.items) {
          const tr = document.createElement('tr');
          for (const value of [item.before, item.after]) {
            const td = document.createElement('td');
            td.textContent = value;
            td.title = value;
            tr.appendChild(td);
          }
          if (!item.changed) tr.classList.add('fbr-unchanged');
          body.appendChild(tr);
        }
        this.message(`${data.count} ausgewählt, ${data.changed} werden umbenannt. Prüfe die Vorschau.`);
      } catch (e) {
        if (version === this.requestVersion) this.message(e.message, true);
      } finally {
        if (version === this.requestVersion) this.busy(false);
      }
    }
    async apply() {
      if (!this.previewData || !this.token) return;
      const paths = this.selected();
      const candidate = this.payload('batchRenamePreview', paths);
      if (this.token !== JSON.stringify(candidate)) {
        this.invalidate();
        this.message('Die Auswahl oder das Muster hat sich geändert. Bitte neue Vorschau erstellen.', true);
        return;
      }
      const request = Object.assign({}, candidate, { action: 'batchRenameApply' });
      this.busy(true);
      try {
        const data = await this.post(request);
        this.previewData = null;
        this.token = null;
        this.node('apply').disabled = true;
        this.message(`${data.changed} Datei(en) erfolgreich umbenannt.`);
        try {
          await Promise.resolve(this.opt.onRenamed(data));
        } catch (refreshError) {
          this.message(`${data.changed} Datei(en) umbenannt. Die Panel-Aktualisierung ist fehlgeschlagen: ${refreshError.message}`, true);
        }
      } catch (e) {
        this.message('Umbenennung abgebrochen: ' + e.message, true);
      } finally {
        this.busy(false);
        this.node('apply').disabled = true;
      }
    }
  }
  global.FilesBatchRename = FilesBatchRename;
})(window);
