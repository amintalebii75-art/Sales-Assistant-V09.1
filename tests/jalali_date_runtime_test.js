'use strict';
global.window = global;
require('../assets/js/jalali-date.js');
const J = global.HippoJalali;
function assert(condition, message) { if (!condition) throw new Error(message); }
assert(J.jalaliToIso(1405, 5, 1) === '2026-07-23', '1405-05-01 conversion failed');
const back = J.isoToJalali('2026-07-23');
assert(back.jy === 1405 && back.jm === 5 && back.jd === 1, 'ISO round-trip failed');
assert(J.formatMonthKey('2026-07').includes('مرداد'), 'legacy month display failed');
assert(J.formatMonthKey('1405-05').includes('مرداد'), 'Jalali month display failed');
assert(J.nextMonthKey('1405-12') === '1406-01', 'month rollover failed');
assert(J.monthLength(1403, 12) === 30, 'Jalali leap Esfand failed');
assert(J.monthLength(1404, 12) === 29, 'Jalali common Esfand failed');
console.log('PASS | Jalali conversion runtime | 7 checks');
