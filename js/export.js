/* ============================================================
   RECOVERY INTERFACE — "Your data": download your history
   ------------------------------------------------------------
   Excel (.xlsx): "Sessions" + "Sets" sheets, dates in YOUR time zone
   (the browser tells the server which one; it's validated there).
   JSON: the complete raw copy, dates in UTC.
   Downloaded with fetch() instead of a plain link, so an expired
   session shows a message instead of saving an error page as a file.
   ============================================================ */

import { state } from './state.js';

const LABELS = { xlsx: 'Excel file', json: 'JSON file' };

/** e.g. "Europe/Lisbon". Falls back to UTC on very old browsers. */
function userTimeZone() {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
  } catch {
    return 'UTC';
  }
}

/** Uses the server's file name (it carries the date), e.g. recovery-interface-export-2026-11-04.xlsx */
function fileNameFrom(response, format) {
  const match = /filename="([^"]+)"/.exec(response.headers.get('Content-Disposition') || '');
  return match ? match[1] : `recovery-interface-export.${format}`;
}

function setExportFeedback(message, kind) {
  const el = document.getElementById('export-feedback');
  if (!el) return;
  el.textContent = message;
  el.className = 'log-feedback' + (kind ? ` ${kind}` : '');
}

async function downloadExport(format, button) {
  button.disabled = true;
  setExportFeedback('Preparing your file…', '');

  try {
    const url = `pages/api_export_data.php?format=${format}&tz=${encodeURIComponent(userTimeZone())}`;
    const response = await fetch(url, { credentials: 'same-origin' });

    if (!response.ok) {
      let message = 'Export failed. Please try again.';
      try {
        message = (await response.json()).error || message;
      } catch {
        // not JSON (e.g. Apache error page): keep the generic message
      }
      throw new Error(message);
    }

    // Turn the bytes into a temporary local URL and "click" a download link for it
    const blob = await response.blob();
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = fileNameFrom(response, format);
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(link.href), 10_000); // free the memory once the download started

    setExportFeedback(`${LABELS[format]} downloaded.`, 'success');
  } catch (err) {
    setExportFeedback(err.message, 'error');
  } finally {
    button.disabled = false;
  }
}

/** Exports need an account: guests see a hint instead of the buttons. */
export function updateExportPanel() {
  const signedIn = Boolean(state.user?.authenticated);
  const actions = document.getElementById('export-actions');
  const hint = document.getElementById('export-guest-hint');
  if (actions) actions.style.display = signedIn ? '' : 'none';
  if (hint) hint.style.display = signedIn ? 'none' : '';
}

export function initExport() {
  for (const format of ['xlsx', 'json']) {
    const button = document.getElementById(`export-${format}-btn`);
    button?.addEventListener('click', () => downloadExport(format, button));
  }
  updateExportPanel();
}
