/**
 * bloom-aura-1/assets/js/login.js
 * Login/Signup page JS.
 * Name field: letters only — digits are blocked in real time.
 */

/* ── Only letters (including accented), spaces, hyphens, apostrophes ── */
var NAME_PATTERN_LOGIN = /^[A-Za-zÀ-ÖØ-öø-ÿ' -]+$/;

/* ── Tab switcher ───────────────────────────────────── */
function switchLoginTab(tab) {
    var signin = document.getElementById('login-panel-signin');
    var signup = document.getElementById('login-panel-signup');
    var tabSignin = document.getElementById('ltab-signin');
    var tabSignup = document.getElementById('ltab-signup');
    if (tab === 'signin') {
        signin.style.display = 'block';
        signup.style.display = 'none';
        tabSignin.classList.add('active');
        tabSignup.classList.remove('active');
    } else {
        signin.style.display = 'none';
        signup.style.display = 'block';
        tabSignin.classList.remove('active');
        tabSignup.classList.add('active');
    }
}

/* ── Password toggle — Sign In ─────────── */
function toggleLoginPass() {
    var inp = document.getElementById('login-pass');
    var btn = inp ? inp.parentNode.querySelector('.lfield-end') : null;
    if (!inp) return;
    if (inp.type === 'password') { inp.type = 'text'; if (btn) btn.textContent = '🙈'; }
    else                         { inp.type = 'password'; if (btn) btn.textContent = '👁'; }
}

/* ── Password toggle — Sign Up ────────── */
function toggleSignupPass() {
    var inp = document.getElementById('signup-pass');
    var btn = inp ? inp.parentNode.querySelector('.lfield-end') : null;
    if (!inp) return;
    if (inp.type === 'password') { inp.type = 'text'; if (btn) btn.textContent = '🙈'; }
    else                         { inp.type = 'password'; if (btn) btn.textContent = '👁'; }
}

/* ── Live password strength hint ─── */
function signupPassHint(input) {
    var hint = document.getElementById('signup-pass-hint');
    var len  = input.value.length;
    if (!hint) return;
    if (len === 0) { hint.textContent = '8+ characters'; hint.style.color = 'rgba(255,255,255,.3)'; }
    else if (len < 8) { hint.textContent = (8 - len) + ' more character' + (8 - len === 1 ? '' : 's') + ' needed'; hint.style.color = '#fca5a5'; }
    else { hint.textContent = '✅ Looks good!'; hint.style.color = '#86efac'; }
}

/* ── Name field: letters only ──────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    var nameInput = document.getElementById('signup-name');
    if (!nameInput) return;

    /* Block digit/invalid key presses in real time */
    nameInput.addEventListener('keypress', function (e) {
        if (e.key.length === 1 && !NAME_PATTERN_LOGIN.test(e.key)) {
            e.preventDefault();
        }
    });

    /* Strip any pasted digits */
    nameInput.addEventListener('input', function () {
        var cleaned = this.value.replace(/[^A-Za-zÀ-ÖØ-öø-ÿ' \-]/g, '');
        if (cleaned !== this.value) this.value = cleaned;
    });

    /* On blur — show inline error if invalid */
    nameInput.addEventListener('blur', function () {
        var val = this.value.trim();
        var wrap = this.closest('.lfield-wrap');
        if (!wrap) return;

        /* Remove old live error */
        var old = wrap.querySelector('.name-live-error');
        if (old) old.remove();

        if (!val) return; /* let server handle empty */

        if (!NAME_PATTERN_LOGIN.test(val) || val.length < 2) {
            var err = document.createElement('div');
            err.className = 'field-error-dark name-live-error';
            err.style.marginTop = '4px';
            err.textContent = 'Name must be letters only — no numbers or special characters.';
            wrap.appendChild(err);
        }
    });

    /* Clear live error on refocus */
    nameInput.addEventListener('focus', function () {
        var wrap = this.closest('.lfield-wrap');
        if (wrap) {
            var err = wrap.querySelector('.name-live-error');
            if (err) err.remove();
        }
    });
});

/* ── Social login placeholder ─────────────────────────────────────────────── */
function socialToast(provider) {
    alert(provider + ' login is not connected yet.\nPlease use the email form below.');
}