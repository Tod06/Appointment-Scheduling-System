/* UHS Appointments - progressive enhancement for the interface. */
(function () {
  'use strict';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  function svg(paths, size, cls) {
    size = size || 16;
    return '<svg class="icon' + (cls ? ' ' + cls : '') + '" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + paths + '</svg>';
  }

  var ICONS = {
    eye: '<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
    eyeOff: '<path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/>',
    checkCircle: '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
    calendar: '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
    arrowUp: '<path d="M7 7h10v10"/><path d="M7 17 17 7"/>',
    alert: '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
    calendarX: '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="m14 14-4 4"/><path d="m10 14 4 4"/>'
  };

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function placeholder(icon, text) {
    return '<div class="placeholder-box">' + svg(ICONS[icon] || ICONS.calendar) + ' <span>' + esc(text) + '</span></div>';
  }

  /* ---------- Mobile navigation ---------- */

  var app = $('[data-app]');
  $$('[data-nav-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () { if (app) app.classList.toggle('nav-open'); });
  });
  $$('[data-nav-close]').forEach(function (el) {
    el.addEventListener('click', function () { if (app) app.classList.remove('nav-open'); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && app) app.classList.remove('nav-open');
  });

  /* ---------- Shared click behaviour ---------- */

  document.addEventListener('click', function (e) {
    var dismiss = e.target.closest('[data-dismiss]');
    if (dismiss) {
      var alertBox = dismiss.closest('[data-alert]');
      if (alertBox) alertBox.remove();
      return;
    }

    var toggle = e.target.closest('[data-toggle-password]');
    if (toggle) {
      var input = document.getElementById(toggle.getAttribute('data-toggle-password'));
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      toggle.innerHTML = svg(show ? ICONS.eyeOff : ICONS.eye);
      return;
    }

    var confirmEl = e.target.closest('button[data-confirm], a[data-confirm]');
    if (confirmEl && !window.confirm(confirmEl.getAttribute('data-confirm'))) {
      e.preventDefault();
    }
  });

  $$('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  // Submitting a select filter on change keeps filter bars to one click.
  $$('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { if (el.form) el.form.submit(); });
  });

  /* ---------- Sign-in account type ---------- */

  var roleInputs = $$('[data-role-input]');
  if (roleInputs.length) {
    var idLabel = $('[data-identifier-label]');
    var registerHint = $('[data-register-hint]');
    var staffHint = $('[data-staff-hint]');
    var syncRole = function () {
      var checked = roleInputs.filter(function (i) { return i.checked; })[0];
      var isPatient = !checked || checked.value === 'patient';
      if (idLabel) idLabel.textContent = isPatient ? 'Email or matric number' : 'Email';
      if (registerHint) registerHint.hidden = !isPatient;
      if (staffHint) staffHint.hidden = isPatient;
    };
    roleInputs.forEach(function (i) { i.addEventListener('change', syncRole); });
    syncRole();
  }

  /* ---------- Booking flow ---------- */

  var bookingForm = $('[data-booking]');
  if (bookingForm) initBooking(bookingForm);

  function initBooking(form) {
    var data = JSON.parse($('#booking-data').textContent);
    var doctorsBox = $('[data-doctors]', form);
    var slotsBox = $('[data-slots]', form);
    var dateInputs = $$('input[name="date"]', form);
    var clinicInputs = $$('[data-clinic-input]', form);
    var submit = $('[data-submit]', form);
    var reason = $('#reason', form);
    var reasonCount = $('[data-reason-count]', form);
    var requestId = 0;

    var state = {
      clinic: data.selected.clinic || 0,
      doctor: data.selected.doctor || 0,
      date: data.selected.date || '',
      time: '',
      timeLabel: ''
    };

    // A preselected doctor implies their clinic.
    var preDoctor = doctorById(state.doctor);
    if (preDoctor) {
      state.clinic = preDoctor.clinic;
      clinicInputs.forEach(function (i) { i.checked = +i.value === state.clinic; });
    }

    function doctorById(id) {
      return data.doctors.filter(function (d) { return d.id === id; })[0] || null;
    }

    function renderDoctors() {
      if (!state.clinic) {
        doctorsBox.innerHTML = placeholder('arrowUp', 'Choose a clinic first.');
        return;
      }
      var list = data.doctors.filter(function (d) { return d.clinic === state.clinic; });
      if (!list.length) {
        state.doctor = 0;
        doctorsBox.innerHTML = placeholder('alert', 'No doctors are taking appointments in this clinic right now.');
        return;
      }
      if (!list.some(function (d) { return d.id === state.doctor; })) {
        state.doctor = list.length === 1 ? list[0].id : 0;
      }
      doctorsBox.innerHTML = '<div class="choice-grid" role="radiogroup" aria-label="Doctor">' + list.map(function (d) {
        return '<div class="choice">'
          + '<input type="radio" name="doctor_id" id="doctor-' + d.id + '" value="' + d.id + '"' + (d.id === state.doctor ? ' checked' : '') + '>'
          + '<label for="doctor-' + d.id + '">'
          + '<span class="avatar">' + esc(d.initials) + '</span>'
          + '<span><span class="choice-title">' + esc(d.name) + '</span>'
          + '<span class="choice-sub" style="display:block">' + esc(d.specialty) + '</span>'
          + '<span class="choice-sub" style="display:block">' + esc(d.daysLabel) + ', ' + esc(d.hours) + '</span></span>'
          + svg(ICONS.checkCircle, 17, 'check-mark')
          + '</label></div>';
      }).join('') + '</div>';
    }

    function updateDates() {
      var doctor = doctorById(state.doctor);
      dateInputs.forEach(function (input) {
        var works = !!doctor && doctor.days.indexOf(+input.getAttribute('data-dow')) !== -1;
        input.disabled = !works;
        if (!works && input.checked) input.checked = false;
      });
      if (state.date) {
        var current = dateInputs.filter(function (i) { return i.value === state.date; })[0];
        if (!current || current.disabled) {
          state.date = '';
        } else {
          current.checked = true;
          var opt = current.parentNode;
          if (opt && opt.parentNode) opt.parentNode.scrollLeft = Math.max(0, opt.offsetLeft - 16);
        }
      }
    }

    function loadSlots() {
      state.time = '';
      state.timeLabel = '';
      if (!state.doctor || !state.date) {
        slotsBox.innerHTML = placeholder('calendar', state.doctor ? 'Choose a date to see open times.' : 'Choose a doctor and a date to see open times.');
        return;
      }
      var id = ++requestId;
      var skeleton = '';
      for (var i = 0; i < 8; i++) skeleton += '<div class="skeleton"></div>';
      slotsBox.innerHTML = '<div class="slot-grid" aria-busy="true">' + skeleton + '</div>';

      var params = 'doctor=' + encodeURIComponent(state.doctor) + '&date=' + encodeURIComponent(state.date) + (data.except ? '&except=' + encodeURIComponent(data.except) : '');
      fetch(data.slotsUrl + '?' + params, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (res) {
          return res.json().catch(function () { return { error: 'Could not load times. Refresh the page and try again.' }; });
        })
        .then(function (res) {
          if (id !== requestId) return;
          if (res.error) {
            slotsBox.innerHTML = placeholder('alert', res.error);
          } else {
            renderSlots(res);
          }
          update();
        })
        .catch(function () {
          if (id !== requestId) return;
          slotsBox.innerHTML = placeholder('alert', 'Could not load times. Check your connection and try again.');
        });
    }

    function renderSlots(res) {
      if (!res.slots.length) {
        slotsBox.innerHTML = placeholder('calendarX', 'The doctor does not consult on this day. Pick another date.');
        return;
      }
      var html = '';
      if (!res.available) {
        html += '<div class="placeholder-box" style="margin-bottom:12px">' + svg(ICONS.calendarX) + ' <span>Fully booked on ' + esc(res.label) + '. Try another date.</span></div>';
      }
      html += '<div class="slot-grid" role="radiogroup" aria-label="Time">' + res.slots.map(function (s) {
        var id = 'slot-' + s.time.replace(':', '');
        var title = s.reason === 'booked' ? 'Already booked' : (s.reason === 'past' ? 'No longer available' : 'Available');
        return '<div class="slot"><input type="radio" name="time" id="' + id + '" value="' + esc(s.time) + '" data-label="' + esc(s.label) + '"' + (s.available ? '' : ' disabled') + '>'
          + '<label for="' + id + '" title="' + title + '">' + esc(s.label) + '</label></div>';
      }).join('') + '</div>';
      slotsBox.innerHTML = html;
    }

    function setSummary(key, value) {
      var el = $('[data-summary="' + key + '"]', form);
      if (!el) return;
      el.textContent = value || 'Not chosen';
      el.classList.toggle('is-empty', !value);
    }

    function markStep(name, done) {
      var step = $('[data-step="' + name + '"]', form);
      if (step) step.classList.toggle('is-done', !!done);
    }

    function update() {
      var clinic = clinicInputs.filter(function (i) { return i.checked; })[0];
      var doctor = doctorById(state.doctor);
      var dateInput = dateInputs.filter(function (i) { return i.value === state.date; })[0];
      var reasonOk = !!data.except || (reason && reason.value.trim().length > 0);

      setSummary('clinic', clinic ? clinic.getAttribute('data-name') : '');
      setSummary('doctor', doctor ? doctor.name : '');
      setSummary('date', state.date && dateInput ? dateInput.getAttribute('data-label') : '');
      setSummary('time', state.timeLabel);

      markStep('clinic', !!clinic);
      markStep('doctor', !!doctor);
      markStep('date', !!state.date);
      markStep('time', !!state.time);
      markStep('reason', reason && reason.value.trim().length > 0);

      submit.disabled = !(state.doctor && state.date && state.time && reasonOk);
      if (reason && reasonCount) reasonCount.textContent = reason.value.length + ' / 500';
    }

    form.addEventListener('change', function (e) {
      var t = e.target;
      if (t.matches('[data-clinic-input]')) {
        state.clinic = +t.value;
        renderDoctors();
        updateDates();
        loadSlots();
      } else if (t.name === 'doctor_id') {
        state.doctor = +t.value;
        updateDates();
        loadSlots();
      } else if (t.name === 'date') {
        state.date = t.value;
        loadSlots();
      } else if (t.name === 'time') {
        state.time = t.value;
        state.timeLabel = t.getAttribute('data-label') || t.value;
      }
      update();
    });

    if (reason) reason.addEventListener('input', update);

    form.addEventListener('submit', function (e) {
      if (submit.disabled) { e.preventDefault(); return; }
      submit.disabled = true;
      submit.textContent = 'Sending request...';
    });

    renderDoctors();
    updateDates();
    loadSlots();
    update();
  }
})();
