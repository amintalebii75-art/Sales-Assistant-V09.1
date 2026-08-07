(function (global) {
  'use strict';

  const MONTHS = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
  const CUMULATIVE_DAYS = [0,31,62,93,124,155,186,216,246,276,306,336];
  const formatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
    numberingSystem: 'latn', year: 'numeric', month: '2-digit', day: '2-digit', timeZone: 'UTC'
  });
  const newYearCache = Object.create(null);

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[ch]));
  }
  function pad(value) { return String(value).padStart(2, '0'); }
  function todayIso() { return new Date().toISOString().slice(0, 10); }
  function toPersianNumber(value) { return Number(value).toLocaleString('fa-IR', {useGrouping:false}); }

  function gregorianToJalali(date) {
    const parts = formatter.formatToParts(date);
    const out = {};
    for (const part of parts) out[part.type] = part.value;
    return {jy: Number(out.year), jm: Number(out.month), jd: Number(out.day)};
  }
  function jalaliNewYearUtc(jy) {
    if (newYearCache[jy]) return newYearCache[jy];
    let cursor = new Date(Date.UTC(jy + 621, 2, 19));
    for (let i = 0; i < 8; i++) {
      const value = gregorianToJalali(cursor);
      if (value.jy === jy && value.jm === 1 && value.jd === 1) {
        newYearCache[jy] = cursor;
        return cursor;
      }
      cursor = new Date(cursor.getTime() + 86400000);
    }
    throw new Error('jalali_new_year_not_found');
  }
  function jalaliYearLength(jy) {
    return Math.round((jalaliNewYearUtc(jy + 1).getTime() - jalaliNewYearUtc(jy).getTime()) / 86400000);
  }
  function monthLength(jy, jm) {
    if (jm <= 6) return 31;
    if (jm <= 11) return 30;
    return jalaliYearLength(jy) - 336;
  }
  function jalaliToIso(jy, jm, jd) {
    const length = monthLength(jy, jm);
    const safeDay = Math.max(1, Math.min(Number(jd) || 1, length));
    const anchor = jalaliNewYearUtc(jy);
    const offset = CUMULATIVE_DAYS[jm - 1] + safeDay - 1;
    return new Date(anchor.getTime() + offset * 86400000).toISOString().slice(0, 10);
  }
  function isoToJalali(iso) {
    const safe = /^\d{4}-\d{2}-\d{2}$/.test(String(iso || '')) ? String(iso) : todayIso();
    return gregorianToJalali(new Date(safe + 'T00:00:00Z'));
  }
  function monthKeyToJalali(monthKey) {
    const value = String(monthKey || '').trim();
    const match = value.match(/^(\d{4})-(0[1-9]|1[0-2])$/);
    if (match) {
      const year = Number(match[1]), month = Number(match[2]);
      if (year >= 1300 && year <= 1599) return {jy: year, jm: month};
      if (year >= 1900 && year <= 2199) {
        // Legacy Gregorian month keys are mapped using day 25, which is inside
        // the Persian month that starts during that Gregorian month.
        const j = isoToJalali(`${year}-${pad(month)}-25`);
        return {jy: j.jy, jm: j.jm};
      }
    }
    const now = isoToJalali(todayIso());
    return {jy: now.jy, jm: now.jm};
  }
  function jalaliMonthKey(jy, jm) { return `${jy}-${pad(jm)}`; }
  function currentMonthKey() {
    const now = isoToJalali(todayIso());
    return jalaliMonthKey(now.jy, now.jm);
  }
  function nextMonthKey(monthKey) {
    let {jy, jm} = monthKeyToJalali(monthKey);
    jm += 1;
    if (jm > 12) { jm = 1; jy += 1; }
    return jalaliMonthKey(jy, jm);
  }
  function yearOptions(selectedYear, pastYears = 20, futureYears = 5) {
    const currentYear = isoToJalali(todayIso()).jy;
    const start = Math.min(currentYear - pastYears, selectedYear);
    const end = Math.max(currentYear + futureYears, selectedYear);
    const values = [];
    for (let year = start; year <= end; year++) values.push(year);
    return values;
  }
  function datePickerHtml(id, isoValue = '', options = {}) {
    const optional = options.optional !== false;
    const hasValue = /^\d{4}-\d{2}-\d{2}$/.test(String(isoValue || ''));
    const j = isoToJalali(hasValue ? isoValue : todayIso());
    const years = yearOptions(j.jy, options.pastYears ?? 20, options.futureYears ?? 5);
    const days = Array.from({length: monthLength(j.jy, j.jm)}, (_, index) => index + 1);
    const row = `<div class="hippo-jdate-row" id="${escapeHtml(id)}_row" ${optional && !hasValue ? 'hidden' : ''}>
      <select id="${escapeHtml(id)}_d" aria-label="روز" title="روز" onchange="HippoJalali.syncDate('${escapeHtml(id)}')">${days.map(day => `<option value="${day}" ${day === j.jd ? 'selected' : ''}>${toPersianNumber(day)}</option>`).join('')}</select>
      <select id="${escapeHtml(id)}_m" aria-label="ماه" title="ماه" onchange="HippoJalali.syncDate('${escapeHtml(id)}')">${MONTHS.map((name, index) => `<option value="${index + 1}" ${index + 1 === j.jm ? 'selected' : ''}>${name}</option>`).join('')}</select>
      <select id="${escapeHtml(id)}_y" aria-label="سال" title="سال" onchange="HippoJalali.syncDate('${escapeHtml(id)}')">${years.map(year => `<option value="${year}" ${year === j.jy ? 'selected' : ''}>${toPersianNumber(year)}</option>`).join('')}</select>
    </div>`;
    const hidden = `<input type="hidden" id="${escapeHtml(id)}" value="${escapeHtml(hasValue ? isoValue : '')}">`;
    if (!optional) return row + hidden;
    return `<label class="hippo-jdate-toggle"><input type="checkbox" id="${escapeHtml(id)}_chk" ${hasValue ? 'checked' : ''} onchange="HippoJalali.toggleDate('${escapeHtml(id)}')"><span>تعیین تاریخ</span></label>${row}${hidden}`;
  }
  function syncDate(id) {
    const year = Number(document.getElementById(id + '_y')?.value || 0);
    const month = Number(document.getElementById(id + '_m')?.value || 0);
    const daySelect = document.getElementById(id + '_d');
    const hidden = document.getElementById(id);
    if (!year || !month || !daySelect || !hidden) return;
    const length = monthLength(year, month);
    const currentDay = Math.min(Number(daySelect.value) || 1, length);
    if (daySelect.options.length !== length) {
      daySelect.innerHTML = Array.from({length}, (_, index) => index + 1)
        .map(day => `<option value="${day}" ${day === currentDay ? 'selected' : ''}>${toPersianNumber(day)}</option>`).join('');
    } else {
      daySelect.value = String(currentDay);
    }
    hidden.value = jalaliToIso(year, month, currentDay);
    hidden.dispatchEvent(new Event('change', {bubbles: true}));
  }
  function toggleDate(id) {
    const checked = Boolean(document.getElementById(id + '_chk')?.checked);
    const row = document.getElementById(id + '_row');
    const hidden = document.getElementById(id);
    if (!row || !hidden) return;
    row.hidden = !checked;
    if (checked) syncDate(id);
    else {
      hidden.value = '';
      hidden.dispatchEvent(new Event('change', {bubbles: true}));
    }
  }
  function monthPickerHtml(id, monthKey = '', options = {}) {
    const current = monthKeyToJalali(monthKey);
    const years = yearOptions(current.jy, options.pastYears ?? 5, options.futureYears ?? 5);
    return `<div class="hippo-jmonth-row">
      <select id="${escapeHtml(id)}_m" aria-label="ماه" title="ماه" onchange="HippoJalali.syncMonth('${escapeHtml(id)}')">${MONTHS.map((name, index) => `<option value="${index + 1}" ${index + 1 === current.jm ? 'selected' : ''}>${name}</option>`).join('')}</select>
      <select id="${escapeHtml(id)}_y" aria-label="سال" title="سال" onchange="HippoJalali.syncMonth('${escapeHtml(id)}')">${years.map(year => `<option value="${year}" ${year === current.jy ? 'selected' : ''}>${toPersianNumber(year)}</option>`).join('')}</select>
    </div><input type="hidden" id="${escapeHtml(id)}" value="${escapeHtml(jalaliMonthKey(current.jy, current.jm))}">`;
  }
  function syncMonth(id) {
    const year = Number(document.getElementById(id + '_y')?.value || 0);
    const month = Number(document.getElementById(id + '_m')?.value || 0);
    const hidden = document.getElementById(id);
    if (!year || !month || !hidden) return;
    hidden.value = jalaliMonthKey(year, month);
    hidden.dispatchEvent(new Event('change', {bubbles: true}));
  }
  function formatDate(value) {
    if (!value) return '—';
    const iso = String(value).slice(0, 10);
    const date = new Date(iso + 'T12:00:00');
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString('fa-IR-u-ca-persian', {year:'numeric', month:'long', day:'numeric'});
  }
  function formatDateTime(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('fa-IR-u-ca-persian', {dateStyle:'short', timeStyle:'short'});
  }
  function formatMonthKey(value) {
    const {jy, jm} = monthKeyToJalali(value);
    return `${MONTHS[jm - 1]} ${toPersianNumber(jy)}`;
  }

  global.HippoJalali = Object.freeze({
    months: MONTHS.slice(), todayIso, isoToJalali, jalaliToIso, monthLength,
    datePickerHtml, monthPickerHtml, syncDate, toggleDate, syncMonth,
    formatDate, formatDateTime, formatMonthKey, currentMonthKey, nextMonthKey,
    monthKeyToJalali, jalaliMonthKey
  });
})(window);
